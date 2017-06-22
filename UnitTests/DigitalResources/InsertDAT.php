<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

//require_once "makeMarc.php";
//require_once "$inclnk/OPAC_class_lib/ConvertXMLtoMarc_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
//require_once "$inclnk/OLS_class_lib/weekcode_class.php";
//require_once 'merge_class.php';

/**
 *
 * @param type $seqno
 */
function getFormatFromXml($db, $cxtm, $seqno) {
    global $tablename;
    $sql = "
                  select originalXML as xml, status from $tablename
                    where
                       seqno = $seqno
                       and provider = 'Pubhub'
                ";
//            echo "$sql";
    $arr = $db->fetch($sql);
    $from = array("\n", '&#x2013;', '&#x201C;', '&#x201D;', '&#x2019;', '&#x2026;');
    $to = array("  ", '-', '"', '"', "'", "...");
    $xml = str_replace($from, $to, $arr[0]['xml']);
    $cxtm->loadXML($xml);
    $ft = $cxtm->getFormatType();
    return $ft;
}

/**
 * This function takes the data files ($datafile[$destination] and transfer the
 * via FTP to "dbcposthus.dbc.dk/datain"
 *
 * @global type $nothing - when true no update of the database or sending of files via FTP
 * @param type $config - the config (ini file) pointer
 * @param type $destination -  PubHubPhus or PubHubBasis - determin which database to sent to.
 * @param type $datafile - an array of datafiles - indexed by $destination
 * @param type $workdir - where to put temporary files
 * @param type $ts - ts, so alle files got the same timestamp
 */
function ftp_transfer($config, $destination, $datafile, $workdir, $ts) {
    global $nothing;

    if (($transline = $config->get_value('transline', $destination)) == false)
        usage("no transline stated in the configuration file ($destination)");
    if (($transfile = $workdir . "/" . $config->get_value('transfile', $destination)) == false)
        usage("no transfile stated in the configuration file ($destination)");

    $transfile = str_replace('$ts', $ts, $transfile);
    $transline = str_replace('$datafile', basename($datafile[$destination]), $transline);

    if (!$fptrans = fopen($transfile, "w")) {
        echo "couldn't open the file:" . $transfile . " for writing\n";
        verbose::log(ERROR, "couldn't open the file:" . $transfile . " for writing");
        exit(3);
    }
    fwrite($fptrans, $transline);
    fwrite($fptrans, "\nslut\n");
    fclose($fptrans);

    $ftp_server = $config->get_value('ftp_server', 'setup');
    $ftp_user_name = $config->get_value('ftp_user_name', 'setup');
    $ftp_user_pass = $config->get_value('ftp_user_pass', 'setup');

    if ($nothing) {
        echo "nothing: No ftp transfer\n";
    } else {

// send the file using FTP
// set up basic connection
        if (!$conn_id = ftp_connect($ftp_server)) {
            verbose::log(ERROR, "FTP connection has failed ($ftp_server)");
            die("FTP connection has failed ($ftp_server)!\n");
        }
// login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);

// check connection
        if ((!$conn_id) || (!$login_result)) {
            verbose::log(ERROR, "FTP connection has failed ($ftp_server,$ftp_user_name,$ftp_user_pass)!");
            die("FTP connection has failed ($ftp_server,$ftp_user_name,$ftp_user_pass)!");
        }

// try to change the directory to datain
        if (!ftp_chdir($conn_id, "datain")) {
            echo "Couldn't change directory to datain\n";
            verbose::log(ERROR, "Couldn't change directory to datain");
            exit(5);
        }
        if (!ftp_put($conn_id, basename($datafile[$destination]), $datafile[$destination], FTP_BINARY)) {
            echo "There was a problem while uploading " . $datafile[$destination] . "\n";
            verbose::log(ERROR, "There was a problem while uploading " . $datafile[$destination]);
            exit(6);
        }
        if (!ftp_put($conn_id, basename($transfile), $transfile, FTP_BINARY)) {
            echo "There was a problem while uploading $transfile\n";
            verbose::log(ERROR, "There was a problem while uploading $transfile");
            exit(6);
        }

// close the connection
        ftp_close($conn_id);

        unlink($transfile);
        unlink($datafile[$destination]);
    }
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
//    echo "\t-f bases (ebib,Netlydbog,eReolen) \n";
//    echo "\t-w numbers-of-weeks PRODUCTION \n\t\t(if stated the week-code will be the current week-code + numbers-of-week, \n\t\tif not stated the week-code will be current week + 1 (only for basis updates))\n";
//    echo "\t-W numbers-of-weeks DAT \n\t\t(if stated the DAT code will be the current week-code + 4 days + numbers-of-week, \n\t\tif not stated the week-code will be current week + 4 days + 1 (only for basis updates))\n";
//    echo "\t-T total - all records (ignoring sent_to_basis)\n";
//    echo "\t-s seqno - make a marc-record from this seqno\n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = FALSE;
$allRecords = false;
//$configsection = "eLibToBasis";
$Phus = 'PubHubToPhus';
$Basis = 'PubHubToBasis';
$seqno = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('S' => 51430, 'n' => 'nothing', 'f' => 'Netlydbog,NetlydbogLicens,eReolen,eReolenLicens,Deff');
//    $options = array('x' => 50924, 'n' => 'nothing', 'f' => 'NetlydbogLicens');
} else {
    $options = getopt("hp:nf:w:W:Ts:");
}

