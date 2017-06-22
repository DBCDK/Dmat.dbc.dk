<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * script der laver en kopi af basis/phus i en tabel i postgres
 *
 * Efterfølgende tester det om der findes poster i DigitalResources
 * der ikke finde i basis/phus og om der er poster i basis/phus som
 * ikke findes i DigitalResources
 *
 *
 *
 * <?xml version="1.0" encoding="UTF-8"?>
 * <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://oss.dbc.dk/ns/opensearch">
 * <SOAP-ENV:Body>
 * <ns1:searchRequest>
 * <ns1:query>term.acSource=ereolen</ns1:query>
 * <ns1:agency>150015</ns1:agency>
 * <ns1:profile>ereolplus</ns1:profile>
 * <ns1:start>1</ns1:start>
 * <ns1:stepValue>50</ns1:stepValue>
 * <ns1:ObjectFormat>briefDisplay</ns1:ObjectFormat>
 * </ns1:searchRequest>
 * </SOAP-ENV:Body>
 * </SOAP-ENV:Envelope>
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/curl_class.php";

/**
 *
 * @param type $faust
 */
function makeLink($faust) {
    $f1 = str_replace(' ', '+', $faust);
    $f2 = str_replace(' ', '', $faust);
    $basis = "http://dev.dbc.dk/seBasis/?lokalid=$f1&bibliotek=&Hent=Send+foresp%F8rgsel";
//    $fedora = "http://aqua-fedora.dbc.dk:8012/fedora/objects/870970-basis:$f2/datastreams/commonData/content";
    $content = "http://aqua-content.dbc.dk:4080/corepo-content/rest/objects/870970-basis:$f2/datastreams/commonData/content";
    $ret = "<a href=$basis>Basis</a>  <a href=$content>Well</a> ";
    return $ret;
}

function verbose_error($arr) {
    verbose::log(ERROR, $arr);
    exit(12);
}

function ExtractFaustOpensearch($curl_proxy) {

    /* kan ikke bruges da den er alt for langsom
     * vi må bruge SOLR i stedet selv om den er ustabil.
     */

    $xml = '<?xml version="1.0" encoding="UTF-8"?>
  <SOAP-ENV:Envelope xmlns:SOAP-ENV="http://schemas.xmlsoap.org/soap/envelope/" xmlns:ns1="http://oss.dbc.dk/ns/opensearch">
  <SOAP-ENV:Body>
  <ns1:searchRequest>
  <ns1:query>term.acSource=ereolen</ns1:query>
  <ns1:agency>150015</ns1:agency>
  <ns1:profile>ereolplus</ns1:profile>
  <ns1:start>$start</ns1:start>
  <ns1:stepValue>50</ns1:stepValue>
  <ns1:ObjectFormat>briefDisplay</ns1:ObjectFormat>
  </ns1:searchRequest>
  </SOAP-ENV:Body>
  </SOAP-ENV:Envelope>';

    if (!$curl = new cURL()) {
        die("new cURL:" . $curl->get_status());
    }
    if ($curl_proxy) {
        $curl->set_option(CURLOPT_PROXY, $curl_proxy);
    }
    $curl->set_post(true);
    $url = "http://opensearch.addi.dk/4.0/";
    $doc = new DOMDocument();

    $start = 7000;
    while (true) {
        $post = str_replace('$start', $start, $xml);

        if (!$curl->set_post_xml($post)) {
            die("set_post_xml:" . $curl->get_status());
        }
        $curl->set_timeout(360);
        if (!$xmlres = $curl->get($url)) {
            print_r($curl->get_status());
            $strng = implode("\n", $curl->get_status());
            die("curl->get($url):" . $strng);
        } else {
            $doc->loadXML($xmlres);
            $collectionCount = $doc->getElementsByTagName('collectionCount')->item(0)->nodeValue;
            if ($collectionCount == 0) {
                break;
            }
//            print_r($xmlres);
            $ts = date(DATE_RFC2822);
            echo "($ts) start = $start\n";
            $start += 50;
        }
    }
    $curl->close();
}

/**
 *
 * @param type $arr
 * @param type $fausts
 */
