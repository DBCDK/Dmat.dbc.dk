s<?php
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
function usage($str = "")
{
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-d datafiles directory\n";
    echo "\t-D dato\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

//$inifile = "TestDigitalResources.ini";
$inifile = $startdir . "/../DigitalResources.ini";
$dir = 'pgdump';
$dato = '';

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('n' => 'true', 's' => 10223);
} else {
    $options = getopt("hp:d:D:");
}
if (array_key_exists('h', $options)) {
    usage();
}

if (array_key_exists('d', $options)) {
    $dir = $options['d'];
}

if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}

echo "dir:$dir\n";

if (array_key_exists('D', $options)) {
    $dato = $options['D'];
}

echo "dato:$dato\n";

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}
echo $inifile . "\n";

if (($logfile = $config->get_value('logfile', 'setup')) == false) {
    usage("no logfile stated in the configuration file");
}
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false) {
    usage("no verboselevel stated in the configuration file");
}

if (($impcmd = $config->get_value('impcmd', 'setup')) == false) {
    usage("no impcmd (import command) stated in the configuration file");
}


$connect_string = $config->get_value("connect", "setup");
$db = new pg_database($connect_string);
$db->open();

importT('mediaservice', $seq = 'seqno');
importT('mediaebooks', $seq = false);
importT('mediaservicedref', $seq = false);
importT('mediaservicenote', $seq = false);
importT('mediaservicerecover', $seq = 'recoverseqno');
importT('runaftererrors', $seq = false);
importT('basisphus', $seq = false);
importT('digitalresources', $seq = 'seqno');
importT('calendarexp', $seq = false);
importT('medialeklog', $seq = 'lekseqno');
importT('calendarexp', $seq = 'seqno');
importT('retroresult', $seq = 'seqno');


// midlertidig ændring af tabeller:
$new = false;
if ($new) {
    $sql = "ALTER TABLE mediaebooks RENAME status  TO active";
    $db->exe($sql);

    $sql = "ALTER TABLE mediaebooks
  ADD PRIMARY KEY (seqno)";
    $db->exe($sql);

    $sql = "ALTER TABLE mediaservice
  DROP COLUMN provider;
ALTER TABLE mediaservice
  DROP COLUMN booktype;
ALTER TABLE mediaservice
  DROP COLUMN filetype;
ALTER TABLE mediaservice
  DROP COLUMN publicationdate;
ALTER TABLE mediaservice
  DROP COLUMN bookid;
ALTER TABLE mediaservice
  DROP COLUMN title;
ALTER TABLE mediaservice
  DROP COLUMN originalxml;
ALTER TABLE mediaservice
  DROP COLUMN isbn13;
ALTER TABLE mediaservice
  DROP COLUMN checksum;
";
//    $db->exe($sql);
// slut
}


//TODO der skal laves om så man kan vælge en anden server/user

function importT($tablename, $seq = false)
{
    global $db, $dir, $dato, $impcmd;

    $her = getcwd();
    chdir($dir);

    $cmd = "ls -tr $tablename.*$dato* | tail -1 ";
    $res = exec($cmd, $output);
    $fil = $output[0];

    $cmd = str_replace('$fil', $fil, $impcmd);
//    $cmd = "psql -h pgtest.dbc.dk -U hhl -f $fil";
//    $cmd = "psql -h pgdrift.dbc.dk -U posthus -f $fil";
    echo "cmd: $cmd\n";
    $res = exec($cmd, $output);
    print_r($output);

    if ($seq) {
        $drop = "DROP SEQUENCE if exists " . $tablename . "seq";
        $db->exe($drop);
        $sql = "select max($seq)  as start from  $tablename ";
        $maxer = $db->fetch($sql);
        $start = $maxer[0]['start'] + 1;
        $create = "CREATE SEQUENCE " . $tablename . "seq START $start";
        echo "$create\n";
        $db->exe($create);
    }
    chdir($her);
}
