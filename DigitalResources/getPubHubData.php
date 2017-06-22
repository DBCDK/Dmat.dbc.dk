<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 02-12-2015
 * Time: 09:52
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$classes/epub_class.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/pubhubFtp_class.php";


function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
//    echo "\t-c client name (ex. ebib, Netlydbog, eReolen ...)\n";
//    echo "\t-n nopthing happens ( no update in the database) \n";
//    echo "\t-f read from file (ex. -f ebib.xml )\n";
    echo "\t-h help (shows this message)\n";
    exit(1);
}

$inifile = $startdir . "/../DigitalResources.ini";
//$nothing = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('c' => 'eReolen', 'N' => 'true');
} else {
    $options = getopt("hp:");
}
if ( !is_array($options)) {
    $options = array();
}
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);


if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
//$curl_proxy = $config->get_value('curl_proxy', 'setup');

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . " ****");

if (($ftp_server = $config->get_value('pubhub_ftp_server', 'setup')) == false)
    die("no pubhub_ftp_server stated in the configuration file");

if (($ftp_user = $config->get_value('pubhub_ftp_user', 'setup')) == false)
    die("no pubhub_ftp_user stated in the configuration file");

if (($ftp_pass = $config->get_value('pubhub_ftp_passwd', 'setup')) == false)
    die("no pubhub_ftp_passwd stated in the configuration file");

if (($workdir = $config->get_value('workdir', 'setup')) == false) {
    usage("no workdir stated in the configuration file");
}
$pubFtp = new pubhubFtp($ftp_server, $ftp_user, $ftp_pass, $workdir);
$pubhubinfo = $pubFtp->getInfo();
$connect_string = $config->get_value("connect", "setup");

$db = new pg_database($connect_string);
$db->open();

$mediadb = new mediadb($db, basename(__FILE__));
$epub = new epub_class('work');

$allRecs = $mediadb->getAllBookids();

// find all ebooks files not been updated.
if ($allRecs) {
    foreach ($allRecs as $rec) {
        $bookid = $rec['bookid'] . "." . $rec['filetype'];
        if (array_key_exists($bookid, $pubhubinfo)) {
            $info = $pubhubinfo[$bookid];
            if ($info['status'] != 'broken') {
                if ($info['fdate'] == $rec['fdate']) {
                    $pubhubinfo[$bookid]['status'] = 'noUpdate';
                } else {
                    $pubhubinfo[$bookid]['status'] = 'update';
                }
            }
        }
    }
}
//$cnt = array();
//foreach ($pubhubinfo as $p) {
//    if (!array_key_exists($p['status'], $cnt)) {
//        $cnt[$p['status']] = 0;
//    }
//    $cnt[$p['status']]++;
//}
//print_r($cnt);

//print_r($pubhubinfo['472ddce2-281e-4dbd-bed6-fc4698ee635e.epub']);
//$tmp = $pubhubinfo['472ddce2-281e-4dbd-bed6-fc4698ee635e.epub'];
//$pubhubinfo = array();
//$pubhubinfo['472ddce2-281e-4dbd-bed6-fc4698ee635e.epub'] = $tmp;
try {

    foreach ($pubhubinfo as $bookid => $info) {
        if ($info['status'] == 'broken') {
            print_r($info);
        }
        $info['renditionlayout'] = false;
        $info['publisher'] = false;
        $info['source'] = false;
        $info['title'] = false;
        if ($info['status'] == 'update') {
            if ($filename = $pubFtp->getEbookFromPubhub($info)) {
                if ($epub->initEpub($filename)) {
                    $info['publisher'] = mb_strcut($epub->getElement('publisher'), 0, 99, "UTF-8");
                    $info['renditionlayout'] = $epub->getLayout();
                    $info['source'] = $epub->getElement('source');
                    $info['title'] = $epub->getTitle();
                } else {
                    verbose::log(ERROR, "how did we end up here?");
                    exit(1);
                }
            }
            if ($info['ext'] != '') {
                $mediadb->updateEbook($bookid, $info);
            }
            $pubFtp->removeFile($filename);

        }
        if ($info['status'] == 'new') {
            //post der kun er i ftp dir. Findes ikke i feeded
//            print_r($info);
        }
    }
    verbose::log(TRACE, "**** STOP " . __FILE__ . " ****");
} catch (epubException $e) {
    verbose::log(ERROR, " e:" . $e);
    echo $e . "\n";
    exit(1);
}

