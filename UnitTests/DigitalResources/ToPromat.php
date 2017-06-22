<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
$mockupclasses = $startdir . "/../UnitTests/classes";

require_once "$classes/mediadb_class.php";
require_once "$classes/promat_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";


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
    echo "\t-t use test database ([testcase]connect) \n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = "../DigitalResources.ini";
$nothing = FALSE;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('n' => 'true', 'x' => 'true');
} else {
    $options = getopt("hp:nt");
}
if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}
if (array_key_exists('h', $options))
    usage();

if (array_key_exists('n', $options)) {
    $nothing = true;
}
$promatNothing = $nothing;

$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
    $promatNothing = true;
}

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);


if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");

if (($ociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser stated in the configuration file");
if (($ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd (seBasis) stated in the configuration file");
if (($ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase (seBasis) stated in the configuration file");

if (($workdir = $config->get_value('workdir', 'setup')) == false)
    usage("no workdir stated in the configuration file");

if (($basedir = $config->get_value('basedir', 'setup')) == false) {
    usage("no basedir  stated in the configuration file [setup]");
}

$connect_string = $config->get_value("connect", $setup);

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");

try {

    $promat = new promat_class($config, $promatNothing);
    if ($promat->error()) {
        usage($promat->error());
    }

    if ($setup == 'testcase') {
        require_once "$mockupclasses/mockUpLibV3_class.php";
        $libV3API = new mockUpLibV3API($basedir,'data');
    } else {
        require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
        $libV3API = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
    }
    $marc = new marc();

    $db = new pg_database($connect_string);
    $db->open();
    $mediadb = new mediadb($db, basename(__FILE__), $nothing);

    $status = 'UpdatePromat';
    $seqnumbers = $mediadb->getCandidates($status);

    if ($seqnumbers) {
        for ($seqnum = 0; $seqnum < count($seqnumbers); $seqnum++) {

            $seqno = $seqnumbers[$seqnum]['seqno'];
            $lokalid = $seqnumbers[$seqnum]['newfaust'];
            $choice = $seqnumbers[$seqnum]['choice'];
            $title = substr(utf8_decode($seqnumbers[$seqnum]['title']), 0, 40);
            $weekcode = '';

            // fetch record from Basis
            $m = $libV3API->getMarcByLB($lokalid, '870970');
            if ($m) {
                $marc->fromIso($m[0]['DATA']);
                $titles = $marc->findSubFields('245', 'a');
                $title = substr($titles[0], 0, 40);
                $DBFs = $marc->findSubFields('032', 'a');
                $weekcode = "";
                foreach ($DBFs as $DBF) {
                    $weekcode .= $DBF . ";";
                }
                $f032xs = $marc->findSubFields('032', 'x');
                foreach ($f032xs as $f032x) {
                    $weekcode .= $f032x . ";";
                }
                $weekcode = rtrim($weekcode, ';');
            }
            verbose::log(TRACE, "seqno:$seqno, title:$title, weekcode:$weekcode, choice:$choice");
            $choices = explode(' ', $choice);
            $instruction = "";
            foreach ($choices as $c) {
                if ($c == 'BKMV') {
                    $instruction .= utf8_decode("Lektørvurdering ");
                }
                if ($c == 'BKX') {
                    $instruction .= "Ekspres ";
                }
            }
            $note = utf8_decode("");

            $promat->insertCandidate($lokalid, $weekcode, $note, $title, $instruction);

            if (!$nothing) {
                $mediadb->newStatus($seqno, 'UpdatePublizon');
            }
            //update mediaservice
//            $mediadb->newStatus($seqno, 'UpdatePublizon');
        }
    }
} catch (Exception $ex) {
    echo $ex . "\n";
    exit;
}

verbose::log(TRACE, "**** STOP " . __FILE__ . "****");




