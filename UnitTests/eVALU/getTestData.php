<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";
$mockupclasses = $startdir . "/../UnitTests/classes";

require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$mockupclasses/mockUpLibV3_class.php";
//require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$classes/mediadb_class.php";
//require_once "$inclnk/OPAC_class_lib/matchLibV3_class.php";
//require_once 'MakeDisplayFiles.php';
//require_once 'showseqno_class.php';
//require_once "session.php";

function getHeadLn($seqno) {
    switch ($seqno) {
    case 1:
        $hl = "Posten er ikke med i testen pt.";
        break;
    case 2:
        $hl = "Posten har en udgivelsesdato der ligger længere fremme end en måned (sat til 20.5.3010). ";
        break;
    case 3:
        $hl = "Posten er fundet som e-bog i Basis. ";
        break;
    case 4:
        $hl = "Posten har fundet en katalogisering på trykt materiale i Basis, "
            . "en ny post dannes og sendes til Basis";
        $nxt = 7;
        break;
    case 5:
    case 6:
        $hl = "Posten er sat til NotActive";
        break;
    case 7:
        $hl = "Ingen automatisk match. Til eVa, 2 kandidater, ingen valgt, "
            . "til eLu og sendes til Basis ";
        $nxt = 8;
        break;
    case 8:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne valgt som forlæg, "
            . "til eLu med BKMV, sendes til Basis";
        $nxt = 9;
        break;
    case 9:
        $hl = "Ingen automatisk match. Til eVa, ingen kandidater, valgt DBF, "
            . "sendes til Basis";
        $nxt = 13;
        break;
    case 10:
        $hl = "Har ikke fundet nogen i match. Til eVa, ingen kandidater DROP";
        break;
    case 11:
        $hl = "Ingen automatisk match. Til eVa, ingen kandidater AFVENTER";
        break;
    case 12:
        $hl = "Ingen automatisk match. Til eVa, ingen kandidater, PENDING da publicerings "
            . "dato er mere end en måned frem i tiden.";
        break;
    case 13:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne vælges. "
            . "Det er en e-bog. Kun opdatering af Publizon ";
        $nxt = 14;
        break;
    case 14:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne vælges. "
            . "Det er en e-bog. Bruges som forlæg. Sendes til Basis ";
        $nxt = 15;
        break;
    case 15:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne vælges. "
            . "Det er en e-bog. Bruges som forlæg. Sendes til eLu. "
            . "Her vælges et ISBN som kommende trykt bog. "
            . "Sender 2 poster til Basis ";

        $nxt = 16;
        break;
    case 16:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne vælges. "
            . "Det er en e-bog. Bruges som forlæg. Sendes til eLu. "
            . "Her vælges et ISBN som kommende trykt bog. "
            . "Sender 2 poster til Basis.";
        $nxt = 17;
        break;
    case 17:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne vælges. "
            . "Det er en e-bog. Bruges som forlæg. Sendes til eLu. "
            . "eLu kan se at der er en post med L.U. "
            . "Denne vælges. Sendes til Basis";
        $nxt = 18;
        break;
    case 18:
        $hl = "Ingen automatisk match. Til eVa, ingen kandidater. Til eLu. "
            . "Der er forvalgt et ISBN taget fra e-bogen "
            . "(forlaget har lagt det ind). "
            . "Det vælges. Sendes til Basis";
        $nxt = 19;
        break;
    case 19:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne vælges. "
            . "Det er en trykt bog. Bruges som forlæg. Sendes til eLu. "
            . "eLu vælger den til Lektør. Sendes til Basis ";
        $nxt = 21;
        break;
    case 21:
        $hl = "Ingen automatisk match. Til eVa, 1 kandidat, denne vælges. "
            . "Det er en trykt bog. Bruges som forlæg. Sendes til eLu. "
            . "eLu kan se at der er en post med L.U. "
            . "Denne vælges. Sendes til Basis";
        $nxt = 22;
        break;
    case 22:
        $hl = "Match i Basis. Ny registrering. Tildeles 'L' i eLu. "
            . "";
        $nxt = 4;
        break;
    case 23:
        $hl = "Ingen match.  Tildeles 'L' i eLu. Isbn på trykt bog, som kommer senere, bliver skrevet i feltet.  "
            . "f07 bliver indsat i trykt bogs kat.";
        $nxt = 24;
        break;
    case 24:
        $hl = "Match på ældre bog. Får DBR istedet for DBF. Den bliver i Dmat afmærket med V ";
        $nxt = 25;
        break;
    case 25:
        $hl = "Ingen match. Får DBR da det er en gammel sag. Bliver afmærket i Dmat med V og L";
        $nxt = 26;
        break;
    case 26:
        $hl = "Match. Får DBR men ingen BKM (ingen V,B,S eller BKMV)";
        $nxt = 27;
        break;
    case 27:
        $hl = "Ingen match. Får DBR men ingen BKM (ingen V,B,S eller BKMV) ";
        $nxt = 2;
        break;
    default:
        $hl = "Empty";
    }
    return array($hl, $nxt);;
}

