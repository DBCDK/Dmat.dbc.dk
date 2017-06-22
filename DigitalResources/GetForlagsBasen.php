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
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$inclnk/OPAC_class_lib/GetMarcFromBasis_class.php";

$inifile = "../DigitalResources.ini";
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}

if (($Bociuser = $config->get_value('ociuser', 'setup')) == false) {
    usage("no ociuser (seBasis) stated in the configuration file");
}
if (($Bocipasswd = $config->get_value('ocipasswd', 'setup')) == false) {
    usage("no ocipasswd (seBasis) stated in the configuration file");
}
if (($Bocidatabase = $config->get_value('ocidatabase', 'setup')) == false) {
    usage("no ocidatabase (seBasis) stated in the configuration file");
}
$Bociuser = 'seforlag';
$Bocipasswd = 'seforlag';

$noDublets = true;
$hsb = true;
$libV3API = new LibV3API($Bociuser, $Bocipasswd, $Bocidatabase);
$libV3API->set('withAuthor', true);
$getM = new GetMarcFromBasis('work', $Bociuser, $Bocipasswd, $Bocidatabase);
$connect_string = $config->get_value("connect", 'setup');

$marc = new marc();
$marcCand = new marc();

$db = new pg_database($connect_string);
$db->open();

$retro = 'forlag';
$drop = "drop table if exists $retro";
$db->exe($drop);

$create = "create table $retro (
             idnr varchar(20),
             forlagisbn int)
            ";
$db->exe($create);

$ts = '1900-01-01 00:00:00';
$rows = $libV3API->getUpdatedSince($ts);

foreach ($rows as $row) {
    $lokalid = $row['LOKALID'];
    $bibliotek = $row['BIBLIOTEK'];
    $recs = $libV3API->getMarcByLokalidBibliotek($lokalid, $bibliotek);
    $marc->fromIso($recs[0]['DATA']);
    $strng = $marc->toLineFormat();
    $ids = $marc->findSubFields('001', 'a');
    $id = $ids[0];
    $isbns = $marc->findSubFields('021', 'a');
    foreach ($isbns as $isbn) {
        $insert = "insert into $retro 
            values ('$id','$isbn')";
        $db->exe($insert);
    }
}
