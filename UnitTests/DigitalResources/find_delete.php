#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


/**
 * @file insertFaustFromUgeleverance.php
 * @brief takes a file with marc-records and compare them with the records in the database to see if they have been catalouged
 * The program will only be used once, when the project is started.
 * It will read the file:   publizon.s00 - which contains marc records.
 * It will extract the isbn/isbn13, and compare them with the database (table:digitalresources)
 * If any "hit" the faust-id from the marc record will be inserted into the database.
 *
 * Because its a one timer, there is no verbose, log or comment in the program
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 *
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
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
    echo "\t-s skip opdatering 1 af tabellen\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/..//DigitalResources.ini";
$nothing = false;

$options = getopt("hp:s");
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];

$skip = false;
if (array_key_exists('s', $options))
    $skip = true;


// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (( $ociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser stated in the configuration file");
if (( $ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd stated in the configuration file");
if (( $ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase stated in the configuration file");

$tablename = 'slettede';
$connect_string = $config->get_value("connect", "setup");
$libV3API = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
$marcBasis = new marc();

try {
    $db = new pg_database($connect_string);
    $db->open();

    $marc = new marc();
    $cnt = $cntere = $notInBasis = 0;

    if (!$skip) {
        $marc->OpenMarcFile('EREOL_SLETTEPOSTER_1248.s00');
//        $marc->OpenMarcFile('201109151036.dbc.EBOGSBIB_TEST');
        while ($marc->readNextMarc()) {
            $faust = "";
//    $baseisbn13 = "";
            //echo "cnt:" . $cnt++ . "\n";
            if ($res = $marc->findSubFields('001', 'a')) {
                foreach ($res as $faust) {
                    echo "faust:$faust\n";
                    //$sql = "select isbn13 from $tablename where isbn13 = '$isbn13' and format = 'Lydbog'";
                    $sql = "select seqno, faust from $tablename where faust = '$faust'";
                    $arr = $db->fetch($sql);
//        );
//        if (!$marcRecords) {
//          $notInBasis++;
//          continue;
//        }
                    $cnt++;
                    if ($arr) {
                        if (count($arr) > 1)
                            die('UHAAAAAAAAAAAAAAAA');
//          print_r($arr);
                        $seqno = $arr[0]['seqno'];
//          $marcRecords = $libV3API->getMarcByLokalidBibliotek($faust, '870970');
//          $marcBasis->fromIso($marcRecords[0]['DATA']);
//          $f032s = $marcBasis->findSubFields('032', 'x');
//          if ($f032s) {
////            print_r($f032s);
//            $erekode = false;
//            foreach ($f032s as $f032) {
//              if (substr($f032, 0, 3) == 'ERE')
//                $erekode = true;
//            }
////            echo "erecode:$erekode\n";
//            if ($erekode) {
//              $cntere++;
//            }
//          }
                        $update = "update $tablename set er_sendt = 'ja' where seqno = $seqno";
                        echo $update . "\n";
                        $db->query_params($update);
//                        $baseisbn13 = $arr[0]['isbn13'];
                        //echo "baseisbn13=$baseisbn13\n";
                    }
                }
            }
        }
    }
    $fp = fopen('findesIBasis', 'w');
    $select = "
    select seqno, faust from slettede where er_sendt is null
   ";
    echo $select;
    $res = $db->fetch($select);
    foreach ($res as $record) {
        print_r($record);
        $faust = $record['faust'];
        $seqno = $record['seqno'];
        $marcRecords = $libV3API->getMarcByLokalidBibliotek($faust, '870970');
        if (!$marcRecords)
            continue;
        $data = $marcRecords[0]['DATA'];
        $marcBasis->fromIso($marcRecords[0]['DATA']);
        $d08s = $marcBasis->findSubFields('d08', 'a');
        print_r($d08s);
        $fjernet = false;
        if ($d08s) {
            foreach ($d08s as $d08) {
                if (substr($d08, 0, 14) == 'Fjernet fra eR')
                    $fjernet = true;
            }
        }
        echo "fjernet:$fjernet\n";
        if (!$fjernet) {
            echo "Skriv\nh";
            fwrite($fp, $data);
        }
//    print_r($marcRecords);
//    fclose($fp);
//    exit;
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}
fclose($fp);
echo "cnt:$cnt, notInBasis:$notInBasis cntere:$cntere\n";
?>