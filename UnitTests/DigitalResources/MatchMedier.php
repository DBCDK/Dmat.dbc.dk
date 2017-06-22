<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
$mockupclasses = $startdir . "/../UnitTests/classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OPAC_class_lib/matchLibV3_class.php";
//require_once "$inclnk/OPAC_class_lib/updateMediaDB_class.php";
require_once "$classes/mediadb_class.php";


//echo "h335";
//exit;
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
    echo "\t-D match også DigtialR poster\n";
    echo "\t-t use test database ([testcase]connect) \n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-s seqno  \n";
    echo "\t-h help (shows this message)\n";
    exit(100);
}

$startdir = dirname(__FILE__);
$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;
$matchDigitalR = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
//    $options = array('n' => 'yes', 's' => 32, 't' => 'testcase');
//    $options = array('t' => true, 'p' => '../UnitTests/dr.ini', 's' => 23);
    $options = array('s' => 42684, 'x' => true, 'x' => true);
//    $options = array();
} else {
    $options = getopt("hp:ns:tD");
}
if (!$options) {
//    $options = array('s' => 23, 'D' => true);
//    $options = array('t' => true, 'p' => '../UnitTests/dr.ini', 's' => 23);
}
if (array_key_exists('h', $options)) {
    usage();
}
if (array_key_exists('n', $options)) {
    $nothing = true;
}
if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}
if (array_key_exists('D', $options)) {
    $matchDigitalR = true;
}
$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
}

$toMatch = array('Pending' => true, 'Hold' => true, 'eVa' => true);
if ($matchDigitalR) {
    $toMatch = array('DigitalR' => true);

}
if (array_key_exists('s', $options)) {
    $directseqno = $options['s'];
} else {
    $directseqno = false;
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
if (($ociuser = $config->get_value('ociuser', 'setup')) == false) {
    usage("no ociuser stated in the configuration file");
}
if (($ocipasswd = $config->get_value('ocipasswd', 'setup')) == false) {
    usage("no ocipasswd stated in the configuration file");
}
if (($ocidatabase = $config->get_value('ocidatabase', 'setup')) == false) {
    usage("no ocidatabase stated in the configuration file");
}
//if (($tablename = $config->get_value("tablename", "pubhubMediaService")) == false)
//    usage("no tablename stated in configuration file");
//if (($reftable = $config->get_value("reftable", "pubhubMediaService")) == false)
//    usage("no reftable stated in configuration file");
//if (($notetable = $config->get_value("notetable", "pubhubMediaService")) == false)
//    usage("no notetable stated in configuration file [pubhubMediaService]");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . " ****");

if ($setup == 'testcase') {
    require_once "$mockupclasses/matchMockUp_class.php";
    $matchLibV3 = new matchMockUp();
} else {
    $matchLibV3 = new matchLibV3($ociuser, $ocipasswd, $ocidatabase);
}

$connect_string = $config->get_value("connect", $setup);
verbose::log(DEBUG, $connect_string);
try {
    $db = new pg_database($connect_string);
    $db->open();

    $mediadb = new mediadb($db, basename(__FILE__), $nothing);
//    $updateMediaDB = new updateMediaDB($db, $reftable, $tablename, 'digitalresources', $notetable);

    $cnt = 0;

    $rows = $mediadb->getMatchObjects($toMatch, $directseqno);
    $total = count($rows);
    verbose::log(TRACE, "count(\$rows) = $total");
    if ($rows) {
        foreach ($rows as $row) {

            $seqno = $row['seqno'];
            $records = $mediadb->getInfoData($seqno);
            $isbncandidate = '';
            if ($records[0]['source']) {
                $txt = $records[0]['source'];
                for ($i = 0; $i < strlen($txt); $i++) {
                    $int = $txt[$i];
                    if (is_numeric($txt[$i])) {
                        $isbncandidate .= $txt[$i];
                    }
                }
            }
            $cnt++;
//            if ($cnt > 10) {
//                break;
//            }
            verbose::log(TRACE, "cnt:($cnt / $total), seqno:$seqno, status:" . $records[0]['status'] . " title:" . $records[0]['title']);
            // the status can have been changed after the initial select
            if (array_key_exists($records[0]['status'], $toMatch) || $directseqno) {
                $found = false;
//                $found = $mediadb->IsItInDigitalResources($records[0]['isbn13']);
//                if ($found && !$matchDigitalR) {
//                    $mMarc = $found;
//                } else {
                $mMarc = $matchLibV3->match($records[0]['originalxml'], $isbncandidate);
                $mediadb->updateRefData($seqno, $mMarc);
//                }
                $mediadb->updateStatus($seqno, $mMarc, $found, $records[0], 'OldTemplate');
            }
        }
    } else {
        echo "No records with status Pending or Hold\n";
    }
} catch (fetException $f) {
    echo $f;
    verbose::log(ERROR, "$f");
}
verbose::log(TRACE, " **** STOP " . __FILE__ . " ****");
?>