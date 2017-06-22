#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file UpdateFaustIsbnTable.php
 * @brief Takes a file with marc-records (option -f) and extract the isbn's
 * and faust and ...
 * Data is inserted in the table faustisbn in the database.
 *
 * @author Hans-Henrik Lund
 *
 * @date 04-01-2012
 *
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";


require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
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
    echo "\t-f datafile (marc iso format) \n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;

$options = getopt("hp:f:");
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('f', $options))
    $datafile = $options['f'];
else
    usage('Datafile (-f) is missing!');
if (array_key_exists('p', $options))
    $inifile = $options['p'];

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

$tablename = 'digitalresources_marcextract';
$connect_string = $config->get_value("connect", "setup");

try {
    $db = new pg_database($connect_string);
    $db->open();

    // look for the "$tablename" - if none, make one.
    $sql = "select tablename from pg_tables where tablename = $1";
    $arr = $db->fetch($sql, array($tablename));
    if (!$arr) {
        $sql = "create table $tablename (
            createdate timestamp,
            recordid varchar(20),
            librarycode varchar(10),
            faust varchar(8),
            title varchar(256),
            weeklabels varchar(100)
            )
            ";
        $db->exe($sql);
        verbose::log(TRACE, "table created:$sql");
    }

    $bondtable = 'digitalresources_faustisbn';
    // look for the "$tablename" - if none, make one.
    $sql = "select tablename from pg_tables where tablename = $1";
    $arr = $db->fetch($sql, array($bondtable));
    if (!$arr) {
        $sql = "create table $bondtable (
            faust varchar(8),
            isbn varchar(13)
            )
            ";
        $db->exe($sql);
        verbose::log(TRACE, "table created:$sql");
    }

    $marc = new marc();
    $cnt = 0;
    $marc->OpenMarcFile($datafile);

    while ($marc->readNextMarc()) {
        $faust = "";
        $title = "unknown";
        $isbns = array();
        $cnt++;
//        echo "cnt:$cnt\n";

        $res = $marc->findSubFields('001', 'a');
        if ($res) {
            foreach ($res as $recordid)
                ;
        }
        $res = $marc->findSubFields('001', 'b');
        if ($res) {
            foreach ($res as $librarycode)
                ;
        }

        $res = $marc->findSubFields('021', 'e');
        if ($res) {
            foreach ($res as $isbn) {
                $isbns[] = materialId::normalizeISBN($isbn);
            }
        }
        $res = $marc->findSubFields('021', 'a');
        if ($res) {
            foreach ($res as $isbn) {
                $isbns[] = materialId::normalizeISBN($isbn);
            }
        }
        $res = $marc->findSubFields('245', 'a');
        if ($res) {
            foreach ($res as $title)
                ;
        }

        if ($librarycode == '870970')
            $faust = materialId::normalizeFAUST($recordid);
//        echo "recordid:$recordid, librarycode:$librarycode, title:$title faust($faust)\n";
//        print_r($isbns);
        $ugekodestring = implode('+', $marc->findSubFields('032', 'x'));

//        print_r($ugekodestring);
        // check whether we know the record, if so we delete the old info.
        $where = "where recordid = '$recordid'
                and librarycode = '$librarycode'\n";
        $sql = "select * from $tablename $where";
        $arr = $db->fetch($sql);
        if ($arr) {
            foreach ($arr as $record) {
                if (array_key_exists('faust', $record)) {
                    $faust = $record['faust'];
                    $sql = "delete from $bondtable where faust = '$faust'";
                    $db->exe($sql);
                }
            }
            $sql = "delete from $tablename $where";
            $db->exe($sql);
        }

        $sql = "insert into $tablename (
                    createdate,
                    recordid,
                    librarycode,
                    faust,
                    title,
                    weeklabels)
              values (current_timestamp,'$recordid','$librarycode','$faust',$1,
                     '$ugekodestring')";
        $title2 = substr($title, 0, 256);
        $title2 = utf8_encode($title2);
//        echo "title:$title\n";
//        echo "title2:$title2\n";
        $db->query_params($sql, array($title2));

        if ($faust) {
            foreach ($isbns as $isbn) {
                $sql = "insert into $bondtable (faust,isbn)
                    values ( '$faust','$isbn')";
                $db->exe($sql);
            }
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}
?>