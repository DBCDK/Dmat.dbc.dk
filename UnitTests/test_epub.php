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

require_once "$classes/epub_class.php";


/**
 * Class testepub_class
 */
class testepub_class extends PHPUnit_Framework_TestCase {
    public function test_construct() {
        if (basename(getcwd()) == 'posthus') {
            chdir('UnitTests');
        }
        $epub = new epub_class();
        $epub->initEpub('data/rod.zip');
        $layout = $epub->getLayout();
        $this->assertEquals('', $layout, "layout:$layout");

        $title = $epub->getTitle();
        $this->assertEquals('Dracula', $title);

//        $data = $epub->getElement('source');
//        $this->assertEquals('urn:isbn:9788711455470', $data);


        $epub->initEpub('data/rod.zip');
        $layout = $epub->getLayout();
        $this->assertEquals('', $layout);

        $title = $epub->getTitle();
        $this->assertEquals('Dracula', $title);

//        $data = $epub->getElement('source');
//        $this->assertEquals('urn:isbn:9788711455470', $data);

//        $epub->initEpub('ebooks/00fc6d34-65a3-418b-b2e2-2ba25874c6b0.epub');
//        $title = $epub->getTitle();
//        $this->assertEquals('Rødhætte og Mohammed', $title);

//        $opfxml = $epub->getOpfXml();
//        echo $opfxml;
    }
}