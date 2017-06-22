<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";

$inifile = '../DigitalResources.ini';

$config = new inifile($inifile);
if ($config->error)
    die($config->error);

if (($logfile = $config->get_value('weblog', 'setup')) == false)
    die("no weblog stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    die("no verboselevel stated in the configuration file");

if (($ftp_server = $config->get_value('pubhub_ftp_server', 'setup')) == false)
    die("no pubhub_ftp_server stated in the configuration file");

if (($ftp_user = $config->get_value('pubhub_ftp_user', 'setup')) == false)
    die("no pubhub_ftp_user stated in the configuration file");

if (($ftp_pass = $config->get_value('pubhub_ftp_passwd', 'setup')) == false)
    die("no pubhub_ftp_passwd stated in the configuration file");

if (($pubhubdir = $config->get_value('pubhubdir', 'setup')) == false)
    die("no pubhubdir stated in the configuration file");

// rens data dir
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


$guid = $_REQUEST['guid'];
$type = $_REQUEST['type'];
$from = array(' ', "'", '`', '´', '-');
$to = array('_', 'x');
$title = str_replace($from, $to, $_REQUEST['title']) . "." . $type;

$server_file = $guid . "." . $type;
$local_file = "$pubhubdir/$server_file";

$conn_id = ftp_connect($ftp_server) or die("Couldn't connect to $ftp_server");

if ($type == 'epub') {
    $type = 'epub+zip';
}

// try to login
if (@ftp_login($conn_id, $ftp_user, $ftp_pass)) {
//    echo "Connected as $ftp_user@$ftp_server\n";
} else {
    echo "Couldn't connect as $ftp_user\n";
}

// try to download $server_file and save to $local_file
if (ftp_get($conn_id, $local_file, $server_file, FTP_BINARY)) {
//    echo "Successfully written to $local_file\n";
} else {
    echo "There was a problem\n";
}

// close the connection
ftp_close($conn_id);

header("Content-type:application/$type");

header("Content-Disposition:attachment;filename=$title");

readfile($local_file);

unlink($local_file);
