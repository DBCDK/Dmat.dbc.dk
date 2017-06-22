<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 24-11-2016
 * Time: 10:20
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$inclnk/OPAC_class_lib/GetMarcFromBasis_class.php";

$inifile = "DigitalDrift.ini";
//$inifile = "../DigitalResources.ini";
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
$noDublets = true;
$hsb = true;
$libV3API = new LibV3API($Bociuser, $Bocipasswd, $Bocidatabase);
$libV3API->set('withAuthor', true);
$getM = new GetMarcFromBasis('work', $Bociuser, $Bocipasswd, $Bocidatabase);
$connect_string = $config->get_value("connect", 'setup');

$marc = new marc();
$marcCand = new marc();

$db = new pg_database($connect_string);
$db->open();

$retro = 'retroresult';
$drop = "drop table if exists $retro";
$db->exe($drop);

$create = "create table $retro (
             type varchar(20),
             seqno int,
             dmatstat varchar(20),
             title varchar(35),
             efaust varchar(15),
             cfaust varchar(15),
             fulltxt varchar(100),
             comparetxt varchar(200)
             )
            ";
$db->exe($create);

$sql = "select seqno, faust, isbn13 from digitalresources 
         where provider = 'Pubhub'
         and format in ('eReolen','eReolenLicens') 
         offset 0";


$rows = $db->fetch($sql);
$candidates = count($rows);
$cntHasLektoer = 0;
$tot = 0;
$cntSave = 0;
$cntNotSave = 0;
$cntdel = 0;
$cntDiff = 0;

