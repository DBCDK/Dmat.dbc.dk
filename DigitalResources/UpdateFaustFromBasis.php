<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file UpdateFaustFromBasis.php
 * @brief Searches the table "digitalresources" records without Faust-number.
 * It will take the isbn10 or isbn13 and make a search in basis.
 * The record will be downloaded to the program in iso2709.
 * If isbn and title are alike, the table "digitalresources" will be
 * updated with the faust number from the marc-record.
 *
 * @author Hans-Henrik Lund
 *
 * @date 27-01-2012
 *
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";


require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OPAC_class_lib/GetMarcFromBasis_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once 'GetFaust_class.php';

$globalMarc = new marc();

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
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
//    echo "\t-f datafile (marc iso format) \n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array();
} else {
    $options = getopt("hp:nc:f:");
}
$options = getopt("hp:n");
if (!$options) {
    $options = array();
}
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('n', $options))
    $nothing = true;
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
if (($ociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser stated in the configuration file");
if (($ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd stated in the configuration file");
if (($ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase stated in the configuration file");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** " . __FILE__ . " ****");

$libv3 = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
$GetFaust = new GetFaust($libv3);

$tablename = 'digitalresources';
$connect_string = $config->get_value("connect", "setup");
verbose::log(DEBUG, $connect_string);
try {
    $db = new pg_database($connect_string);
    $db->open();

    // this command does that we can make a rollback - no commit is done until
    // the commit command is executed
//  $db->exe('START TRANSACTION');
// search for records without faust
    $select = "select seqno, isbn13, provider, format, title, status, faust "
        . "from $tablename "
        . "where provider in ('Pubhub') "
//            . "and format = 'eReolenKlik' "
        . "and faust is null ";
//    $select = "select seqno, isbn13, provider, format, title, status, faust
//            from $tablename
//            where seqno in (SELECT seqno
//            FROM runaftererrors
//               where updated is not null and type = 'ManglerBasis'
//                order by createdate desc)";

    $sql = $select;
    verbose::log(TRACE, "sql:\n$sql");
    $arr = $db->fetch($sql);
    $pwuids = posix_getpwuid(posix_getuid());
    $workdir = "/tmp/PafWorkDir." . $pwuids['name'];
    verbose::log(TRACE, "workdir:$workdir");
    $getMarcFromBasis = new GetMarcFromBasis($workdir, $ociuser, $ocipasswd, $ocidatabase);

    $cnt = 0;
    $hit = 0;
    if ($arr) {
        foreach ($arr as $result) {
            $cnt++;
            verbose::log(TRACE, "*********************** new record *******************");
            verbose::log(TRACE, "result from query in $tablename:" . $result['isbn13'] . "," . $result['provider'] .
                "," . $result['format'] . "," . $result['title'] . "," . $result['status']
            );
            $isbn13 = $result['isbn13'];
            $title = $result['title'];
            $status = $result['status'];
            $seqno = $result['seqno'];
//    $oldfaust = $result['faust'];
//    $format = $result['format'];
            // Is there already a record in the table with the same ISBN and with a faust number?
            $sql = "select faust, status, title "
                . "from $tablename "
                . "where provider = 'Pubhub' "
                . "and format in ('eReolen','ebib','eReolenLicens','eReolenKlik') "
                . "and faust is not null "
                . "and isbn13 = '$isbn13' "
                . "and seqno != $seqno ";
            $allreadyThere = $db->fetch($sql);
//            $allreadyThere = false;
            /* test whether the title er almost alike.  */
            if ($allreadyThere) {
                $titleDR = $allreadyThere[0]['title'];
                if (!$globalMarc->FuzzyCompare($title, $titleDR)) {
                    verbose::log(TRACE, "Titles not alike: $title - $titleDR");
                    $allreadyThere = false;
                }
            }

            if ($allreadyThere) {
                $faust = $allreadyThere[0]['faust'];
                $status = $allreadyThere[0]['status'];
                $titleold = $title;
                $title = $allreadyThere[0]['title'];
                if ($globalMarc->FuzzyCompare($title, $titleold))
                    verbose::log(TRACE, "found i Digitalresources: $isbn13 ($status) -  $title");
                $update = "update $tablename set faust = '$faust' "
                    . "where seqno = $seqno";
                verbose::log(TRACE, "update:$update");
                if ($nothing == true) {
                    echo "sql(not executed):$update\n";
                }
//        else {
                $db->exe($update);
//          $db->commit();
//        }
            } else {
                // this will search both for isbn and isbn13
                $marc = $getMarcFromBasis->getMarc($isbn13);
                if ($marc) {
                    verbose::log(TRACE, "found in Basis : $isbn13");
                } else {
                    verbose::log(TRACE, "Not found in Basis: $isbn13");
                }


                if ($marc) {
                    $hit++;
                    verbose::log(TRACE, "cnt:$cnt hit:$hit count(marc):" . count($marc));

                    $faustTitles = $GetFaust->retrive($marc, $isbn13, $title, $status, $nothing);

                    // if more than one candidate returned from GetFaust->retrive,
                    // choose the one with the lowest number.
                    if (count($faustTitles) > 1) {
                        $ftsort = array();
                        foreach ($faustTitles as $ft) {
                            $ftsort[$ft[0]] = $ft;
                        }
                        krsort($ftsort);
                        $faustTitles = array();
                        $faustTitles[] = array_pop($ftsort);
                    }


                    if (count($faustTitles) > 0) {
                        $faustTitle = $faustTitles[0];
                        $update = "update $tablename set faust = $1 "
//                                . ",title = $2 "
                            . "where seqno = $seqno";
                        $strng = str_replace('$1', $faustTitle[0], $update);
                        verbose::log(TRACE, "update:$strng");
//            verbose::log(TRACE, "$1:" . $faustTitle[0] . " $2:$title");
                        if ($nothing == true) {
                            $strng = str_replace('$1', $faustTitle[0], $update);
                            echo "sql(not executed):$strng\n";
                            print_r($faustTitle);
                        } else {
//            $title = utf8_encode(substr($faustTitle[1], 0, 99));
//              $db->query_params($update, array($faustTitle[0], $title));
                            $db->query_params($update, array($faustTitle[0]));
//              $db->commit();
                        }
                    }
                }
            }
        }
    }

    if (!$nothing) {
        verbose::log(TRACE, "Commit");
        $db->exe('commit');
    }
} catch (fetException $f) {
    echo $f;
    verbose::log(ERROR, "$f");
} catch (GetMarcFromBasisException $G) {
    echo "G:" . $G;
    verbose::log(ERROR, "$G");
}
verbose::log(TRACE, "**** STOP " . __FILE__ . " ****");
?>