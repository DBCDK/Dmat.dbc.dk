<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/OLS_class_lib";
$classes = $startdir . "/../classes";

require_once "$classes/mediadb_class.php";
require_once "$inclnk/inifile_class.php";
require_once "$inclnk/verbose_class.php";
require_once "$inclnk/pg_database_class.php";

$inifile = '../DigitalResources.ini';

if (array_key_exists('test', $_REQUEST)) {
    $test = true;
} else {
    $test = false;
}
if ($test) {
    echo "<br />inifile:$inifile<br />\n";
}

$config = new inifile($inifile);
if ($config->error)
    die($config->error);

if (($pubhubdir = $config->get_value('pubhubdir', 'setup')) == false)
    die("no pubhubdir stated in the configuration file");

// rens data dir hvis filer ved et uheld ligger her
$files = scandir($pubhubdir);
$now = time();
// maxdiff = 1 day
$maxdiff = 1 * 24 * 60 * 60;
foreach ($files as $file) {
    $filename = "$pubhubdir/$file";
    if ( !is_dir($filename)) {
        $diff = $now - fileatime($filename);
        if ($diff > $maxdiff) {
            unlink($filename);
        }
    }
}


if (($logfile = $config->get_value('logfile', 'setup')) == false)
    die("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    die("no verboselevel stated in the configuration file");

if (($ftp_server = $config->get_value('pubhub_ftp_server', 'setup')) == false)
    die("no pubhub_ftp_server stated in the configuration file");

if (($ftp_user = $config->get_value('pubhub_ftp_user', 'setup')) == false)
    die("no pubhub_ftp_user stated in the configuration file");

if (($ftp_pass = $config->get_value('pubhub_ftp_passwd', 'setup')) == false)
    die("no pubhub_ftp_passwd stated in the configuration file");

$connect_string = $config->get_value("connect", "setup");


$isbn = trim($_REQUEST['isbn'], '"');

if ($test) {
    echo "ISBN:$isbn <br />\n";
}

if ( !$isbn) {
    die("<h1>Der er ikke angivet noget ISBN!</h1>");
}
// find GUID
try {
    $db = new pg_database($connect_string);
    $db->open();

    $mediadb = new mediadb($db, basename(__FILE__), $nothing);
    $rows = $mediadb->fetchByIsbn($isbn);

    $row = $rows[0];
    if ($isbn == '' || $row['isbn13'] != $isbn) {
        die("<h1>Bogen med isbn \"$isbn\" blev ikke fundet</h1>");
    }
    if ($test) {
        echo "sql:$sql\n";
        foreach ($row as $key => $val) {
            echo "\$row[$key]=$val<br/>\n";
        }
    }
    $type = $row['filetype'];
    $guid = $row['bookid'];
    $from = array(' ', "'", '`', '´', '-');
    $to = array('_', 'x');
    $title = str_replace($from, $to, $row['title']) . "." . $type;
    $server_file = $guid . "." . $type;
    $local_file = "$pubhubdir/$server_file";
    if ($test) {
        echo "server_file:$server_file  >>>>>> local_file:$local_file<br />\n";
    }

    $conn_id = ftp_connect($ftp_server) or die("Couldn't connect to $ftp_server");

    if ($type == 'epub') {
        $type = 'epub+zip';
    }

// try to login
    if (@ftp_login($conn_id, $ftp_user, $ftp_pass)) {
//    echo "Connected as $ftp_user@$ftp_server\n";
    } else {
        die("Couldn't connect as $ftp_user\n");
    }

// try to download $server_file and save to $local_file
    if (ftp_get($conn_id, $local_file, $server_file, FTP_BINARY)) {
//    echo "Successfully written to $local_file\n";
    } else {
        die("There was a problem, can't open the local_file: $local_file\n");
    }

// close the connection
    ftp_close($conn_id);

    if ($test) {
        echo "header(\"Content-type:application/$type\")<br />\n";
    } else {
        header("Content-type:application/$type");

        header("Content-Disposition:attachment;filename=$title");

        readfile($local_file);

        unlink($local_file);
    }
} catch (Exception $err) {
    echo "The exception code is: " . $err->getCode() . "\n";
    echo $err . "\n";
    $err_txt = $err->getMessage();
    $getLine = "Line number:" . $err->getLine();
    $getTrace = $err->getTraceAsString();
    exit;
}