if (array_key_exists('s', $options)) {
    $seqno = $options['s'];
}
if (array_key_exists('h', $options))
    usage();

if (array_key_exists('n', $options))
    $nothing = true;

if (array_key_exists('T', $options))
    $allRecords = true;

if (array_key_exists('p', $options))
    $inifile = $options['p'];

//if (!array_key_exists('f', $options) && !$seqno) {
//    usage('bases is missing');
//}
//$foption = $options['f'];
//$arr = explode(',', $foption);
//$formats = "";
//foreach ($arr as $format) {
//    $formats .= "'" . $format . "',";
//}
//$formats = trim($formats, ',');

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");

if (($Bociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser (seBasis) stated in the configuration file");
if (($Bocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd (seBasis) stated in the configuration file");
if (($Bocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase (seBasis) stated in the configuration file");

if (($Pociuser = $config->get_value('ociuser', 'sePhus')) == false)
    usage("no ociuser (sePhus) stated in the configuration file");
if (($Pocipasswd = $config->get_value('ocipasswd', 'sePhus')) == false)
    usage("no ocipasswd (sePhus)stated in the configuration file");
if (($Pocidatabase = $config->get_value('ocidatabase', 'sePhus')) == false)
    usage("no ocidatabase (sePhus) stated in the configuration file");

if (($workdir = $config->get_value('workdir', 'setup')) == false)
    usage("no workdir stated in the configuration file");

$datafile = array();
if (($datafile[$Basis] = $workdir . "/" . $config->get_value('datafile', $Basis)) == false)
    usage("no datafile stated in the configuration file ($Basis)");

if (($datafile[$Phus] = $workdir . "/" . $config->get_value('datafile', $Phus)) == false)
    usage("no datafile stated in the configuration file ($Phus)");

$ts = date('YmdHi');
$datafile[$Basis] = str_replace('$ts', $ts, $datafile[$Basis]);
$datafile[$Phus] = str_replace('$ts', $ts, $datafile[$Phus]);

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");


try {
    $tablename = "digitalresources";

    $libV3API = new LibV3API($Bociuser, $Bocipasswd, $Bocidatabase);

    $connect_string = $config->get_value("connect", "setup");

    $marcBasis = new marc();
    $db = new pg_database($connect_string);
    $db->open();


    $sql = "SELECT seqno, provider, createdate, format, idnumber, title, originalxml,
       marc, faust, isbn13, sent_to_basis, sent_to_well, sent_to_covers,
       status, deletedate, sent_xml_to_well, cover_status, updated,
       costfree
         FROM digitalresources
         where provider in ('Pubhub','PubhubDel')
        and status = 'd'";
    $rows = $db->fetch($sql);

    $cnt = array();
    $cnt[$Phus] = 0;
    $cnt[$Basis] = 0;
    $date = new DateTime();
    $dat = 'DAT991608';
    $update = false;
    if ($rows) {
        foreach ($rows as $row) {
            $lokalid = $row['faust'];
            $res = $libV3API->getMarcByLokalidBibliotek($lokalid, '870970');
            if ($res) {
                $marcBasis->fromIso($res[0]['DATA']);
//                $strng = $marcBasis->toLineFormat();
                $weekCodes = $marcBasis->findSubFields('032', 'x');
                if (count($weekCodes) == 0) {
                    $update = true;
                } else {

                    foreach ($weekCodes as $weekCode) {
                        $code = substr($weekCode, 0, 3);
                        if ($code == 'DAT') {
                            $dwcode = substr($weekCode, 3, 6);
                            $dato = substr($row['deletedate'], 0, 10);
                            $date->createFromFormat('Y-m-d', $dato);
                            $delweek = $date->format('YW');
                            if ($delweek > $dwcode) {
                                $update = true;

                            }

                        }
                    }
                }

                $update = true;
                if ($update) {
                    $PhusOrBasis = $Basis;
                } else {
                    $PhusOrBasis = $Phus;
                }
                $marcBasis->insert_subfield($dat, '032', 'x');
                $isomarc = $marcBasis->toIso();
                if (!$fp = fopen($datafile[$PhusOrBasis], "a")) {
                    echo "couldn't open the file:" . $datafile[$PhusOrBasis] . " for writing\n";
                    verbose::log(ERROR, "couldn't open the file:" . $datafile[$PhusOrBasis] . " for writing");
                    exit(3);
                }
                fwrite($fp, $isomarc);
                fclose($fp);
                $cnt[$PhusOrBasis]++;
            } else {
                echo "ikke fundet i basis $lokalid\n";
            }
        }
    }

    print_r($cnt);

    if ($cnt[$Basis])
        ftp_transfer($config, $Basis, $datafile, $workdir, $ts);
    if ($cnt[$Phus])
        ftp_transfer($config, $Phus, $datafile, $workdir, $ts);


} catch (marcException $me) {
    echo "Fanget $me";
    exit;
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}
?>
