<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file XmlsFromPubHub.php
 * @brief extract all records from PubHub's (Publizon) WS and put all new XML's into the database.
 * It is both eReolen, ebib, DEFF and Netlydbog
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 */

/**
 * A home made soapClient.  The XML structure is stored in the DigitalResources.ini file.
 * soapClient is using the cURL php functions.
 *
 * @param string $url the URL for the WebService
 * @param string $method - the soap XML document for retriving
 * @param DOMdocument $doc DOMdocmunet structure where the XML will be stored
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

//require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$startdir/XmlDiff_class.php";
//require_once "$classes/mediadb_class.php";
require_once "$classes/soapClient_class.php";
require_once "$classes/dr_db_class.php";

//function process($db, $url, $pubhub, $curl_proxy, $provider, $clientname, $recstatus) {
function process(dr_db $dbdb, &$xml, $clientname, $nothing) {

    $cnt = $upd = $ins = 0;
    $doc = new DOMDocument('');
    $doc->formatOutput = true;
    $doc->loadXML($xml);
    $compare = new XmlDiff();
    $old = new DOMDocument();
    $current_format = "";

    // split the xml input file into XML's for each product and store them in the database
    $products = $doc->getElementsByTagName("product");
    foreach ($products as $product) {
        $cnt++;
//    if ($cnt > 10)
//      break;
//    echo "cnt:$cnt\n";

        $newdoc = new DOMDocument;
        $newdoc->formatOutput = true;
        $newdoc->loadXML("<root></root>");
        $product = $newdoc->importNode($product, true);

        $newdoc->documentElement->appendChild($product);
        $xml = $newdoc->saveXML();
        verbose::log(DEBUG, "next xml document:\n$xml\n");
        $isbn = $isbn13 = "";

        foreach ($newdoc->getElementsByTagName('external_ids') as $entries) {
            $id_type = $entries->getElementsByTagName("id_type")->item(0)->nodeValue;
            $id = $entries->getElementsByTagName("id")->item(0)->nodeValue;
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
        $cf = $newdoc->getElementsByTagName('costfree')->item(0)->nodeValue;
        $current_format = $clientname;

        $title = mb_strcut($newdoc->getElementsByTagName('title')->item(0)->nodeValue, 0, 99, "UTF-8");
        verbose::log(DEBUG, "isbn13:$isbn13, format:$current_format, title:$title");
        if ($nothing) {
            echo "isbn13:$isbn13, format:$current_format, title:$title\n";
        }

        $id = $isbn13;

        $arr = $dbdb->getInfo($isbn13, $current_format);
        if ($arr) {
            $seqno = $arr['seqno'];
//            if ($seqno == 10534) {
//                echo "fundet";
//            }
            $nullVars = array();

            if (strlen($arr['originalxml'])) {
                $old->loadXML($arr['originalxml']);
                $newCoverImageUrl = $compare->diff('product/coverimage', $old, $newdoc);
                if ($newCoverImageUrl) {
                    $nullVars['cover_status'] = true;
                    $nullVars['sent_to_covers'] = true;
                }
                $newDescription = $compare->diff('product/description', $old, $newdoc);
                if ($newDescription) {
                    $nullVars['sent_xml_to_well'] = true;
                }
            }
            if ($arr['costfree'] != $cf) {
                $nullVars['sent_to_basis'] = true;
            }
            if ($arr['status'] == 'd') {  // Back to live
                $nullVars['sent_to_basis'] = true;
                $nullVars['sent_to_well'] = true;
//                $nullVars['sent_xml_to_well'] = true;
                $nullVars['cover_status'] = true;
                $nullVars['sent_to_covers'] = true;

            }
            $dbdb->UpdateRow($seqno, $nullVars, $cf, $title, $xml);
        } else {
            $dbdb->insertRow($current_format, $id, $title, $isbn13, $cf, $xml);
        }
    }

    $dbdb->setToDelete($current_format);
    $string = "Retrived:$cnt, Updated:$upd, Inserted:$ins";
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
    echo "\t-f read from file (ex. -f ebib.xml )\n";
    echo "\t-h help (shows this message)\n";
    exit(1);
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;

$options = array('c' => 'eReolen');
if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('c' => 'eReolen', 'N' => 'true', 'F' => 'erl.xml');
} else {
    $options = getopt("hp:nc:f:");
}
//if (!$options) {
//    $options = array('c' => 'Netlydbog');
//}
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];
if (array_key_exists('n', $options))
    $nothing = true;
if (array_key_exists('c', $options)) {
    $clientname = $options['c'];
}
$filename = null;
if (array_key_exists('f', $options))
    $filename = $options['f'];

//$clientname = 'Netlydbog';

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

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . basename(__FILE__) . " ****");

$connect_string = $config->get_value("connect", "setup");
verbose::log(DEBUG, "connect_string:" . $connect_string);

$soapClient = new soapClient_class($config, 'pubhubLibraryService', $clientname);
if ($er = $soapClient->getError()) {
    usage($er);
}
if ($filename) {
    $soapClient->setReadFromFile();
} else {
    $filename = "$clientname.xml";
}
$xml = $soapClient->soapClient($filename);


try {
    $db = new pg_database($connect_string);
    $db->open();

    $drdb = new dr_db($db, basename(__FILE__), $nothing);

//    $provider = 'Pubhub';


    process($drdb, $xml, $clientname, $nothing);

} catch (Exception $e) {
    verbose::log(ERROR, " e:" . $e);
    echo $e . "\n";
    exit;
} catch (DOMException $DOMe) {
    verbose::log(ERROR, " DOMe:" . $DOMe);
    echo "DOMe: " . $DOMe . "\n";
    exit(4);
}

verbose::log(TRACE, "**** STOP " . __FILE__ . " ****");
?>
