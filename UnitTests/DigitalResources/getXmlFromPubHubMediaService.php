#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file getXmlsFromPubHub.php
 * @brief extract all records from PubHub's (Publizon) WS and put all new XML's into the database.
 * It is both eReolen and Netlydbog
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$startdir/XmlDiff_class.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/soapClient_class.php";


putenv('http_proxy');

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


function process(mediadb $mediadb, $xml, $nothing) {

    $cnt = $upd = $ins = 0;

    $doc = new DOMDocument('');
    $doc->formatOutput = true;
    $doc->loadXML($xml);

    /*
     *  split the xml input file into XML's for each product and store them in the database
     */
    $products = $doc->getElementsByTagName("Book");
    foreach ($products as $product) {
        $cnt++;

        //        if ($cnt > 50)
        //            exit;
        //        echo "cnt:$cnt\n";

        /*
         * TO DO - når der er test hele vejen i gennem vil jeg prøve med nedenstående der gemmer data som utf-8 i XML'en
         */
        //        $newdoc = new DOMDocument('1.0', 'utf-8');
        //        $newdoc->formatOutput = true;
        //        $element = $newdoc->createElement('root');
        //        $newdoc->appendChild($element);


        $newdoc = new DOMDocument;
        $newdoc->formatOutput = true;
        $newdoc->loadXML("<root></root>");

        $product = $newdoc->importNode($product, true);
        $newdoc->documentElement->appendChild($product);

        $isbn = $isbn13 = "";

        $isbn13 = $newdoc->getElementsByTagName('Identifier')->item(0)->nodeValue;
        $booktype = $newdoc->getElementsByTagName('BookType')->item(0)->nodeValue;
        $filetype = $newdoc->getElementsByTagName('FileType')->item(0)->nodeValue;
        $bookid = $newdoc->getElementsByTagName('BookId')->item(0)->nodeValue;
        $publicationdate = $newdoc->getElementsByTagName('PublicationDate')->item(0)->nodeValue;

        $isbn13 = materialId::normalizeISBN($isbn13);
        //        if ($isbn13 == '9788758812120') {
        //            echo "slå test til";
        //        }
        $title = mb_strcut($newdoc->getElementsByTagName('Title')->item(0)->nodeValue, 0, 99, "UTF-8");
        $titles = explode("\n", $title);
        $title = $titles[0];
        //        verbose::log(TRACE, "($cnt) isbn13:$isbn13, format:$booktype,"
        //            . " filetype:$filetype title:$title, PublicationDate:$publicationdate");
        if ($nothing) {
            echo "isbn13:$isbn13, booktype:$booktype, filetype:$filetype, title:$title\n";
        }

        $xml = $newdoc->saveXML();
        //        verbose::log(DEBUG, "next xml document:\n$xml\n");
        $mediadb->updateBooks($booktype, $filetype, $bookid, $isbn13, $title, $publicationdate, $xml);
        //        $mediadb->updateTable($booktype, $filetype, $bookid, $isbn13, $title, $publicationdate, $xml);
    }
    $mediadb->setNotActive();
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
    echo "\t-f xml data file (use this file as input)\n";
    echo "\t-t use test database ([testcase]connect) \n";
    echo "\t-n nopthing happens ( no update in the database) \n";
    echo "\t-h help (shows this message)\n";
    exit(100);
}

$inifile = $startdir . "/../DigitalResources.ini";

$nothing = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('f' => '../UnitTests/data/ms.2.xml', 't' => true);
    //    $options = array('f' => 'pubhubMediaService.xml');
} else {
    $options = getopt("hp:nf:t");
}

//$aa = getcwd();

if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];
$datafile = null;
if (array_key_exists('f', $options))
    $datafile = $options['f'];
$setup = 'setup';
if (array_key_exists('t', $options))
    $setup = 'testcase';
if (array_key_exists('n', $options))
    $nothing = true;
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
verbose::log(TRACE, "**** START getXmlsFromPubHubMediaService.php " . __FILE__ . " ****");


$connect_string = $config->get_value("connect", $setup);
verbose::log(DEBUG, "connect_string:" . $connect_string);

$soapClient = new soapClient_class($config, 'pubhubMediaService');
if ($er = $soapClient->getError()) {
    usage($er);
}
if ($datafile) {
    $soapClient->setReadFromFile();
} else {
    $datafile = "pubhubMediaService.xml";
}
$xml = $soapClient->soapClient($datafile);

try {
    $db = new pg_database($connect_string);
    $db->open();

    $mediadb = new mediadb($db, basename(__FILE__), $nothing);

    process($mediadb, $xml, $nothing);
} catch (Exception $e) {
    verbose::log(ERROR, " e:" . $e);
    echo $e . "\n";
    exit(1);
} catch (DOMException $DOMe) {
    verbose::log(ERROR, " DOMe:" . $DOMe);
    echo "DOMe: " . $DOMe . "\n";
    exit(4);
}

verbose::log(TRACE, "**** STOP getXmlsFromPubHubMediaService.php ****");
?>
