<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";


require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$classes/mediadb_class.php";
//require_once 'merge_class.php';
//require_once "$inclnk/OPAC_class_lib/ConvertXMLtoMarc_class.php";

function getLektoer(LibV3API $libv3, $faust) {
    $records = $libv3->getMarcByLB($faust, '870970');
    $record = $records[0];
    $m = new marc();
    $m->fromIso($record['DATA']);
    $f06bs = $m->findSubFields('f06', 'b');
    if ($f06bs) {
//        $strng = $m->toLineFormat();
        foreach ($f06bs as $f06b) {
            if (strtolower($f06b) == 'l')
                return true;
        }
    }
    $f990bs = $m->findSubFields('990', 'b');
    if ($f990bs) {
//        $strng = $m->toLineFormat();
        foreach ($f990bs as $f990b) {
            if (strtolower($f990b) == 'l')
                return true;
        }
    }
    return false;
}

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
    echo "\t-s status (DigitalR) \n";
//    echo "\t-a indholdet af ACC koden fx. 999999 \n";
    echo "\t-S seqno, kun dette seqno \n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = "../DigitalResources.ini";
$nothing = FALSE;

$Phus = 'MediaToPhus';
$Basis = 'MediaToBasis';
$acc = '';
$onlyThisSeqno = '';


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('s' => 'matchDigitalR', 'n' => 'nothing', 'X' => 30692);
} else {
    $options = getopt("hp:ns:a:S:");
}
//if (!$options) {
//    $options = array('s' => 'matchDigitalR', 'S' => 16655);
//}
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('n', $options))
    $nothing = true;
if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}

$toMatch = array('matchDigitalR' => true);

if (array_key_exists('S', $options)) {
    $directseqno = $options['S'];
} else {
    $directseqno = false;
}

$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
if (($ociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser stated in the configuration file");
if (($ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd stated in the configuration file");
if (($ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase stated in the configuration file");
//if (($tablename = $config->get_value("tablename", "pubhubMediaService")) == false)
//    usage("no tablename stated in configuration file");
//if (($reftable = $config->get_value("reftable", "pubhubMediaService")) == false)
//    usage("no reftable stated in configuration file");
//if (($notetable = $config->get_value("notetable", "pubhubMediaService")) == false)
//    usage("no notetable stated in configuration file [pubhubMediaService]");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . " ****");

//$matchLibV3 = new matchLibV3($ociuser, $ocipasswd, $ocidatabase);
$libv3 = new LibV3API($ociuser, $ocipasswd, $ocidatabase);

$connect_string = $config->get_value("connect", 'setup');
verbose::log(DEBUG, $connect_string);
try {
    $db = new pg_database($connect_string);
    $db->open();

    $mediadb = new mediadb($db, basename(__FILE__), $nothing);

    $cnt = $noMatch = $moreThanOne = $lek = $printedFaust = $pfl = $eVa = $evaLek = 0;

    $rows = $mediadb->getMatchObjects($toMatch, $directseqno);
    $total = count($rows);
    verbose::log(TRACE, "count(\$rows)= $total");

    if ($rows) {
        foreach ($rows as $row) {
            $seqno = $row['seqno'];
            $records = $mediadb->getInfoData($seqno);
            $cnt++;
            $nMarc = $mediadb->getRefDataNmarc($seqno);
            $best_match = array();
            if ($nMarc) {
                foreach ($nMarc as $lokalid => $res) {
                    foreach ($res as $bib => $dataset) {
                        foreach ($dataset as $base => $data) {
                            $matchtype = $data['matchtype'];
                            switch ($matchtype) {
                                case 306:
                                case 304:
                                case 303:
                                case 302:
                                case 301:
                                case 300:
                                case 206:
                                case 204:
                                case 203:
                                case 202:
                                case 201:
                                case 200:
                                case 106:
                                case 104:
                                case 103:
                                case 102:
                                case 101:
                                case 100:
                                    if (!array_key_exists($matchtype, $best_match)) {
                                        $best_match[$matchtype]['cnt'] = 0;
                                    }
                                    if ($base == 'Phus') {
                                        $matchtype = 50;
                                    }
                                    $best_match[$matchtype]['cnt']++;
                                    $best_match[$matchtype]['lokalid'] = $lokalid;
                                    $best_match[$matchtype]['bib'] = $bib;
                                    $best_match[$matchtype]['base'] = $base;
                                    break;
                            }
                        }
                    }
                }
            }
            if ($best_match) {
                krsort($best_match);
                foreach ($best_match as $key => $value) {
                    break;
                }
                if ($value['cnt'] > 1) {
                    $moreThanOne++;
                } else {
                    $lektoer = getLektoer($libv3, $value['lokalid']);
                    if ($lektoer) {
                        $lek++;
                    }
                    switch ($key) {
                        case 306:
                        case 302:
                        case 300:
                        case 206:
                        case 202:
                        case 106:
                        case 102:
                            // this is a "Template case" we will automaticly insert the pointer
                            $mediadb->updatePrintedFaust($seqno, $value['lokalid']);
                            $printedFaust++;
                            if ($lektoer) {
                                $pfl++;
                            }
                            break;
                        default:
                            // this is a "eVa case", exm. wheter there is a f06 in one of the records
                            $eVa++;
                            if ($lektoer) {
                                $evaLek++;
                            }
                            break;
                    }
                }
            } else {
                $noMatch++;
                echo "no Match faust:" . $records[0]['faust'] . " ($seqno)\n";
            }
        }
    }
    echo "cnt:$cnt, noMatch:$noMatch, moreThanOne:$moreThanOne, lek:$lek, "
        . "printedFaust:$printedFaust, pfl:$pfl, eVa:$eVa, evaLek:$evaLek\n";

} catch (fetException $f) {
    echo $f;
    verbose::log(ERROR, "$f");
}


verbose::log(TRACE, "**** STOP " . __FILE__ . " ****");
?>