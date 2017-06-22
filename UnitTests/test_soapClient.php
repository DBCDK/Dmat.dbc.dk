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
require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$classes/soapClient_class.php";

class testsoapclientclass extends PHPUnit_Framework_TestCase {

    public function test_construct() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $config = new inifile($inifile);

        $soapClient = new soapClient_class($config, 'pubhubLibraryService', 'UnKnown');
        $er = $soapClient->getError();
        $exp = "no agreementid  stated in configuration file under [UnKnown]";
        $this->assertEquals($exp, $er);

        $soapClient = new soapClient_class($config, 'pubhubLibraryServiceTest', 'eReolen');
        $er = $soapClient->getError();
//        $exp = "no url stated in configuration file [pubhubLibraryServiceTest]";
        $exp = "no retailerkeycode stated in configuration file";
        $this->assertEquals($exp, $er);

        $soapClient = new soapClient_class($config, 'pubhubLibraryService', 'eReolen');
        $er = $soapClient->getError();
        $this->assertFalse($er);
    }

    /**
     * @throws soapClientException
     */
    function test_fetch() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $config = new inifile($inifile);
        $soapClient = new soapClient_class($config, 'pubhubLibraryService', 'eReolenLicens');
        $er = $soapClient->getError();
        $this->assertFalse($er);

        $file1 = 'work/ereolen.xml';
        $xml = $soapClient->soapClient($file1);
        $this->assertFileExists($file1);

        $soapClient->setWriteToFile();
        $file2 = 'work/NoNo.xml';
        $xml = $soapClient->soapClient($file2);
        $this->assertFileNotExists($file2);

    }

    function est_openSearch() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $config = new inifile($inifile);
        $soapClient = new soapClient_class($config, 'openSearch');
        $er = $soapClient->getError();
        $this->assertFalse($er);

        $file1 = 'work/ereolen.xml';
        $xml = $soapClient->soapClient($file1);
        $this->assertFileExists($file1);

        $soapClient->setWriteToFile();
        $file2 = 'work/NoNo.xml';
        $xml = $soapClient->soapClient($file2);
        $this->assertFileNotExists($file2);
    }

    /**
     * @expectedException soapClientException
     * @expectedExceptionMessage datafile UnitTests/work/NoNo.xml does not exists
     */
    public function test_exception() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/dr.ini";
        $config = new inifile($inifile);
        $soapClient = new soapClient_class($config, 'pubhubLibraryService', 'eReolenLicens');
        $soapClient->setReadFromFile();

        $file2 = 'UnitTests/work/NoNo.xml';
        $xml = $soapClient->soapClient($file2);
    }
}