<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

// TO DO bruge mediadb

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";


$inifile = '../DigitalResources.ini';

$config = new inifile($inifile);
if ($config->error)
    die($config->error);

$connect_string = $config->get_value("connect", "setup");

//$tablename = $config->get_value("mediaservicetable", "setup");
if (($tablename = $config->get_value('tablename', 'pubhubMediaService')) == false)
    die("no pubhubMediaService/tablename stated in the configuration file");

try {
    $db = new pg_database($connect_string);
    $db->open();
    $seqno = 1;
    if (array_key_exists('seqno', $_REQUEST)) {
        $seqno = $_REQUEST['seqno'];
    }
    $select = "select originalxml from mediaebooks "
        . "where seqno = $seqno";
    $rows = $db->fetch($select);
    if ($rows) {
        $xml = $rows[0]['originalxml'];
        $doc = new DOMDocument();
        $doc->loadXML($xml);
        $xmlstrng = htmlentities($doc->saveXML());
        echo "<pre>$xmlstrng</pre>";
    }
} catch (Exception $err) {
    echo "The exception code is: " . $err->getCode() . "\n";
    echo $err . "\n";
    $err_txt = $err->getMessage();
    $getLine = "Line number:" . $err->getLine();
    $getTrace = $err->getTraceAsString();
//    mail($err_mail_to, "Exception utc-process.php", "----------------------\n$err_txt\n---------------------\n
//      $getLine\n$getTrace\n");
    exit;
}

