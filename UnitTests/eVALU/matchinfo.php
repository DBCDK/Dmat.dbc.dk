<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OPAC_class_lib/matchLibV3_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$classes/mediadb_class.php";
//require_once "session_class.php";

//$a = getcwd();

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

if (($blogin = $config->get_value('ociuser', 'seBasis')) == false) {
    die("no seBasis/ociuser stated in the configuration file");
}
if (($bpasswd = $config->get_value('ocipasswd', 'seBasis')) == false) {
    die("no seBasis/passwd stated in the configuration file");
}
if (($bhost = $config->get_value('ocidatabase', 'seBasis')) == false) {
    die("no seBasis/ocihost stated in the configuration file");
}

$page = new view('html/matchinfo.phtml');
$setup = 'setup';
if ($_REQUEST['setup']) {
    $setup = $_REQUEST['setup'];
}

$seqno = $_REQUEST['seqno'];
if (!$seqno) {
    exit();
}

$matchLibV3 = new matchLibV3($blogin, $bpasswd, $bhost);
$connect_string = $config->get_value("connect", $setup);
$db = new pg_database($connect_string);
$db->open();
$mediadb = new mediadb($db, 'matchinfo.php', false);

$info = $mediadb->getInfoData($seqno);
$xml = $info[0]['originalxml'];
$matchLibV3->setVerbose();
$mMarc = $matchLibV3->match($xml);
$mediadb->updateRefData($seqno, $mMarc);
$debugstrng = $matchLibV3->getVerbose();
$page->set('debugstrng', $debugstrng);
$page->set('seqno', $seqno);


?>
