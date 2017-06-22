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
 * Class testmediadbclass
 */
class testinsertfaust extends PHPUnit_Framework_TestCase {


    private $db;
    public $mediadb;
    private $dirini;
    private $digi;


    protected function setUp() {

        $her = getcwd();
//        echo "her:$her\n";


        $startdir = dirname(__FILE__);

        $inifile = $startdir . "/../DigitalResources.ini";
        $this->digi = $startdir . "/../DigitalResources";
        $this->dirini = $inifile;
//        echo "inifile:$inifile\n";
        $config = new inifile($inifile);
        $this->assertEquals("", $config->error, "No inifile found!");
        $connect_string = $config->get_value("connect", "testcase");

        $this->db = new pg_database($connect_string);
        $this->db->open();


//        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
    }


    // skal gøres aktivt igen
    function test_insertFaustMediaService() {
        $drini = $this->dirini;

        /*
         * Run insertFaustMediaService.php and check for the result.
         */
        chdir($this->digi);
//        $this->copyTablesIn('_elu');
        $this->mediadb = new mediadb($this->db, basename(__FILE__), $nothing = false);
        $this->mediadb->copyTablesIn('_elu');

        $cmd = "php insertFaustMediaService.php -p $drini -t";
        exec($cmd, $output, $return);
        echo "\n" . implode("\n", $output) . "\n";
        $this->assertEquals(0, $return, "ERROR  cmd:[$cmd] ----  fejler \n" . implode("\n", $output) . "\n");

        $res = $this->mediadb->getInfoData(8);
        $this->assertEquals('9 211 114 8', $res[0]['newfaust'], "");

//        $this->copyTablesOut('_insF');
        $this->mediadb->copyTablesOut('_insF');
    }
}
