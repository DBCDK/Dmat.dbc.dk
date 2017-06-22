<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";


//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$classes/mediadb_class.php";
//require_once "$inclnk/OPAC_class_lib/matchLibV3_class.php";
//require_once 'MakeDisplayFiles.php';
//require_once 'showseqno_class.php';
//require_once "session.php";

$inifile = '../DigitalResources.ini';


$config = new inifile($inifile);
if ($config->error)
    die($config->error);
$setup = 'testcase';
//$setup = 'setup';
$connect_string = $config->get_value("connect", $setup);
$db = new pg_database($connect_string);
$db->open();
$mediadb = new mediadb($db, basename(__FILE__));

$rundir = '../UnitTests';
//print_r($_REQUEST);
$page = new view('html/testapps.phtml');

$cmd = $_REQUEST['cmd'];

$run = false;
switch ($cmd) {
case 'run_test_MatchMedier':
    $run = "phpunit $rundir/test_MatchMedier.php";
    break;
case 'match_backup':
    $mediadb->copyTablesOut('_match');
    break;
case 'match_restore':
    $mediadb->copyTablesIn('_match');
    break;
case 'eVa_backup':
    $mediadb->copyTablesOut('_eva');
    break;
case 'eVa_restore':
    $mediadb->copyTablesIn('_eva');
    break;
case 'eLu_backup':
    $mediadb->copyTablesOut('_elu');
    break;
case 'eLu_restore':
    $mediadb->copyTablesIn('_elu');
    break;
case 'insF_backup':
    $mediadb->copyTablesOut('_insF');
    break;
case 'insF_restore':
    $mediadb->copyTablesIn('_insF');
    break;
case 'ToBasis_backup':
    $mediadb->copyTablesOut('_To_Basis');
    break;
case 'ToBasis_restore':
    $mediadb->copyTablesIn('_To_Basis');
    break;
case 'ToPromat_backup':
    $mediadb->copyTablesOut('_ToPromat');
    break;
case 'ToPromat_restore':
    $mediadb->copyTablesIn('_ToPromat');
    break;
default:
    $run = false;
}
sleep(2);
if ($run) {
//    echo $run;
    exec($run, $output, $return);
//    echo "\n" . implode("\n", $output) . "\n";
    $result = "";
    foreach ($output as $ln) {
        if ($ln) {
            $result .= "<br/>" . $ln;
        }
    }
    $result .= "<br/>";
//    $result = implode("<br/>", $output) . "<br/>";
    $page->set('result', $result);
//    $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

}



