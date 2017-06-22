<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 01-12-2015
 * Time: 14:29
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
$her = getcwd();

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/weekcode_class.php";


/**
 * Class testepub_class
 */
class testweekcode_class extends PHPUnit_Framework_TestCase {
    public function test_getweekcode() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/../DigitalResources.ini";
        $config = new inifile($inifile);
        $connect_string = $config->get_value("connect", "testcase");
        $her = getcwd();
        if (basename(getcwd()) == 'posthus') {
            chdir('UnitTests');
        }
//        $startdir = dirname(__FILE__);
//        $inifile = $startdir . "dr.ini";
//        $inifile = "DigitalResources.ini";
//        $config = new inifile($inifile);

//        $connect_string = $config->get_value("connect", "setup");
        $db = new pg_database($connect_string);
        $db->open();
        $wc = new weekcode($db);

//        $weekcode = $wc->getweekcode();
//        $this->assertEquals('201551', $weekcode);

//        $datweekcode = $wc->getDatWeekcode();
//        $this->assertEquals('201551', $datweekcode);

        $date = '20151207';
        $weekcode = $wc->getweekcode($date);
        $datweekcode = $wc->getDatWeekcode($date);
//        $this->assertEquals('201551', $weekcode);
        $this->assertEquals('201552', $weekcode);
        $this->assertEquals('201551', $datweekcode);

        $date = '20151208';
        $weekcode = $wc->getweekcode($date);
        $datweekcode = $wc->getDatWeekcode($date);
//        $this->assertEquals('201551', $weekcode);
        $this->assertEquals('201552', $weekcode);
        $this->assertEquals('201551', $datweekcode);

        $date = '20151209';
        $weekcode = $wc->getweekcode($date);
        $datweekcode = $wc->getDatWeekcode($date);
//        $this->assertEquals('201551', $weekcode);
        $this->assertEquals('201552', $weekcode);
        $this->assertEquals('201551', $datweekcode);

        $date = '20151210';
        $weekcode = $wc->getweekcode($date);
        $datweekcode = $wc->getDatWeekcode($date);
        $this->assertEquals('201552', $weekcode);
//        $this->assertEquals('201551', $weekcode);
        $this->assertEquals('201552', $datweekcode);

        $date = '20161211';
        $weekcode = $wc->getweekcode($date);
        $datweekcode = $wc->getDatWeekcode($date);
//        $this->assertEquals('201650', $weekcode);
        $this->assertEquals('201651', $weekcode);
        $this->assertEquals('201651', $datweekcode);
    }
}