function extractFaust(cURL $curl, $solrformat, $format, &$fausts) {
//    $baseurl = 'http://aqua-solr.dbc.dk:8014/solr/broend/select?q=rec.collectionIdentifier:"150015-$format"+&rows=99999999&fl=fedoraPid&wt=csv&indent=true';
    $start = 0;
    $step = 5000;
    $baseurl = 'http://aqua-solr.dbc.dk:8983/solr/aqua-corepo-searcher/select?q=rec.collectionIdentifier:"150015-$format"+&rows=$step&fl=fedoraPid&wt=csv&indent=true&start=$start';
    $fetchrecords = true;
    while ($fetchrecords) {
        $url = str_replace('$format', "$solrformat", $baseurl);
        $url = str_replace('$step', $step, $url);
        $url = str_replace('$start', $start, $url);
        $res = $curl->get($url);
        $ids = explode("\n", $res);
        foreach ($ids as $id) {
            if (substr($id, 0, 6) == '870970') {
                $faust = substr($id, 13, 1) . ' ' . substr($id, 14, 3)
                    . ' ' . substr($id, 17, 3) . ' ' . substr($id, 20, 1);
                $fausts[$faust] = $format;
            }
        }
        $start += $step;
        $fetchrecords = count($ids) - 2;
    }
}


function toBeRemovedFromWell($db, $solr, $format) {
    $sql = "select * from $solr "
        . "where format = '$format' "
        . "and faust not in "
        . "(select faust from digitalresources "
        . "where provider = 'Pubhub' "
        . "and status = 'n' "
        . "and format = '$format')";
    $rows = $db->fetch($sql);
    $ret = "";
    if ($rows) {
        foreach ($rows as $row) {
            $ret .= "<strong>For meget i Brønden (SOLR): </strong>";
            foreach ($row as $key => $val) {
                $ret .= "$key:$val ";
            }
            $faust = $row['faust'];
            $link = makeLink($faust);
            $ret .= " $link <br /><br />\n\n";
        }
    }
    return $ret;
}

function notinWell($db, $solr, $format) {
    $sql = "select faust, isbn13, title from digitalresources "
        . "where  provider = 'Pubhub' "
        . "and status = 'n' "
        . "and format = '$format' "
        . "and faust not in "
        . "( select faust from $solr "
        . "where format = '$format')";
    $rows = $db->fetch($sql);
    $ret = "";
    if ($rows) {
        foreach ($rows as $row) {
            $ret .= "<strong>Mangler i Brønden (SOLR): </strong>";
            foreach ($row as $key => $val) {
                $ret .= "$key:$val ";
            }
            $faust = $row['faust'];
            $link = makeLink($faust);
            $ret .= " $link <br /><br />\n\n";
        }
    }
    return $ret;
}

/**
 *
 * @param type $db
 * @param type $basisphus
 * @param type $format
 */
function notinDigitalResources($db, $basisphus, $format) {
    global $table;

    $sql = "select seqno, dbuser, faust, status, type, title from $basisphus "
        . "where type = '$format' "
        . "and status != 'd' "
        . "and faust not in ( "
        . "select faust from digitalresources "
        . "where provider = 'Pubhub' "
        . "and status = 'n' "
        . "and format = '$format' "
        . ")";
    $sql = str_replace('$basisphus', "$basisphus", $sql);
    $sql = str_replace('$format', "$format", $sql);
    $rows = $db->fetch($sql);
    $ret = "";
    if ($rows) {
        foreach ($rows as $row) {
            $ret .= "<strong>Mangler i DigitalResources: </strong>";
            foreach ($row as $key => $val) {
                $ret .= "$key:$val ";
            }
            $faust = $row['faust'];
            $link = makeLink($faust);
            $ret .= " $link <br /><br />\n\n";


            $seqno = $row['seqno'];
            $select = "select seqno from $table "
                . "where seqno = $seqno and type = 'ManglerDR' ";
            $seqnos = $db->fetch($select);
            if (!$seqnos) {
                $insert = "insert into $table "
                    . "(seqno, createdate, updated, type) "
                    . "values "
                    . "($seqno, current_timestamp, current_timestamp, 'ManglerDR' ) ";
                $db->exe($insert);
            } else {
                $update = "update $table "
                    . "set updated = current_timestamp "
                    . "where seqno = $seqno  and type = 'ManglerDR' ";
                $db->exe($update);
            }
        }
    }
    return $ret;
}

