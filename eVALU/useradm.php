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
//require_once "$inclnk/OPAC_class_lib/updateMediaDB_class.php";
require_once "$classes/mediadb_class.php";
require_once "session.php";

$initials = login($_REQUEST);
if ($_SESSION['username']) {
    $initials = $_SESSION['username'];
}
if ($_SESSION['role'] != 'admin') {
    header("Location:index.php");
}

$inifile = '../DigitalResources.ini';

$config = new inifile($inifile);
if ($config->error)
    die($config->error);

$connect_string = $config->get_value("connect", "setup");

if (($logfile = $config->get_value('weblog', 'setup')) == false)
    die("no weblog stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    die("no verboselevel stated in the configuration file");


verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . " ****");

if (($tablename = $config->get_value('tablename', 'pubhubMediaService')) == false)
    die("no pubhubMediaService/tablename stated in the configuration file");

if (($reftable = $config->get_value('reftable', 'pubhubMediaService')) == false)
    die("no pubhubMediaService/reftable stated in the configuration file");

if (($notetable = $config->get_value('notetable', 'pubhubMediaService')) == false)
    die("no pubhubMediaService/notetable stated in the configuration file");

$page = new view('html/useradm.phtml');
if ($_SESSION['role'] == 'admin') {
    $page->set('role', $_SESSION['role']);
}
$cmd = $_REQUEST['cmd'];

$page->set('cmd', $cmd);
try {
    verbose::log(DEBUG, "connect string:$connect_string");
    $db = new pg_database($connect_string);
    $db->open();
    $mediadb = new mediadb($db, __FILE__, false);

    switch ($cmd) {
    case 'Slet':
        $user = $_REQUEST['user'];
        $mediadb->useradm($cmd, $user);
        $page->set('user', $user);
        $page->set('cmd', 'delok');
        break;
    case 'Nulstil passwd':
        $user = $_REQUEST['user'];
        $role = $_REQUEST['role'];
        $mediadb->useradm($cmd, $user, $role);
        $page->set('cmd', 'resetok');
        $page->set('user', $user);
        $page->set('role', $role);
        $page->set("sel" . $role, 'selected');
        break;
    case 'Opdater':
        $user = $_REQUEST['user'];
        $role = $_REQUEST['role'];
        $mediadb->useradm($cmd, $user, $role);
        $page->set('cmd', 'updateok');
        $page->set('user', $user);
        $page->set('role', $role);
        $page->set("sel" . $role, 'selected');
        break;
    case 'Opret':
        $user = $_REQUEST['user'];
        $role = $_REQUEST['role'];
        $page->set('user', $user);
        $users = $mediadb->useradm('update', $user);
        if ($users) {
            $page->set('cmd', 'knownuser');
        } else {
            $mediadb->useradm($cmd, $user, $role);
        }
        $page->set('cmd', 'createok');
        $page->set('user', $user);
        $page->set('role', $role);
        $page->set("sel" . $role, 'selected');
        break;
    case 'list':
        $rows = $mediadb->useradm($cmd, 'dummy');
        $page->set('rows', $rows);
        break;
    case 'update':
        $user = $_REQUEST['user'];
        $rows = $mediadb->useradm('Hent', $user);
        $row = $rows[0];
        $page->set('createdate', $row['createdate']);
        $page->set('update', $row['update']);
        $page->set('role', $row['role']);
        $page->set('user', $user);
        $page->set('passwd', $row['passwd']);
        $page->set("sel" . $row['role'], 'selected');
        break;
    case 'create':
        break;
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