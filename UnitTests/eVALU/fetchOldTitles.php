<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes/";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$inclnk/OPAC_class_lib/matchLibV3_class.php";
require_once "$classes/mediadb_class.php";
require_once "session_class.php";

$count = 10; // Default number of new titles to fetch

if ($_REQUEST['count']) {
    $count = $_REQUEST['count'];
}

$setup = 'setup';
if ($_REQUEST['setup']) {
    $setup = $_REQUEST['setup'];
}

$inifile = '../DigitalResources.ini';
$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}
if (($connect_string = $config->get_value("connect", $setup)) == false) {
    die("no $setup/connect stated in the configuration file");
}
if (($login = $config->get_value('ociuser', 'seBasis')) == false) {
    die("no seBasis/ociuser stated in the configuration file");
}
if (($passwd = $config->get_value('ocipasswd', 'seBasis')) == false) {
    die("no seBasis/passwd stated in the configuration file");
}
if (($host = $config->get_value('ocidatabase', 'seBasis')) == false) {
    die("no seBasis/ocihost stated in the configuration file");
}

$db = new pg_database($connect_string);
$db->open();

$mediadb = new mediadb($db, basename(__FILE__), $nothing);
$matchLibV3 = new matchLibV3($login, $passwd, $host);

$seqnos = $mediadb->getNextOlds($count);

foreach($seqnos as $seqno) {
    $infoData = $mediadb->getInfoData($seqno);
    $xml = $infoData[0]['originalxml'];
    $mMarc = $matchLibV3->match($xml);
    $mediadb->updateRefData($seqno, $mMarc);
    $mediadb->updateStatusOnly($seqno, 'eVa');
}

?>
