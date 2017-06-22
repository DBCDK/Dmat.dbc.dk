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
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$classes/ftp_transfer_class.php";

class testftp_transfer extends PHPUnit_Framework_TestCase {


    public function test_construct() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";

        $config = new inifile($inifile);
        $workdir = $config->get_value('workdir', 'setup');
        $this->assertNotEmpty($workdir, "Workdir:$workdir");

        $Basis = 'MediaToBasis';
        $status = 'UpdateBasis';

        $ftptransfer = new ftp_transfer_class($config, $status, $Basis, $nothing = true);
        if ($ftptransfer->error()) {
            $this->assertNull($ftptransfer->error());
        }

        $datafile = $ftptransfer->getDatafile();
        $ts = $ftptransfer->get('ts');

        $exp = "$workdir/870970.$ts.M$status";
        $act = $datafile[$Basis];
        $this->assertEquals($exp, $act);

        // test write
        $isomarc = "en lang post-";
        $ftptransfer->write($isomarc);
        $ftptransfer->write($isomarc);

        $tfile = $ftptransfer->get('transfile');
        $exp = "$workdir/870970.$ts.M$status.truns";
        $this->assertEquals($exp, $tfile);

        $tline = $ftptransfer->get('transline');
        $exp = "b=basis,f=870970.$ts.M$status,t=dm2iso,c=latin-1,o=digital,m=hhl@dbc.dk";
        $this->assertEquals($exp, $tline);

        $err = $ftptransfer->put();
        $this->assertEquals($err, 'nothing: No ftp transfer');

        $ftptransfer->removeFiles();


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