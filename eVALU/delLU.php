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

$inifile = '../DigitalResources.ini';
$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}


$setup = 'setup';
if ($_REQUEST['setup']) {
    $setup = $_REQUEST['setup'];
}
$connect_string = $config->get_value("connect", $setup);
$db = new pg_database($connect_string);
$db->open();
$mediadb = new mediadb($db, __FILE__, false);

$seqno = $_REQUEST['seqno'];
$info = $mediadb->getInfoData($seqno);
if ($_REQUEST['cmd'] == 'delete') {
    if ($info[0]['updatelekbase'] != 'Done') {
        echo "Kan ikke slette Lektørudtalelsen da status=(" . $info[0]['updatelekbase'] . "\n";
    }
    if (!$info[0]['lekfaust']) {
        echo "Har ikke noget faustnummer på lektørudtalelsen\n";
    }

    $mediadb->updateLekStatus($seqno, 'ToBeDeleted');
    echo "OK";
    return true;
}

$page = new view('html/delLU.phtml');
$page->set('seqno', $seqno);
$page->set('title', $info[0]['title']);
$page->set('updatelekbase', $info[0]['updatelekbase']);
$page->set('lekfaust', $info[0]['lekfaust']);

?>
