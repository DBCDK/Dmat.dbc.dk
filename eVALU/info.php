<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$classes/mediadb_class.php";
require_once "session_class.php";

$a = getcwd();

$seqno = $_REQUEST['seqno'];
if (!$seqno) {
    exit;
}

//$initials = getInitials();
//$a = $_REQUEST;
if ($_REQUEST['passwd']) {
    $pw = true;
}

$inifile = '../DigitalResources.ini';
$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}


$page = new view('html/info.phtml');
$setup = 'setup';
if ($_REQUEST['setup']) {
    $setup = $_REQUEST['setup'];
}
$connect_string = $config->get_value("connect", $setup);
$db = new pg_database($connect_string);
$db->open();
$mediadb = new mediadb($db, 'info.php', false);


$disp = array();
//$seqno = $_REQUEST['seqno'];
$page->set('seqno', $seqno);
$page->set('details', $_REQUEST['details']);

$infos = $mediadb->getInfoData($seqno);
$info = $infos[0];
foreach ($infos[0] as $name => $value) {
//    echo "$name ";
    if ($name == 'createdate' or $name == 'publicationdate') {
        $value = formatdate($value);
    }
    $page->set($name, $value);
}
$i['Navn'] = $info['initials'];
$i['Program'] = $info['program'];
$i['Rettet'] = formatdate($info['update']);
$i['Status'] = $info['status'];
$i['Valg'] = $info['choice'];
$i['Fra faust'] = nbsp($info['faust']);
$i['Tildelt faust'] = nbsp($info['newfaust']);
$i['Nyt trykt faust'] = $info['newfaustprinted'];
$i['Trykt faust'] = $info['printedfaust'];
$i['Lektør faust'] = $info['lekfaust'];
$i['Trykt udg. dato'] = $info['printedpublished'];
//$i['ecreatdedate'] = formatdate($info['ecreatedate']);
//$i['eupdate'] = formatdate($info['eupdate']);
if ($info['is_in_basis']) {
    $i['Er i Basis'] = formatdate($info['is_in_basis']);
}
else {
    $i['Er i Basis'] = '';
}
$i['lekDB upd.'] = $info['updatelekbase'];
$i['Forventet bogs ISBN'] = $info['expisbn'];
$i['BKX'] = $info['bkxwc'];
//$i['Forventet bogs dato'] = '23-12-2016';
$disp[] = $i;

$recInfos = $mediadb->getRecoverInfo($seqno);
foreach ($recInfos as $info) {
    $i = array();
    $i['Navn'] = $info['initials'];
    $i['Program'] = $info['program'];
    $i['Rettet'] = formatdate($info['update']);
    $i['Status'] = $info['status'];
    $i['Valg'] = $info['choice'];
    $i['Fra faust'] = $info['faust'];
    $i['Tildelt faust'] = $info['newfaust'];
    $i['Nyt trykt faust'] = $info['newfaustprinted'];
    $i['Trykt faust'] = $info['printedfaust'];
    $i['Lektør faust'] = $info['lekfaust'];
    $i['Trykt udg. dato'] = $info['printedpublished'];
//$i['ecreatdedate'] = formatdate($info['ecreatedate']);
//$i['eupdate'] = formatdate($info['eupdate']);
    if ($info['is_in_basis']) {
        $i['Er i Basis'] = formatdate($info['is_in_basis']);
    }
    else {
        $i['Er i Basis'] = '';
    }
    $i['lekDB upd.'] = $info['updatelekbase'];
    $i['Forventet bogs ISBN'] = $info['expisbn'];
    $i['BKX'] = $info['bkxwc'];
//    $i['Forventet bogs dato'] = 'xxx';
    $disp[] = $i;

}

$page->set('disp', $disp);
//var_dump($info);

function formatdate($date) {
    $time = strtotime($date);
    if (strlen($date) == 8) {
        $val = date('d-m-Y', $time);
    }
    else {
        $val = date('d-m-Y/H:m', $time);
    }
    return $val;
}

function nbsp($txt) {
    return str_replace(' ', '&nbsp;', $txt);
}

?>
