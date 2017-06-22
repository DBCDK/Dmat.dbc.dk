<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$classes/mediadb_class.php";
require_once "$inclnk/OPAC_class_lib/matchLibV3_class.php";
require_once 'MakeDisplayFiles.php';
require_once 'showseqno_class.php';
require_once "session.php";
require_once "$classes/scanSaxo_class.php";
require_once "$classes/RBD_class.php";

$uri = $_SERVER['REQUEST_URI'];
//print_r($_REQUEST);
//exit;

$initials = login($_REQUEST);
if (array_key_exists('username', $_SESSION)) {
//if ($_SESSION['username']) {
    $initials = $_SESSION['username'];
}


$inifile = '../DigitalResources.ini';

$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}

$setup = 'setup';
if (array_key_exists('setup', $_SESSION)) {
    $setup = $_SESSION['setup'];
}
$connect_string = $config->get_value("connect", $setup);
//var_dump($config);
if (($datadir = $config->get_value('datadir', 'setup')) == false) {
    die("no datadir stated in the configuration file");
}
if (($logfile = $config->get_value('weblog', 'setup')) == false) {
    die("no weblog stated in the configuration file");
}
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false) {
    die("no verboselevel stated in the configuration file");
}

if (($blogin = $config->get_value('ociuser', 'seBasis')) == false) {
    die("no seBasis/ociuser stated in the configuration file");
}
if (($bpasswd = $config->get_value('ocipasswd', 'seBasis')) == false) {
    die("no seBasis/passwd stated in the configuration file");
}
if (($bhost = $config->get_value('ocidatabase', 'seBasis')) == false) {
    die("no seBasis/ocihost stated in the configuration file");
}

if (($plogin = $config->get_value('ociuser', 'sePhus')) == false) {
    die("no sePhus/ociuser stated in the configuration file");
}
if (($ppasswd = $config->get_value('ocipasswd', 'sePhus')) == false) {
    die("no sePhus/passwd stated in the configuration file");
}
if (($phost = $config->get_value('ocidatabase', 'sePhus')) == false) {
    die("no sePhus/ocihost stated in the configuration file");
}

if (($basedir = $config->get_value('basedir', 'setup')) == false) {
    die("no basedir  stated in the configuration file [setup]");
}
//phpinfo();
//$basedir = $_SERVER["CONTEXT_DOCUMENT_ROOT"];
//$basedir = $_SERVER['SCRIPT_FILENAME'];
//$basedir = dirname(dirname($basedir));
//echo "BASEDIR:$basedir<br>";
//var_dump($_SESSION);
//var_dump($_SERVER);

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . " ****");
verbose::log(DEBUG, "URI:$uri");
$matchLibV3 = new matchLibV3($blogin, $bpasswd, $bhost);
if ($setup != 'setup') {

    require_once "../UnitTests/classes/mockUpLibV3_class.php";

    $libv3 = new mockUpLibV3API($basedir, 'data');

} else {
    require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
    $libv3 = new LibV3API($blogin, $bpasswd, $bhost);
    $libv3->setPhusLogin($plogin, $ppasswd, $phost);
}

