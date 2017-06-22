<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/inifile_class.php";

function removeOldDatafiles($dir) {
    // rens data dir
    $files = scandir($dir);
    $now = time();
    // maxdiff = 7 day
    $maxdiff = 7 * 24 * 60 * 60;
    foreach ($files as $file) {
        $filename = "$dir/$file";
        if ( !is_dir($filename)) {
            $diff = $now - fileatime($filename);
            if ($diff > $maxdiff) {
                unlink($filename);
            }
        }
    }
}

function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
//    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-l Dump your local database (default the production db (pgdrift))\n";
    echo "\t-d outputdir\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

//$inifile = $startdir . "/../DigitalResources.ini_INSTALL";
$dir = 'pgdump';
$connectType = 'danbib';

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('l' => true);
} else {
    $options = getopt("hp:d:l");
}

//if (array_key_exists('p', $options)) {
//    $inifile = $options['p'];
//}

if (array_key_exists('l', $options)) {
    $connectType = getenv('USER');
}

if (array_key_exists('d', $options)) {
    $dir = $options['d'];
}

if (array_key_exists('h', $options)) {
    usage('HELP');
}

if ( !is_dir($dir)) {
    mkdir($dir);
}
//echo "inifile:$inifile\n";
//$config = new inifile($inifile);
//if ($config->error) {
//    usage($config->error);
//}
//$connect = "";
//$connect_string = $config->get_value('connect', "setup");
//echo "connet:$connect_string\n";
//$her = getcwd();
//echo "her:$her\n";
require_once '../Dmat_INSTALL/globalVars.php';
require_once "../Dmat_INSTALL/privat_$connectType.php";
//$connect_string = str_replace(array('$dbhost', '$dbuser', '$psqlport', '$dbname', '$dbpassword'), array("$dbhost", "$dbuser", "$psqlport", "$dbname", "$dbpassword"), $connect_string);
//
//echo "connect:$connect_string\n";
//$connect_string = str_replace('  ', ' ', $connect_string);
//$arr = explode(' ', $connect_string);
//foreach ($arr as $opt) {
//    $pairs = explode('=', $opt);
//    $sw = trim($pairs[0]);
//    $val = trim($pairs[1]);
//    switch ($sw) {
//        case 'host':
//            $connect .= " -h $val";
//            break;
//        case 'dbname':
//            $dbname = $val;
//            break;
//        case 'user':
//            $connect .= " -U $val";
//            break;
//        case 'port':
//            $connect .= " -p $val";
//            break;
//    }
//}


removeOldDatafiles($dir);

//$dmp = "pg_dump $connect " . "--format plain --no-owner --clean --no-tablespaces " . "--file +outputfile+ --table +table+ $dbname";
$dmp = "pg_dump -h $dbhost -p $psqlport -U $dbuser --format plain --no-owner --clean --no-tablespaces " . "--file +outputfile+ --table +table+ $dbname";
$ts = date('YmdHi');
$tables = array('digitalresources', 'lastupdated', 'mediaservice', 'mediaebooks', 'mediaservicenote', 'mediaservicedref', 'mediaservicerecover', 'runaftererrors', 'basisphus', 'calendarexp', 'medialeklog', 'calendarexp', 'retroresult');
foreach ($tables as $tablename) {

    $outputfile = $dir . "/$tablename.$ts.sql";

    $cmd = str_replace('+outputfile+', $outputfile, $dmp);
    $cmd = str_replace('+table+', $tablename, $cmd);

    echo $cmd . "\n";
    system($cmd);
    //    $cmd = "gzip $outputfile";
    //echo $cmd . "\n";
    //    system($cmd);
}
