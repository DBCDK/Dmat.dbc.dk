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

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$classes/dr_db_class.php";

class testdr_dbclass extends PHPUnit_Framework_TestCase {

    public function test_construct() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $config = new inifile($inifile);

        $connect_string = $config->get_value("connect", "testcase");
        $db = new pg_database($connect_string);
        $db->open();

        $dr = new dr_db($db, basename(__FILE__), $nothing = true);

        $st = $dr->setStarttime();

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

    /**
     * @throws soapClientException
     */
    function est_fetch() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResourcs.ini";
        $config = new inifile($inifile);
        $soapClient = new soapClient_class($config, 'pubhubLibraryService', 'eReolenLicens');
        $er = $soapClient->getError();
        $this->assertFalse($er);

        $file1 = 'UnitTests/work/ereolen.xml';
        $xml = $soapClient->soapClient($file1);
        $this->assertFileExists($file1);

        $soapClient->setWriteToFile();
        $file2 = 'UnitTests/work/NoNo.xml';
        $xml = $soapClient->soapClient($file2);
        $this->assertFileNotExists($file2);

    }


    /**
     * @expectedException drdbException
     * @expectedExceptionMessage  No program name in mediadb_class constructor!!
     */
    public function test_exception() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $config = new inifile($inifile);

        $connect_string = $config->get_value("connect", "testcase");
        $db = new pg_database($connect_string);
        $db->open();

        $dr = new dr_db($db, '', $nothing = true);
    }
}