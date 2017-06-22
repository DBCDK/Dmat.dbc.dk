<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";


$inifile = '../DigitalResources.ini';
//$inifile = 'DigitalDrift.ini';

$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}

$connect_string = $config->get_value("connect", 'setup');

$db = new pg_database($connect_string);
$db->open();


$sql = "alter table mediaservice "
//    . "add column newfaustprinted character varying(11), "
//    . "add column printedfaust character varying(11), "
//    . "add column expisbn character varying(13), "
//    . "add column updatelekbase character varying(20), "
//    . "add column lekfaust character varying(11) ";
    . "add column printedpublished character varying(10), "
    . "add column bkxwc character varying(10) ";
$db->exe($sql);

$sql = "alter table mediaservice
alter updatelekbase type varchar(20);";
$db->exe($sql);

$sql = "alter table mediaservicerecover
alter updatelekbase type varchar(20);";
$db->exe($sql);

$sql = "alter table mediaservicedref "
    . "add column lektoer boolean ";
//$db->exe($sql);

$sql = "alter table mediaservicerecover "
//    . "add column newfaustprinted character varying(11), "
//    . "add column printedfaust character varying(11), "
//    . "add column expisbn character varying(13), "
//    . "add column updatelekbase character varying(20), "
//    . "add column lekfaust character varying(11) ";
    . "add column printedpublished character varying(10), "
    . "add column bkxwc character varying(10) ";
$db->exe($sql);

// fjern fejlagtige rækker i recovertabellen

$sql = "select seqno, recoverseqno  from mediaservicerecover "
    . "where status = 'NotActive' order by seqno, recoverseqno ";
$rows = $db->fetch($sql);
$oldseqno = -1;
foreach ($rows as $row) {
    $seqno = $row['seqno'];
    $recoverseqno = $row['recoverseqno'];
    if ($seqno != $oldseqno) {
        $del = "delete from mediaservicerecover "
            . "where seqno = $seqno and recoverseqno > $recoverseqno ";
//        $db->exe($del);
        echo "$seqno,$oldseqno,$del \n";
    }
    $oldseqno = $seqno;
}

// lav eLu om til Pending da de ikke har alle data som de skal
$sql = "update mediaservice set status = 'Pending' where status = 'eLu'";
//$db->exe($sql);

$sql = "update mediaservice set status = 'Done' where status = 'UpdatePublizon' and updatepub is not null";
$db->exe($sql);

