<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

/*require_once "$inclnk/OLS_class_lib/curl_class.php";*/
require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
//require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$startdir/XmlDiff_class.php";
require_once "$classes/mediadb_class.php";

//$her = getcwd();
//
//echo "HER:$her\n";


/**
 * Class testmediadbclass
 */
class testregression extends PHPUnit_Framework_TestCase {


    private $db;
    public $mediadb;
    private $dirini;
    private $datadir;
    private $digi;


    private function copyTablesOut($postfix) {
        $this->db->exe("drop table IF EXISTS mediaservice$postfix");
        $this->db->exe("drop table IF EXISTS mediaebooks$postfix");
        $this->db->exe("drop table IF EXISTS mediaservicedref$postfix");
        $this->db->exe("drop table IF EXISTS mediaservicenote$postfix");
        $this->db->exe("drop table IF EXISTS mediaservicerecover$postfix");
//        $this->db->exe("drop table IF EXISTS mediaserviceuserinfo$postfix");
        $this->db->exe("create table mediaservice$postfix as (select * from mediaservice)");
        $this->db->exe("create table mediaebooks$postfix as (select * from mediaebooks)");
        $this->db->exe("create table mediaservicedref$postfix as (select * from mediaservicedref)");
        $this->db->exe("create table mediaservicenote$postfix as (select * from mediaservicenote)");
        $this->db->exe("create table mediaservicerecover$postfix as (select * from mediaservicerecover)");
//        $this->db->exe("create table mediaserviceuserinfo$postfix as (select * from mediaserviceuserinfo)");

    }

    private function copyTablesIn($postfix) {
        $this->db->exe("drop table IF EXISTS mediaservice");
        $this->db->exe("drop table IF EXISTS mediaebooks");
        $this->db->exe("drop table IF EXISTS mediaservicedref");
        $this->db->exe("drop table IF EXISTS mediaservicenote");
        $this->db->exe("drop table IF EXISTS mediaservicerecover");
//        $this->db->exe("drop table IF EXISTS mediaserviceuserinfo");
        $this->db->exe("create table mediaservice as (select * from mediaservice$postfix)");
        $this->db->exe("create table mediaebooks as (select * from mediaebooks$postfix)");
        $this->db->exe("create table mediaservicedref as (select * from mediaservicedref$postfix)");
        $this->db->exe("create table mediaservicenote as (select * from mediaservicenote$postfix)");
        $this->db->exe("create table mediaservicerecover as (select * from mediaservicerecover$postfix)");
//        $this->db->exe("create table mediaserviceuserinfo as (select * from mediaserviceuserinfo)");
    }

    private function deleteTables($postfix) {
        $this->db->exe("drop table IF EXISTS mediaservice$postfix");
        $this->db->exe("drop table IF EXISTS mediaebooks$postfix");
        $this->db->exe("drop table IF EXISTS mediaservicedref$postfix");
        $this->db->exe("drop table IF EXISTS mediaservicenote$postfix");
        $this->db->exe("drop table IF EXISTS mediaservicerecover$postfix");
    }

    protected function setUp() {

        $her = getcwd();
//        echo "her:$her\n";


        $startdir = dirname(__FILE__);

        $inifile = $startdir . "/dr.ini";
        $this->digi = $startdir . "/../DigitalResources";
        $this->dirini = $inifile;
        $this->datadir = $startdir . "/data";
//        echo "inifile:$inifile\n";
        $config = new inifile($inifile);
        $this->assertEquals("", $config->error, "No inifile found!");
        $connect_string = $config->get_value("connect", "testcase");

        $this->db = new pg_database($connect_string);
        $this->db->open();


//        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
    }


    public function test_getXmlFromPubHubMediaService() {
        chdir($this->digi);
        $drini = $this->dirini;
        $datadir = $this->datadir;

        $this->deleteTables('');

        /*
         *  test on a empty database (no tables) create the tables and  insert all the records in ms.1.xml
         */
        $cmd = "php getXmlFromPubHubMediaService.php -p $drini -f $datadir/ms.1.xml -t";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd]----fejler \n" . implode("\n", $output) . "\n");

        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
        $txt = $this->mediadb->getSta();
        $mustBe = "status:Pending|booktype:Ebog|filetype:epub|cnt:16|status:Pending|booktype:Ebog|filetype:pdf|cnt:6|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        $this->assertEquals($mustBe, $txt, "result #1 fra db is not valid");


        /**
         * ms.2.xml has more records than ms.1.xml.  The new records should be added.
         */
        $cmd = "php getXmlFromPubHubMediaService.php -p ../UnitTests/dr.ini -f ../UnitTests/data/ms.2.xml  -t";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");
        $txt = $this->mediadb->getSta();
        $mustBe = "status:Pending|booktype:Ebog|filetype:epub|cnt:19|status:Pending|booktype:Ebog|filetype:pdf|cnt:6|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        $this->assertEquals($mustBe, $txt, "result #2 fra db is not valid");


