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


/**
 * Class testMatchMedier
 */
class testMatchMedier extends PHPUnit_Framework_TestCase {


    private $db;
    public $mediadb;
    private $dirini;
    private $datadir;
    private $digi;

    protected function setUp() {

        $her = getcwd();
        //        echo "her:$her\n";


        $startdir = dirname(__FILE__);

        $inifile = $startdir . "/../DigitalResources.ini";
        $this->digi = $startdir . "/../DigitalResources";
        $this->dirini = $inifile;
        $this->datadir = $startdir . "/data";
        echo "inifile:$inifile\n";
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

        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
        //        $this->deleteTables('');
        $this->mediadb->deleteTables('');

        /*
         *  test on a empty database (no tables) create the tables and  insert all the records in ms.1.xml
         */
        $cmd = "php getXmlFromPubHubMediaService.php  -f $datadir/ms.1.xml -t";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd]----fejler \n" . implode("\n", $output) . "\n");


        $txt = $this->mediadb->getSta();
        $mustBe = "status:Pending|booktype:Ebog|filetype:epub|cnt:16|status:Pending|booktype:Ebog|filetype:pdf|cnt:6|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        $mustBe = "status:Pending|booktype:Ebog|filetype:epub|cnt:17|status:Pending|booktype:Ebog|filetype:pdf|cnt:5|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        $mustBe = "status:Pending|booktype:Ebog|filetype:epub|cnt:13|status:Pending|booktype:Ebog|filetype:pdf|cnt:4|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:4|";
        $this->assertEquals($mustBe, $txt, "result #1 fra db is not valid");


        /**
         * ms.2.xml has more records than ms.1.xml.  The new records should be added.
         * One, # 19 is NotActive
         */
        $cmd = "php getXmlFromPubHubMediaService.php  -f ../UnitTests/data/ms.2.xml  -t";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");
        $txt = $this->mediadb->getSta();
        $mustBe = "status:Pending|booktype:Ebog|filetype:epub|cnt:19|status:Pending|booktype:Ebog|filetype:pdf|cnt:6|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        $mustBe = "status:NotActive|booktype:Ebog|filetype:epub|cnt:1|status:Pending|booktype:Ebog|filetype:epub|cnt:18|status:Pending|booktype:Ebog|filetype:pdf|cnt:6|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        //        $mustBe = "status:NotActive|booktype:Ebog|filetype:epub|cnt:1|status:NotActive|booktype:Ebog|filetype:pdf|cnt:1|status:Pending|booktype:Ebog|filetype:epub|cnt:18|status:Pending|booktype:Ebog|filetype:pdf|cnt:5|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        $mustBe = "status:NotActive|booktype:Ebog|filetype:epub|cnt:1|status:Pending|booktype:Ebog|filetype:epub|cnt:19|status:Pending|booktype:Ebog|filetype:pdf|cnt:5|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:7|";
        $mustBe = "status:NotActive|booktype:Ebog|filetype:epub|cnt:1|status:Pending|booktype:Ebog|filetype:epub|cnt:12|status:Pending|booktype:Ebog|filetype:pdf|cnt:4|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:4|";
        $this->assertEquals($mustBe, $txt, "result #2 fra db is not valid");


        /*
         * ms.3.xml has is missing 2 records compared with ms.2.xml.
         * #19 is set to Active again
         */
        $cmd = "php getXmlFromPubHubMediaService.php  -f ../UnitTests/data/ms.3.xml  -t";
        $output = array();
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");
        $txt = $this->mediadb->getSta();
        //        $mustBe = "status:NotActive|booktype:Lydbog|filetype:zip_mp3|cnt:2|status:Pending|booktype:Ebog|filetype:epub|cnt:19|status:Pending|booktype:Ebog|filetype:pdf|cnt:6|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:5|";
        //        $mustBe = "status:NotActive|booktype:Ebog|filetype:pdf|cnt:1|status:NotActive|booktype:Lydbog|filetype:zip_mp3|cnt:2|status:Pending|booktype:Ebog|filetype:epub|cnt:19|status:Pending|booktype:Ebog|filetype:pdf|cnt:5|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:5|";
        //        $mustBe = "status:NotActive|booktype:Lydbog|filetype:zip_mp3|cnt:2|status:Pending|booktype:Ebog|filetype:epub|cnt:20|status:Pending|booktype:Ebog|filetype:pdf|cnt:5|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:5|";
        //        $mustBe = "status:NotActive|booktype:Lydbog|filetype:zip_mp3|cnt:2|status:Pending|booktype:Ebog|filetype:epub|cnt:16|status:Pending|booktype:Ebog|filetype:pdf|cnt:4|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:2|";
        //        $mustBe = "status:NotActive|booktype:Lydbog|filetype:zip_mp3|cnt:2|status:Pending|booktype:Ebog|filetype:epub|cnt:17|status:Pending|booktype:Ebog|filetype:pdf|cnt:4|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:2|";
        $mustBe = "status:NotActive|booktype:Lydbog|filetype:zip_mp3|cnt:2|status:Pending|booktype:Ebog|filetype:epub|cnt:19|status:Pending|booktype:Ebog|filetype:pdf|cnt:4|status:Pending|booktype:Lydbog|filetype:zip_mp3|cnt:2|";
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
        $mustBe = "program:getXmlFromPubHubMediaService.php|status:Pending|cnt:3|";
        //        $mustBe = "program:getXmlFromPubHubMediaService.php|status:Pending|cnt:4|";
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
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
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
        //        $expected = "status:ProgramMatch|booktype:Ebog|filetype:pdf|cnt:1|";
        $expected = "status:eVa|booktype:Ebog|filetype:pdf|cnt:1|";

        $this->assertEquals($expected, $actual);

        /*
         * seqno 5 & 6. NotActive
         */

        /*
        * seqno 7. A records which must be handled by a libarien.
        * Status -> eVa
        */
        $actual = $this->exeCmd(7);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);


        /*
        * seqno 8-18. records in eVa.
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
        $actual = $this->exeCmd(19);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(21);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(22);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(23);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(24);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(25);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(26);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);
        $actual = $this->exeCmd(27);
        $expected = "status:eVa|booktype:Ebog|filetype:epub|cnt:1|";
        $this->assertEquals($expected, $actual);

    }

    function test_getPubHubData() {
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
        $info = array();
        $info['renditionlayout'] = false;
        $info['publisher'] = false;
        $info['source'] = '9801111111118';
        $info['title'] = false;
        $info['filesize'] = 1000;
        $info['ext'] = 'epub';
        $info['fdate'] = 'Jan 01 2016';
        $info['bookid'] = "fe097cde-5b4d-4bbb-93d3-fda6d1ca1cfc";
        $this->mediadb->updateEbook(18, $info);

        $info = array();
        $info['renditionlayout'] = false;
        $info['publisher'] = false;
        $info['source'] = '9788711513798';
        $info['title'] = false;
        $info['filesize'] = 1000;
        $info['ext'] = 'epub';
        $info['fdate'] = 'Jan 01 2016';
        $info['bookid'] = "c6ca0437-49cf-48e2-8315-d5395df11e1e";
        $this->mediadb->updateEbook(22, $info);

        $this->mediadb->copyTablesOut('_match');

    }

}
