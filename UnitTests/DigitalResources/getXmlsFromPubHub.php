#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

///  DEPRECATED:  use XmlsFromPubHub.php  instead  (g -> G)
/**
 * @file getXmlsFromPubHub.php
 * @brief extract all records from PubHub's (Publizon) WS and put all new XML's into the database.
 * It is both eReolen.dk, ebib.dk and Netlydbog.dk
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 */

/**
 * A home made soapClient.  The XML structure is stored in the posthus.ini file.
 * soapClient is using the cURL php functions.
 *
 * @param string $url the URL for the WebService
 * @param string $method - the soap XML document for retriving
 * @param DOMdocument $doc DOMdocmunet structure where the XML will be stored
 */
function verbose_error($arr) {
    verbose::log(ERROR, $arr);
    exit(12);
}

function soapClient($url, $method, $doc, $curl_proxy, $clientname, $del = 'n') {

    try {
// Send request to webservice
        if (!$curl = new cURL())
            verbose_error("new cURL:" . $curl->get_status());
        if ($curl_proxy)
            $curl->set_option(CURLOPT_PROXY, $curl_proxy);
//    $curl->set_timeout(10);
        $curl->set_post(true);
        if (!$curl->set_post_xml($method))
            verbose_error("set_post_xml:" . $curl->get_status());
//    $arr = $curl->get_option();
//    print_r($arr);

        if (!$res = $curl->get($url)) {
            print_r($curl->get_status());
            $strng = implode("\n", $curl->get_status());
            verbose_error("curl->get($url):" . $strng);
        } else
            verbose::log(TRACE, "data fetched from:$url");

        $curl->close();
        if ($del == 'd')
            $fp = fopen($clientname . 'del.xml', "w");
        else
            $fp = fopen($clientname . '.xml', 'w');
        fwrite($fp, $res);
        fclose($fp);

        $doc->formatOutput = true;
        $doc->loadXML($res);
//        $fault = $doc->getElementsByTagName("Fault")->item(0);

        return $res;
    } catch (DOMException $DOMe) {
        verbose::log(ERROR, "DOMe:" . $DOMe);
        echo "DOMe: " . $DOMe . "\n";
        exit(4);
    }
}

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$startdir/XmlDiff_class.php";