try {
    verbose::log(DEBUG, "connect string:$connect_string");

    $db = new pg_database($connect_string);
    $db->open();
//    var_dump($db);
    $seqno = 1;
    if (array_key_exists('seqno', $_REQUEST)) {
        $seqno = $_REQUEST['seqno'];
    }

    $mediadb = new mediadb($db, basename(__FILE__));
//    $matid = new materialId();
//    $isbn = '978' + '44';
//    $res = materialId::validateEAN($isbn);
//    materialId::
//    $res = materialId::validateEAN($isbn);
//    echo "RES:$res";
    $scanSaxo = new scanSaxo_class($config, $setup);
    $rbd = new RBD_class();

    $showseqno = new showseqno($mediadb, $libv3, $matchLibV3, 'html/showseqno_2.phtml', 'eVa', $datadir, $scanSaxo, $rbd);
    $cmd = 'Til oversigten';
    if (array_key_exists('cmd', $_REQUEST)) {
        $cmd = $_REQUEST['cmd'];
    }
    if (array_key_exists('faust', $_REQUEST)) {
        $faust = $_REQUEST['faust'];
    }
//    foreach ($_REQUEST as $key => $item) {
//        if (substr($key, 0, 4) == 'eVA-' or $key = 'expisbn') {
//            $_SESSION[$key] = $item;
//        }
//    }

    if (array_key_exists('seqnodirect', $_REQUEST)) {
        $seqnodirect = $_REQUEST['seqnodirect'];
    }
    if (array_key_exists('Lek', $_REQUEST)) {
        $cmd = 'Lektør';
        if (strlen($_REQUEST['expisbn']) > 0) {
            $cmd = 'expisbn';
        }
        $_REQUEST['cmd'] = $cmd;
    }

    if ($cmd == 'choose') {
        $cmd = 'secondChoice';
    }

    if ($cmd == 'OK' and strlen($_REQUEST['faust']) > 0) {
        $cmd = 'showFaust';
    }
    $next = 1;
    $back = -1;
    switch ($cmd) {
        case 'nxtpage':
            $_SESSION['page'] += 1;
            $showseqno->showtable($seqno, 0, $_REQUEST);
            break;
        case 'prepage':
            $_SESSION['page'] += -1;
            $showseqno->showtable($seqno, 0, $_REQUEST);
            break;
        case 'thispage':
            $showseqno->showtable($seqno, 0, $_REQUEST);
            break;
        case 'Oversigt':
            $mediadb->releaseLck($seqno, $initials);
            header('Location:index.php');
            break;
        case 'titlesearch':
            $showseqno->showtable($seqno, 0, $_REQUEST);
            break;
        case 'onlyone':
            $_SESSION['page'] = 1;
            if ($_SESSION['seqno']) {
                if ( !$seqno) {
                    $seqno = $_SESSION['seqno'];
                }
            }
            $mediadb->releaseLck($seqno);
            $showseqno->showtable($seqno, 0, $_REQUEST);

            break;
        case 'Drop':
            $mediadb->releaseLck($seqno);
            $mediadb->updateDB($_REQUEST);
            $showseqno->showseqno($seqno, 'next', $_REQUEST);
            break;
        case 'Locked':
            $showseqno->locked(true);
        case 'Til oversigten' :
            $mediadb->releaseLck($seqno);
            $showseqno->showtable($seqno);
            break;
        case 'none':
            $showseqno->showseqno($seqno, 'none');
            break;
        case 'showFaust':
            $showseqno->showseqno($seqno, 'faust', $_REQUEST);
            break;
        case 'show':
            $mediadb->releaseLck($seqno);
            $showseqno->showseqno($seqno, 'direct', $_REQUEST);
//            $showseqno->showseqno($seqno, 'direct');
            break;
        case 'details':
            $mediadb->releaseLck($seqno);
//            print_r($_REQUEST);
//            $showseqno->showseqno($seqno, 'direct', $_REQUEST);
//            $showseqno->showseqno($seqno, 'direct');
            if ($seqno) {
                header("Location:info.php?seqno=$seqno&details=true");
            } else {
//                header("Location:eVa.php?seqno=$seqno&cmd=Oversigt");
                header("Location://");
            }
            break;
        case 'showdirect' :
            $mediadb->releaseLck($seqno, $initials);
            $showseqno->showseqno($seqnodirect, 'direct');
            break;
        case 'Back' :
            $mediadb->releaseLck($seqno);
            $mediadb->updateDB($_REQUEST);
            if ($seqno < 1) {
                $seqno = 1;
            }
            $showseqno->showseqno($seqno, 'back');
            break;
        case 'secondChoice':
            $showseqno->showseqno($seqno, 'secondChoice', $_REQUEST);
            break;
        case 'expisbn':
            $showseqno->showseqno($seqno, 'expisbn', $_REQUEST);
            break;
        case 'Ny registrering':
        case 'Er registreret':
//        case 'choose':
        case 'OK' :
        case 'Lektør':
        case 'LekFinal':
            $mediadb->releaseLck($seqno);
            $rows = $mediadb->getData('next', $seqno);
            $nxtseqno = $rows[0]['seqno'];
            $mediadb->updateDB($_REQUEST);
//            $showseqno->showseqno($nxtseqno, 'direct', $_REQUEST);
            $showseqno->showseqno($nxtseqno, 'direct');
            break;
        case 'Afventer':
            $showseqno->showseqno($seqno, 'Afventer');
            break;
        case 'Fortryd' :
            $mediadb->releaseLck($seqno);
//            $showseqno->showseqno($seqno, 'next', $_REQUEST);
            $showseqno->showseqno($seqno, 'next');
            break;
        case 'd08' :
            $showseqno->showseqno($seqno, 'Note');
            break;
        case 'f991' :
            $showseqno->showseqno($seqno, 'f991');
            break;
        case 'Afvent' :
            $mediadb->releaseLck($seqno, $initials);
            $mediadb->updateNote($seqno, $_REQUEST);
            $mediadb->updateDB($_REQUEST);
            $showseqno->showseqno($seqno, 'next', $_REQUEST);
            break;
        case 'insertLink':
//            $mediadb->updateLekFaust($seqno, $_REQUEST);
            $showseqno->showseqno($seqno, 'direct', $_REQUEST);
//            $showseqno->showseqno($seqno, 'direct');
            break;
        case 'insert991':
            $mediadb->updateNote($seqno, $_REQUEST);
            $showseqno->showseqno($seqno, 'direct');
            break;
        case 'Gem d08' :
            $mediadb->updateNote($seqno, $_REQUEST);
            $showseqno->showseqno($seqno, 'direct');
            break;
        case 'eORrRegret':
            $showseqno->showseqno($seqno, 'direct');
            break;
        default:
            $showseqno->showseqno($seqno, 'next', $_REQUEST);
    }
} catch (Exception $err) {
    echo "The exception code is: " . $err->getCode() . "\n";
    echo $err . "\n";
    $err_txt = $err->getMessage();
    $getLine = "Line number:" . $err->getLine();
    $getTrace = $err->getTraceAsString();
//    mail($err_mail_to, "Exception utc-process.php", "----------------------\n$err_txt\n---------------------\n
//      $getLine\n$getTrace\n");
    exit;
}




