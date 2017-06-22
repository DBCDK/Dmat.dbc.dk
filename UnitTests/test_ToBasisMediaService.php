<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
$mockupclasses = $startdir . "/../UnitTests/classes";

/*require_once "$inclnk/OLS_class_lib/curl_class.php";*/
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
//require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$startdir/XmlDiff_class.php";
require_once "$classes/mediadb_class.php";
require_once "$mockupclasses/mockUpLibV3_class.php";


/**
 * Class testToBasisMediaService
 */
class testToBasisMediaService extends PHPUnit_Framework_TestCase {


    private $db;
    public $mediadb;
    private $dirini;
    private $digi;
    private $basedir;

    protected function setUp() {

        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $this->digi = $startdir . "/../DigitalResources";
        $this->dirini = $inifile;
        $config = new inifile($this->dirini);
        $this->assertEquals("", $config->error, "No inifile found! " . $this->dirini);
        $connect_string = $config->get_value("connect", "testcase");
        $this->basedir = $config->get_value('basedir', 'setup');
        $this->db = new pg_database($connect_string);
        $this->db->open();


    }

    function test_ToBasisProgMatch() {
        $drini = $this->dirini;
        $this->libV3API = new mockUpLibV3API($this->basedir, 'data');
        $marc = new marc();

        chdir($this->digi);
        $mediadb = new mediadb ($this->db, 'test_ToBasisProgMatch', false);
        $mediadb->copyTablesIn('_insF');
//        $this->copyTablesIn('_insF');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        $cmd = "php ToBasisMediaService.php -p $drini -t -s ProgramMatch ";
        exec($cmd, $output, $return);

//        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        foreach ($output as $ln) {
            if (substr($ln, 0, 9) == 'DATAFILE:') {
                $datafile = substr($ln, 9);
                break;
            }
        }
        #4
        echo "DATAFILE:$datafile\n";
        $marc->openMarcFile($datafile);
        $marc->readNextMarc();
        $res = $this->compare($marc, '9 211 111 3');
//        Er blevet udkommenteret da der ikke kommer nogen til ProgramMatch i øjeblikket
//        De bliver "omdiregret" til eVa i stedet. Senere til eLu
        //$this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

    }

    function test_ToBasisTemplate() {
        $drini = $this->dirini;
        $this->libV3API = new mockUpLibV3API($this->basedir, 'data');
        $marc = new marc();

        chdir($this->digi);
//        $this->copyTablesIn('_insF');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        $cmd = "php ToBasisMediaService.php -p $drini -t -s Template ";
        exec($cmd, $output, $return);

//        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        foreach ($output as $ln) {
            if (substr($ln, 0, 9) == 'DATAFILE:') {
                $datafile = substr($ln, 9);
                break;
            }
        }
        #14
        $marc->openMarcFile($datafile);
        $marc->readNextMarc();
        $res = $this->compare($marc, '9 211 116 4');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #26
        $marc->readNextMarc();
        $dbrs = $marc->findSubFields('032', 'a');
        $founddbr = false;
        foreach ($dbrs as $dbr) {
            if (substr($dbr, 0, 3) == 'DBR') {
                $founddbr = true;
                $this->assertNotEquals('DBR999999', $dbr, "Forkert DBR kode: [$dbr] (seqno 26)");
            }
        }
        $this->assertTrue($founddbr, "ikke noget DBR i 032 *a");
        $res = $this->compare($marc, '9 211 133 4');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

    }

    function test_UpdateBasis() {
        $drini = $this->dirini;
        $this->libV3API = new mockUpLibV3API($this->basedir, 'data');
        $marc = new marc();

        chdir($this->digi);
//        $this->copyTablesIn('_insF');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        $cmd = "php ToBasisMediaService.php -p $drini -t -s UpdateBasis ";
        echo "$cmd \n";
        exec($cmd, $output, $return);

//        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");
        foreach ($output as $ln) {
            if (substr($ln, 0, 9) == 'DATAFILE:') {
                $datafile = substr($ln, 9);
                break;
            }
        }
        $marc = new marc();
        $marcrecs = array();
        $marc->openMarcFile($datafile);
        while ($marc->readNextMarc()) {
            $ids = $marc->findSubFields('001', 'a');
            $marcrecs[$ids[0]] = $marc->toIso();
        }

        #18
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 122 9']);
        $res = $this->compare($marc, '9 211 122 9-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        #18 printed
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 123 7']);
        $res = $this->compare($marc, '9 211 123 7-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);


        #7
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 112 1']);
        $res = $this->compare($marc, '9 211 112 1-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #8
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 114 8']);
        $res = $this->compare($marc, '9 211 114 8');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #15
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 117 2']);
        $res = $this->compare($marc, '9 211 117 2');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        # make a printed version exp. to come soon.
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 118 0']);
        $res = $this->compare($marc, '9 211 118 0-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #17
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 121 0']);
        $res = $this->compare($marc, '9 211 121 0');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #22
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 126 1']);
        $res = $this->compare($marc, '9 211 126 1');
//        echo "\n" . $res['expected'] . "\n";
//        echo "\n" . $res['actual'] . "\n";
//        exit;
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #9
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 115 6']);
        $res = $this->compare($marc, '9 211 115 6-1');
//        $arr = explode("\n", $res['actual']);
//        foreach ($arr as $ln) {
//            echo "[$ln]\n";
//        }
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #16
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 119 9']);
        $res = $this->compare($marc, '9 211 119 9');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        # make a printed version exp. to come soon.
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 120 2']);
        $res = $this->compare($marc, '9 211 120 2-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #21
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 125 3']);
        $res = $this->compare($marc, '9 211 125 3-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        # make a printed version exp. to come soon.
//        $marc->readNextMarc();
//        $res = $this->compare($marc, '2 ');
//        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #19
//        $marc->readNextMarc();
        $marc->fromIso($marcrecs['9 211 124 5']);
        $res = $this->compare($marc, '9 211 124 5');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
//        # make a printed version exp. to come soon.
//        $marc->readNextMarc();
//        $res = $this->compare($marc, '9 211 120 2');
//        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #23
        $marc->fromIso($marcrecs['9 211 128 8']);
        $res = $this->compare($marc, '9 211 128 8-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);
        // printed
        $marc->fromIso($marcrecs['9 211 129 6']);
        $res = $this->compare($marc, '9 211 129 6-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #24
        $marc->fromIso($marcrecs['9 211 131 8']);
        $strng = $marc->toLineFormat(78);
        $dbrs = $marc->findSubFields('032', 'a');
        $founddbr = false;
        foreach ($dbrs as $dbr) {
            if (substr($dbr, 0, 3) == 'DBR') {
                $founddbr = true;
                $this->assertEquals('DBR999999', $dbr, "Forkert DBR kode  $dbr (seqno 24)");
            }
        }
        $this->assertTrue($founddbr, "ikke noget DBR i 032 *a");
        $res = $this->compare($marc, '9 211 131 8');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);

        #25
        $marc->fromIso($marcrecs['9 211 132 6']);
        $dbrs = $marc->findSubFields('032', 'a');
        $founddbr = false;
        foreach ($dbrs as $dbr) {
            if (substr($dbr, 0, 3) == 'DBR') {
                $founddbr = true;
                $this->assertEquals('DBR999999', $dbr, "Forkert DBR kode  $dbr (seqno 25)");
            }
        }
        $res = $this->compare($marc, '9 211 132 6-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);


        #27
        $marc->fromIso($marcrecs['9 211 134 2']);
        $dbrs = $marc->findSubFields('032', 'a');
        $founddbr = false;
        foreach ($dbrs as $dbr) {
            if (substr($dbr, 0, 3) == 'DBR') {
                $founddbr = true;
                $this->assertEquals('DBR20', substr($dbr, 0, 5), "Forkert DBR kode  $dbr (seqno 27)");
            }
        }
        $res = $this->compare($marc, '9 211 134 2-1');
        $this->assertEquals($res['expected'], $res['actual'], $res['cmptxt']);


        $res = $this->mediadb->getInfoData(8);
        $this->assertEquals('9 211 114 8', $res[0]['newfaust'], "Update shall be different from createdate");


        $mediadb = new mediadb ($this->db, 'test_ToBasisProgMatch', false);
        $mediadb->copyTablesOut('_To_basis');
    }

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

        $arr = $this->libV3API->getMarcByLokalidBibliotek($faust, '870970');
        $iso = $arr[0]['DATA'];
        $basis = new marc();
        $basis->fromIso($iso);
        $lokalid = $basis->findSubFields('001', 'a');
        $this->mediadb->insertIntoMarcTable($lokalid[0], '870970', $basis->toLineFormat());
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
