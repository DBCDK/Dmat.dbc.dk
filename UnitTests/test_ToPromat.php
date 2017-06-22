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
class testToPromat extends PHPUnit_Framework_TestCase {

    private $db;
    public $mediadb;
    private $dirini;
    private $datadir;
    private $digi;
    private $basedir;

    protected function setUp() {

        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $this->digi = $startdir . "/../DigitalResources";
        $this->dirini = $inifile;
        $this->datadir = $startdir . "/data";
        $config = new inifile($inifile);
        $this->assertEquals("", $config->error, "No inifile found!");
        $connect_string = $config->get_value("connect", "testcase");
        $this->basedir = $config->get_value('basedir', 'setup');
        $this->db = new pg_database($connect_string);
        $this->db->open();

    }

    function test_ToPromat() {
        $drini = $this->dirini;
        $this->libV3API = new mockUpLibV3API($this->basedir, 'data');
        $marc = new marc();

        chdir($this->digi);
//        $this->copyTablesIn('_ToBasis');

        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
        $this->mediadb->copyTablesIn('_To_basis');

        $cmd = "php ToPromat.php -p $drini -t ";
        exec($cmd, $output, $return);

//        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        foreach ($output as $ln) {
            echo "$ln\n";
        }

        $expd = false;
        $cand = $this->mediadb->getCandidates('UpdatePromat');
        $this->assertEquals($cand, $expd, 'ToPromat failure!');

//        $this->copyTablesOut('_ToPromat');
        $this->mediadb->copyTablesOut('_topromat');
    }
}
