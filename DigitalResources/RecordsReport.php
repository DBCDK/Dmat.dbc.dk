<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

//require_once "makeMarc.php";
require_once "ConvertXMLtoMarc_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";

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
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = FALSE;

$options = getopt("hp:n");

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

if (( $logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (( $verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
if (( $ociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser stated in the configuration file");
if (( $ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd stated in the configuration file");
if (( $ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase stated in the configuration file");
if (( $pociuser = $config->get_value('ociuser', 'sePhus')) == false)
    usage("no ociuser stated in the configuration file");
if (( $pocipasswd = $config->get_value('ocipasswd', 'sePhus')) == false)
    usage("no ocipasswd stated in the configuration file");
if (( $pocidatabase = $config->get_value('ocidatabase', 'sePhus')) == false)
    usage("no ocidatabase stated in the configuration file");
$upload_cmd = str_replace('@', '"', $config->get_value('upload_cmd', 'setup'));
$isofile = "isofile";

//$Phus = '7';
$Basis = '1';
$PhusOrBasis = $Basis;

$tablename = "digitalresources";
$connect_string = $config->get_value("connect", "setup");
$cxtm = new ConvertXMLtoMarc('eReolenPhus');

$marc = new marc();
$fpErr = fopen('errRecords.txt', "w");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START  " . __FILE__ . "****");

try {

    $libV3API = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
    $PhusLibV3API = new LibV3API($pociuser, $pocipasswd, $pocidatabase);

    $db = new pg_database($connect_string);
    $db->open();
    $sql = "
      select seqno, faust, format, originalxml
        from $tablename
        where provider = 'Pubhub'
        and faust is not null
        and status != 'd'
    ";

    $results = $db->fetch($sql);

    $OKS = array(0 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0);

    $cnt = $without = 0;
    foreach ($results as $result) {
        $cnt++;
//    print_r($result);
        $faust = $result['faust'];
        $seqno = $result['seqno'];
        $format = $result['format'];
        $xml = $result['originalxml'];
        $replace = true;

        $marcRecords = $libV3API->getMarcByLokalidBibliotek($faust, '870970');
//    print_r($marcRecords);
        if ($marcRecords) {
            $ok = 0;
            $weekcode = $stacode = false;
            $marc->fromIso($marcRecords[0]['DATA']);

            $f032s = $marc->findSubFields('032', 'x');
            foreach ($f032s as $f032) {
                if (substr($f032, 0, 3) == 'ERE' || substr($f032, 0, 3) == 'NLY') {
                    $weekcode = true;
                }
            }
            $sta = $marc->findSubFields('004', 'r', 1);
            if ($sta != 'd') {
                $stacode = true;
            }

            $f21index = -1;
            if ($weekcode && $stacode) {
// is there a f21 field
                $f21s = $marc->findFields('f21');
//        print_r($f21s);
                foreach ($f21s as $index => $f21) {
                    $ok = 0;
                    $subfields = $f21['subfield'];
                    foreach ($subfields as $subfield) {
                        if ($subfield[0] == 'a') {
                            $pos = strstr(strtolower($subfield), 'pubhub', 1);
                            if ($pos) {
                                $ok += 1;
                                $f21index = $index;
                            }
                        }
                        if ($subfield[0] == 'n' && strlen($subfield) > 1) {
                            $ok += 2;
                        }
                    }

                    if ($ok == 3) {
                        $replace = false;
                    }
//          $sta = $marc->findSubFields('004', 'r', 1);
//          if ($sta == 'd') {
//            $replace = false;
//          }
// Test udskrivning
//        if ($OKS[$ok] == 1) {
//          echo "((Type:$ok)$cnt)BASIS:\n" . $marc->toLineFormat() . "\n";
//        }
                }
                if ($replace) {
                    $OKS[$ok] = $OKS[$ok] + 1;
                    $without++;
                    $xmltype = $format . 'Phus';
                    $status = 'n';
                    $cxtm->loadXML($xml);
                    $isomarc = $cxtm->Convert2Marc($faust, $status, $xmltype);
                    $xmlMarc = new marc();
                    $xmlMarc->fromIso($isomarc);

                    if ($OKS[$ok] == 1) {
                        $arr = explode("\n", $xmlMarc->toLineFormat());
                        foreach ($arr as $line) {
                            if (substr($line, 0, 3) == 'f21') {
                                echo $line . "\n";
                            }
                        }
                        echo "((Type:$ok)$cnt)BASIS:\n" . $marc->toLineFormat() . "\n";
//        echo "($cnt/$without)MARC:\n" . $xmlMarc->toLineFormat() . "\n";
                    }
//          $newf21 = $xmlMarc->findFields('f21');
                    if ($f21index > -1) {
//            if ( $ok == 1 || $ok == 5 ) {
                        $marc->remField('f21', $f21index);
                    }
//          if ( $ok == 0 || $ok == 2 ) {
                    //indsæt nyt felt
                    $newf21 = $xmlMarc->findFields('f21');
                    $marc->insert($newf21[0]);
                    echo "((Type:$ok)$cnt)NEW BASIS:\n" . $marc->toLineFormat() . "\n";
                    $isomarc = $marc->toIso();
                    if (!$fp = fopen($isofile, "w")) {
                        echo "couldn't open the file:$isofile for writing\n";
                        verbose::log(ERROR, "couldn't open the file:$isofile for writing");
                        exit(3);
                    }
                    fwrite($fp, $isomarc);
                    fclose($fp);

                    $cmd = $startdir . "/" . $upload_cmd;
                    $cmd = str_replace('$PhusOrBasis', "$PhusOrBasis", $cmd);
                    if ($nothing) {
                        $fout = fopen('marc.iso', 'a');
                        fwrite($fout, $isomarc);
                        fclose($fout);
                        echo "\ncmd:$cmd\n";
                        verbose::log(DEBUG, "THIS COMMAND IS NOT EXECUTED cmd:$cmd");
                    } else {
                        echo "[" . $cmd . "]\n";
                        verbose::log(TRACE, "cmd:$cmd");
                        $return_string = system($cmd, $return_var);
                        if ($return_var) {
                            echo "The cmd:$cmd is in error: $return_var\n";
                            echo "return_string:$return_string\n";
                            verbose::log(ERROR, "The cmd:$cmd is in error: $return_var, return_string:$return_string");
                            exit(2);
                        }
                    }
                }
            }
        } else {
//        echo "delete eller uden NLY/ERE\n";
//        echo "((Type:$ok)$cnt)BASIS:\n" . $marc->toLineFormat() . "\n";
// check om den findes i Phus
            $PhusRecords = $PhusLibV3API->getMarcByLokalidBibliotek($faust, '870970');
            if (!$PhusRecords) {
                fwrite($fpErr, $marc->toLineFormat() . "\n");
            }
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}

fclose($fpErr);
print_r($OKS);
verbose::log(TRACE, "**** STOP RecordsReport.php " . __FILE__ . "****");
?>