foreach ($rows as $row) {
    $seqno = $row['seqno'];
    $faust = $row['faust'];
    $tot++;
    echo "Total:$tot save:$cntSave, not save:$cntNotSave del:$cntdel Diff:$cntDiff\n";
    $rec = $libV3API->getMarcByLokalidBibliotek($faust, '870970');
    if (!$rec) {
        echo "posten findes ikke i Basis:$seqno $faust \n";
        continue;
    }
    // findes den i Dmat
    $isbn13 = $row['isbn13'];
    $sql = "select s.seqno as seqno, status, faust, newfaust, printedfaust 
             from mediaservice s, mediaebooks e 
             where isbn13 = '$isbn13' 
                and s.seqno = e.seqno";
    $dmats = $db->fetch($sql);
    if ($dmats) {
        if (count($dmats) != 1) {
            echo "ERROR: forkert antal hits i digitalresources $isbn13\n";
            exit;
        }
    } else {
        echo "ERROR: posten findes ikke i Dmat $isbn13\n";
        continue;
    }
    $dmat = $dmats[0];
    $dmatseqno = $dmat['seqno'];

    $marc->fromIso($rec[0]['DATA']);
    $astrng = $marc->toLineFormat();
    if ($marc->HasLektoer()) {
        $cntHasLektoer++;
        continue;
    }
    $f032xs = $marc->findSubFields('032', 'x');
    $found032 = false;
    if ($f032xs) {
        foreach ($f032xs as $f032x) {
            if (substr($f032x, 0, 3) == 'BKM') {
                $found032 = true;
            }
        }
    }
    if (!$found032) {
        echo "No BKM\n";
        continue;
    }
    // er det en bind post?
    $multvol = $marc->findSubFields('004', 'a');
    if ($multvol) {
        if ($multvol[0] == 'b') {
            continue;
        }
    }
    $search = '';
    $author = '';
    $title = '';
    $field = $marc->findSubFields('245', 'a');
    if ($field) {
        $title = $field[0];
    }
//    $author = '';
//    $names = getNames($marc);
//    if (count($names)) {
//        $author = $names[0];
//    }
//
    $field = $marc->findSubFields('100', 'a');
    if ($field) {
        $author = $field[0];
    }
    $field = $marc->findSubFields('100', 'h');
    if ($field) {
        $author .= ' ' . $field[0];
    }
    if (!$author) {
        $field = $marc->findSubFields('700', 'a');
        if ($field) {
            $author = $field[0];
        }
        $field = $marc->findSubFields('700', 'h');
        if ($field) {
            $author .= ' ' . $field[0];
        }
    }

    if ($title == '') {
        echo "Ingen titel:\n";
        echo "$astrng\n";
    }

    if ($author == '') {
        echo "Ingen forfatter:\n";
        echo "$astrng\n";
//        exit;
    }
    $search .= "ti='$title' og fo='$author'";
    $search = str_replace('?', '', $search);
    echo "search:$search\n";
    $records = $getM->getMarc($search, $noDublets, $hsb);
    $fundet = false;
    echo "count(records):" . count($records) . " $search, $seqno\n";
    if ($records) {
        foreach ($records as $record) {
            if ($record['BIBLIOTEK'] != '870970') {
                continue;
            }
            if ($record['LOKALID'] == $row['faust']) {
                $fundet = true;
                continue;
            }
            $marcCand->fromIso($record['DATA']);
            $strng = $marcCand->toLineFormat();
            $sta = $marcCand->findSubFields('004', 'r');
            if ($sta[0] == 'd') {
                $cntdel++;
                continue;
            }
            // er det en bind post?
            $multvol = $marcCand->findSubFields('004', 'a');
            if ($multvol) {
                if ($multvol[0] == 'b') {
                    continue;
                }
            }
            $lek = $marcCand->HasLektoer();
            if (!$lek) {
                continue;
            }
            // er det en autoLU?
            $autolu = $marcCand->findSubFields('f07', 'a');
            if ($autolu) {
                continue;
            }
            $tiOK = titlecompare($marc, $marcCand);
            if (!$tiOK) {
                echo "TITLERNE PASSER IKKE\n";
                $strng = $marc->toLineFormat(78);
                echo "marc:\n$strng";
                $strng = $marcCand->toLineFormat(78);
                echo "marcCand:\n$strng";
//                die("titlen passer ikke\n");
                continue;
            }
            $auOK = authorcompare($marc, $marcCand);
            if (!$auOK) {
                echo "FORFATTERNE PASSER IKKE\n";
                $strng = $marc->toLineFormat(78);
                echo "marc:\n$strng";
                $strng = $marcCand->toLineFormat(78);
                echo "marcCand:\n$strng";
//                die("titlen passer ikke\n");
                continue;
            }
            $pubOK = publishercompare($db, $marc, $marcCand);
            if (!$pubOK) {
                echo "FORLAG PASSER IKKE\n";
                $strng = $marc->toLineFormat(78);
                echo "marc:\n$strng";
                $strng = $marcCand->toLineFormat(78);
                echo "marcCand:\n$strng";
//                die("titlen passer ikke\n");
//                continue;
                // skal sættes til notSave
            }

            $af250s = $marc->findSubFields('250', 'a');
            if (!$af250s) {
                $af250s[0] = '';
            }
            $af250 = normalize_250($af250s[0]);

            $f250s = $marcCand->findSubFields('250', 'a');
            if (!$f250s) {
                $f250s[0] = '';
            }
            $f250 = normalize_250($f250s[0]);

            if ($af250 == $f250) {
                if ($pubOK) {
                    $cntSave++;
                    $printedfaust = $Dmatfaust = $Dmatnewfaust = $Dmatprintedfaust = '';
                    $printedfaust = $record['LOKALID'];
                    $Dmatfaust = $dmat['faust'];
                    $Dmatnewfaust = $dmat['newfaust'];
                    $Dmatprintedfaust = $dmat['printedfaust'];
                    $dmatstatus = $dmat['status'];
                    echo "PAR: Dmatstatus:$dmatstatus, Dmatfaust:$Dmatfaust, Dmatnewfaust:$Dmatnewfaust, ";
                    echo "Dmatprintedfaust:$Dmatprintedfaust - DRfaust:$faust,  Fundetfaust:$printedfaust\n";
//                    if ($dmat['status'] != 'Done') {
//                        $upd = "update mediaservice
//                          set newfaust = '$faust', printedfaust = '$printedfaust'
//                          where seqno in
//                              (select seqno  from
//                                  mediaebooks where isbn13 = '$isbn13')
//                        ";
//                    } else {
//                        $upd = "update mediaservice
//                          set  printedfaust = '$printedfaust'
//                          where seqno in
//                              (select seqno  from
//                                  mediaebooks where isbn13 = '$isbn13')
//                        ";
//                    }
//
//                    $db->exe($upd);
//                    echo $upd;
                    $sta = 'Save';
                    echo "LU Save Ebog:" . $faust . " Kandidat:" . $record['LOKALID'] . " (" . $af250s[0] .
                        "/" . $f250s[0] . ") - ($af250/$f250)\n";
                } else {
                    $cntNotSave++;
                    $sta = 'notSave';
                    echo "LU notSave Ebog:" . $faust . " Kandidat:" . $record['LOKALID'] . " (" . $af250s[0] . "/" . $f250s[0] . ") -  ($af250/$f250)\n";
                }
            } else {
                $anum = is_numeric($af250);
                $num = is_numeric($f250);
                if ($anum && $num) {
                    $sta = 'diff';
                    echo "LU diff Ebog:" . $faust . " Kandidat:" . $record['LOKALID'] . " (" . $af250s[0] . "/" . $f250s[0] . ") -  ($af250/$f250)\n";
                    $cntDiff++;
                } else {
                    $cntNotSave++;
                    $sta = 'notSave';
                    echo "LU notSave Ebog:" . $faust . " Kandidat:" . $record['LOKALID'] . " (" . $af250s[0] . "/" . $f250s[0] . ") -  ($af250/$f250)\n";
                }
            }
            $lokid = $record['LOKALID'];
            $dmatsta = $dmat['status'];
            $fulltxt = $af250s[0] . "/" . $f250s[0];
            $fulltxt = utf8_encode($fulltxt);
            $comparetxt = utf8_encode("$af250/$f250");
            $subtitle = mb_substr(utf8_encode($title), 0, 35, 'UTF-8');
            $subtitle = str_replace("'", '', $subtitle);
            $insert = "insert into $retro values('$sta',$dmatseqno,'$dmatsta','$subtitle','$faust','$lokid','$fulltxt', '$comparetxt')";
            $db->exe($insert);
        }

        if (!$fundet) {
            echo "Fandt ikke sig selv: search:$search\n";
            echo "$astrng\n";
//            exit;
        }
    }
}

