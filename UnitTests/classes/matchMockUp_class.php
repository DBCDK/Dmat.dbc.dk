<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../";

//require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "GetMarcFromBasis_class.php";
//require_once 'mark_best_records.php';
//require_once "$inclnk/OLS_class_lib/marc_class.php";
//require_once "$inclnk/OLS_class_lib/material_id_class.php";

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 07-03-2016
 * Time: 10:29
 */
class matchMockUp {

    private $doc;

    function __construct() {
        $this->doc = new DOMDocument();
    }

    private function getData($name) {
        $elements = $this->doc->getElementsByTagName($name);
        $txt = "";
        foreach ($elements as $element) {
            $txt .= $element->nodeValue . "\n";
        }
        return rtrim($txt);
    }

    function match($xml) {
        $mMarc = array();
        $this->doc->loadXML($xml);
        $title = $this->getData('Title');
        $isbn13 = $this->getData('Identifier');
        echo "isbn13: $isbn13, title: $title\n";
        switch ($isbn13) {
        case '9788711321706':
            $dat = array('type' => 'title', 'matchtype' => 200, 'lektoer' => false);
            $mMarc['1 111 111 1']['870970']['Basis'] = $dat;
            break;
        case '9788793098268':
            $dat = array('type' => 'isbn13', 'matchtype' => 317, 'lektoer' => false);
            $mMarc['2 222 222 2']['870970']['Basis'] = $dat;
            break;
        case '9788779346321':
            $dat = array('type' => 'isbn13', 'matchtype' => 202, 'lektoer' => true);
            $mMarc['2 222 230 2']['870970']['Basis'] = $dat;
            break;
        case '9788711349755':
            $dat = array('type' => 'isbn13', 'matchtype' => 200, 'lektoer' => false);
            $mMarc['2 222 222 2']['870970']['Basis'] = $dat;
            $mMarc['2 222 223 2']['870970']['Basis'] = $dat;
            break;
        case '9788711378076':
            $dat = array('type' => 'title', 'matchtype' => 116, 'lektoer' => false);
            $mMarc['2 222 224 2']['870970']['Basis'] = $dat;
            break;
        case '9788792241801':
            return false;
            break;
        case '9788774575375':
            return false;
            break;
        case '9788776917371':
            $dat = array('type' => 'isbn13', 'matchtype' => 216, 'lektoer' => false);
            $mMarc['2 222 225 2']['870970']['Basis'] = $dat;
            break;
        case '9788711406267':
            $dat = array('type' => 'isbn13', 'matchtype' => 216, 'lektoer' => false);
            $mMarc['2 222 226 2']['870970']['Basis'] = $dat;
            break;
        case '9788792922106':
            $dat = array('type' => 'isbn13', 'matchtype' => 216, 'lektoer' => false);
            $mMarc['2 222 227 2']['870970']['Basis'] = $dat;
            break;
        case '9788771246292':
            $dat = array('type' => 'isbn13', 'matchtype' => 216, 'lektoer' => false);
            $mMarc['2 222 228 2']['870970']['Basis'] = $dat;
            break;
        case '9788711394908':
            $dat = array('type' => 'isbn13', 'matchtype' => 216, 'lektoer' => true);
            $mMarc['2 222 229 2']['870970']['Basis'] = $dat;
            break;
        case '9788758812120':
            $dat = array('type' => 'title', 'matchtype' => 201, 'lektoer' => false);
            $mMarc['2 222 231 2']['870970']['Basis'] = $dat;
            break;
        case '9788711437155':
            $dat = array('type' => 'title', 'matchtype' => 201, 'lektoer' => true);
            $mMarc['2 222 232 2']['870970']['Basis'] = $dat;
            break;
        case '9788711483084':
            $dat = array('type' => 'title', 'matchtype' => 216, 'lektoer' => false);
            $mMarc['5 245 516 2']['870970']['Basis'] = $dat;
            break;
            break;
        case '9788793059160':
            return false;
//                $dat = array('type' => 'title', 'matchtype' => 201, 'lektoer' => false);
//                $mMarc['5 246 282 7']['870970']['Basis'] = $dat;
            break;
        case '9788702216257':
            $dat = array('type' => 'title', 'matchtype' => 201, 'lektoer' => false);
            $mMarc['5 243 345 2']['870970']['Basis'] = $dat;
            break;
        case '9878711111111':
            $dat = array('type' => 'title', 'matchtype' => 300, 'lektoer' => false);
            $mMarc['0 637 642 8']['870970']['Basis'] = $dat;
            break;
        case '9788702097061':
            $dat = array('type' => 'title', 'matchtype' => 350, 'lektoer' => false);
            $mMarc['2 790 303 7']['870970']['Basis'] = $dat;
            break;
        default:
            return false;
        }

        return $mMarc;
    }
}