#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file insertFaustMediaService.php
 * @brief The program will look for records in the database which don't have a faust.
 * It will retrive a faust from the "no-roll", using the program Rck_send, and insert it in the database.
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 *
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$classes/mediadb_class.php";

/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-t use test database ([testcase]connect) \n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;
$fakefaust = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('t' => true);
} else {
    $options = getopt("hp:nt");
}
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('n', $options))
    $nothing = true;
if (array_key_exists('p', $options))
    $inifile = $options['p'];
$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
    $fakefaust = true;
}

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
if (($tablename = $config->get_value('tablename', 'pubhubMediaService')) == false)
    usage("no mediaservicetabel stated in the configuration file");


if (($tablename = $config->get_value('tablename', 'pubhubMediaService')) == false)
    usage("no mediaservicetabel stated in the configuration file");
if (($notetable = $config->get_value('notetable', 'pubhubMediaService')) == false)
    usage("no notetable stated in the configuration file");
if (($reftable = $config->get_value("reftable", "pubhubMediaService")) == false)
    usage("no reftable stated in configuration file");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START insertFaustMediaService.php " . __FILE__ . "****");

$connect_string = $config->get_value("connect", $setup);
$getFaust_cmd = $config->get_value('getFaust_cmd', 'setup');

$cnt = 0;
try {

    $db = new pg_database($connect_string);
    $db->open();

    $mediadb = new mediadb($db, basename(__FILE__), $nothing);
    $arr = $mediadb->getIsbns();
    if ($fakefaust) {
//         trækker 1 ekstra numre da de resterende test regner med disse faust numre.
        $n = $mediadb->getFakeFaust();
//        $n = $mediadb->getFakeFaust();
    }


    if ($arr) {
        foreach ($arr as $isbns) {
            $cnt++;
            $isbn13 = $isbns['isbn13'];
            $cmd = $startdir . "/" . $getFaust_cmd;
            $cmd = $getFaust_cmd;
            if ($nothing) {
                echo "cmd:$cmd\n";
//                $new_faust = 'x xxx xxx x';
            } else {
                if ($fakefaust) {
                    $new_faust = $mediadb->getFakeFaust();
                } else {
                    $new_faust = system($cmd, $return_var);
                    if ($return_var) {
                        $strng = "The cmd:$cmd is in error: $return_var";
                        echo $strng . "\n";
                        verbose::log(ERROR, $strng);
                        exit(2);
                    }
                }
                $newfaustprinted = "";
                if ($isbns['expisbn']) {
                    if ($fakefaust) {
                        $newfaustprinted = $mediadb->getFakeFaust();
                    } else {
                        $newfaustprinted = system($cmd, $return_var);
                        if ($return_var) {
                            $strng = "The cmd:$cmd is in error: $return_var";
                            echo $strng . "\n";
                            verbose::log(ERROR, $strng);
                            exit(2);
                        }
                    }
                }
            }
            $mediadb->insertNewFaust($isbn13, $new_faust, $newfaustprinted);
        }
        $db->commit();
    }
} catch
(Exception $e) {
    echo $e . "\n";
    exit;
}

verbose::log(TRACE, "$cnt faust numbers inserted");
verbose::log(TRACE, "**** STOP insertFaustMediaService.php ****");
?>
