<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 24-11-2016
 * Time: 10:20
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
//require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
//require_once "$inclnk/OPAC_class_lib/GetMarcFromBasis_class.php";
require_once "$classes/mediadb_class.php";

//$inifile = "../DigitalResources.ini";
$inifile = "DigitalDrift.ini";
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}

//if (($Bociuser = $config->get_value('ociuser', 'setup')) == false) {
//    usage("no ociuser (seBasis) stated in the configuration file");
//}
//if (($Bocipasswd = $config->get_value('ocipasswd', 'setup')) == false) {
//    usage("no ocipasswd (seBasis) stated in the configuration file");
//}
//if (($Bocidatabase = $config->get_value('ocidatabase', 'setup')) == false) {
//    usage("no ocidatabase (seBasis) stated in the configuration file");
//}
//$noDublets = true;
//$hsb = true;
//$libV3API = new LibV3API($Bociuser, $Bocipasswd, $Bocidatabase);
//$libV3API->set('withAuthor', true);
//$getM = new GetMarcFromBasis('work', $Bociuser, $Bocipasswd, $Bocidatabase);
$connect_string = $config->get_value("connect", 'setup');

//$marc = new marc();
//$marcCand = new marc();

$db = new pg_database($connect_string);
$db->open();

//$mediadb = new mediadb($db, 'RetroLek2.php');

$retro = 'retroresult';
//$drop = "drop table if exists $retro";
//$db->exe($drop);

//$create = "create table $retro (
//             type varchar(20),
//             seqno int,
//             dmatstat varchar(20),
//             title varchar(35),
//             efaust varchar(15),
//             cfaust varchar(15),
//             fulltxt varchar(100),
//             comparetxt varchar(50)
//             )
//            ";
//$db->exe($create);
$notSave = true;
$dublets = true;
$save = false;

// find alle poster der har mere end 1 kandidat og er "notSave", skal altså laves til "RETRO_eLu"
if ($notSave) {
    $sql = "select * from retroresult
          where efaust  not in
            (select efaust from retroresult
              where type = 'Save'
              and dmatstat != 'RETRO_eLu'
              group by efaust
              having count(*) > 1)
          and type = 'notSave'";

    $rows = $db->fetch($sql);

    foreach ($rows as $row) {
        $seqno = $row['seqno'];
        $cfaust = $row['cfaust'];
        $efaust = $row['efaust'];
        $dmatsta = $row['dmatstat'];
        updateRef($db, $seqno, $cfaust);
        updateSer($db, $seqno, $efaust, $dmatsta, 'RETRO_eLu');
    }
}


if ($save) {
    $scnt = 0;
    $sql = "select * from retroresult
          where efaust in
            (select  efaust from retroresult
              where type = 'Save'
              and dmatstat != 'RETRO_eLu'
              group by efaust
              having count(*) = 1)
          and type = 'Save'";
    $rows = $db->fetch($sql);
    foreach ($rows as $row) {
        $scnt++;
        if ($scnt > 2000) {
            break;
        }
        $seqno = $row['seqno'];
        echo "$scnt, $seqno\n";
        $cfaust = $row['cfaust'];
        $efaust = $row['efaust'];
        $dmatsta = $row['dmatstat'];
        updateRef($db, $seqno, $cfaust);
//        updateSer($db, $seqno, $efaust, $dmatsta, $dmatsta, $cfaust);
        updateSer($db, $seqno, $efaust, $dmatsta, $dmatsta, $cfaust);
    }
}

if ($dublets) {
    $sql = "select * from retroresult
          where efaust in
            (select  efaust from retroresult
              where type = 'Save'
              and dmatstat != 'RETRO_eLu'
              group by efaust
              having count(*) > 1)
          and type = 'Save'";

    $rows = $db->fetch($sql);

    foreach ($rows as $row) {
        $seqno = $row['seqno'];
        $cfaust = $row['cfaust'];
        $efaust = $row['efaust'];
        $dmatsta = $row['dmatstat'];
        updateRef($db, $seqno, $cfaust);
        updateSer($db, $seqno, $efaust, $dmatsta, 'RETRO_eLu');
    }
}


function updateRef(pg_database $db, $seqno, $faust) {
    $select = "select  * from mediaservicedref
            where seqno = $seqno 
            and lokalid = '$faust'";
    $rows = $db->fetch($select);
    if ($rows) {
        $sql = "update mediaservicedref 
                set lektoer = TRUE 
                where seqno = $seqno 
            and lokalid = '$faust'";
    } else {
        $sql = "insert into mediaservicedref 
              values ($seqno,current_timestamp,'Basis','$faust','870970','title',500,true)";
    }
    $db->exe($sql);
}

function updateSer(pg_database $db, $seqno, $efaust, $dmatsta, $status, $cfaust = '') {
//    if ($dmatsta == 'OldEva' or $dmatsta == 'DigitalR' ) {
    $newfaust = ", newfaust = '$efaust' ";
    $printedfaust = '';
//    } else {
//        $newfaust = '';
//    }
    if ($status == 'Done' or $status == 'DigitalR' or $status == 'OldTemplate' or $status == 'OldEva') {
        $printedfaust = ", printedfaust = '$cfaust' ";
    }
    $sql = "update mediaservice 
            set status = '$status'
            $newfaust
            $printedfaust
            where seqno = $seqno
            ";
    $db->exe($sql);
    echo $sql;
}

function updateSer2(pg_database $db, $seqno, $efaust, $dmatsta, $status, $cfaust = '') {
//    if ($dmatsta == 'OldEva' or $dmatsta == 'DigitalR' ) {
    $newfaust = ", newfaust = '$efaust' ";
    $printedfaust = '';
//    } else {
//        $newfaust = '';
//    }
    if ($status == 'Done' or $status == 'DigitalR' or $status == 'OldTemplate' or $status == 'OldEva') {
        $printedfaust = ", printedfaust = '$cfaust' ";
    }
    $sql = "update mediaservice 
            set 
            $newfaust
            $printedfaust
            where seqno = $seqno";
    $db->exe($sql);
}

