<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";

/**
 * usage will write out which options that are allowed.
 *
 * @param sting $str  - will be display when calling usage.
 */
function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-h help (shows this message)\n";
    exit(1);
}

$inifile = $startdir . "/../DigitalResources.ini";

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array();
} else {
    $options = getopt("hp:");
}

if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (( $logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (( $verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START  " . __FILE__ . " ****");

if (($tablename = $config->get_value("tablename", "pubhubMediaService")) == false)
    usage("no tablename stated in configuration file");
if (($statistics = $config->get_value("statistics", "pubhubMediaService")) == false)
    usage("no statistics stated in configuration file [pubhubMediaService]");
$seqname = $statistics . "seq";


$connect_string = $config->get_value("connect", "setup");
verbose::log(DEBUG, "connect_string:" . $connect_string);

try {
    $db = new pg_database($connect_string);
    $db->open();

// look for the "$statistics" - if none, make one.
    $sql = "select tablename from pg_tables where tablename = $1";

    $arr = $db->fetch($sql, array($statistics));

    if (!$arr) {
        $sql = "create table $statistics (
            seqno integer primary key,
            createdate timestamp with time zone,
            status varchar(20),
            provider varchar(50),
            booktype varchar(50),
            filetype varchar(25),
            count integer
        )
            ";
        $db->exe($sql);
        verbose::log(TRACE, "table created:$sql");


        $sql = "drop sequence if exists $seqname";
        $db->exe($sql);
        $sql = "create sequence $seqname";
        $db->exe($sql);
    }

    $select = "select current_timestamp";
    $rows = $db->fetch($select);
    $now = $rows[0]['now'];
    $select = "SELECT status, provider,  booktype, filetype, count(*) as count "
            . "FROM $tablename "
            . "group by status, provider, booktype, filetype ";
    $rows = $db->fetch($select);
    foreach ($rows as $params) {
        $insert = "insert into $statistics "
                . "(seqno, createdate, status, provider, booktype, filetype, count) "
                . "values "
                . "(nextval('$seqname'), "
                . "'$now', "
                . "$1, $2, $3, $4, $5 ) ";
        $db->query_params($insert, $params);
    }
} catch (Exception $e) {
    verbose::log(ERROR, " e:" . $e);
    echo $e . "\n";
    exit;
}

verbose::log(TRACE, "**** STOP " . __FILE__ . " ****");
?>
