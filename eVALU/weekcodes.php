<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$inclnk/OLS_class_lib/weekcode_class.php";
require_once "session.php";

$initials = login($_REQUEST);

$inifile = '../DigitalResources.ini';

$config = new inifile($inifile);
if ($config->error)
    die($config->error);

$connect_string = $config->get_value("connect", "setup");

//$tablename = $config->get_value("tablename", "pubhubMediaService");
//$reftable = $config->get_value("reftable", "pubhubMediaService");


$page = new view('html/weekcodes.phtml');
$page->set('initials', $initials);


try {
    $db = new pg_database($connect_string);
    $db->open();

    $wc = new weekcode($db);

    if (array_key_exists('fortryd', $_REQUEST)) {
        $_REQUEST = array();
    }

    $updatewcodes = true;
    $par = $wc->getparameters();
    if (array_key_exists('days', $_REQUEST)) {
        if ($par['days'] != $_REQUEST['days'] or
            $par['numbers'] != $_REQUEST['numbers']
        ) {
            $wc->updateparameters($_REQUEST['days'], $_REQUEST['numbers']);
            $par = $wc->getparameters();
            $updatewcodes = false;
        }
    }
    $page->set('days', $par['days']);
    $page->set('numbers', $par['numbers']);
    $wcodes = $dcodes = array();
    foreach ($_REQUEST as $p => $val) {
        if (substr($p, 0, 1) == 'y') {
            $wcodes[substr($p, 1)] = $val;
        }
        if (substr($p, 0, 1) == 'x') {
            $dcodes[substr($p, 1)] = $val;
        }
    }
    if ($updatewcodes) {
        if (count($wcodes)) {
            if (!$wc->updateWeekCodes($wcodes, 'week')) {
                $page->set('werror', $wc->getErrMsg());
            }
        }
        if (count($dcodes)) {
            if (!$wc->updateWeekCodes($dcodes, 'dat')) {
                $page->set('werror', $wc->getErrMsg());
            }
        }
    }
    if (array_key_exists('wcode', $_REQUEST)) {
        if (strlen($_REQUEST['wcode']) == 6) {
            $page->set('wcode', $_REQUEST['wcode']);
            $disp = array();
            $acodes = $wc->getAllWeek($_REQUEST['wcode']);
            foreach ($acodes as $type => $rows) {
                $page->set($type, $rows);
            }
            foreach ($acodes['dat'] as $key => $val) {
                $disp[$key] = substr($key, 6, 2) . "/" . substr($key, 4, 2) . "/" . substr($key, 2, 2);
            }
            $page->set('disp', $disp);
        }
    }
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


