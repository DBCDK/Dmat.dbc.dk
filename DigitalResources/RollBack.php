<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";

/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-B ToBasis send posterne igen til basis, kræver et seqno\n";
    echo "\t-s seqno denne post er grundlag for hvilke poster der skal sendes \n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = "../DigitalResources.ini";
$nothing = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('B' => 'ToBasis', 'n' => 'nothing', 's' => 26313);
} else {
    $options = getopt("hp:Bs:n");
}

if (array_key_exists('h', $options))
    usage();

if (array_key_exists('n', $options))
    $nothing = true;

if (array_key_exists('p', $options))
    $inifile = $options['p'];

if (array_key_exists('B', $options))
    $action = 'ToBasis';

if (array_key_exists('s', $options))
    $seqno = $options['s'];

switch ($action) {
    case 'ToBasis':
        if (!$seqno) {
            usage('seqno mangler');
        }
        $getrecoverseqno = "select max(recoverseqno) as recoverseqno
              from mediaservicerecover
                  where seqno in (
                   select seqno from mediaservice
                      where update in (
                        SELECT  update FROM mediaservice
                         where seqno = $seqno
                      )
                 )
                group by seqno";
        break;
    default :
        usage('Ukendt action');
}

$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

try {
    $connect_string = $config->get_value("connect", "setup");

    $db = new pg_database($connect_string);
    $db->open();
    echo "$getrecoverseqno\n";
    $rows = $db->fetch($getrecoverseqno);
    echo "count(\$rows):" . count($rows) . "\n";
    foreach ($rows as $row) {
        $recoverseqno = $row['recoverseqno'];
        $get = "select seqno, status, update, faust, newfaust, base, choice, "
                . "promat, initials "
                . "from mediaservicerecover "
                . "where recoverseqno = $recoverseqno ";
        $all = $db->fetch($get);
        $data = $all[0];
        $seqno = $data['seqno'];
        $status = $data['status'];
        $update = $data['update'];
        $faust = $data['faust'];
        $newfaust = $data['newfaust'];
        $base = $data['base'];
        $choice = $data['choice'];
        $promat = $data['promat'];
        $initials = $data['initials'];
        if ($promat) {
            $promat = "promat = '$promat', ";
        } else {
            $promat = "promat = null, ";
        }
        $update = "update mediaservice set "
                . "status = '$status', "
                . "update = '$update', "
                . "faust = '$faust', "
                . "newfaust = '$newfaust', "
                . "base = '$base', "
                . "choice = '$choice', "
                . "$promat "
                . "initials = '$initials' "
                . "where seqno = $seqno";
        echo $update . "\n";
        if (!$nothing) {
            $db->exe($update);
        }
    }
} catch (Exception $ex) {

}