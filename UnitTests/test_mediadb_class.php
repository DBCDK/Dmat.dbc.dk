<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
//$mockupclasses = $startdir . "/../UnitTests/classes";

//require_once "$classes/dmat_db_class.php";
require_once "$classes/ONIX_class.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/pubhubFtp_class.php";
require_once "$classes/epub_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 15-11-2016
 * Time: 11:06
 */
class test_mediadb_class extends PHPUnit_Framework_TestCase {

    private $db;
    private $nothing;
    private $pubhubFtp;
    private $epub;

    function construct() {
        $startdir = dirname(__FILE__);
        $inifile = $startdir . "/dr.ini";
        $config = new inifile($inifile);
        $connect_string = $config->get_value("connect", "testcase");
//        $this->faustcmd = $config->get_value('getFaust_cmd', 'setup');
        $this->db = new pg_database($connect_string);
        $this->db->open();
        if (($ftp_server = $config->get_value('pubhub_ftp_server', 'setup')) == false)
            die("no pubhub_ftp_server stated in the configuration file");

        if (($ftp_user = $config->get_value('pubhub_ftp_user', 'setup')) == false)
            die("no pubhub_ftp_user stated in the configuration file");

        if (($ftp_pass = $config->get_value('pubhub_ftp_passwd', 'setup')) == false)
            die("no pubhub_ftp_passwd stated in the configuration file");
        if (basename(getcwd()) == 'posthus') {
            chdir('UnitTests');
        }
        $work = '../DigitalResources/work';
        $this->pubhubFtp = new pubhubFtp($ftp_server, $ftp_user, $ftp_pass, $work);
        $this->epub = new epub_class($work);
        $this->nothing = false;
        $mediadb = new mediadb(
            $this->db,
            basename(__FILE__),
            $this->nothing,
            $this->pubhubFtp,
            $this->epub
        );
        $mediadb->deleteTables('');
    }

    function test_mediadb() {
        $this->construct();
        $ONIX = new ONIX();
        if (basename(getcwd()) == 'posthus') {
            chdir('UnitTests');
        }
        $xml = file_get_contents('data/onix/products.01.xml');
        $ONIX->load_xml($xml);
        $ONIX->startFile();

        $mediadb = new mediadb(
            $this->db,
            basename(__FILE__),
            $this->nothing,
            $this->pubhubFtp,
            $this->epub
        );

        /*
         * First record inserted in databse.
         */
        $info = $ONIX->getNext();
        $xml = $ONIX->getXml();
        $mediadb->updateONIX($info, $xml);
        $inf = $mediadb->getInfoByIsbn($info['isbn13']);
        $this->assertEquals(16, count($inf));
        $cnts = $mediadb->getUpdateCnts();
        $exp = array('tot' => 1, 'ins' => 1, 'upd' => 0, 'del' => 0);
        $this->assertEquals($exp, $cnts);

        /*
         * insert the rest of the test records
         */
        while ($info = $ONIX->getNext()) {
            $xml = $ONIX->getXml();
            $mediadb->updateONIX($info, $xml);
        }
        $cnts = $mediadb->getUpdateCnts();
        $exp = array('tot' => 21, 'ins' => 21, 'upd' => 0, 'del' => 0);
        $this->assertEquals($exp, $cnts);

        /*
         * New session, update one record
         */
        $mediadb = new mediadb(
            $this->db,
            basename(__FILE__),
            $this->nothing,
            $this->pubhubFtp,
            $this->epub
        );
        $ONIX->startFile();
        while ($info = $ONIX->getNext()) {
            $xml = $ONIX->getXml();
            $mediadb->updateONIX($info, $xml);
        }
        $cnts = $mediadb->getUpdateCnts();
        $exp = array('tot' => 21, 'ins' => 0, 'upd' => 0, 'del' => 0);
        $this->assertEquals($exp, $cnts);

        /*
         *  One record is updated in test records
         *  One record is updated. Was Afventer. Now Pending
         *  One is set to NotActive
         */
        $xml = file_get_contents('data/onix/products.02.xml');
        $ONIX->load_xml($xml);
        $ONIX->startFile();

        $mediadb = new mediadb(
            $this->db,
            basename(__FILE__),
            $this->nothing,
            $this->pubhubFtp,
            $this->epub
        );
        // set record # 4 to "afventer"
        $mediadb->updateStatusOnly(4, 'Afventer');

        while ($info = $ONIX->getNext()) {
            $xml = $ONIX->getXml();
            $mediadb->updateONIX($info, $xml);
        }
        $cnts = $mediadb->getUpdateCnts();
        $exp = array('tot' => 21, 'ins' => 0, 'upd' => 2, 'del' => 1);
        $this->assertEquals($exp, $cnts);

        /*
         *  Three more records is inserted
         *  Two is set to NotActive
         *  One is set updated
         */
        $xml = file_get_contents('data/onix/products.03.xml');
        $ONIX->load_xml($xml);
        $ONIX->startFile();

        $mediadb = new mediadb(
            $this->db,
            basename(__FILE__),
            $this->nothing,
            $this->pubhubFtp,
            $this->epub
        );
        while ($info = $ONIX->getNext()) {
            $xml = $ONIX->getXml();
            $mediadb->updateONIX($info, $xml);
        }
        $cnts = $mediadb->getUpdateCnts();
        $exp = array('tot' => 24, 'ins' => 3, 'upd' => 1, 'del' => 2);
        $this->assertEquals($exp, $cnts);


        $mediadb->copyTablesOut('_onix');
    }
}