function process($db, $url, $pubhub, $curl_proxy, $provider, $clientname, $recstatus) {
    global $tablename, $seqname, $nothing;

    $cnt = $upd = $ins = 0;

    $doc = new DOMDocument('');
    $res = soapClient($url, $pubhub, $doc, $curl_proxy, $clientname, $recstatus);


    $compare = new XmlDiff();
    $old = new DOMDocument();

// split the xml input file into XML's for each product and store them in the database
    $products = $doc->getElementsByTagName("product");
    foreach ($products as $product) {
        $cnt++;
//        if ($cnt > 10)
//            break;
//        echo "cnt:$cnt\n";

        $newdoc = new DOMDocument;
        $newdoc->formatOutput = true;
        $newdoc->loadXML("<root></root>");
        $product = $newdoc->importNode($product, true);
        $newdoc->documentElement->appendChild($product);
        $xpath = new DOMXpath($newdoc);
        $xml = $newdoc->saveXML();
        verbose::log(DEBUG, "next xml document:\n$xml\n");
        $isbn = $isbn13 = "";

// our query is relative to the external_ids node
        $query = '/root/product/external_ids';
        $entries = $xpath->query($query, $newdoc);

        foreach ($entries as $external_id) {
            $id = $id_type = "";
            $id_type = $external_id->getElementsByTagName("id_type")->item(0)->nodeValue;
            $id = $external_id->getElementsByTagName("id")->item(0)->nodeValue;
//            echo "id_type:$id_type, id:$id<br>\n";
            if ($id_type == 'ISBN')
                $isbn = $id;
            if ($id_type == 'ISBN13' || $id_type == 'GTIN13')
                $isbn13 = $id;
        }
        if ((!$isbn13) && $isbn) {
            $isbn13 = materialId::convertISBNToEAN($isbn);;
        } else {
            if ((!$isbn13) && (!$isbn)) {
                echo "Fejl, ingen ISBN\n$xml\n";
                exit(2);
            }
        }
        $isbn13 = materialId::normalizeISBN($isbn13);
        $current_format = $format_id = "";
        $formats = $newdoc->getElementsByTagName('formats');
//    foreach ($formats as $format) {
//      $format_id = $format->getElementsByTagName("format_id")->item(0)->nodeValue;
//      if ($format_id == '71' || $format_id == '75' || $format_id == '230')
//        $current_format = 'Netlydbog';
//      if ($format_id == '50' || $format_id == '58')
//        $current_format = 'eReolen';
//    }
//    if ($clientid == 'ebib-frontend')
//      $current_format = 'ebib';
        $current_format = $clientname;

        $title = mb_strcut($newdoc->getElementsByTagName('title')->item(0)->nodeValue, 0, 99, "UTF-8");
        verbose::log(DEBUG, "provider:$provider, isbn13:$isbn13, format:$current_format, title:$title");
        if ($nothing) {
            echo "provider:$provider, isbn13:$isbn13, format:$current_format, title:$title\n";
        }

        $id = $isbn13;
        $where = "where idnumber = '$isbn13' and provider ='$provider' and format = '$current_format'";
        $sql = "select seqno, idnumber, status, originalxml from $tablename $where \n";
        $arr = $db->fetch($sql);

        verbose::log(DEBUG, "sql:$sql\n");

        if ($arr) {
            $idnumber = $arr[0]['idnumber'];
            $seqno = $arr[0]['seqno'];
            $call_update = false;
            $cover_status = $sent_to_covers = $sent_to_well = $deletedate = "";
            $status = $sent_xml_to_well = $sent_to_basis = $faust_upd = "";

            if (strlen($arr[0]['originalxml'])) {
                $old->loadXML($arr[0]['originalxml']);
                $newCoverImageUrl = $compare->diff('product/coverimage', $old, $newdoc);
                if ($newCoverImageUrl) {
                    $cover_status = "cover_status = null, ";
                    $sent_to_covers = "sent_to_covers = null, ";
                    $call_update = true;
                }
                $newDescription = $compare->diff('product/description', $old, $newdoc);
                if ($newDescription) {
                    $sent_xml_to_well = "sent_xml_to_well = null, ";
                    $call_update = true;
                }
            } else {
                verbose::log(DEBUG, 'originalxml is null, it will be updated from API');
                $sent_xml_to_well = "sent_xml_to_well = null, ";
                $call_update = true;
            }
            if ($arr[0]['status'] == 'd') {  // Back to live
                if ($recstatus == 'n') {
                    $sent_to_basis = "sent_to_basis = null, ";
                    $sent_to_well = "sent_to_well = null, ";
                    $sent_xml_to_well = "sent_xml_to_well = null, ";
                    $cover_status = "cover_status = null, ";
                    $sent_to_covers = "sent_to_covers = null, ";
                    $faust_upd = "faust = null, ";
//          $deletedate = "deletedate = null, ";
                    $status = "status = 'n', ";
                    $call_update = true;
                } else {
                    if ($nothing) {
                        echo "this records is already set to Delete\n";
                    }
                }
            }
            if ($arr[0]['status'] == 'n') {  // Go to sleep
                if ($recstatus == 'd') {
                    $sent_to_basis = "sent_to_basis = null, ";
                    $sent_to_well = "sent_to_well = null, ";
                    $sent_xml_to_well = "sent_xml_to_well = null, ";
//          $cover_status = "cover_status = null, ";
//          $sent_to_covers = "sent_to_covers = null, ";
                    $deletedate = "deletedate = current_timestamp, ";
                    $status = "status = 'd', ";
                    $call_update = true;
                }
            }

            if ($call_update) {
                $upd++;
                $sql = "update $tablename set
                    $faust_upd
                    $sent_to_well
                    $sent_to_basis
                    $sent_to_covers
                    $cover_status
                    $deletedate
                    $status
                    $sent_xml_to_well
                    title = $1, originalXML = $2
                    where seqno = $seqno
               ";
                if ($nothing) {
                    echo "Nothing SQL:$sql\n";
                } else {
                    verbose::log(TRACE, "record updated:$idnumber - $current_format - $title");
                    $db->query_params($sql, array($title, $xml));
                }
            }
        }

        if (!$arr) {   // the record is not known in the database
            $ins++;
            $sql = "
                insert into $tablename
                    (seqno,provider,createdate,format,idnumber,title,originalXML,isbn13,status)
                values
                    (nextval('$seqname'),
                     '$provider',
                     current_timestamp,
                     '$current_format',
                     '$id',
                     $1,
                     $2,
                     '$isbn13',
                     '$recstatus')
                 ";
            if ($nothing) {
                echo "Nothing SQL:$sql\n";
            } else {
                verbose::log(TRACE, "record inserted:$id - $current_format - $title");
                $db->query_params($sql, array($title, $xml));
            }
        }
    }
    $string = "Status:'$recstatus' - Retrived:$cnt, Updated:$upd, Inserted:$ins";
    verbose::log(TRACE, $string);
}

/**
 * usage will write out which options that are allowed.
 *
 * @param sting $str - will be display when calling usage.
 */
function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-c client name (ex. ebib, Netlydbog, eReolen ...)\n";
    echo "\t-n nopthing happens ( no update in the database) \n";
    echo "\t-h help (shows this message)\n";
    exit(1);
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;


$options = getopt("hp:nc:");
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];
if (array_key_exists('n', $options))
    $nothing = true;
if (array_key_exists('c', $options))
    $clientname = $options['c'];

if (!$clientname)
    usage("Missing option 'c' clientname");

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
$curl_proxy = $config->get_value('curl_proxy', 'setup');

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START getXmlsFromPubHub.php " . __FILE__ . " ****");

if (($soap = $config->get_value("soap", "pubhub")) == false)
    usage("no soap stated in configuration file");
