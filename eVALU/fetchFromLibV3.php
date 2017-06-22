<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";

require_once "$inclnk/OLS_class_lib/view.php";

$base = $_REQUEST['base'];
$lokalid = $_REQUEST['lokalid'];
$bibliotek = $_REQUEST['bibliotek'];
$matchtype = $_REQUEST['matchtype'];
$chosen = $_REQUEST['chosen'];
$seqno = $_REQUEST['seqno'];

$fname = "data/" . str_replace(' ', '', $lokalid) . ".$bibliotek";

if (file_exists($fname)) {
    $strng = file_get_contents($fname);
} else {
//    require_once "$inclnk/OLS_class_lib/inifile_class.php";
    require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
    require_once "$inclnk/OLS_class_lib/marc_class.php";
    $libv3 = new LibV3API('sebasis', 'sebasis', 'dora11.dbc.dk');
    $libv3->set('hsb');
    $libv3->set('withAuthor');
    $j = 0;
    $strng = '';
    $marc = new marc();
    $records = $libv3->getMarcByLokalidBibliotek($lokalid, $bibliotek);
    foreach ($records as $record) {
        $rec = $record['DATA'];

        $marc->fromIso($rec);
        if ($j > 0) {
            $strng .= "---------------------\n";
        }
        $strng .= utf8_encode($marc->toLineFormat(70));

        $j++;
    }

//    $iso = $arr[0]['DATA'];

//    $marc->fromIso($iso);
//    $strng = utf8_encode($marc->toLineFormat());

}
$page = new view('html/fetchFromLibV3.phtml');

$page->set('Title', "$base:$lokalid/$bibliotek");
$page->set('base', $base);
$page->set('marc', $strng);
$page->set('lokalid', $lokalid);
$page->set('bibliotek', $bibliotek);
$page->set('matchtype', $matchtype);
$page->set('chosen', $chosen);
$page->set('seqno', $seqno);
//$page->set('link', $link);
