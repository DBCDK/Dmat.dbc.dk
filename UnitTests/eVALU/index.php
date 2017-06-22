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
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$classes/mediadb_class.php";
require_once "session_class.php";
require_once "session.php";


if (array_key_exists('ini', $_REQUEST)) {
    $cmd = $_REQUEST['cmd'];
    if ($cmd != 'login') {
        $initials = login($_REQUEST);
    }
}
//echo "REQUEST:" . $_SERVER['REQUEST_URI'];
$initials = getInitials();
if ($_SESSION['username']) {
//    print_r($_SESSION);
    $initials = $_SESSION['username'];
}

$inifile = '../DigitalResources.ini';


$config = new inifile($inifile);
if ($config->error)
    die($config->error);

$setup = 'setup';
if (array_key_exists('setup', $_SESSION)) {
    $setup = $_SESSION['setup'];
}
if ($_REQUEST['test'] == 'true') {
    $_SESSION['setup'] = 'testcase';
}
if ($_REQUEST['test'] == 'false') {
    $_SESSION['setup'] = 'setup';
}
if (array_key_exists('setup', $_SESSION)) {
    $setup = $_SESSION['setup'];
}
$connect_string = $config->get_value("connect", $setup);

$tablename = $config->get_value("tablename", "pubhubMediaService");
$reftable = $config->get_value("reftable", "pubhubMediaService");
$notetable = $config->get_value('notetable', 'pubhubMediaService');

$page = new view('html/welcome_1.phtml');

$page->set('setup', $setup);

//$page->set('calllogin', $calllogin);
$page->set('initials', $initials);
if ($_SESSION['role'] == 'admin') {
    $page->set('role', $_SESSION['role']);
}
if (!$initials && $_REQUEST['cmd'] == 'login') {
    $page->set('alert', true);
}
try {
    $db = new pg_database($connect_string);
    $db->open();

    $page->set('loginfailure', $_SESSION['loginfailure']);
    $mediadb = new mediadb($db, __FILE__, $nothing = false);
//    $mediadb = new mediadb($db, $reftable, $tablename, 'digitalresources', $notetable);
    $sum_prime = $mediadb->getSummary($prime = true);
    $sum_not_prime = $mediadb->getSummary($prime = false);
    $sql = "select isbn13, format from digitalresources
             where provider = 'Pubhub'
             and status = 'n'
             and format not in ('Netlydbog','Deff','NetlydbogLicens')
             and faust is  null
           ";
//    $sql = "select isbn13, status from mediaservice s, mediaebooks e
//             where
//             s.status in ( 'eVa', 'eLu')
//             and s.seqno = e.seqno ";
    if ($setup != 'testcase') {
        $isbns = $db->fetch($sql);
    } else {
        $isbns = false;
    }
//    print_r($isbns);
    $ekspress = array();
    if ($isbns) {
        foreach ($isbns as $isbn) {
            $arr = $mediadb->fetchByIsbn($isbn['isbn13']);
            if ($arr) {
                $arr2 = $mediadb->getInfoData($arr[0]['seqno']);
                if ($arr2[0]['status'] == 'OldEva' or $arr2[0]['status'] == 'DigitalR') {
                    $mediadb->newStatus($arr[0]['seqno'], 'eVa', $clear = true);
//                $up = "update mediaservice set status = 'eVa' where seqno = " . $arr[0]['seqno'];
//                $db->exe($up);
                    $arr2[0]['status'] = 'eVa';
                }
                $ekspress[] = array('seqno' => $arr[0]['seqno'], 'status' => $arr2[0]['status'], 'title' => $arr[0]['title']);
            } else {
                $ekspress[] = array('seqno' => $isbn['isbn13'], 'status' => 'Ikke i dmat', 'title' => 'Kontakt HHL');
            }
        }
    }
    $page->set('ekspress', $ekspress);
    $page->set('sum_prime', $sum_prime);
    $page->set('sum_not_prime', $sum_not_prime);
//    $page->set('rootdir', $_SERVER['DOCUMENT_ROOT']);
} catch (Exception $err) {
    echo "The exception code is: " . $err->getCode() . "\n";
    echo $err . "\n";
    $err_txt = $err->getMessage();
    $getLine = "Line number:" . $err->getLine();
    $getTrace = $err->getTraceAsString();
//    mail($err_mail_to, "Exception utc-process.php", "----------------------\n$err_txt\n---------------------\n
//      $getLine\n$getTrace\n");
    exit;
}