function ERAerror($db, $basisphus) {
    global $table;

//    $sql = "SELECT d.seqno, d.format, d.faust, d.isbn13, d.status, d.costfree as drcostfree, b.costfree as basisCostFree
//  FROM digitalresources d, $basisphus b
//  where d.faust = b.faust
//  and d.costfree != b.costfree
//  and provider = 'Pubhub'
//  and d.status = 'n'
//  and b.status = 'n'
//  ";
    // if there is a record both in basis and phus, basis is choosen.
    $sql = "create temp table xx as
            select * from $basisphus where (faust,dbuser) in (
                select  faust, min (dbuser) from $basisphus
                where status != 'd'
                group by faust
            )";
    $sql = str_replace('$basisphus', "$basisphus", $sql);
//    echo "sql:$sql\n";
    $db->exe($sql);
    $sql = "select count(*) from xx ";
//    echo "sql:$sql\n";
    $rows = $db->fetch($sql);
//    echo "count:" . count($rows) . "\n";
    $sql = "select seqno,faust from digitalresources where seqno in
            (SELECT d.seqno
            FROM digitalresources d, xx b
            where d.faust = b.faust
            and d.costfree != b.costfree
            and provider = 'Pubhub'
            and d.status = 'n'
            and b.status = 'n'
        )";
//    echo "sql:$sql\n";
    $rows = $db->fetch($sql);
//    echo "fetch end\n";
//    print_r($rows);
    $ret = '';
    if ($rows) {
        foreach ($rows as $row) {
            $ret .= "<strong>Forkert ERA kode: </strong>";
//            foreach ($row as $key => $val) {
//                $ret .= "$key:$val ";
//            }
            $seqno = $row['seqno'];
            $faust = $row['faust'];
            $link = makeLink($faust);
            $ret .= " $link<br /><br />\n\n";

            $select = "select seqno from $table "
                . "where seqno = $seqno and type = 'ForkertERA' ";
            $seqnos = $db->fetch($select);
//            var_dump($seqno);
            if (!$seqnos) {
                $insert = "insert into $table "
                    . "(seqno, createdate, updated, type) "
                    . "values "
                    . "($seqno, current_timestamp, current_timestamp, 'ForkertERA' ) ";
                $db->exe($insert);
            } else {
                $update = "update $table "
                    . "set updated = current_timestamp "
                    . "where seqno = $seqno and type = 'ForkertERA' ";
                $db->exe($update);
            }
        }
    }
    return $ret;
}

/**
 *
 * @param type $basisphus
 * @param type $format
 */
function notinPhusBasis($db, $basisphus, $format) {
    global $table;

    $sql = "select seqno, provider, createdate, format, faust, isbn13, status, "
        . "sent_to_basis, title "
        . "from digitalresources "
        . "where faust not in "
        . "(select faust from $basisphus "
        . "where type = '$format' "
        . "and status in ('c','n') ) "
        . "and provider = 'Pubhub' "
        . "and format = '$format' "
        . "and status = 'n' ";

    $sql = str_replace('$basisphus', "$basisphus", $sql);
    $sql = str_replace('$format', "$format", $sql);
//    echo "$sql";
    $rows = $db->fetch($sql);
//    print_r($rows);
    $ret = "";
    if ($rows) {
        foreach ($rows as $row) {
            $ret .= "<strong>Mangler i Basis/Phus: </strong>";
            foreach ($row as $key => $val) {
                $ret .= "$key:$val ";
            }
            $faust = $row['faust'];
            $link = makeLink($faust);
            $ret .= " $link<br /><br />\n\n";

            $seqno = $row['seqno'];
            $select = "select seqno from $table "
                . "where seqno = $seqno and type = 'ManglerBasis' ";
            $seqnos = $db->fetch($select);
            if (!$seqnos) {
                $insert = "insert into $table "
                    . "(seqno, createdate, updated, type) "
                    . "values "
                    . "($seqno, current_timestamp, current_timestamp, 'ManglerBasis' ) ";
                $db->exe($insert);
            } else {
                $update = "update $table "
                    . "set updated = current_timestamp "
                    . "where seqno = $seqno and type = 'ManglerBasis' ";
                $db->exe($update);
            }
        }
    }

    return $ret;
}

/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "") {
    global $argv, $inifile, $basisphus;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-t name of the table (default: $basisphus)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$basisphus = 'basisphus';

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('x' => 'z');
} else {
    $options = getopt("hp:f:");
}


if (array_key_exists('h', $options))
    usage();

if (array_key_exists('t', $options))
    $basisphus = $options['t'];

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");