function splt($strng) {
    $lnlength = 70;
    $lns = explode("\n", $strng);
    $strng = "";
    foreach ($lns as $ln) {
        while (strlen($ln) > $lnlength) {
            $strng .= substr($ln, 0, $lnlength) . "\n";
            $ln = '    ' . substr($ln, $lnlength);
        }
        $strng .= $ln . "\n";
    }
    return $strng;
}


$inifile = '../DigitalResources.ini';
//$libV3API = new LibV3API();
$libV3API = new mockUpLibV3API($basedir,'data');

$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}

$page = new view('html/getTestData.phtml');

$setup = 'testcase';
$connect_string = $config->get_value("connect", $setup);

$marcBasis = new marc();
$marcFromXml = new marc();


$seqno = $_REQUEST['seqno'];

$db = new pg_database($connect_string);
$db->open();
$mediadb = new mediadb($db, basename(__FILE__), $nothing);

$page->set('seqno', $seqno);
$headln = getHeadLn($seqno);
$page->set('headln', $headln[0]);

//$page->set('nxtseqno', $headln[1]);
$nxtseqno = $seqno + 1;
if ($nxtseqno > 27 or $nxtseqno < 0) {
    $nxtseqno = 1;
}
$bckseqno = $seqno - 1;
if ($bckseqno < 1) {
    $bckseqno = 27;
}
$page->set('nxtseqno', $nxtseqno);
$page->set('bckseqno', $bckseqno);

$rows = $mediadb->getInfoData($seqno);
$row = $rows[0];
$info = array();
$info['faust i basis'] = $row['faust'];
$info['nyt faust'] = $row['newfaust'];
$info['nyt faust trykt'] = $row['newfaustprinted'];
$info['faust til Lektør(trykt)'] = $row['printedfaust'];
$info['isbn til trykt'] = $row['expisbn'];
$info['skal til promat'] = 'Nej';
if ($row['promat']) {
    $info['skal til promat'] = 'Ja';
}
$info['er i basis'] = 'Nej';
if ($row['is_in_basis']) {
    $info['er i basis'] = 'Ja';
}
$info['valg i eVa og eLu'] = $row['choice'];

$page->set('info', $info);

$topub = $mediadb->getToPublizon($seqno);
$page->set('topub', $topub);

$frompub = $mediadb->getFromMarcTable($row['newfaust'], 'publizon');
$page->set('frompub', splt($frompub));

$frombasis = $libV3API->getMarcByLB($row['faust'], '870970');
$marcBasis->fromIso($frombasis[0]['DATA']);
$frombasis = utf8_encode($marcBasis->toLineFormat(60));
$page->set('frombasis', splt($frombasis));
//$page->set('frombasis', $frombasis);

$tobasis = $mediadb->getFromMarcTable($row['newfaust'], '870970');
$page->set('tobasis', splt($tobasis));

$printed = $mediadb->getFromMarcTable($row['newfaustprinted'], '870970');
//if ($printed) {
$page->set('printed', splt($printed));
//}
$ToLek = $mediadb->getFromMarcTable($row['lekfaust'], '870976');
$page->set('tolek', splt($ToLek));

$newBasis = $mediadb->getFromMarcTable($row['faust'], '870970');
$page->set('newBasis', splt($newBasis));

