#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file insertFaustFromUgeleverance.php
 * @brief takes a file with marc-records and compare them with the records in the database to see if they have been catalouged
 * The program will only be used once, when the project is started.
 * It will read the file:   publizon.s00 - which contains marc records.
 * It will extract the isbn/isbn13, and compare them with the database (table:digitalresources)
 * If any "hit" the faust-id from the marc record will be inserted into the database.
 *
 * Because its a one timer, there is no verbose, log or comment in the program
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 *
 */
$startdir = dirname(realpath($argv[0]));
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";

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
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;

$options = getopt("hp:");
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

$tablename = 'digitalresources';
$connect_string = $config->get_value("connect", "setup");

try {
    $db = new pg_database($connect_string);
    $db->open();

    $marc = new marc();
    $cnt = 0;
    $marc->OpenMarcFile('publizon.s00');
//        $marc->OpenMarcFile('201109151036.dbc.EBOGSBIB_TEST');
    while ($marc->readNextMarc()) {
        $faust = "";
        $baseisbn13 = "";
        //echo "cnt:" . $cnt++ . "\n";
        if ($res = $marc->findSubFields('021', 'e')) {
            foreach ($res as $isbn13) {
                //echo "isbn13:$isbn13\n";
                //$sql = "select isbn13 from $tablename where isbn13 = '$isbn13' and format = 'Lydbog'";
                $sql = "select isbn13 from $tablename where isbn13 = '$isbn13'";
                $arr = $db->fetch($sql);
                if ($arr) {
                    //print_r($arr);
                    $baseisbn13 = $arr[0]['isbn13'];
                    //echo "baseisbn13=$baseisbn13\n";
                }
            }
        }
        if ($baseisbn13 == "") {
            if ($res = $marc->findSubFields('021', 'a')) {
                if ($ugekoder = $marc->findSubFields('032', 'x')) {
                    foreach ($ugekoder as $ugekode) {
                        if (substr($ugekode, 0, 3) == 'NLY') {
                            $arr = $marc->getArray();
                            print_r($arr);
                            exit(1);
                        }
                    }
                }
            }
        }
        if ($baseisbn13 == "") {
            if ($res = $marc->findSubFields('021', 'x')) {
                //print_r($res);
                if ($ugekoder = $marc->findSubFields('032', 'x')) {
                    foreach ($ugekoder as $ugekode) {
//                            if ( substr($ugekode,0,3) == 'NLY' ) {
                        if (substr($ugekode, 0, 3) != 'NLY') {
//                                $arr = $marc->getArray();
                            //print_r($arr);
                            foreach ($res as $isbn) {
                                $isbn13 = "";
                                $isbn = materialId::normalizeISBN($isbn);
                                if (strlen($isbn) == 10) {
                                    $isbn13 = materialId::convertISBNToEAN($isbn);
                                }
                                if (strlen($isbn) == 13)
                                    $isbn13 = $isbn;
                                if ($isbn13) {
                                    $sql = "select isbn13 from $tablename where isbn13 = '$isbn13'";
                                    //echo $sql . "\n";
                                    $arr = $db->fetch($sql);
                                    if ($arr) {
                                        //print_r($arr);
                                        $baseisbn13 = $arr[0]['isbn13'];
                                        //echo "baseisbn13=$baseisbn13\n";
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        if ($baseisbn13) {
            $fausts = $marc->findSubFields('001', 'a');
            foreach ($fausts as $faust) {
                $sql = "update $tablename set faust = '$faust', sent_to_basis = '1970-01-01', sent_to_well = '1970-01-01' where isbn13 = '$baseisbn13'";
                //echo "sql:$sql\n";
                $db->exe($sql);
                //	    exit;
            }
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}
?>