if (($retailerid = $config->get_value("retailerid", "pubhub")) == false)
    usage("no retailerid stated in configuration file");
if (($retailerkeycode = $config->get_value("retailerkeycode", "pubhub")) == false)
    usage("no retailerkeycode stated in configuration file");
if (($url = $config->get_value("url", "pubhub")) == false)
    usage("no url stated in configuration file");
if (($clientid = $config->get_value("clientid", $clientname)) == false)
    usage("no clientid  stated in configuration file under [$clientname]");


foreach ($soap as $indx => $values) {
    $$indx = "";
    foreach ($values as $value) {
        $data = str_replace("@", '"', "$value");
        $data = str_replace("+retailerid+", $retailerid, $data);
        $data = str_replace('+clientid+', $clientid, $data);
        $$indx .= str_replace("+retailerkeycode+", $retailerkeycode, $data) . "\n";
    }
}

verbose::log(DEBUG, "soap xml:\n" . $pubhub);
$tablename = 'digitalresources';
$seqname = $tablename . 'seq';

$updatetable = 'update_digitalresources';
$updseq = $updatetable . 'seq';

$connect_string = $config->get_value("connect", "setup");
verbose::log(DEBUG, "connect_string:" . $connect_string);

try {
    $db = new pg_database($connect_string);
    $db->open();


// look for the "$tablename" - if none, make one.
    $sql = "select tablename from pg_tables where tablename = $1";
    $arr = $db->fetch($sql, array($tablename));
    if (!$arr) {
        $sql = "create table $tablename (
            seqno integer primary key,
            provider varchar(50),
            createdate timestamp,
            format varchar(50),
            idnumber varchar(50),
            title varchar(100),
            originalXML text,
            marc text,
            faust varchar(11),
            isbn13 varchar(13),
            sent_to_basis timestamp,
            sent_to_well timestamp,
            sent_to_covers timestamp,
            sent_xml_to_well timestamp,
            status char(1),
            deletedate timestamp,
            cover_status character varying(250)
            )
            ";
        $db->exe($sql);
        verbose::log(TRACE, "table created:$sql");
        $sql = "
        drop sequence if exists $seqname
       ";
        $db->exe($sql);
        $sql = "
      create sequence $seqname
    ";
        $db->exe($sql);
    }
    $sql = "select tablename from pg_tables where tablename = $1";
    $arr = $db->fetch($sql, array($updatetable));
    if (!$arr) {
        $sql = "create table $updatetable (
            seqno integer primary key,
            provider varchar(50),
            createdate timestamp,
            format varchar(50),
            idnumber varchar(50),
            title varchar(100),
            from_faust varchar(11),
            to_faust varchar(11),
            done varchar(10)
            )
            ";
        $db->exe($sql);
        verbose::log(TRACE, "table created:$sql");
        $sql = "
        drop sequence if exists $updseq
       ";
        $db->exe($sql);
        $sql = "
      create sequence $updseq
    ";
        $db->exe($sql);
    }
    $provider = 'Pubhub';

    /**
     * New and Update
     *
     * this part of the code is only for new and updated records
     *
     */
    process($db, $url, $pubhub, $curl_proxy, $provider, $clientname, 'n');


    /**
     * Delete records
     *
     * this part of the code is only for deleted records
     */
    if (($soap = $config->get_value("soap", "pubhubdelete")) == false)
        usage("no soap stated in configuration file");
    if (($retailerid = $config->get_value("retailerid", "pubhubdelete")) == false)
        usage("no retailerid stated in configuration file");
    if (($retailerkeycode = $config->get_value("retailerkeycode", "pubhubdelete")) == false)
        usage("no retailerkeycode stated in configuration file");
    if (($url = $config->get_value("url", "pubhubdelete")) == false)
        usage("no url stated in configuration file");
    foreach ($soap as $indx => $values) {
        $$indx = "";
        foreach ($values as $value) {
            $data = str_replace("@", '"', "$value");
            $data = str_replace("+retailerid+", $retailerid, $data);
            $data = str_replace('+clientid+', $clientid, $data);
            $$indx .= str_replace("+retailerkeycode+", $retailerkeycode, $data) . "\n";
        }
    }


    verbose::log(DEBUG, "soap xml:\n" . $pubhubdelete);
    // ER BLEVET SLÅET FRA INDTIL PUBLIZON FÅR RETTET HVAD DER ER DELETE OG HVAD DER ER NYE
    if ($clientname != 'ebib')
        process($db, $url, $pubhubdelete, $curl_proxy, $provider, $clientname, 'd');
} catch (Exception $e) {
    verbose::log(ERROR, " e:" . $e);
    echo $e . "\n";
    exit;
} catch (DOMException $DOMe) {
    verbose::log(ERROR, " DOMe:" . $DOMe);
    echo "DOMe: " . $DOMe . "\n";
    exit(4);
}

verbose::log(TRACE, "**** STOP getXmlsFromPubHub.php ****");
?>
