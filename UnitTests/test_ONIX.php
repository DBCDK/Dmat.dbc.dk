<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
$mockupclasses = $startdir . "/../UnitTests/classes";

require_once "$classes/ONIX_class.php";

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 15-11-2016
 * Time: 11:06
 */
class test_ONIX_class extends PHPUnit_Framework_TestCase {


    function test_ONIX() {
        // chdir('UnitTests');
        $ONIX = new ONIX();
        $a = getcwd();
        if (basename($a) == 'posthus') {
            chdir('UnitTests');
        }
        $xml = file_get_contents('data/onix/products.01.xml');
        $ONIX->load_xml($xml);


        $ONIX->startFile();
        $info = $ONIX->getNext();
        $this->assertEquals(20, count($info));
        $this->assertEquals('Publizon', $info['senderName']);
        $this->assertEquals('20150721T13:51:50Z', $info['datestamp']);
        $this->assertEquals('Active', $info['status']);
        $this->assertEquals('9788711321706', $info['isbn13']);
        $this->assertEquals('Ebog', $info['ContentType']);
        $this->assertEquals('Reflowable', $info['form']);
        $this->assertEquals('Spil med spejle', $info['title']['text']);
        $this->assertEquals('author', $info['author'][0]['role']);
        $this->assertEquals('Agatha', $info['author'][0]['first']);
        $this->assertEquals('Christie', $info['author'][0]['last']);
        $this->assertEquals('translated', $info['author'][1]['role']);
        $this->assertEquals('Tage', $info['author'][1]['first']);
        $this->assertEquals('la Cour', $info['author'][1]['last']);

//        $this->assertTrue(array_key_exists('DescriptiveDetail', $info));
//        $this->assertEquals(6, count($info['DescriptiveDetail']));
//        $this->assertTrue(array_key_exists('CollateralDetail', $info));
//        $this->assertEquals(2, count($info['CollateralDetail']));
//        $this->assertTrue(array_key_exists('PublishingDetail', $info));
//        $this->assertEquals(3, count($info['PublishingDetail']));
//        $this->assertTrue(array_key_exists('ProductSupply', $info));
//        $this->assertEquals(3, count($info['ProductSupply']));
//        print_r($info);

        // next is working
        $info = $ONIX->getNext();
        $this->assertEquals('Publizon', $info['senderName']);
        $this->assertEquals('20160207T13:29:08Z', $info['datestamp']);

        // cnt is working
        $cnt = $ONIX->getCnt();
        $this->assertEquals(2, $cnt);

        $this->assertEquals('9788773326626', $ONIX->getId());
        $xml = $ONIX->getXml();
        $lngth = strlen($xml);
        $this->assertEquals(4760, $lngth);

        // only giving ONIX a xml for one record
        $info = $ONIX->getInfo($xml);
        $this->assertEquals('Publizon', $info['senderName']);
        $this->assertEquals('20160207T13:29:08Z', $info['datestamp']);

        $info = $ONIX->getNext();
        $this->assertEquals('Publizon', $info['senderName']);
        $this->assertEquals('20160613T08:16:46Z', $info['datestamp']);

        $info = $ONIX->getNext();
        $this->assertEquals('Publizon', $info['senderName']);
        $this->assertEquals('20121024T11:16:46Z', $info['datestamp']);

        for ($i = $ONIX->getCnt(); $i < 21; $i++) {
            $info = $ONIX->getNext();
        }

        $info = $ONIX->getNext();
        $this->assertFalse($info);

    }
}
