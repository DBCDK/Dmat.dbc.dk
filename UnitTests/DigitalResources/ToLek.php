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
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$classes/lek_class.php";
require_once "$classes/Calendar_class.php";
require_once "$classes/getFaust_class.php";
//require_once "$mockupclasses/getFaust_class.php";
require_once "$classes/ftp_transfer_class.php";
require_once "$classes/ssh_class.php";

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
    echo "\t-s seqno, tag dette seqno \n";
    echo "\t-m max antal poster der skal behandles\n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = "../DigitalResources.ini";
$nothing = FALSE;
$onlyThisSeqno = '';
$max = 0;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('x' => 'true', 'y' => 'false', 'n' => 'true', 's' => 16097, 'm' => 10000);
} else {
    $options = getopt("hp:ntrs:m:");
}

if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}
if (array_key_exists('h', $options)) {
    usage();
}

if (array_key_exists('n', $options)) {
    $nothing = true;
}
if (array_key_exists('m', $options)) {
    $max = $options['m'];
}
if (array_key_exists('s', $options)) {
    $onlyThisSeqno = $options['s'];
}
//$promatNothing = $nothing;

$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
//    $promatNothing = true;
}

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}


if (($logfile = $config->get_value('logfile', 'setup')) == false) {
    usage("no logfile stated in the configuration file");
}
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false) {
    usage("no verboselevel stated in the configuration file");
}

if (($ociuser = $config->get_value('ociuser', 'seLektor')) == false) {
    usage("no ociuser stated in the configuration file");
}
if (($ocipasswd = $config->get_value('ocipasswd', 'seLektor')) == false) {
    usage("no ocipasswd (seBasis) stated in the configuration file");
}
if (($ocidatabase = $config->get_value('ocidatabase', 'seLektor')) == false) {
    usage("no ocidatabase (seBasis) stated in the configuration file");
}

if (($bociuser = $config->get_value('ociuser', 'seBasis')) == false) {
    usage("no ociuser stated in the configuration file");
}
if (($bocipasswd = $config->get_value('ocipasswd', 'seBasis')) == false) {
    usage("no ocipasswd (seBasis) stated in the configuration file");
}
if (($bocidatabase = $config->get_value('ocidatabase', 'seBasis')) == false) {
    usage("no ocidatabase (seBasis) stated in the configuration file");
}

if (($workdir = $config->get_value('workdir', 'setup')) == false) {
    usage("no workdir stated in the configuration file");
}
if (($basedir = $config->get_value('basedir', 'setup')) == false) {
    usage("no basedir  stated in the configuration file [setup]");
}

$faustcmd = $config->get_value('getFaust_cmd', 'setup');

$connect_string = $config->get_value("connect", $setup);

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");


try {
    $ftpnothing = $nothing;
    $sshtransferLek = new ssh($config, 'lekToDios', $ftpnothing);
    if ($sshtransferLek->error()) {
        usage($sshtransferLek->error());
    }
    $ftptransferBasis = new ftp_transfer_class($config, 'lektoer', 'MediaToBasis', $ftpnothing);
    if ($ftptransferBasis->error()) {
        usage($ftptransferBasis->error());
    }
//    $ftptransferAquapr2 = new ftp_transfer_class($config, 'lektoer', 'MediaToAquapr2', $ftpnothing);
//    if ($ftptransferAquapr2->error()) {
//        usage($ftptransferAquapr2->error());
//    }

    if ($setup == 'testcase') {
        require_once "$mockupclasses/mockUpLibV3_class.php";
        $LektorBasen = new mockUpLibV3API($basedir,'data');
        $Basis = new mockUpLibV3API($basedir,'data');
    } else {
        $LektorBasen = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
        $Basis = new LibV3API($bociuser, $bocipasswd, $bocidatabase);
    }
    // kun under test indtil opbygning af data i mockup
//    $LektorBasen = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
//    $Basis = new LibV3API($bociuser, $bocipasswd, $bocidatabase);

    $marc = new marc();

    $db = new pg_database($connect_string);
    $db->open();
    $mediadb = new mediadb($db, basename(__FILE__), $nothing);
    $year = date('Y');
    $calendar = new Calendar_class($db, $year);
    $gfaust = new getFaust($faustcmd);
    $lek = new lek_class($mediadb, $Basis, $LektorBasen, $calendar, $gfaust, $nothing);
//    $lek = new lek_class($mediadb, $Basis, $nothing);

    $lek->setMax($max);
    $lek->findUpdates();

    if ($onlyThisSeqno) {
        $upd = $lek->getUpdates();
        if (!array_key_exists($onlyThisSeqno, $upd)) {
            die ("seqno $onlyThisSeqno er ikke en kandidat i \$upd\n");
        }
        $newupd = array();
        $newupd[$onlyThisSeqno] = $upd[$onlyThisSeqno];
        $lek->setUpdates($newupd);
    }

    $marclns = $lek->processUpdates();
    if ($marclns) {
        foreach ($marclns as $iso) {
            // convert to lineformat:78
            $marc->fromIso($iso);
            $ln = $marc->toLineFormat(78);
            $sshtransferLek->write($ln);
            $id = $marc->findSubFields('001', 'a', 1);
            // er det en uden karens?
            $ajour = $marc->findSubFields('001', 'c', 1);
            $create = $marc->findSubFields('001', 'd', 1);
            $ajour = substr($ajour, 0, 4) . "-" . substr($ajour, 4, 2) . '-' . substr($ajour, 6, 2);
            $create = substr($create, 0, 4) . "-" . substr($create, 4, 2) . '-' . substr($create, 6, 2);
            $ajour = strtotime("$ajour - 6 months");
            $create = strtotime("$create");
//            if ($create < $ajour) {
//                $ftptransferAquapr2->write($iso);
//            }
        }
    }
//    echo "--------------------------------------------------------\n";
    // send posterne til Simut

    $toBasis = $lek->getBasisRecs();
    foreach ($toBasis as $iso) {
        $ftptransferBasis->write($iso);
        $marc->fromIso($iso);
        $strng = $marc->toLineFormat();
    }


    $sshtransferLek->scp();
    $ftptransferBasis->put();
//    $ftptransferAquapr2->put();

} catch (Exception $ex) {
    echo $ex . "\n";
    exit;
}

verbose::log(TRACE, "**** STOP " . __FILE__ . "****");




