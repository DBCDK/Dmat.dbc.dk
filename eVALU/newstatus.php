<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";

session_start();
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "session_class.php";
//$initials = getInitials();
//$a = $_REQUEST;

if ($_REQUEST['newstat']) {
//    require_once "$inclnk/OPAC_class_lib/updateMediaDB_class.php";
    require_once "$classes/mediadb_class.php";
    require_once "$inclnk/OPAC_class_lib/matchLibV3_class.php";

    $seqno = $_SESSION['seqno'];
    $newstatus = $_SESSION['newstatus'];

    $inifile = '../DigitalResources.ini';
    $config = new inifile($inifile);
    if ($config->error)
        die($config->error);
    $connect_string = $config->get_value("connect", "setup");
    $db = new pg_database($connect_string);
    $db->open();

    $blogin = $config->get_value('ociuser', 'seBasis');
    $bpasswd = $config->get_value('ocipasswd', 'seBasis');
    $bhost = $config->get_value('ocidatabase', 'seBasis');

//    $tablename = $config->get_value("tablename", "pubhubMediaService");
//    $reftable = $config->get_value("reftable", "pubhubMediaService");
//    $notetable = $config->get_value('notetable', 'pubhubMediaService');
//    $dbm = new updateMediaDB($db, $reftable, $tablename, 'digitalresources', $notetable);
    $mediadb = new mediadb($db, basename(__FILE__));
    $matchLibV3 = new matchLibV3($blogin, $bpasswd, $bhost);

    $mediadb->newStatus($seqno, $newstatus, $clear = true);
    $xml = $mediadb->getXml($seqno);
//    $mMarc = $matchLibV3->match($xml[0]['xml']);
//    $mediadb->updateRefData($seqno, $mMarc);
    header("Location: eVa.php?cmd=show&seqno=$seqno");
}

$page = new view('html/newstatus.phtml');
$page->set('newstatus', $_REQUEST['newstatus']);
//$connect_string = $config->get_value("connect", "setup");
//$db = new pg_database($connect_string);
//$db->open();
?>