        /*
         * ms.3.xml has is missing 2 records compared with ms.2.xml.
         */
        $cmd = "php getXmlFromPubHubMediaService.php -p ../UnitTests/dr.ini -f ../UnitTests/data/ms.3.xml  -t";
        $output = array();
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");
        $txt = $this->mediadb->getSta();
        $mustBe = "status:NotActive|booktype:Lydbog|filetype:zip_mp3|cnt:2|status:Pending|booktype:Ebog|filetype:epub|cnt:19|status:Pending|booktype:Ebog|filetype:pdf|cnt:6|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:5|";
        $this->assertEquals($mustBe, $txt, "result #3 fra db is not valid");

        /*
         *  one record (seqno = 1) has one char diff from the same record in ms.1.xml.  The xml will be updated.
         */
        $res = $this->mediadb->getInfoData(1);
        $this->assertNotEquals($res[0]['ecreatedate'], $res[0]['eupdate'], "Update shall be different from createdate");

        /*
         *  there will be 2 records in mediaservicerecover, mediarecover for update has been removed.
         */
        $txt = $this->mediadb->getStaRecover();
        $mustBe = "program:getXmlFromPubHubMediaService.php|status:Pending|cnt:2|";
        $this->assertEquals($mustBe, $txt);
    }

    private function exeCmd($seqno) {
        $output = array();
        $cmd = "php MatchMedier.php -p " . $this->dirini . " -t -s $seqno";
        exec($cmd, $output, $return);
        echo "\nseqno:$seqno " . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");
        return $this->mediadb->getSta($seqno);
    }

    function test_MatchMedier() {
        chdir($this->digi);
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        /*
         * seqno 1.  Isbn found in digitalresources status will be matchDigitalR
         */
        $actual = $this->exeCmd(1);
        $expected = "status:matchDigitalR|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);

        /*
         * seqno 2. The publicationdate is more than 14 days ahead
         * Status -> Pending
         */
        $seqno = 2;
        $toMatch = array('Pending' => true, 'Hold' => true, 'eVa' => true);
        $rows = $this->mediadb->getMatchObjects($toMatch);
//        print_r($rows);
        $found = false;
        foreach ($rows as $row) {
            if ($row['seqno'] == $seqno) {
                $found = true;
            }
        }
        $this->assertEquals(false, $found, "seqno  should not be in rows[]");

        /*
         * seqno 3. The record is already in the database.
         * Status -> UpdataPublizon
         */
        $actual = $this->exeCmd(3);
        $expected = "status:UpdatePublizon|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);

        /*
           * seqno 4. A printed record is in the database.
           * Status -> ProgramMatch
           */
        $actual = $this->exeCmd(4);
        $expected = "status:ProgramMatch|booktype:Ebog|filetype:pdf|cnt:1|";
        $this->assertEquals($expected, $actual);

        /*
        * seqno 7. A records which must be handled by a libarien.
        * Status -> eVa
        */
        $actual = $this->exeCmd(7);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);


        /*
        * seqno 8-xxx. records used in eVa.
        * Status -> eVa -
        */
        $actual = $this->exeCmd(8);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(9);
        $expected = "status:eVa|booktype:Ebog|filetype:pdf|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(10);
        $expected = "status:eVa|booktype:Ebog|filetype:pdf|cnt:1|";
        $this->assertEquals($expected, $actual, "seqno 10");
        $actual = $this->exeCmd(11);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(13);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(14);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(15);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(16);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(17);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(18);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
    }

    // skal gøres aktivt igen
    function est_insertFaustMediaService() {
        $drini = $this->dirini;

        /*
         * Run insertFaustMediaService.php and check for the result.
         */
        chdir($this->digi);
        $this->copyTablesIn('c1');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        $cmd = "php insertFaustMediaService.php -p $drini -t";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        $res = $this->mediadb->getInfoData(13);
        $this->assertEquals('9 211 111 3', $res[0]['newfaust'], "Update shall be different from createdate");

        $txt = $this->mediadb->getStaRecover();
        $mustBe = "program:getXmlFromPubHubMediaService.php|status:Pending|cnt:2|program:insertFaustMediaService.php|status:ProgramMatch|cnt:1|program:MatchMedier.php|status:Pending|cnt:25|";
        $this->assertEquals($mustBe, $txt);

        $this->copyTablesOut('c2');
    }

    function est_ToBasisMediaService() {
        $dirini = $this->dirini;

        /*
         * Run test_ToBasisMediaService.php and check for the result.
         */
        chdir($this->digi);
        $this->copyTablesIn('c2');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        $cmd = "php ToBasisMediaService.php -p $dirini -s UpdateBasis";
        exec($cmd, $output, $return);
//        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        $this->copyTablesOut('c3');
    }

    function est_ToPromat() {
        $dirini = $this->dirini;

        /*
         * Run test_ToBasisMediaService.php and check for the result.
         */
        chdir($this->digi);
        $this->copyTablesIn('c3');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        $cmd = "php ToPromat.php -p $dirini -t -s UpdateBasis";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        $this->copyTablesOut('c4');
    }

    function est_ToPublizon() {
        $dirini = $this->dirini;

        /*
         * Run test_ToBasisMediaService.php and check for the result.
         */
        chdir($this->digi);
        $this->copyTablesIn('c4');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);

        $cmd = "php ToPublizon.php -p $dirini -t ";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        $this->copyTablesOut('c5');
    }
}
