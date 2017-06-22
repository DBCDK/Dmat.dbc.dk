<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";

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
    echo "\t-t name of the table, if it exist the program will halt\n";
    echo "\t-u user (could be anything, ex. Basis, Phus...\n";
    echo "\t-A add to an existing table\n";
    echo "\t-f input file (default STDIN)\n";
    echo "\t-D delete the table before processing\n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = FALSE;
$deltable = false;
$add2table = false;
$input = STDIN;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('t' => 'xbasis', 'u' => 'basis', 'f' => 'basis.iso', 'D' => 'true');
} else {
    $options = getopt("hp:nt:u:Af:D");
}


if (array_key_exists('h', $options))
    usage();

if (array_key_exists('n', $options))
    $nothing = true;

if (array_key_exists('D', $options))
    $deltable = true;

if (array_key_exists('A', $options))
    $add2table = true;

if (array_key_exists('f', $options))
    $input = $options['f'];

if (array_key_exists('p', $options))
    $inifile = $options['p'];
$tablename = "";
if (array_key_exists('t', $options))
    $tablename = $options['t'];

if (!$tablename) {
    usage("Please state at tablename");
}
$user = "?";
if (array_key_exists('u', $options))
    $user = $options['u'];


// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

$connect_string = $config->get_value("connect", "setup");

try {
    $db = new pg_database($connect_string);
    $db->open();
    //          look for the "$tablename" - if none, make one otherwise abort.
    $sql = "select tablename from pg_tables where tablename = $1";
    $arr = $db->fetch($sql, array(strtolower($tablename)));
    $seqname = $tablename . "seq";

    if (!$add2table) {
        if ($deltable) {
            if ($arr) {
                $del = "drop table $tablename ";
                $db->exe($del);
            }
        } else {
            if ($arr) {
                die("\nThere is already a table with this name: $tablename\n\n");
            }
        }


        $sql = "
       create table $tablename (
         seqno integer,
         dbuser varchar(50),
         type varchar(20),
         createdate timestamp,
         faust varchar(11),
         isbn13 varchar(13),
         status varchar(1),
         costfree varchar(5),
         title varchar(100),
         marc text
       )
    ";
        $db->exe($sql);

        $sql = "
        drop sequence if exists $seqname
    ";
        $db->exe($sql);
        $sql = "
        create sequence $seqname
    ";

        $db->exe($sql);
    }
    $db->exe("SET CLIENT_ENCODING TO 'LATIN1'");

    $total = 0;
    $netlydbog = 0;
    $ereol = 0;
    $ereollicens = 0;
    $ebib = 0;
    $del = 0;
    $marc = new marc();
//    $marc->openMarcFile(STDIN);
//    $marc->openMarcFile('2poster.iso');
    $marc->openMarcFile($input);

    while ($marc->readNextMarc()) {
        $total++;
//        echo "total:$total\n";
        $arr = $marc->findFields('000');
        $status = substr($arr[0]['subfield'][0], 5, 1);
        if ($status == 'd') {
            $del++;
//            continue;
        }

        $ERLcntcodes = $EREcntcodes = $NLYcntcodes = 0;
        $typestrng = '';
        $costfree = 'false';
//        $f21s = $marc->findFields('f21');
//        if ($f21s) {
        $f032s = $marc->findSubFields('032', 'x');
        if (count($f032s)) {
//        print_r($arr);

            foreach ($f032s as $weekcode) {
                $type = substr($weekcode, 0, 3);
//                if ($type == 'EBI') {
//                    $ebib++;
//                    $typestrng = 'ebib';
//                }
                if ($type == 'ERL') {
                    $ereollicens++;
                    $ERLcntcodes = 1;
                    $typestrng = 'eReolenLicens';
                }
                if ($type == 'ERE') {
                    $ereol++;
                    $EREcntcodes = 1;
                    $typestrng = 'eReolen';
                }
                if ($type == 'NLY') {
                    $typestrng = 'Netlydbog';
                    $netlydbog++;
                    $NLYcntcodes = 1;
                }
                if ($type == 'ERA') {
                    $costfree = 'true';
                }
            }
            $cntcodes = $EREcntcodes + $ERLcntcodes + $NLYcntcodes;
            if ($cntcodes > 1) {
                $faust = $marc->findSubFields('001', 'a', 1);
                echo "More than one production code (NLY,ERE,ERL):  $total, $faust\n";
            }
            if ($typestrng) {
                $found = false;
                $f856us = $marc->findSubFields('856', 'u');
                foreach ($f856us as $f856u) {
                    if (strstr($f856u, 'ereolen.dk')) {
                        $found = true;
                    }
                    if (strstr($f856u, 'netlydbog.dk')) {
                        $found = true;
                    }
                }
                if (!$found) {
                    if ($status != 'd') {
//                        $ln = $marc->toLineFormat();
                        $faust = $marc->findSubFields('001', 'a', 1);
                        echo "Missing field 856: $total, $faust\n";
                        $update = "update digitalresources set sent_to_basis = null "
                            . "where faust = '$faust' and provider = 'Pubhub'";
                        $db->exe($update);
                    }
                }
            }
            if ($typestrng) {
//        if ($type == 'ERE' || $type == 'NLY' || $type == 'ERL' || $type == 'EBI') {
                echo "Fundet total:$total, del:$del, netlydbog:$netlydbog, ereol:$ereol ereollicens:$ereollicens ebib:$ebib\n";
//                    $lokalid = $marc->findSubFields('001', 'a', 1);
                $faust = $marc->findSubFields('001', 'a', 1);
                $isbn13s = $marc->findSubFields('021', 'e');
                $titles = $marc->findSubFields('245', 'a');
                if (count($titles)) {
                    $title = substr($titles[0], 0, 99);
                } else {
                    $title = "Unkown";
                }
                $strng = $marc->toLineFormat();
//                if (count($isbn13s) > 1) {
//                    echo "More than 1 (one) isbn13 in the record - first occurence choosen\n";
//                    print_r($isbn13s);
//                }
                $isbn13 = '';
                if ($isbn13s) {
                    if (count($isbn13)) {
                        $isbn13 = $isbn13s[0];
                    }
                }
                $sql = "
                        insert into $tablename
                        (seqno, createdate, dbuser, type, faust, isbn13, status, costfree, title, marc)
                        VALUES
                        (nextval('$seqname'),
                        current_timestamp,
                        '$user',
                        '$typestrng',
                        '$faust',
                        '$isbn13',
                        '$status',
                        '$costfree',
                        $1,
                        $2
                     )
                 ";
                $db->query_params($sql, array($title, $strng));
            }
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}