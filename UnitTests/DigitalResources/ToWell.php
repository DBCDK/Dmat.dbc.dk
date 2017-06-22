#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file ToWell.php
 * @brief convert from  xml to danmarc2 or a raw xml and send the records
 * to "posthuset" along with a "trans" file.
 *
 * @author Hans-Henrik Lund
 *
 * @date 24-04-2012
 *
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

//require_once "makeMarcPubHub.php";
require_once "$inclnk/OPAC_class_lib/ConvertXMLtoMarc_class.php";
require_once "XMLtoRawXml_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";

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
    echo "\t-t transfile section (mandatory)\n";
    echo "\t-S seqno\n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-v verbose level\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "../../DigitalResources.ini";
$transSection = "";
$nothing = false;
$test = 0;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('t' => 'PubHubXMLtoWell', 'n' => 'nothing', 'S' => 11811);
} else {
    $options = getopt("hv:np:t:");
}
//$options['t'] = 'PubHubXMLtoWell';
//$options['p'] = 'DigitalResources.kiska.ini';

if (array_key_exists('t', $options))
    $transSection = $options['t'];
else
    usage('Transfile section (-t) is missing');

if (array_key_exists('h', $options))
    usage();
if (array_key_exists('n', $options))
    $nothing = true;
if (array_key_exists('p', $options))
    $inifile = $options['p'];
$seqno = 0;
if (array_key_exists('S', $options)) {
    $seqno = $options['S'];
}
if (array_key_exists('v', $options))
    $test = $options['v'];


// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

//$transSection = 'PubHubToWell';
if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
if (($transline = $config->get_value('transline', $transSection)) == false)
    usage("no transline stated in the configuration file ($transSection)");
if (($transfile = $config->get_value('transfile', $transSection)) == false)
    usage("no transfile stated in the configuration file ($transSection)");
if (($datafile = $config->get_value('datafile', $transSection)) == false)
    usage("no datafile stated in the configuration file ($transSection)");
if (($format = $config->get_value('format', $transSection)) == false)
    usage("no format stated in the configuration file ($transSection)");
if (($outputformat = $config->get_value('outputformat', $transSection)) == false)
    usage("no outputformat stated in the configuration file ($transSection)");
if (($sent_to = $config->get_value('sent_to', $transSection)) == false)
    usage("no sent_to stated in the configuration file ($transSection)");

$ts = date('Ymdhi');
$datafile = str_replace('$ts', $ts, $datafile);
$transfile = str_replace('$ts', $ts, $transfile);
$transline = str_replace('$datafile', $datafile, $transline);

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");

$ftp_server = $config->get_value('ftp_server', 'setup');
$ftp_user_name = $config->get_value('ftp_user_name', 'setup');
$ftp_user_pass = $config->get_value('ftp_user_pass', 'setup');

if (!$fpdata = fopen($datafile, "w")) {
    echo "couldn't open the file:$datafile for writing\n";
    verbose::log(ERROR, "couldn't open the file:$datafile for writing");
    exit(3);
}
$tablename = "digitalresources";
//$tablename = "diff";

if ($outputformat == 'RawXml') {
    $cxtm = new XMLtoRawXml($outputformat);
} else {
    $cxtm = new ConvertXMLtoMarc();
}

