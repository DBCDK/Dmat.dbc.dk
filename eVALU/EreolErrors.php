<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @todo omskrives til mediadb_class.php
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";

require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";

$tablename = "digitalresources";
$seqname = $tablename . 'seq';

$inifile = '../DigitalResources.ini';

$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

$connect_string = $config->get_value("connect", "setup");

if (($logfile = $config->get_value('weblog', 'setup')) == false)
    die("no weblog stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    die("no verboselevel stated in the configuration file");

if (($blogin = $config->get_value('ociuser', 'seBasis')) == false)
    die("no seBasis/ociuser stated in the configuration file");
if (($bpasswd = $config->get_value('ocipasswd', 'seBasis')) == false)
    die("no seBasis/passwd stated in the configuration file");
if (($bhost = $config->get_value('ocidatabase', 'seBasis')) == false)
    die("no seBasis/ocihost stated in the configuration file");

if (($plogin = $config->get_value('ociuser', 'sePhus')) == false)
    die("no sePhus/ociuser stated in the configuration file");
if (($ppasswd = $config->get_value('ocipasswd', 'sePhus')) == false)
    die("no sePhus/passwd stated in the configuration file");
if (($phost = $config->get_value('ocidatabase', 'sePhus')) == false)
    die("no sePhus/ocihost stated in the configuration file");


$libv3 = new LibV3API($blogin, $bpasswd, $bhost);

$page = new view('html/EreolErrors.phtml');

try {
    verbose::log(DEBUG, "connect string:$connect_string");
    $db = new pg_database($connect_string);
    $db->open();

    $type = $_REQUEST['type'];
    $seqno = $_REQUEST['seqno'];
    $page->set('seqno', $seqno);

    // there has been an update
    if (array_key_exists('errtxt', $_REQUEST)) {
        $errtxt = $_REQUEST['errtxt'];
        $_REQUEST['show'] = 'false';
//        echo "errtxt:$errtxt\n";
        $update = "update runaftererrors "
            . "set errtxt = $1 "
            . "where seqno = $seqno and type = '$type' ";
//        echo $update . "\n";
        $db->query_params($update, array($errtxt));
    }

    if (array_key_exists('chosenFaust', $_REQUEST)) {
        if ($type == 'ManglerBasis') {
            $chosenFaust = $_REQUEST['chosenFaust'];
            // TrimDR.php?seq0=50012&newfaust=5+196+445+4&show=Ret&oldfaust='5 196 391 1'
            $db->exe('START TRANSACTION');

            $insert = "insert into $tablename "
                . "   select nextval('$seqname'), 'PubhubDel', createdate, format, idnumber, title, originalxml, "
                . "   marc, faust, isbn13, sent_to_basis, null, sent_to_covers, "
                . "   'd', null, null, cover_status "
                . "   from $tablename "
                . "   where seqno = $seqno ";
            $db->exe(($insert));
            $update = "update $tablename "
                . "set faust = '$chosenFaust', sent_to_well = null, sent_to_covers = null, "
                . "sent_xml_to_well = null "
                . "where seqno = $seqno ";
            $db->exe($update);

            $update = "update runaftererrors "
                . "set newfaust = current_timestamp "
                . "where seqno = $seqno ";
            $db->exe($update);

            $db->exe('COMMIT');
        }
    }

    $select = "select seqno, to_char(createdate,'DD-MM-YYYY HH24:MI:SS') as create, "
        . "type, errtxt, newfaust  from runaftererrors  "
        . "where updated is not null and type = 'ManglerBasis' "
        . "order by createdate desc ";
    if ($_REQUEST['show'] == 'true') {
        if ($type == 'basisphus') {
            $showpost = true;
            $page->set('showpost', $showpost);
            $page->set('type', $type);
            $select = "select seqno, to_char(createdate,'DD-MM-YYYY HH24:MI:SS') as create, "
                . "type, errtxt, newfaust  from runaftererrors  "
                . "where seqno = $seqno ";
        }
    }

    $rows = $db->fetch($select);
    $missingBasis = array();
    foreach ($rows as $row) {
        $mis = array();
        $seqno = $row['seqno'];

        $get = "select seqno, isbn13, title, format, faust, status from digitalresources "
            . "where seqno = $seqno and provider = 'Pubhub' ";

        $drinfos = $db->fetch($get);
        $info = $drinfos[0];
        if ($row['newfaust']) {
            $info['updateClass'] = "updateClass";
        }
        $ufaust = str_replace(' ', '&nbsp;', $info['faust']);
        $info['faust'] = "<a href='http://dev.dbc.dk/seBasis?lokalid=" . $info['faust'] . "'>" . $ufaust . "</a>";
        $info['create'] = $row['create'];
        $info['title'] = mb_substr($info['title'], 0, 100);
        $info['fejltekst'] = $row['errtxt'];
        if ($showpost) {
            $info['fejltekst'] = "<input type='text' name='errtxt' size='50' value='" . $info['fejltekst'] . "' />";
        }
        $info['TargetSeqno'] = $info['seqno'];
        $info['seqno'] = "<a href=?show=true&type=basisphus&seqno=" . $info['seqno'] . ">" . $info['seqno'] . "</a>";

        $missingBasis[] = $info;
    }
    $page->set('missingBasis', $missingBasis);

    $seqno = $_REQUEST['seqno'];
    $page->set('seqno', $seqno);
    $select = "select seqno, to_char(createdate,'DD-MM-YYYY HH24:MI:SS') as create, "
        . "type, errtxt from runaftererrors  "
        . "where updated is not null and type = 'ManglerDR' "
        . "order by createdate desc ";

    if ($_REQUEST['show'] == 'true') {
        if ($type == 'dr') {
            $showpostDR = true;
            $page->set('showpostDR', $showpostDR);
            $page->set('type', $type);
            $select = "select seqno, to_char(createdate,'DD-MM-YYYY HH24:MI:SS') as create, "
                . "type, errtxt  from runaftererrors  "
                . "where seqno = $seqno ";
        }
    }

    $rows = $db->fetch($select);
    $missingDR = array();
    foreach ($rows as $row) {
        $mis = array();
        $seqno = $row['seqno'];
        $get = "select seqno, dbuser, title, type, faust, status  from basisphus "
            . "where seqno = $seqno  ";

        $drinfos = $db->fetch($get);
        $info = $drinfos[0];
        $info['create'] = $row['create'];
        $info['faustNumber'] = $info['faust'];
        $ufaust = str_replace(' ', '&nbsp;', $info['faust']);
        $info['faust'] = "<a  href='http://dev.dbc.dk/seBasis?lokalid=" . $info['faust'] . "'>" . $ufaust . "</a>";
        $info['fejltekst'] = $row['errtxt'];
        if ($showpostDR) {
            $info['fejltekst'] = "<input type='text' name='errtxt' size='30' value='" . $info['fejltekst'] . "' />";
        }
        $info['TargetSeqno'] = $info['seqno'];
        $info['seqno'] = "<a href=?show=true&type=dr&seqno=" . $info['seqno'] . ">" . $info['seqno'] . "</a>";
        $missingDR[] = $info;
    }
    $page->set('missingDR', $missingDR);

    $seqno = $_REQUEST['seqno'];
    $page->set('seqno', $seqno);
    $select = "select seqno, to_char(createdate,'DD-MM-YYYY HH24:MI:SS') as create, "
        . "type, errtxt  from runaftererrors  "
        . "where updated is not null and type = 'ForkertERA' "
        . "order by createdate desc ";
    if ($_REQUEST['show'] == 'true') {
        if ($type == 'ERA') {
            $showpostERA = true;
            $page->set('showpostERA', $showpostERA);
            $page->set('type', $type);
            $select = "select seqno, to_char(createdate,'DD-MM-YYYY HH24:MI:SS') as create, "
                . "type, errtxt  from runaftererrors  "
                . "where seqno = $seqno ";
        }
    }

    $rows = $db->fetch($select);
    $ERAerror = array();
    if ($rows) {
        foreach ($rows as $row) {
            $mis = array();
            $seqno = $row['seqno'];

            $get = "select seqno, isbn13, title, format, faust, status from digitalresources "
                . "where seqno = $seqno and provider = 'Pubhub' ";

            $drinfos = $db->fetch($get);
            foreach ($drinfos as $info) {
//        $info = $drinfos[0];
                $ufaust = str_replace(' ', '&nbsp;', $info['faust']);
                $info['faust'] = "<a href='http://dev.dbc.dk/seBasis?lokalid=" . $info['faust'] . "'>" . $ufaust . "</a>";
                $info['create'] = $row['create'];
                $info['title'] = mb_substr($info['title'], 0, 20);
                $info['fejltekst'] = $row['errtxt'];
                if ($showpostERA) {
                    $info['fejltekst'] = "<input type='text' name='errtxt' size='10' value='" . $info['fejltekst'] . "' />";
                }
                $info['seqno'] = "<a href=?show=true&type=ERA&seqno=" . $info['seqno'] . ">" . $info['seqno'] . "</a>";

                $ERAerror[] = $info;
            }
        }
    }
    if (!$ERAerror) {
        $ERAerror[0] = array();
    }
    $page->set('ERAerror', $ERAerror);
//    print_r($missingBasis);
} catch (Exception $err) {
    echo "The exception code is: " . $err->getCode() . "\n";
    echo $err . "\n";
    $err_txt = $err->getMessage();
    $getLine = "Line number:" . $err->getLine();
    $getTrace = $err->getTraceAsString();
//    mail($err_mail_to, "Exception utc-process.php", " ----------------------\n$err_txt\n---------------------\n
//      $getLine\n$getTrace\n");
    exit;
}
