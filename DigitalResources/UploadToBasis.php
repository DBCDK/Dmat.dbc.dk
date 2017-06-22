<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

//require_once "makeMarc.php";
require_once "ConvertXMLtoMarc_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";

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
    //echo "\t-t table type ('Biblioteksvaesner' or 'Betjeningssteder' or 'Hjaelpetabel')\n";
    //echo "\t-d datafilename (the program will extract the table type from the datafilename) \n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    //echo "\t-v verbose level\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = FALSE;
//$configsection = "eLibToBasis";

$Phus = '7';
$Basis = '1';

$options = getopt("hp:n");
//if (!$options)
//    $options = array();

if (array_key_exists('h', $options))
    usage();

if (array_key_exists('n', $options))
    $nothing = true;

if (array_key_exists('p', $options))
    $inifile = $options['p'];

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (( $logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (( $verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
if (( $ociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser stated in the configuration file");
if (( $ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd stated in the configuration file");
if (( $ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase stated in the configuration file");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START ToBasis.php " . __FILE__ . "****");

$marcfil = new marc();
$upload_cmd = str_replace('@', '"', $config->get_value('upload_cmd', 'setup'));
$isofile = 'isofile';

try {
    $tablename = "digitalresources";
    $marcfil->OpenMarcFile('ToBasis.iso');
    while ($marcfil->readNextMarc()) {
        $fausts = $marcfil->findSubFields('001', 'a');
        print_r($fausts);
        $isomarc = $marcfil->toIso();
        $fp = fopen($isofile, "w");
        fwrite($fp, $isomarc);
        fclose($fp);
        $cmd = $startdir . "/" . $upload_cmd;
        $cmd = str_replace('$PhusOrBasis', "$Basis", $cmd);
        $return_string = system($cmd, $return_var);
        if ($return_var) {
            echo "The cmd:$cmd is in error: $return_var\n";
            echo "return_string:$return_string\n";
            verbose::log(ERROR, "The cmd:$cmd is in error: $return_var, return_string:$return_string");
            exit(2);
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}