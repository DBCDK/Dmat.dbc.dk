<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by IntelliJ IDEA.
 * User: hhl
 * Date: 24-05-2017
 * Time: 12:48
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/inc";

require_once "$inclnk/OLS_class_lib/inifile_class.php";

/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "") {
    global $argv, $inifile, $instalini, $dir;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-i INSTALL ini file (default:\"$instalini\")\n";
    echo "\t-v files with vars (from git, ex. globalVars.php,privat_hhl.php)\n";
    echo "\t-d pointer to Dir with vars files  (default: $dir\n";
    echo "\t-h help (shows this message)\n";
    exit;
}


// make a link to Rck_send
// todo Rck_send skal laves om til en web-app
//chdir('DigitalResources');
//$out = array();
//chmod('Rck_send-2011.1.0-1149', 0777);
//exec('ln -s Rck_send-2011.1.0-1149 Rck_send');
//foreach ($out as $ln) {
//    echo "$ln\n";
//}
//$out = array();
//chmod('addi_load-2011.1.0-2159', 0777);
//exec('ln -s addi_load-2011.1.0-2159 addi-load');
//foreach ($out as $ln) {
//    echo "$ln\n";
//}
//chdir('..');


//$inifile = "TestDigitalResources.ini";
$dir = "Dmat_INSTALL";
$inifile = $startdir . "/DigitalResources.ini";
$instalini = $startdir . "/DigitalResources.ini_INSTALL";


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('v' => 'globalVars.php,privat_hhl.php');
} else {
    $options = getopt("hp:i:v:d:");
}
if (array_key_exists('h', $options)) {
    usage();
}
if ( !array_key_exists('v', $options)) {
    usage("I need some input!! (-v filename1,filename2,... ");
}

if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}

if (array_key_exists('d', $options)) {
    $dir = $options['d'];
}
if (array_key_exists('i', $options)) {
    $instalini = $options['i'];
}

$USER = getenv('USER');
// is there an instalini file?
if ( !file_exists($instalini)) {
    usage("Unknown INSTALL file: $instalini ");
}
$inputfiles = explode(',', $options['v']);
foreach ($inputfiles as $inputfile) {
    $inputfile = $dir . '/' . $inputfile;
    $inputfile = str_replace('USER', $USER, $inputfile);
    if ( !file_exists($inputfile)) {
        usage("-v $inputfile does not exists");
    }
    include_once $inputfile;
}
$file = file_get_contents($instalini);

foreach ($inputfiles as $inputfile) {
    $inputfile = str_replace('USER', $USER, $inputfile);
    $inputfile = $dir . '/' . $inputfile;
    $lines = file($inputfile);
    foreach ($lines as $line) {
        $line = trim($line);
        if (substr($line, 0, 1) == '$') {
            $tokens = explode('=', $line);
            $key = trim($tokens[0]);
            $val = ${substr($key, 1)};
            $file = str_replace($key, $val, $file);
        }
    }
}
file_put_contents($inifile, $file);

//if ($logdir) {
//    if ( !is_dir($logdir)) {
//        mkdir($logdir, 0777, true);
//    }
//}

/**
 * read the inifile and make logfiles
 * logfiles will have a+rw reights.
 */
$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}
makeDir('logdir');
makeDir('webdir');
makeDir('workdir');
makeDir('pubhubdir');
makeDir('datadir');

makeFile('logfile');
makeFile('weblog');


/**
 * is docker running
 */
$name = "postgres$USER";
startDocker($name, $psqlport);

$name = "postgres${USER}test";
startDocker($name, $psqlTestPort);

function makeFile($name) {
    global $config;
    $file = $config->get_value("$name", 'setup');
    if ( !$file) {
        die("no $name stated in the configuration file($file)\n");
    }
    if ( !file_exists($file)) {
        touch($file);
        chmod($file, 0666);
    }

}

function makeDir($name) {
    global $config;
    $dir = $config->get_value("$name", 'setup');
    if ( !$dir) {

        die("no $name stated in the configuration file($dir)");
    }
    if ( !is_dir($dir)) {
        mkdir($dir);
    }
    chmod($dir, 0777);
}


function startDocker($name, $port) {
    $len = strlen($name) * -1;
    $output = array();
    $found = false;
    exec("docker ps -a -f name=$name", $output);
    foreach ($output as $ln) {
        if (substr($ln, $len) == $name) {
            $found = true;
            break;
        }
    }
    if ($found) {
        // start the container, just in case!
        $cmd = "docker start $name";
        echo "$cmd\n";
        exec("$cmd", $out);
    } else {
        // run the container
        $cmd = "docker run --detach --name $name -p $port:5432 docker.dbc.dk/dbc-postgres";
        echo "$cmd\n";
        exec("$cmd");
    }
}