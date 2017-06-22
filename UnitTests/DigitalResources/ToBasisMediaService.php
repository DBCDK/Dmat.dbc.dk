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
require_once "$inclnk/OLS_class_lib/verbose_class.php";

require_once "$classes/mediadb_class.php";
require_once "$classes/ftp_transfer_class.php";
require_once "$classes/merge_class.php";
require_once "$inclnk/OPAC_class_lib/ConvertXMLtoMarc_class.php";
require_once "$classes/Calendar_class.php";


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
    echo "\t-s status (Template, ProgramMatch, OldTemplate or UpdateBasis) \n";
    echo "\t-a indholdet af ACC koden fx. 999999 \n";
    echo "\t-S seqno, kun dette seqno \n";
    echo "\t-t use test database ([testcase]connect) \n";
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
    $options = array('s' => 'UpdateBasis', 'n' => 'nothing', 'S' => 46628, 'x' => 'true', 'x' => '../UnitTests/dr.ini');
} else {
    $options = getopt("hp:ns:a:S:t");
}

if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}

if (array_key_exists('h', $options)) {
    usage();
}

if (array_key_exists('n', $options)) {
    $nothing = true;
}
$ftpnothing = $nothing;

if (array_key_exists('a', $options)) {
    $acc = $options['a'];
}

if (array_key_exists('S', $options)) {
    $onlyThisSeqno = $options['S'];
}
$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
    $fakefaust = true;
    $ftpnothing = true;
}