if (($Bociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser (seBasis) stated in the configuration file");
if (($Bocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd (seBasis) stated in the configuration file");
if (($Bocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase (seBasis) stated in the configuration file");

if (($Pociuser = $config->get_value('ociuser', 'sePhus')) == false)
    usage("no ociuser (sePhus) stated in the configuration file");
if (($Pocipasswd = $config->get_value('ocipasswd', 'sePhus')) == false)
    usage("no ocipasswd (sePhus)stated in the configuration file");
if (($Pocidatabase = $config->get_value('ocidatabase', 'sePhus')) == false)
    usage("no ocidatabase (sePhus) stated in the configuration file");

$mailresult = "";

//ExtractFaustOpensearch($curl_proxy);


/**
 * lav en kopi af basis
 */
$dumpcmd = 'dump_v3 $ocilogin/$ocipasswd@$ocidatabase '
    . '-w" where bibliotek = \'870970\' '
    . 'and ($productcodes) " | '
    . 'php InsertIntoPostgres.php -t $basisphus -u $user $option '
    . '> insert.$user.log';


$productcodes = "data like '%xNLY%' or data like '%xNLL%' or data like '%xERE%' or data like '%xERL%'";
$dump = str_replace('$ocilogin', "$Bociuser", $dumpcmd);
$dump = str_replace('$ocipasswd', "$Bocipasswd", $dump);
$dump = str_replace('$ocidatabase', "$Bocidatabase", $dump);
$dump = str_replace('$basisphus', "$basisphus", $dump);
$dump = str_replace('$user', "basis", $dump);
$dump = str_replace('$option', "-D", $dump);
$dump = str_replace('$productcodes', $productcodes, $dump);

//echo "$dump\n";
//exit;
$res = system($dump);
//exit;
$log = "insert.basis.log";
$lncnt = 0;
$fp = fopen($log, 'r');
while ($ln = fgets($fp)) {
    $lncnt++;
    if (substr($ln, 0, 4) == 'More' || substr($ln, 0, 7) == 'Missing') {
        $arr = explode(',', $ln);
//        $f = str_replace(' ', '+', trim($arr[1]));
//        $f2 = str_replace(' ', '', trim($arr[1]));
        foreach ($arr as $faustno) {
        }
        $f = str_replace(' ', '+', trim($faustno));
        $f2 = str_replace(' ', '', trim($faustno));
        $link = "http://dev.dbc.dk/seBasis/?lokalid=$f&bibliotek=&Hent=Send+foresp%F8rgsel";
//        $fedora = "http://aqua-fedora.dbc.dk:8012/fedora/objects/870970-basis:$f2/datastreams/commonData/content";
        $content = "http://aqua-content.dbc.dk:4080/corepo-content/rest/objects/870970-basis:$f2/datastreams/commonData/content";
        $mailresult .= "<strong>$log</strong> $ln ";
        $mailresult .= "<a href=$link>Basis</a> "
            . "<a href=$content>Well</a> "
            . "<br /><br />\n";
    }
}
fclose($fp);

$dump = str_replace('$ocilogin', "$Pociuser", $dumpcmd);
$dump = str_replace('$ocipasswd', "$Pocipasswd", $dump);
$dump = str_replace('$ocidatabase', "$Pocidatabase", $dump);
$dump = str_replace('$basisphus', "$basisphus", $dump);
$dump = str_replace('$user', "phus", $dump);
$dump = str_replace('$option', "-A", $dump);
$dump = str_replace('$productcodes', $productcodes, $dump);

//echo "$dump\n";
$res = system($dump);
//exit;
$log = "insert.phus.log";
$fp = fopen($log, 'r');
while ($ln = fgets($fp)) {
    if (substr($ln, 0, 4) == 'More' || substr($ln, 0, 7) == 'Missing') {
        $arr = explode(',', $ln);
        foreach ($arr as $faustno) {
        }
        $f = str_replace(' ', '+', trim($faustno));
        $f2 = str_replace(' ', '', trim($faustno));
//        $f = str_replace(' ', '+', trim($arr[1]));
//        $f2 = str_replace(' ', '', trim($arr[1]));
        $link = "http://dev.dbc.dk/seBasis/?lokalid=$f&bibliotek=&Hent=Send+foresp%F8rgsel";
//        $fedora = "http://aqua-fedora.dbc.dk:8012/fedora/objects/870970-basis:$f2/datastreams/commonData/content";
        $content = "http://aqua-content.dbc.dk:4080/corepo-content/rest/objects/870970-basis:$f2/datastreams/commonData/content";
        $mailresult .= "<strong>$log</strong>[ $ln ";
        $mailresult .= "<a href=$link>Basis</a> "
            . "<a href=$content>Well</a> "
            . "<br /><br />\n";
    }
}
fclose($fp);

$fausts = array();
//* get data from SOLR

$curl = new cURL();
// user danbib have a env http_proxy.  This will unset the variable
putenv('http_proxy');

extractFaust($curl, 'netlydbog', 'Netlydbog', $fausts);
extractFaust($curl, 'ereol', 'eReolen', $fausts);
extractFaust($curl, 'erelic', 'eReolenLicens', $fausts);


//echo "count:" . count($fausts) . "\n";
//print_r($fausts);


/*
 * findes der poster i digitalresources som ikke findes i phusbasis
 */
try {

    $table = 'runaftererrors';

    $connect_string = $config->get_value("connect", "setup");
    $db = new pg_database($connect_string);
    $db->open();

    $sql = "select tablename from pg_tables where tablename = '$table'";
    $arr = $db->fetch($sql);
    if (!$arr) {
        $sql = "create table $table "
            . "(seqno integer primary key, "
            . "createdate timestamp, "
            . "updated timestamp, "
            . "type varchar(15)) ";
        $db->exe($sql);
    }
    $update = "update $table "
        . "set updated = null ";
    $db->exe($update);

    $sql = "select tablename from pg_tables where tablename = 'solr'";
    $arr = $db->fetch($sql);
    if ($arr) {
        $drop = "drop table solr";
        $db->exe($drop);
    }
    $sql = "create table solr "
        . "(seqno integer primary key, "
        . "createdate timestamp, "
        . "format varchar(15), "
        . "faust varchar(11)) ";
    $db->exe($sql);
    $drop = "drop sequence if exists solrseq";
    $db->exe($drop);
    $db->exe('create sequence solrseq');

    foreach ($fausts as $key => $val) {
        $insert = "insert into solr "
            . "(seqno, createdate, format, faust) "
            . "values "
            . "(nextval('solrseq'), "
            . "current_timestamp, "
            . "'$val', "
            . "'$key')";
        $db->exe($insert);
    }


    $tablename = "digitalresources";
//    echo "ERAerror(\$db, \$basisphus)\n";
    $mailresult .= ERAerror($db, $basisphus);

//    echo "notinPhusBasis(\$db, \$basisphus, 'eReolen')\n";
    $mailresult .= notinPhusBasis($db, $basisphus, 'eReolen');
//    echo "notinPhusBasis(\$db, \$basisphus, 'eReolenLicens')\n";
    $mailresult .= notinPhusBasis($db, $basisphus, 'eReolenLicens');
//    echo "notinPhusBasis(\$db, \$basisphus, 'Netlydbog')\n";
    $mailresult .= notinPhusBasis($db, $basisphus, 'Netlydbog');


    $mailresult .= notinDigitalResources($db, $basisphus, 'eReolen');
    $mailresult .= notinDigitalResources($db, $basisphus, 'eReolenLicens');
    $mailresult .= notinDigitalResources($db, $basisphus, 'Netlydbog');

//    echo "\n[$mailresult]\n";
    $mailresult .= notinWell($db, 'solr', 'eReolen');
    $mailresult .= notinWell($db, 'solr', 'eReolenLicens');
    $mailresult .= notinWell($db, 'solr', 'Netlydbog');

    $mailresult .= toBeRemovedFromWell($db, 'solr', 'eReolen');
    $mailresult .= toBeRemovedFromWell($db, 'solr', 'eReolenLicens');
    $mailresult .= toBeRemovedFromWell($db, 'solr', 'Netlydbog');

    if ($mailresult) {
        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type: text/html; charset=UTF-8' . "\r\n";

// Additional headers
        $headers .= "To: hhl@dbc.dk\r\n";
//        $headers .= "Cc: " . $mdata['MailCc'] . "\r\n";
//        $headers .= "Bcc: " . $mdata['MailBcc'] . "\r\n";
        $headers .= "From: noreply@digitalresources.dbc.dk\r\n";
        $subject = "DR og Basis passer ikke";
        mail('', "DR og Basis passer ikke", $mailresult, $headers);
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}