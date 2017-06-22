<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 26-11-2015
 * Time: 10:29
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$classes/mediadb_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";
//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
//require_once "$classes/lek_class.php";
//require_once "$classes/Calendar_class.php";
//require_once "$classes/ssh_class.php";
//require_once "$classes/getFaust_class.php";
//require_once "$mockupclasses/getFaust_class.php";
require_once "$classes/match_marc_class.php";
require_once "$inclnk/OPAC_class_lib/ConvertXMLtoMarc_class.php";


/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "")
{
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-f faust nummer\n";
    echo "\t-a ikke kun den sidste men alle poster fra raapost\n";
    echo "\t-F fil med faust numre, et par linje\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = "../DigitalResources.ini";


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('x' => 'faust.txt', 'f' => '5 141 283 4', 'a' => 'true');
} else {
    $options = getopt("hf:F:a");
}


if (array_key_exists('h', $options)) {
    usage();
}

$allrecs = false;
if (array_key_exists('a', $options)) {
    $allrecs = true;
}

if (array_key_exists('f', $options)) {
    $faust = $options['f'];
}
$faustfil = '';
if (array_key_exists('F', $options)) {
    $faustfil = $options['F'];
}

if (array_key_exists('n', $options)) {
    $nothing = true;
}
$onlyThisSeqno = 0;
if (array_key_exists('S', $options)) {
    $onlyThisSeqno = $options['S'];
}


$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}


if (($Bociuser = $config->get_value('ociuser', 'setup')) == false) {
    usage("no ociuser (seBasis) stated in the configuration file");
}
if (($Bocipasswd = $config->get_value('ocipasswd', 'setup')) == false) {
    usage("no ocipasswd (seBasis) stated in the configuration file");
}
if (($Bocidatabase = $config->get_value('ocidatabase', 'setup')) == false) {
    usage("no ocidatabase (seBasis) stated in the configuration file");
}

$oci = new oci($Bociuser, $Bocipasswd, $Bocidatabase);
$oci->set_charset('WE8ISO8859P1');
$oci->connect();

//verbose::open($logfile, $verboselevel);
//verbose::log(TRACE, "**** START " . __FILE__ . "****");

$marcBasis = new marc();
$localData = new marc();

echo "allrecs:$allrecs\n";

if ($faustfil) {
    $faustnumre = array();
    $fp = fopen($faustfil, 'r');
    while ($ln = fgets($fp, 100)) {
        $faustnumre[] = $ln;
    }
} else {
    $faustnumre = array($faust);
}

//echo "FAUST:$faust        ++++++++++++++++++++++++++++++\n";
//$faust = str_replace(' ', '', $faust);

foreach ($faustnumre as $faust) {
    $faust = str_replace(' ', '', $faust);
    $faust = trim($faust);
    $cmd = "rawrepo-record-inspector --db=cisterne_rawrepo_ro:laesmig@db.rr.cisterne.prod.dbc.dk:5432/cisterne_rawrepo_db 870970 $faust";
    $output = array();
    exec($cmd, $output);
    foreach ($output as $key => $ln) {
        if ($key > 0) {
//            echo "$ln\n";
            $rec = array();
            $k = $key - 1;
            $cmd2 = $cmd . " $k";
            exec($cmd2, $rec);
            $xml = implode("", $rec);
            $marcBasis->fromMarcExchange($xml);
            insertAutNumber();
            $cmd3 = str_replace('870970', '191919', $cmd2);
            $rec = array();
            exec($cmd3, $rec);
            $xml = implode("", $rec);
            $localData->fromMarcExchange($xml);
            $marcBasis->merge191919($localData);
            $strng = $marcBasis->toLineFormat();
            echo "$strng\n";
            // hvis alle varianter skal med skal nedenstående break fjerrnes
            if (!$allrecs) {
                break;
            }
        }
    }
}

