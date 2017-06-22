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

/*require_once "$inclnk/OLS_class_lib/curl_class.php";*/
require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
//require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$startdir/XmlDiff_class.php";
require_once "$classes/Calendar_class.php";

class testCalender extends PHPUnit_Framework_TestCase {

    private $db;
    public $mediadb;
//    private $dirini;
//    private $datadir;
    private $digi;

    protected function init() {

        $her = getcwd();
//        echo "her:$her\n";


        $startdir = dirname(__FILE__);

        $inifile = $startdir . "/../DigitalResources.ini";
//        $inifile = $startdir . "DigitalDrift.ini";
        $this->digi = $startdir . "/../DigitalResources";
//        $this->dirini = $inifile;
//        $this->datadir = $startdir . "/data";
//        echo "inifile:$inifile\n";
        $config = new inifile($inifile);
        $this->assertEquals("", $config->error, "No inifile found!");
        $connect_string = $config->get_value("connect", "testcase");

        $this->db = new pg_database($connect_string);
        $this->db->open();


//        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
    }


    public function test_getDBFday() {

        $this->init();
        $c = new Calendar_class($this->db, '2016');
        $nxtweek = '201601';
        while (substr($nxtweek, 0, 4) == '2016') {
            $arr = $c->getDBFday($nxtweek);
//            echo "nxtweek:$nxtweek\n";
//            print_r($arr);
            $nxtweek = $arr['nxtweek'];
        }

    }

    public function test_getWeekCodesOfTheDay() {
        $this->init();
        $c = new Calendar_class($this->db, '2017');
        $res = $c->getWeekCodesOfTheDay('25-01-2017');
        print_r($res);
        $res = $c->getWeekCodesOfTheDay('26-01-2017');
        print_r($res);
        $res = $c->getWeekCodesOfTheDay('27-01-2017');
        print_r($res);

        $res = $c->getWeekCodesOfTheDay('05-04-2017');
        print_r($res);
        $res = $c->getWeekCodesOfTheDay('06-04-2017');
        print_r($res);
        $res = $c->getWeekCodesOfTheDay('07-04-2017');
        print_r($res);
        $res = $c->getWeekCodesOfTheDay('08-04-2017');
        print_r($res);
        $res = $c->getWeekCodesOfTheDay('13-04-2017');
        print_r($res);
        $res = $c->getWeekCodesOfTheDay('20-04-2017');
        print_r($res);

    }

//        $soapClient = new soapClient_class($config, 'pubhubLibraryService', 'UnKnown');
//        $er = $soapClient->getError();
//        $exp = "no agreementid  stated in configuration file under [UnKnown]";
//        $this->assertEquals($exp, $er);
//
//        $soapClient = new soapClient_class($config, 'pubhubLibraryServiceTest', 'eReolen');
//        $er = $soapClient->getError();
//        $exp = "no url stated in configuration file [pubhubLibraryServiceTest]";
//        $this->assertEquals($exp, $er);
//
//        $soapClient = new soapClient_class($config, 'pubhubLibraryService', 'eReolen');
//        $er = $soapClient->getError();
//        $this->assertFalse($er);


}