function normalize_250($f250) {
    if (!$f250) {
        return 1;
    }
    $arr = explode(',', $f250);
    $arr = explode('(', $arr[0]);
    $f = $arr[0];

    $f = strtolower($f);
    $f = str_replace(array(' ', '-', '.'), '', $f);
    $f = str_replace(array('ebogs', 'elektroniske', 'pdf', 'epub', 'ebog'), '', $f);
    $f = str_replace(array('udgave', 'e'), '', $f);

    if ($f == '') {
        $f = '1';
    }
    return $f;
}

function titlecompare($marc, $marcCand) {
    $res = $marcCand->findSubFields('245', 'a');
    $res2 = $marcCand->findSubFields('512', 't');
    $cands = array_merge($res, $res2);
    $res = $marc->findSubFields('245', 'a');
    $res2 = $marc->findSubFields('512', 't');
    $mrcs = array_merge($res, $res2);
    if (count($cands) > 1 or count($mrcs) > 1) {
        echo "FLERE TITLER:\nmarc:\n" . $marc->toLineFormat(78) . "\n";
        echo "marcCand:\n" . $marcCand->toLineFormat(78) . "\n";
    }
    $found = false;
    foreach ($cands as $cand) {
        foreach ($mrcs as $mrc) {
            if ($cand == $mrc) {
                $found = true;
                break;
            }
        }
    }

    return $found;
}

function publishercompare(pg_database $db, marc $marc, marc $marcCand) {
    $pubs = $marc->findSubFields('260', 'b');
    $pubsCand = $marcCand->findSubFields('260', 'b');
    $found = false;
    foreach ($pubs as $pub) {
        foreach ($pubsCand as $pubCand) {
            if ($pub == $pubCand) {
                $found = true;
            }
        }
    }
    if (!$found) {
        $isbns = $marc->findSubFields('021', 'ae');
        $publishers = getPubsIds($db, $isbns[0]);
        $isbnsCand = $marcCand->findSubFields('021', 'ae');
        $publishersCand = getPubsIds($db, $isbnsCand[0]);
        if ($publishers[0]['idnr'] == $publishersCand[0]['idnr']) {
            echo "FORSKELLIG NAVN ENS ISBN: $pub <-> $pubCand\n";
            $found = true;
        }
    }
    return $found;
}

function getPubsIds(pg_database $db, $nr) {
    $nr = str_replace(array(' ', '-'), '', $nr);
    $nr = substr($nr, -8);
    $publishers = array();
    while (strlen($nr) > 1) {
        $nr = substr($nr, 0, strlen($nr) - 1);
        $sql = "select * from forlag where forlagisbn = $nr";
        $hits = $db->fetch($sql);
        if ($hits) {
            $publishers[] = $hits[0];
            break;
        }
    }
    return $publishers;
}

function authorcompare($marc, $marcCand) {
    $marcNames = getNames($marc);
    $marcCandNames = getNames($marcCand);
    $found = false;
    foreach ($marcNames as $marcName) {
        foreach ($marcCandNames as $marcCandName) {
            if ($marcName == $marcCandName) {
                $found = true;
                break;
            }
        }
    }
    return $found;
}

function getNames($m) {
    $names = array();
    $aus = $m->findFields('100');
    foreach ($aus as $au) {
        $first = $last = '';
        foreach ($au['subfield'] as $sub) {
            if (substr($sub, 0, 1) == 'a') {
                $last = substr($sub, 1);
            }
            if (substr($sub, 0, 1) == 'h') {
                $first = substr($sub, 1);
            }
        }
        $names[] = $first . " " . $last;
    }
    $aus = $m->findFields('700');
    foreach ($aus as $au) {
        $first = $last = '';
        foreach ($au['subfield'] as $sub) {
            if (substr($sub, 0, 1) == 'a') {
                $last = substr($sub, 1);
            }
            if (substr($sub, 0, 1) == 'h') {
                $first = substr($sub, 1);
            }
        }
        $names[] = $first . " " . $last;
    }
    $aus = $m->findSubFields('720', 'o');
    foreach ($aus as $au) {
        $names[] = $au;
    }
    return $names;
}