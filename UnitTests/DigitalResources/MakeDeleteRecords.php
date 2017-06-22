<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/ftp_transfer_class.php";

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
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = FALSE;
$add2table = false;


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('x' => true, 'n' => true);
//    $options = array('f' => 'pubhubMediaService.xml');
}
else {
    $options = getopt("hp:n");
};

if (array_key_exists('h', $options)) {
    usage();
}

if (array_key_exists('n', $options)) {
    $nothing = true;
}

if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}


// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}
$connect_string = $config->get_value("connect", $setup);
$db = new pg_database($connect_string);
$db->open();

$mediadb = new mediadb($db, basename(__FILE__), $nothing);

$ftpBasis = new ftp_transfer_class($config, 'FromPhus', 'MediaToBasis', $nothing);
$ftpPhus = new ftp_transfer_class($config, 'DelPhus', 'MediaToBasis', $nothing);

$total = 0;
$netlydbog = 0;
$ereol = 0;
$ebib = 0;
$del = 0;
$cnt = 0;


$marc = new marc();
$marc->openMarcFile('dump.iso');
//$fp = fopen(STDOUT,"a");
while ($marc->readNextMarc()) {
    $total++;
//        echo "total:$total\n";
    $arr = $marc->findFields('000');
    $status = substr($arr[0]['subfield'][0], 5, 1);
    if ($status == 'd') {
        $del++;
        continue;
    }

    $ereolrec = false;
    $f032s = $marc->findSubFields('032', 'x');
    if (count($f032s)) {
        foreach ($f032s as $weekcode) {
            $type = substr($weekcode, 0, 3);
            if ($type == 'ERE' or $type == 'ERL') {
                $ereolrec = true;
            }
        }
    }
    if ($ereolrec) {
        $f021s = $marc->findSubFields('021', 'e');

        $isbn13 = $f021s[0];
        if (!$isbn13) {
            $strng = $marc->toLineFormat();
            echo $strng;
        }
        $arr = $mediadb->fetchByIsbn($isbn13);
        $seqno = $arr[0]['seqno'];
        if (!$seqno) {
            $strng = $marc->toLineFormat();
            echo $strng;

        }
        else {
            $info = $mediadb->getInfoData($seqno);
            $status = $info[0]['status'];
            $del = false;
            switch ($status) {
                case 'DigitalR':
                    // ingen handling alt er OK
                    break;
                case 'Drop':
                    // ingen handling alt er OK
                    break;
                case 'Afventer':
                    // ved ikke hvad jeg skal
                    echo "Afventer: seqno $seqno\n";
                    break;
                case 'OldEva':
                case 'OldTemplate':
                    $del = true;
                    break;
                case 'Done':
                    // ved ikke hvad jeg skal
                    echo "Done: seqno $seqno\n";
                    break;
                case 'eVa':
                    $del = true;
                    break;
                default:
                    echo "Ukendt status: $status, seqno=$seqno\n";
                    exit;
            }
            if ($del) {
                $cnt++;
                echo "DEL($cnt):$seqno\n";
//                $strng = $marc->toLineFormat();
                $ftpBasis->write($marc->toIso());
                $marc->substitute('004', 'r', 'd');
                $ftpPhus->write($marc->toIso());

            }
        }
    }
}
$ftpBasis->put();
$ftpPhus->put();

echo "total:$total\n";
?>