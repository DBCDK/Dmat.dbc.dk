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
class testToPublizon extends PHPUnit_Framework_TestCase {


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

    function test_ToPublizon() {
        $drini = $this->dirini;
        $this->libV3API = new mockUpLibV3API($this->basedir, 'data');
        $marc = new marc();

        chdir($this->digi);
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
        $this->mediadb->copyTablesIn('_topromat');
        $cmd = "php ToPublizon.php -p $drini -t ";
        exec($cmd, $output, $return);

//        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");


        foreach ($output as $ln) {
            echo "$ln\n";
        }
//        $exp = "cnt:13, dbf: 11, bkm:8, bkmV:6, bkmB:0, bkmS:1, lu=4, faust:4";
//        $exp = "cnt:15, dbf: 13, bkm:10, bkmV:8, bkmB:1, bkmS:2, lu=5, faust:5";
//        $exp = "cnt:16, dbf: 14, bkm:11, bkmV:9, bkmB:1, bkmS:2, lu=6, faust:6";
//        $exp = "cnt:17, dbf: 15, bkm:12, bkmV:10, bkmB:1, bkmS:2, lu=6, faust:6";
//        $exp = "cnt:16, dbf: 14, bkm:12, bkmV:10, bkmB:1, bkmS:2, lu=6, faust:6";
//        $exp = "cnt:18, dbf: 16, bkm:14, bkmV:12, bkmB:1, bkmS:2, lu=7, faust:7";
        $exp = "cnt:20, dbf: 18, bkm:14, bkmV:12, bkmB:1, bkmS:2, lu=7, faust:7";
        $actual = array_pop($output);
        $this->assertEquals($exp, $actual, "forkert optælling - ToPublizon");
//        $expd = false;
//        $cand = $this->mediadb->getCandidates('UpdatePromat');
//        $this->assertEquals($cand, $expd, 'ToPromat failure!');

    }
}
