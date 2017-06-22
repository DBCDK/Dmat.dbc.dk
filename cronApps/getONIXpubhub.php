<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 04-10-2016
 * Time: 12:29
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";


require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$startdir/XmlDiff_class.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/soapClient_class.php";
require_once "$classes/ONIX_class.php";
//require_once "$classes/dmat_db_class.php";
require_once "$classes/pubhubFtp_class.php";
require_once "$classes/epub_class.php";

putenv('http_proxy');


function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-f xml data file (use this file as input/output)\n";
    echo "\t-t use test database ([testcase]connect) \n";
    echo "\t-r read from file (default: product.xml)\n";
    echo "\t-n nopthing happens ( no update in the database) \n";
    echo "\t-h help (shows this message)\n";
    exit(100);
}


$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('x' => true, 'x' => true);
//    $options = array('f' => 'pubhubMediaService.xml');
} else {
    $options = getopt("hp:nf:tr");
}

//$aa = getcwd();

if (array_key_exists('h', $options)) {
    usage();
}
if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}

$datafile = 'products.xml';
if (array_key_exists('f', $options)) {
    $datafile = $options['f'];
}

$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
}
if (array_key_exists('n', $options)) {
    $nothing = true;
}

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}

if (($logfile = $config->get_value('logfile', 'setup')) == false) {
    usage("no logfile stated in the configuration file");
}
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false) {
    usage("no verboselevel stated in the configuration file");
}

$soapClient = new soapClient_class($config, 'ONIXpubhub');
if ($er = $soapClient->getError()) {
    usage($er);
}

if (array_key_exists('r', $options)) {
    $soapClient->setReadFromFile();
}

if (($ftp_server = $config->get_value('pubhub_ftp_server', 'setup')) == false)
    die("no pubhub_ftp_server stated in the configuration file");

if (($ftp_user = $config->get_value('pubhub_ftp_user', 'setup')) == false)
    die("no pubhub_ftp_user stated in the configuration file");

if (($ftp_pass = $config->get_value('pubhub_ftp_passwd', 'setup')) == false)
    die("no pubhub_ftp_passwd stated in the configuration file");

if (($workdir = $config->get_value('workdir', 'setup')) == false)
    die("no [setup] workdir stated in the configuration file");

$pubhubFtp = new pubhubFtp($ftp_server, $ftp_user, $ftp_pass, $workdir);
$epub = new epub_class($workdir);

//if (basename(getcwd()) == 'posthus') {
//    chdir('UnitTests');
//}
verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START getXmlsFromPubHubMediaService.php " . __FILE__ . " ****");

$connect_string = $config->get_value("connect", $setup);
verbose::log(DEBUG, "connect_string:" . $connect_string);


try {
    $xml = $soapClient->soapClient($datafile);

    $db = new pg_database($connect_string);
    $db->open();

    $mediadb = new mediadb($db, basename(__FILE__), $nothing, $pubhubFtp, $epub);
//    $dmatdb = new dmat_db_class($db, basename(__FILE__), $nothing, $pubhubFtp, $epub);
    $ONIX = new ONIX();
//    $ONIX->load_xml($xml);
//    $ONIX->startFile();
    $ONIX->fastStart($xml);
    $i = 0;
//    while (($info = $ONIX->getNext())) {
    while (($info = $ONIX->fastNext())) {
        $i++;
        if ($i == 1000) {
            $i = 0;
            $cnt = $ONIX->getCnt();
            echo "Behandlet $cnt -- " . mb_strcut($info['title']['text'], 0, 99, "UTF-8") . "\n";
        }
        $xml = $ONIX->getXml();
        $mediadb->updateONIX($info, $xml);
    }

    $mediadb->lastOnixUpdate();

    $cnt = $ONIX->getCnt();
    echo "Behandlet $cnt Product(s)\n";
    $sta = $mediadb->getUpdateSta();
    foreach ($sta as $key => $val) {
        echo "$key: $val - ";
    }
    echo "\n";

} catch (Exception $e) {
    $id = $ONIX->getId();
    $cnt = $ONIX->getCnt();
//    $xml = $ONIX->getXml();
    verbose::log(ERROR, "ID:($cnt)$id, e:" . $e);
    echo $e . "\n";
    echo "Number in file:$cnt, Record Identifier:$id\n---------------------------------\n";
    echo $xml . "\n";
    exit(1);
} catch (DOMException $DOMe) {
    verbose::log(ERROR, " DOMe:" . $DOMe);
    echo "DOMe: " . $DOMe . "\n";
    exit(4);
}