<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "session_class.php";

$a = getcwd();

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


$page = new view('html/login.phtml');
$setup = 'setup';
if ($_REQUEST['setup']) {
    $setup = $_REQUEST['setup'];
}
$connect_string = $config->get_value("connect", $setup);
$db = new pg_database($connect_string);
$db->open();
$login = new session($db);
$login->login($_REQUEST);
if ($_SESSION['username']) {
    $initials = $_SESSION['username'];
}
$page->set('initials', $initials);
$page->set('pw', $_REQUEST['passwd']);
$page->set('loginfailure', $_REQUEST['loginfailure']);
?>
