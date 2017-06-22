<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 04-08-2016
 * Time: 09:34
 */

/**
 * This function shows how to use the program
 * @param string $str
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/inc";
$classes = $startdir . "/classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";

function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-s sauceLabs,  using saucelabs testing environment, default: local testing\n";
    echo "\t-d User dockers when testing \n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = "DigitalResources.ini";
$nothing = FALSE;


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('s' => 'ProgramMatch', 'N' => 'nothing', 'S' => 4, 't' => 'true');
} else {
    $options = getopt("hsd");
}

$where = 'local';
if (array_key_exists('s', $options)) {
    $where = 'remote';
}

$docklands = false;
if (array_key_exists('d', $options)) {
    $docklands = true;
}

if (array_key_exists('h', $options)) {
    usage();
}

$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($psqlTestPort = $config->get_value('psqlTestPort', 'setup')) == false)
    usage("no psqlTestPort stated in the configuration file");

if (($basedir = $config->get_value('basedir', 'setup')) == false)
    usage("no basedir stated in the configuration file");

$user = getenv('USER');
$base = basename($basedir);
echo "basedir:$basedir\n";
$url = "http://localhost/~${user}/$base";
$txt = "Running: " . __FILE__;
$strng = '';
for ($i = 0; $i < strlen($txt); $i++) {
    $strng .= '*';
}
echo "\n\n$strng\n$txt\n$strng\n\n";

//if (file_exists('DigitalResources/logfile')) {
//    unlink('DigitalResources/logfile');
//}

// skal kaldes inden chdir('UnitTests');
source();

chdir('UnitTests');

$her = getcwd();
echo "her:$her\n";

MakeDir('test_reports');

callPhpUnit('MatchMedier');

$port = 2612;
$KEY = $sckey;
$USERNAME = "lunvig";
if ($where == 'remote') {
    $port = 80;
    cmd("saucelabs/sc -u $USERNAME --daemonize -k $KEY -f WaitForMe.file -d PID.file -l sc.log");
    cmd("php WaitForFile.php -f WaitForMe.file");
}

system('type geckodriver');

if ($docklands) {
    cmd("python run_eVa_test.py $where $USERNAME $KEY http://devel7.dbc.dk:2612");
    cmd("python run_eLu_test.py $where $USERNAME $KEY http://devel7.dbc.dk:2612");
} else {
    cmd("python run_eVa_test.py $where $USERNAME $KEY $url");
    cmd("python run_eLu_test.py $where $USERNAME $KEY $url");
}

sleep(3);

if ($where == 'remote') {
    $pid = trim(file_get_contents('PID.file'));
    posix_kill($pid, 15);
}


callPhpUnit('insertFaust');
callPhpUnit('ToBasisMediaService');
callPhpUnit('ToPromat');
callPhpUnit('ToPublizon');
callPhpUnit('lek');


function callPhpUnit($name) {
    $fphp = 'test_' . $name . '.php';
    $xml = $name . ".xml";
    $cmd = "phpunit --log-junit phpunitlog/$xml $fphp";
    $strng = '';
    for ($i = 0; $i < strlen($cmd); $i++) {
        $strng .= '-';
    }
    echo "$strng\n$cmd\n$strng\n";
    exec($cmd, $output, $sta);
    foreach ($output as $ln) {
        echo "$ln\n";
    }
    echo "$strng\n\n";
    if ($sta) {
        die("Fundet en fejl, vi stopper\n\n");
    }
}

function MakeDir($dir) {
    if ( !file_exists($dir)) {
        echo "Making dir:$dir\n";
        mkdir($dir);
    }
    //    chmod($dir, 0777);
}

function cmd($cmd) {

    $strng = '';
    for ($i = 0; $i < strlen($cmd); $i++) {
        $strng .= '+';
    }
    echo "$strng\n$cmd\n$strng\n";
    exec($cmd, $output, $sta);
    foreach ($output as $ln) {
        echo "$ln\n";
    }
    if ($sta) {
        die("Fundet en fejl, vi stopper\n\n");
    }
    echo "$strng\n* * * STOP * * *\n$strng\n\n\n";
}

function source() {
    // Same as "source $dir"
    $VIRTUAL_ENV = getcwd() . "/venv";
    putenv("VIRTUAL_ENV=$VIRTUAL_ENV");
    $PATH = getenv("PATH");
    $PATH = $VIRTUAL_ENV . "/bin:" . $PATH;
    putenv("PATH=$PATH");
    exec('hash -r', $output);
    foreach ($output as $ln) {
        echo "$ln\n";
    }
}

?>
