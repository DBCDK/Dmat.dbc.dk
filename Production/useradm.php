<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "session_class.php";

//$initials = login($_REQUEST);
//if ($_SESSION['username']) {
//    $initials = $_SESSION['username'];
//}
session_start();
//print_r($_SESSION);
//echo "role:" . $_SESSION['role'];
if ($_SESSION['role'] != 'admin') {
    header("Location:ProductionPlan.php");
}

$inifile = '../DigitalResources.ini';

//phpinfo();
$config = new inifile($inifile);
if ($config->error)
    die($config->error);

$connect_string = $config->get_value("connect", "setup");

//print_r($_REQUEST);

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
//if ($_SESSION['role'] == 'admin') {
//    $page->set('role', $_SESSION['role']);
//}
$cmd = $_REQUEST['cmd'];

//print_r($_REQUEST);

$page->set('cmd', $cmd);
try {
    verbose::log(DEBUG, "connect string:$connect_string");
    $db = new pg_database($connect_string);
    $db->open();
    $login = new session($db, 'pro_plan');
    $username = $login->getUserName();
    $username = $_SESSION[$username];
//    $m = "mediaserviceuserinfo";
    $page->set('username', $username);
    $role = $_SESSION['role'];
    $page->set('role', $role);
//    echo "username:$username, role:$role";
    switch ($cmd) {
    case 'Slet':
        $user = $_REQUEST['user'];
        $login->delete($user);
        $page->set('user', $user);
        $page->set('cmd', 'delok');
        break;
    case 'Nulstil passwd':
        $user = $_REQUEST['user'];
        $role = $_REQUEST['role'];
        $login->updatePasswd($user, $user, $role);
        $page->set('cmd', 'resetok');
        $page->set('user', $user);
//            $page->set('role', $role);
        $page->set("sel" . $role, 'selected');
        break;
    case 'Opdater':
        $user = $_REQUEST['user'];
        $role = $_REQUEST['role'];
        $login->update($user, $role, $role);
        $page->set('cmd', 'updateok');
        $page->set('user', $user);
        $page->set('b_role', $role);
        $page->set("sel" . $role, 'selected');
        break;
    case 'Opret':
        $user = $_REQUEST['user'];
        $role = $_REQUEST['role'];
//            $page->set('user', $user);
        $res = $login->insert($user, $role, $role);
        if ($res == 'knownuser') {
            $page->set('cmd', 'knownuser');
        } else {
            $page->set('cmd', 'createok');
        }
        $page->set('user', $user);
        $page->set('b_role', $role);
        $page->set("sel" . $role, 'selected');
        break;
    case 'list':
        $rows = $login->listusers();
        $page->set('rows', $rows);
//            $page->set('role', $role);
        break;
    case 'update':
        $user = $_REQUEST['user'];
        $row = $login->getInfo($user);
        $page->set('createdate', $row['createdate']);
        $page->set('update', $row['update']);
        $page->set('userrole', $row['role']);
//            $page->set('role', $role);
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