$connect_string = $config->get_value("connect", "setup");
try {
    $db = new pg_database($connect_string);
    $db->open();

    // in order to have a  roll back starting point
//    $db->exe("START TRANSACTION");

    $cnt = 0;
    $inputformats = explode(',', $format);
    $sqlformat = "";
    foreach ($inputformats as $iformat) {
        $sqlformat .= "'$iformat',";
    }
    $sqlformat = "and format in (" . rtrim($sqlformat, ',') . ")";
    if ($seqno) {
        $sql = "select seqno from $tablename where
      provider in ('Pubhub','PubhubDel')
      and faust is not null
      and left(faust,2) != 'd:'
      and seqno = $seqno
      and originalXML is not null
      $sqlformat
  ";
    } else {
        $sql = "select seqno from $tablename where
      provider in ('Pubhub','PubhubDel')
      and faust is not null
      and left(faust,2) != 'd:'
      and $sent_to is null
      and originalXML is not null
      $sqlformat
  ";
    }
    $seqnumbers = $db->fetch($sql);
    echo "SQL before: \n$sql\n";
    if ($nothing) {
        echo "nothing:\n$sql\n" . "antal:" . count($seqnumbers) . "\n";
//    exit;
    }
    $a = count($seqnumbers[0]);
    echo "antal poster:$a\n";
    if ($seqnumbers) {
        foreach ($seqnumbers as $seqnos) {
            $a = count($seqnos);
            echo "antal: $a\n";
            foreach ($seqnos as $seqno) {
                $sql = "select originalXML, faust, isbn13, status, format "
                    . "from $tablename where seqno = $seqno";
                $arr = $db->fetch($sql);
                $orgxml = $arr[0]['originalxml'];
                $faust = $arr[0]['faust'];
                $status = $arr[0]['status'];
                $isbn13 = $arr[0]['isbn13'];
                $provider_format = $arr[0]['format'];
                // in the description element, there is some UTF-8 encoding that you have to deal with manually
                $cnt++;
                if ($nothing)
                    echo "CNT:$cnt\n";
                if ($outputformat == 'RawXml') {
                    $cxtm->loadXML($orgxml);
                    $rawxml = $cxtm->Convert2Raw($faust, $status, $provider_format);
                    $pos = strpos($rawxml, "\n");
                    if ($pos)
                        $rawxml = ltrim(substr($rawxml, $pos));
                    if ($cnt == 1)
                        fwrite($fpdata, '<?xml version="1.0" encoding="UTF-8"?>' . "\n<DbcWrapper>\n");
                    fwrite($fpdata, $rawxml);
                    $sql = "update $tablename "
                        . "set $sent_to = current_timestamp "
                        . "where seqno = $seqno";
                } else {
                    $from = array("\n", '&#x2013;', '&#x201C;', '&#x201D;', '&#x2019;', '&#x2026;');
                    $to = array("  ", '-', '"', '"', "'", "...");
                    $xml = str_replace($from, $to, $arr[0]['originalxml']);
                    $xml = str_replace('*', '@*', $xml);
                    $cxtm->loadXML($xml);
                    $marc = $cxtm->Convert2Marc($faust, $status, $outputformat);
                    fwrite($fpdata, $marc);
                    $sql = "update $tablename "
                        . "set $sent_to = current_timestamp "
                        . "where seqno = $seqno";
                }
                verbose::log(DEBUG, "sql:$sql");
                if ($nothing) {
                    echo "nothing:$sql";
                } else {
                    $db->exe($sql);
                    echo "SQL:$sql\n";
                }
            }
        }
    }
    if ($outputformat == 'RawXml')
        fwrite($fpdata, '</DbcWrapper>' . "\n");
    fclose($fpdata);

    if ($cnt) {
        if (!$fptrans = fopen($transfile, "w")) {
            echo "couldn't open the file:$transfile for writing\n";
            verbose::log(ERROR, "couldn't open the file:$transfile for writing");
            exit(3);
        }
        fwrite($fptrans, $transline);
        fwrite($fptrans, "\nslut\n");
        fclose($fptrans);

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
            if (!ftp_put($conn_id, basename($datafile), basename($datafile), FTP_BINARY)) {
                echo "There was a problem while uploading $datafile\n";
                verbose::log(ERROR, "There was a problem while uploading $datafile");
                exit(6);
            }
            if (!ftp_put($conn_id, basename($transfile), basename($transfile), FTP_BINARY)) {
                echo "There was a problem while uploading $transfile\n";
                verbose::log(ERROR, "There was a problem while uploading $transfile");
                exit(6);
            }

            // close the connection
            ftp_close($conn_id);

            $db->exe('commit');
            unlink($transfile);
            unlink($datafile);
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    verbose::log(ERROR, "Exception:$e");
    exit(6);
} catch (DOMException $e) {
    echo $e . "\n";
    verbose::log(ERROR, "DOMException:$e");
    exit(7);
}
verbose::log(TRACE, "$cnt records sent to Well");
verbose::log(TRACE, "**** STOP ToWell.php ****");
?>