if ( !array_key_exists('s', $options)) {
    usage('status is missing');
}
$status = $options['s'];
if ($status != 'Template' && $status != 'UpdateBasis' && $status != 'OldTemplate' && $status != 'ProgramMatch') {
    usage('-s must be Template, ProgramMatch, OldTemplate or UpdateBasis');
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

if (($Bociuser = $config->get_value('ociuser', 'setup')) == false) {
    usage("no ociuser (seBasis) stated in the configuration file");
}
if (($Bocipasswd = $config->get_value('ocipasswd', 'setup')) == false) {
    usage("no ocipasswd (seBasis) stated in the configuration file");
}
if (($Bocidatabase = $config->get_value('ocidatabase', 'setup')) == false) {
    usage("no ocidatabase (seBasis) stated in the configuration file");
}
if (($basedir = $config->get_value('basedir', 'setup')) == false) {
    usage("no basedir  stated in the configuration file [setup]");
}

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");

try {

    $ftptransfer = new ftp_transfer_class($config, $status, $Basis, $ftpnothing);
    if ($ftptransfer->error()) {
        usage($ftptransfer->error());
    }


    if ($setup == 'testcase') {
        require_once "$mockupclasses/mockUpLibV3_class.php";
        $libV3API = new mockUpLibV3API($basedir, "data");
    } else {
        require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
        $libV3API = new LibV3API($Bociuser, $Bocipasswd, $Bocidatabase);
    }
    $connect_string = $config->get_value("connect", $setup);
    $marcBasis = new marc();
    $marcFromXml = new marc();
    $merge = new merge_class();

    $db = new pg_database($connect_string);
    $db->open();
    $mediadb = new mediadb($db, basename(__FILE__), $nothing);

    $year = date('Y');
    $calendar = new Calendar_class($db, $year);
    $cxtm = new ConvertXMLtoMarc($calendar);

    $mediadb->startTransaction();

    $dd = date('Ymd');
    $seqnumbers = $mediadb->getCandidates($status, $onlyThisSeqno);
//    var_dump($seqnumbers);
//    exit;
    $cnt = 0;
    if ($seqnumbers) {
        for ($seqnum = 0; $seqnum < count($seqnumbers); $seqnum++) {
//            if ($seqnum > 20)
//                break;

            $seqno = $seqnumbers[$seqnum]['seqno'];
            $arr = $mediadb->getInfoData($seqno);
            $faust = $arr[0]['faust'];
            $newfaust = $arr[0]['newfaust'];
            $newfaustprinted = $arr[0]['newfaustprinted'];
            $printedfaust = $arr[0]['printedfaust'];
            $publicationdate = $arr[0]['publicationdate'];
            $printedpublished = $arr[0]['printedpublished'];
            $isbn13 = $arr[0]['isbn13'];
            $expisbn = $arr[0]['expisbn'];
            $filetype = $arr[0]['filetype'];
            $choice = $arr[0]['choice'];
            $promat = $arr[0]['promat'];
            $initials = $arr[0]['initials'];
            $is_in_basis = $arr[0]['is_in_basis'];
            $xmltype = 'publizonBasis';

            $notes = $mediadb->getNoteData($seqno);
            $f991 = "991 00";

            $f07 = $f512 = $f512x = $d08x = "";
            $d08 = "d08 00";
            $d08x = $d08;
            if ($notes) {
                foreach ($notes as $note) {
                    if ($note['type'] == 'f991') {
                        $f991 .= '*o' . $note['text'];
                    } else {
                        $d08 .= '*a' . $note['text'];
                    }
                }
            }

            $lu = false;
            $dbr = false;
            $bkm = false;
            $cho = explode(' ', $choice);
            foreach ($cho as $val) {
                if ($val == 'BKMV') {
                    $d08 .= '*aSat til BKM-vurdering';
                }
                if ($val == 'L') {
                    $lu = true;
                }
                if ($val == 'DBR') {
                    $dbr = true;
                }
                if ($val == 'V' or $val == 'B' or $val == 'S' or $val == 'BKMV') {
                    $bkm = true;
                }
            }
            if ($bkm) {
                $dbr = false;
            }
            if ($newfaustprinted and !$promat) {
                $f07 .= 'f07 00*a' . $newfaustprinted . "*nautoLU";
            }
            $insertyear = false;
            if ($printedfaust) {
                $res = $libV3API->getMarcByLokalidBibliotek($printedfaust, '870970');
                $isomarc = $res[0]['DATA'];
                $marcBasis->fromIso($isomarc);
                $year = $marcBasis->findSubFields('260', 'c', 1);
                $f07c = '';
                if ($year) {
                    $f07c = "*c$year";
                }
                $f07 .= 'f07 00*a' . $printedfaust . "*nautoLU" . $f07c;
//                $insertyear = true;
                $choice .= ' L';
            }
//            if ($status == 'ProgramMatch' || $status == 'Template' || $status == 'UpdateBasis') {
//                if ($faust) {
//                    $f07 .= '*l' . $faust;
//                }
//            }


            if ($status == 'ProgramMatch') {
                $d08 .= '*oProgramMatch';
            } else {
                if ($initials) {
                    $d08 .= "*o$initials";
                    $d08x .= "*o$initials";
                } else {
                    $d08 .= "*odmat";
                }
            }
            if ($publicationdate > $dd) {
                $pd = substr($publicationdate, 6, 2) . '-' . substr($publicationdate, 4, 2) . '-' . substr($publicationdate, 0, 4);
                $d08 .= "*aForventet udgivelsesdato $pd";
                $f512 = '';
            }
            if ($printedpublished) {
//                $pdate = substr($printedpublished, 6, 4) . substr($printedpublished, 3, 2) . substr($printedpublished, 0, 2);
//                if ($pdate > $dd) {
                $d08x = $d08x . "*aForhåndsoprettet til brug for bibliotekerne, bog IKKE modtaget. Forventet udgivelsesdato ifølge forlaget $printedpublished";
                $f512x = "512 00*aEndnu ikke modtaget, forventet udgivelsesdato ifølge forlaget d. $printedpublished";
//                }
            }


            if (count($arr[0]['originalxml'])) {
                $cxtm->loadXML($arr[0]['originalxml']);
                $isomarc = $cxtm->Convert2MarcMediaService($newfaust, $xmltype, $d08, $f512, $f991, $f07, $choice, $arr[0]);
                $marcFromXml->fromIso($isomarc);
                $strng = $marcFromXml->toLineFormat();
                if ($nothing) {
                    $strng = $marcFromXml->toLineFormat();
                    echo "------------------------------------------------------------\nFra Publizon\n$strng\n\n";
                }
                if ($setup == 'testcase') {
                    $mediadb->insertIntoMarcTable($newfaust, 'publizon', $strng);
                }
                $newstatus = 'UpdatePublizon';
                $callmerge = false;
                if ($status == 'UpdateBasis') {
                    if ($promat) {
                        $newstatus = 'UpdatePromat';
                    }
                    if ($faust) {
                        $callmerge = true;
                    }
                }

                if ($status == 'Template' or $status == 'OldTemplate' or $status == 'ProgramMatch') {
                    $callmerge = true;
                }

                if ($callmerge) {
                    $res = $libV3API->getMarcByLokalidBibliotek($faust, '870970');
                    if ($status == 'OldTemplate') {
                        $year = substr($res[0]['OPRET'], 0, 4);
                    } else {
                        $year = 2015;
                    }
                    if ($year > 2009) {
                        $cnt++;
                        $isomarc = $res[0]['DATA'];
                        $marcBasis->fromIso($isomarc);

                        if ($nothing) {
                            $strng = $marcBasis->toLineFormat();
                            echo "Fra Basis(Ajour:$year)\n$strng \n\n";
                        }
                        verbose::log(DEBUG, "marc from xml:\n" . $marcFromXml->toLineFormat());
                        if ($is_in_basis) {
                            if ($dbr) {
                                $marcBasis = $merge->mergeRBD($marcBasis, $marcFromXml);
                            } else {
                                $marcBasis = $merge->mergeSmallTemplate($marcBasis, $marcFromXml);
                            }
                        } else {
                            if ($dbr) {
                                $marcBasis = $merge->mergeRBD($marcBasis, $marcFromXml);
                            } else {
                                $marcBasis = $merge->mergeTemplate($marcBasis, $marcFromXml, $status, $insertyear);
                            }
                        }
                        if ($nothing) {
                            $strng = $marcBasis->toLineFormat();
                            echo "Efter Merge:\n$strng\n\n";
                        }
//                        if ($setup == 'testcase') {
//                            $mediadb->insertIntoMarcTable($newfaust, '870970', $strng);
//                        }
                        $isomarc = $marcBasis->toIso();
                    }
                }
                $ftptransfer->write($isomarc);

                if ($expisbn) {
                    // is there already a record in basis with newfaustprinted ?
                    $alreadyThere = $libV3API->getMarcByLokalidBibliotek($newfaustprinted, '870970');
                    if ( !$alreadyThere) {
                        if ($lu) {
                            $f07 = "f07 00*a" . $newfaust . "*nautoLU";
                        } else {
//                            $f07 = "f07 00*a" . $newfaust;
                            $f07 = '';
                        }
                        $d08x .= "*aOnlineversion $newfaust";
                        $cxtm->setACCcode('ACT');
                        $isomarc = $cxtm->Convert2MarcMediaService($newfaustprinted, $xmltype, $d08x, $f512x, $f991, $f07, $choice, $arr[0]);
                        $cxtm->setACCcode('ACC');
                        $marcBasis->fromIso($isomarc);
                        $strng = $marcBasis->toLineFormat();
                        $isomarc = $merge->convert2printedFormat($marcBasis, $expisbn, $f512x);
                        $marcBasis->fromIso($isomarc);
                        $strng = $marcBasis->toLineFormat();
                        $ftptransfer->write($isomarc);
                    }
                }
                $mediadb->newStatus($seqno, $newstatus);
            }
        }
        $ftptransfer->put();
    }
    if ( !$nothing) {
        // only if no errors the database will be updated.
        $mediadb->endTransaction();
    }

} catch (Exception $e) {
    echo $e . "\n";
    exit;
}
verbose::log(TRACE, "**** STOP " . __FILE__ . " ****");
