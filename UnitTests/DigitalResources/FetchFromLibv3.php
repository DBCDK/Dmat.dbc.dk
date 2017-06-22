<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

//require_once "makeMarc.php";
//require_once "$inclnk/OPAC_class_lib/ConvertXMLtoMarc_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/ftp_transfer_class.php";


function getFausts($filename) {
    if (!is_file($filename)) {
        die("filen:$filename findes ikke!\n");
    }
    $ret = array();
    $fp = fopen($filename, "r");
    while ($ln = fgets($fp)) {
        $ln = str_replace(' ', '', trim($ln));
        if (strlen($ln) == 8) {
            $faust = substr($ln, 0, 1) . ' ' . substr($ln, 1, 3) . ' ' . substr($ln, 4, 3) . ' ' . substr($ln, 7, 1);
            $ret[]['LOKALID'] = $faust;
        }
    }
    fclose($fp);
    return $ret;
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
    echo "\t-f base (seBasis, sePhus or seLektor) \n";
    echo "\t-d start date 'YYYY-MM-DD' overrules date in database (oldest rec Phus 20082013, Basis 26112013)\n";
    echo "\t-t start time 'HH:MM:SS' overrules date in database (only valid together with -t  - default '00:00:00' \n";
    echo "\t-I input file with faust number (one at each line)\n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = FALSE;
$allRecords = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('f' => 'seLektor', 'x' => 'nothing', 'x' => 'work/faustlist', 'd' => '2017-02-07');
} else {
    $options = getopt("hp:nf:d:t:I:");
}

if (array_key_exists('h', $options))
    usage('HELP');

if (array_key_exists('p', $options))
    $inifile = $options['p'];

$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (!array_key_exists('f', $options))
    usage('base is missing');
$base = $options['f'];

if (array_key_exists('n', $options))
    $nothing = true;

$startdate = false;
if (array_key_exists('d', $options)) {
    $startdate = $options['d'];
}
$starttime = '000000';
if (array_key_exists('t', $options)) {
    $starttime = $options['t'];
}
$faustfile = "";
if (array_key_exists('I', $options)) {
    $faustfile = $options['I'];
}

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");

if (($ociuser = $config->get_value('ociuser', $base)) == false)
    usage("no ociuser ($base) stated in the configuration file");
if (($ocipasswd = $config->get_value('ocipasswd', $base)) == false)
    usage("no ocipasswd ($base) stated in the configuration file");
if (($ocidatabase = $config->get_value('ocidatabase', $base)) == false)
    usage("no ocidatabase ($base) stated in the configuration file");

if (($workdir = $config->get_value('workdir', 'setup')) == false)
    usage("no workdir stated in the configuration file");

if ($base == 'seBasis') {
    $transinfo = 'EreolBasisToWell';
}
if ($base == 'sePhus') {
    $transinfo = 'EreolPhusToWell';
}
if ($base == 'seLektor') {
    $transinfo = 'MediaToAquapr2';
}

$marc = new marc();

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");

$libv3 = new LibV3API($ociuser, $ocipasswd, $ocidatabase);

$connect_string = $config->get_value("connect", "setup");
$db = new pg_database($connect_string);
$db->open();
$mediadb = new mediadb($db, __FILE__, $nothing);

$ftpnothing = $nothing;
//$ftpnothing = true;
$ftptransfer = new ftp_transfer_class($config, 'lektoer', $transinfo, $ftpnothing);
if ($ftptransfer->error()) {
    usage($ftptransfer->error());
}


if ($startdate) {
    $lastupdated = $startdate . " " . str_replace(':', '', $starttime);
    $mediadb->setPar($base, 'ts', $lastupdated);
}
$lupar = $mediadb->getPar($base);
$lastupdated = $lupar['lastupdated'];

if ($nothing) {
    echo "lastupdate:" . $lastupdated . "\n";
}

$dbtime = $mediadb->getDBtime();
echo "dbtime:$dbtime\n";

$karensdato = date('Ymd', strtotime("$dbtime - 6 months"));

