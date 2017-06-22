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
$mockupclasses = $startdir . "/../UnitTests/classes";

//require_once "$classes/mediadb_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
//require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$inclnk/OLS_class_lib/oci_class.php";
//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
//require_once "$classes/lek_class.php";
//require_once "$classes/Calendar_class.php";
//require_once "$classes/ssh_class.php";
//require_once "$classes/getFaust_class.php";
//require_once "$mockupclasses/getFaust_class.php";
//require_once "$mockupclasses/mockUpLibV3_class.php";
require_once "$classes/scanSaxo_class.php";

class test_scanSaxoclass extends PHPUnit_Framework_TestCase {

//    private $db;
//    private $ssh;
//    private $faustcmd;
//    private $libV3API;
    private $nothing;
    private $config;


    public function __construct() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/dr.ini";
        $this->config = new inifile($inifile);

//        $connect_string = $config->get_value("connect", "testcase");
//        $this->faustcmd = $config->get_value('getFaust_cmd', 'setup');
//        $this->db = new pg_database($connect_string);
//        $this->db->open();
//        $this->nothing = false;
//        $this->mediadb = new mediadb($this->db, basename(__FILE__), $this->nothing);

//        $this->ssh = new ssh($config, 'lekToDios', $nothing = false);
    }


    function test_getByTitleAuthor() {
        $scansaxo = new scanSaxo_class($this->config, 'testcase');

        $res = $scansaxo->getPrintedDirect('9788702207651');
//        $res = $scansaxo->getByTitleAuthor('Sig ikke noget', 'Harlan Coben');
//        $res = $scansaxo->getByTitleAuthor('Når man forveksler kærlighed med en saks', '');
        $res = $scansaxo->getByTitleAuthor('Kongen af Thule', 'Kurt L. Frederiksen');


    }
}