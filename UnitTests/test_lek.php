<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 26-11-2015
 * Time: 10:29
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
require_once "$classes/ssh_class.php";
//require_once "$classes/getFaust_class.php";
require_once "$mockupclasses/getFaust_class.php";
require_once "$mockupclasses/mockUpLibV3_class.php";


class test_lekclass extends PHPUnit_Framework_TestCase {

    private $db;
    private $ssh;
    private $faustcmd;
    private $libV3API;
    private $nothing;
    private $basedir;

    public function construct() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $config = new inifile($inifile);

        $connect_string = $config->get_value("connect", "testcase");
        $this->faustcmd = $config->get_value('getFaust_cmd', 'setup');
        $this->basedir = $config->get_value('basedir', 'setup');

        $this->db = new pg_database($connect_string);
        $this->db->open();
        $this->nothing = false;
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $this->nothing);

//        $this->ssh = new ssh($config, 'lekToDios', $nothing = false);
    }


    function test_Error() {
        $this->construct();
//        $marc = new marc();

        $LektorBasen = new mockUpLibV3API($this->basedir, 'lek-data');
        $Basis = new mockUpLibV3API($this->basedir, 'lek-data');
//        $mediadb = new mediadb($this->db, basename(__FILE__), $nothing);
        $this->mediadb->copyTablesIn('_ToPromat');

        $year = date('Y');
        $calendar = new Calendar_class($this->db, $year);
        $gfaust = new getFaust($this->faustcmd);

        $lek = new lek_class($this->mediadb, $Basis, $LektorBasen, $calendar, $gfaust, $this->nothing);

        $lek->findUpdates();
        $upd = $lek->getUpdates();
//        print_r($upd);
        $this->assertEquals(6, count($upd), "Expected 6 candidates!");
//        $this->assertTrue(array_key_exists(8, $upd), "Unknown seqno!");
        $this->assertTrue(array_key_exists(15, $upd), "Unknown seqno!");
        $this->assertTrue(array_key_exists(16, $upd), "Unknown seqno!");
        $this->assertTrue(array_key_exists(17, $upd), "Unknown seqno!");
        $this->assertTrue(array_key_exists(18, $upd), "Unknown seqno!");
//        $this->assertTrue(array_key_exists(19, $upd), "Unknown seqno!");
        $this->assertTrue(array_key_exists(21, $upd), "Unknown seqno!");
//        $this->assertTrue(array_key_exists(22, $upd), "Unknown seqno!");
        $this->assertTrue(array_key_exists(23, $upd), "Unknown seqno! 23");

    }

    function test_notReady() {
        $this->construct();

//        $nothing = false;
        $LektorBasen = new mockUpLibV3API($this->basedir, 'lek-data');
        $Basis = new mockUpLibV3API($this->basedir, 'lek-data');
//        $mediadb = new mediadb($this->db, basename(__FILE__), $nothing);
        $this->mediadb->copyTablesIn('_ToPromat');

        $year = date('Y');
        $calendar = new Calendar_class($this->db, $year);
        $gfaust = new getFaust($this->faustcmd);

        $lek = new lek_class($this->mediadb, $Basis, $LektorBasen, $calendar, $gfaust, $this->nothing);
        $lek->findUpdates();
        $upd = $lek->getUpdates();

        // Fromfaust:printedfaust  den post som har lektørudtalelsen
        // Tofaust:newfaust  den post der skal have lektørudtalelsen

        // fromfaust er endnu ikke i basis
        $newupd = array();
        $seqno = 17;
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['printedfaust'] = '92111';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(0, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $this->assertNull($info[0]['updatelekbase']);

        // fromfaust er en delete post
        $newupd = array();
        $seqno = 17;
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['printedfaust'] = '52455162';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(0, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $this->assertNull($info[0]['updatelekbase']);

        // fromfaust er en "NY TITEL
        $newupd = array();
        $seqno = 17;
        $newupd[$seqno] = $upd[$seqno];
//        $newupd[$seqno]['newfaust'] = '92111261-04';
        $newupd[$seqno]['printedfaust'] = '92111237';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(0, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $this->assertNull($info[0]['updatelekbase']);

        // fromfaust er ikke en "NY TITEL". Har ikke noget 'L'
        // Får derfor status Done
        $newupd = array();
        $seqno = 17;
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['printedfaust'] = '92111261-01';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(0, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $this->assertEquals('Done2', $info[0]['updatelekbase']);

        // hent basen ind igen.
        $this->mediadb->copyTablesIn('_ToPromat');

        // fromfaust er ikke en "NY TITEL", Har  'L', Lektør post ikke i 870976
        $newupd = array();
        $seqno = 17;
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['printedfaust'] = '92111261-02';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(0, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $this->assertNull($info[0]['updatelekbase']);


        // fromfaust er ikke en "NY TITEL", Har  'L', Lektør post er i 870976
        // tofaust er ikke i Basis
        $newupd = array();
        $seqno = 17;
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['printedfaust'] = '92111261-03';
        $newupd[$seqno]['newfaust'] = '12312312';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(0, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $this->assertNull($info[0]['updatelekbase']);


        // fromfaust er ikke en "NY TITEL", Har  'L', Lektør post er i 870976
        // der er 2 lektørposter.  Den uden 700 *f skole vælges. (bliver ikke valgt da newfaust ikke eksisterer
        $newupd = array();
        $seqno = 17;
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['printedfaust'] = '92111261-05';
        $newupd[$seqno]['newfaust'] = '12312312';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(0, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $this->assertNull($info[0]['updatelekbase']);


    }

    function test_oneOK() {
        $this->construct();

        $this->libV3API = new mockUpLibV3API($this->basedir, 'lek-data');
        $this->construct();
        $marc = new marc();

        $LektorBasen = new mockUpLibV3API($this->basedir, 'lek-data');
        $Basis = new mockUpLibV3API($this->basedir, 'lek-data');
        $this->mediadb->copyTablesIn('_ToPromat');
        $year = date('Y');
        $calendar = new Calendar_class($this->db, $year);
        $gfaust = new getFaust($this->faustcmd);

        $lek = new lek_class($this->mediadb, $Basis, $LektorBasen, $calendar, $gfaust, $this->nothing);
        $lek->findUpdates();
        $upd = $lek->getUpdates();


        #test case #2,
        $seqno = 18;
        $newupd = array();
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['newfaust'] = '92111229-01';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(1, count($marclns));
        $marc->fromIso($marclns[0]);
//        $this->assertEquals(1, count($marclns));
        $info = $this->mediadb->getInfoData($seqno);
        $res = $this->compare($marc, $info[0]['lekfaust']);
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        $updBasis = $lek->getBasisRecs();
        $this->assertEquals('1', count($updBasis));

        $this->assertEquals('Done', $info[0]['updatelekbase']);

        # testcase #3,
        $seqno = 17;
        $newupd = array();
        $newupd[$seqno] = $upd[$seqno];
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals(1, count($marclns));
        $marc->fromIso($marclns[0]);
        $info = $this->mediadb->getInfoData($seqno);
        $res = $this->compare($marc, $info[0]['lekfaust']);
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        $updBasis = $lek->getBasisRecs();
        $marc->fromIso($updBasis[0]);
        $res = $this->compare($marc, $newupd[$seqno]['newfaust']);
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        $this->assertEquals('Done', $info[0]['updatelekbase']);
        $this->mediadb->insertIntoMarcTable($newupd[$seqno]['newfaust'], '870970', $marc->toLineFormat());

        # testcase #4,
        $seqno = 16;
        $newupd = array();
        $newupd[$seqno] = $upd[$seqno];
        $newupd[$seqno]['newfaustprinted'] .= '-01';
        $lek->setUpdates($newupd);
        $marclns = $lek->processUpdates();
        $this->assertEquals('1', count($marclns));
        $marc->fromIso($marclns[0]);
        $info = $this->mediadb->getInfoData($seqno);
        $res = $this->compare($marc, $info[0]['lekfaust']);
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        $updBasis = $lek->getBasisRecs();
        $marc->fromIso($updBasis[0]);
        $res = $this->compare($marc, $newupd[$seqno]['newfaust']);
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        $this->assertEquals('Done', $info[0]['updatelekbase']);
        $this->mediadb->insertIntoMarcTable($newupd[$seqno]['newfaust'], '870970', $marc->toLineFormat());


    }

//    function test_retroDR() {
//        $this->libV3API = new mockUpLibV3API();
//        $this->construct();
//        $marc = new marc();
//
//        $LektorBasen = new mockUpLibV3API('data');
//        $Basis = new mockUpLibV3API('data');
//        $this->mediadb->copyTablesIn('_ToPromat');
//        $year = date('Y');
//        $calendar = new Calendar_class($this->db, $year);
//        $gfaust = new getFaust($this->faustcmd);
//
//        $lek = new lek_class($this->mediadb, $Basis, $LektorBasen, $calendar, $gfaust, $this->nothing);
//        $lek->findUpdates();
//        $upd = $lek->getUpdates();
//
//        # testcase #1
//        $seqno = 22;
//        $newupd[$seqno] = $upd[$seqn];
//
//    }

    function compare(marc $marc, $faust) {
//        $lokalid = $marc->findSubFields('001', 'a');
//        $this->mediadb->insertIntoMarcTable($lokalid[0], 'publizon', $marc->toLineFormat());

        $title = 'UnKnown';
        $titles = $marc->findSubFields('245', 'a');
        if ($titles) {
            $title = $titles[0];
        }
        echo "title:$title\n";
        $marc->remSubfieldText('001', 'c', '2');
        $marc->remSubfieldText('001', 'd', '2');
        $marc->remField('530');
        while ($marc->thisField('032')) {
            while ($marc->thisSubfield('x')) {
                $txt = substr($marc->subfield(), 0, 3) . '201601';
                $marc->updateSubfield($txt);
            }
            while ($marc->thisSubfield('a')) {
                $txt = substr($marc->subfield(), 0, 3) . '201601';
                $marc->updateSubfield($txt);
            }
        }
        $ret['actual'] = $marc->toLineFormat();

        $arr = $this->libV3API->getMarcByLokalidBibliotek($faust, '870976');
        if ( !$arr) {
            $arr = $this->libV3API->getMarcByLokalidBibliotek('empty', '870976');
        }
        $iso = $arr[0]['DATA'];
        $basis = new marc();
        $basis->fromIso($iso);
        $lokalid = $basis->findSubFields('001', 'a');
        $this->mediadb->insertIntoMarcTable($lokalid[0], '870976', $basis->toLineFormat());
        $basis->remSubfieldText('001', 'c', '2');
        $basis->remSubfieldText('001', 'd', '2');
        $basis->remField('530');
        while ($basis->thisField('032')) {
            while ($basis->thisSubfield('x')) {
                $txt = substr($basis->subfield(), 0, 3) . '201601';
                $basis->updateSubfield($txt);
            }
            while ($basis->thisSubfield('a')) {
                $txt = substr($basis->subfield(), 0, 3) . '201601';
                $basis->updateSubfield($txt);
            }
        }
        $ret['expected'] = $basis->toLineFormat();

        $ret['cmptxt'] = "Faust:$faust, title:$title";
        return $ret;
    }

}