function insertAutNumber()
{
    global $marcBasis, $oci;

    $f = array('100', '700');
    $s = array('a', 'h', 'c');

    foreach ($f as $field) {
        $remove = array();
        $fds = $marcBasis->findFields($field);
        foreach ($fds as $key => $fd) {
            $subarr = $fd['subfield'];
            if (subExist('4', $subarr)) {
                $linkstring = '';
                foreach ($s as $sub) {
                    if ($txt = subExist($sub, $subarr)) {
//                        $txt = $subarr[$sub];
                        $linkstring .= "and data like '%$txt%' ";
                    }
                }
                $sql = "select * from poster where bibliotek = '870979' and substr(data,6,1) != 'd' $linkstring ";
                $arr = $oci->fetch_all_into_assoc($sql);
                if (count($arr) == 1) {
                    $sub6 = $arr[0]['LOKALID'];
                    $strng = "$field 00*5870970*6$sub6";
                    foreach ($subarr as $ss) {
                        if (substr($ss, 0, 1) == '4') {
                            $strng .= "*$ss";
                        }
                    }

                    $dta = array('key' => $key, 'strng' => $strng);
                    $remove[] = $dta;

                } else {
                    echo "Forkert antal retur fra auth basen: count == " . count($arr) . "\n";
                    foreach ($arr as $nxt) {
                        $marcBasis->fromIso($nxt['DATA']);
                        $nxtstr = $marcBasis->toLineFormat();
                        echo "$nxtstr \n";
                        exit(10);
                    }
                }

                foreach ($remove as $rem) {
                    $marcBasis->remField($field, $rem['key']);
                }
                foreach ($remove as $rem) {
                    $marcBasis->insert($marcBasis->stringToField($rem['strng']));
//                    $st = $marcBasis->toLineFormat();
                }
                while ($marcBasis->remField('900')) ;
            }
        }
    }
//    $st = $marcBasis->toLineFormat();
//    $a = 0;
}

//
//    foreach ($f as $field) {
//        while ($marcBasis->thisField($field)) {
//            $likestring = '';
//            if (!$marcBasis->thisSubfield('4')) {
//                continue;
//            }
//            foreach ($s as $subfield) {
//                while ($marcBasis->thisSubfield($subfield)) {
//                    if ($txt = $marcBasis->subfield()) {
//                        $likestring .= "and data like '%$subfield" . "$txt%' ";
//                        $marcBasis->remSubfield();
//                    }
//                }
//            }
//            $sql = "select * from poster where bibliotek = '870979' and substr(data,6,1) != 'd' $likestring ";
//            $arr = $oci->fetch_all_into_assoc($sql);
//            if (count($arr) == 1) {
//                $sub6 = $arr[0]['LOKALID'];
//                $marcBasis->addSubfield('5', '870979');
//                $marcBasis->addSubfield('6', $sub6);
//                // this lines only for having the order of subfields: 5, 6, 4
////                $sub4arr = array();
////                while ($marcBasis->thisSubfield('4')) {
////                    $sub4arr[] = $marcBasis->subfield();
////                    $marcBasis->remSubfield();
////                }
////                foreach ($sub4arr as $sub4) {
////                    $marcBasis->addSubfield('4', $sub4);
////                }
//                $marcBasis->changeOrderSubfields($field, '564');
//                while ($marcBasis->remField('900')) ;
//                $st = $marcBasis->toLineFormat();
//            } else {
//                echo "Forkert antal retur fra auth basen: count == " . count($arr) . "\n";
//                foreach ($arr as $nxt) {
//                    $marcBasis->fromIso($nxt['DATA']);
//                    $nxtstr = $marcBasis->toLineFormat();
//                    echo "$nxtstr \n";
//                    exit(10);
//                }
//            }
//
//        }
//    }
//}

function subExist($subfield, $sub)
{
    foreach ($sub as $ss) {
        if (substr($ss, 0, 1) == $subfield) {
            return $ss;
        }
    }
    return false;
}