if ($faustfile) {
    $updates = getFausts($faustfile);
} else {
    if ($base == 'seLektor') {
        $library = '870976';
        $upd1 = $libv3->getLekUpdates($lastupdated);
        $upd2 = $mediadb->getLekUpdates($karensdato);
        foreach ($upd1 as $row) {
            $row['karens'] = false;
            $updates[] = $row;
        }
        foreach ($upd2 as $row) {
            $row['LOKALID'] = $row['lokalid'];
            $row['karens'] = true;
            $updates[] = $row;
        }

    } else {
        $library = '870970';
        $updates = $libv3->getUpdatedRecs($lastupdated);
    }
}
$cntupdates = count($updates);
if ($nothing) {
    echo "number of updates:$cntupdates\n";
}
$updlukarens = array();

verbose::log(DEBUG, "number of updates:$cntupdates");
$cnt = $total = $dump = 0;
if ($cntupdates) {
    foreach ($updates as $row) {
        $total++;
        $dump++;
//        if ($cnt == 1) {
//            break;
//        }
        if ($dump == 1000) {
            verbose::log(DEBUG, "fetched records from libv3:$total ($cntupdates)");
            $dump = 0;
        }
        $lokalid = $row['LOKALID'];
        $posten = $libv3->getMarcByLB($lokalid, $library);
        $iso = $posten[0]['DATA'];
        $marc->fromIso($iso);
        $strng = $marc->toLineFormat(78);
        $found = $accfound = $lekrec = false;
        $subs = $marc->findSubFields('032', 'x');
        if ($subs) {

            foreach ($subs as $sub) {
                $code = substr($sub, 0, 3);
                switch ($code) {
                case 'ACC':
                    $accfound = true;
                    break;
                case 'NLY':
                case 'ERE':
                case 'ERL':
                    $found = true;
                    break;
                case 'LEA':
//                case 'LEK':
                    $lekrec = true;
                    break;
//                default:
//                    $lekrec = true;
//                    $found = false;
//                    $found = true;
                }
            }
            if ($lekrec) {
                $opretdato = $marc->findSubFields('001', 'd', 1);
                $opretdato = substr($opretdato, 0, 8) . ' ' . substr($opretdato, 8, 6);
                if ($opretdato <= $karensdato) {
                    $found = true;
                    if (array_key_exists('karens', $row)) {
                        if ($row['karens']) {
                            $updlukarens[] = $lokalid;
                        }
                    }

                } else {
                    $ajour = $marc->findSubFields('001', 'c', 1);
                    $ajour = substr($ajour, 0, 8) . ' ' . substr($ajour, 8, 6);
                    $mediadb->updateKarens($lokalid, $opretdato, $ajour);
                }
                //                }
            } else {
                if (!$accfound) {
                    $marc->insert_subfield('ACC999999', '032', 'x');
                    $iso = $marc->toIso();
                    verbose::log(TRACE, "insert ACC $lokalid/870970");
                }
                if (!$found) {
                    $f856s = $marc->findSubFields('856', 'u');
                    foreach ($f856s as $f856) {
                        if (strstr($f856, 'ereolen.dk'))
                            $found = true;
                    }
                }

                if (!$found) {
                    $f21s = $marc->findSubFields('f21', 'a');
                    foreach ($f21s as $f21) {
                        if (strstr($f21, 'pubhub'))
                            $found = true;
                    }
                }
            }


            if ($found) {
                $cnt++;
                verbose::log(DEBUG, "records to well:$cnt");
//                fwrite($fp, $iso);
                $ftptransfer->write($iso);
            }


        }
    }
}
//fclose($fp);
if ($cnt) {
    if (!$nothing) {
//        ftp_transfer($config, $transinfo, $datafile, $workdir, $ts, $nothing);
        $ftptransfer->put();
        $mediadb->startTransaction();
        if (!$faustfile) {
            $mediadb->setPar($base, 'ts', $dbtime);
        }
        foreach ($updlukarens as $lokalid) {
            $mediadb->updateKarens($lokalid, 'xx', 'yy', 'Done');
        }
        $mediadb->endTransaction();
        $ftptransfer->removeFiles();
    }
} else {
    if (($nothing)) {
        echo "Nothing to update\n";
        $ftptransfer->removeFiles();
    }
}


verbose::log(TRACE, "$cnt records has been processed");
verbose::log(TRACE, "**** STOP " . __FILE__ . " ****");
?>