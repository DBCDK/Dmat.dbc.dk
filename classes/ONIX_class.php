<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Class soapClientException
 */
class ONIXException extends Exception {

    public function __toString() {
        return 'ONIXException -->' . $this->getMessage() . ' --- ' .
            "\nStack trace:\n" . $this->getTraceAsString();
    }

}

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 11-10-2016
 * Time: 14:57
 */
class ONIX {

    private $doc;
    private $products;
    private $plength;
    private $senderName;
    private $cnt;
    private $head;
    private $tail;
    private $xpath;
    private $workdoc;
    private $productindx;
    private $xml;
    private $id;
    private $list17;
    private $list23;
    private $list24;
    private $list81;
    private $list153;
    private $list175;

//    private $shortOnly;

    function __construct() {
        $this->list17 = array(
            'A01' => 'author',
            'A12' => 'ill',
            'B01' => 'edited',
            'B02' => 'revised',
            'B06' => 'translated',
            'E07' => 'readBy'
        );
        $this->list23 = array(
            '00' => 'pages',
            '09' => 'duration',
            '22' => 'size',
            '50' => 'duration'
        );
        $this->list24 = array(
            '03' => 'pages',
            '05' => 'minutes',
            '06' => 'seconds',
            '18' => 'kbytes'
        );
        $this->list81 = array(
            '01' => 'Lydbog',
            '10' => 'Ebog'
        );
        $this->list153 = array(
            '02' => 'short',
            '03' => 'desc',
            '04' => 'toc',
            '05' => 'flap',
            '10' => 'PromotionalHeadline'
        );
        $this->list175 = array(
            'A103' => '',
            'E107' => '',
            'E200' => 'Reflowable',
            'E201' => 'FixedFormat'
        );
//        $this->shortOnly = false;
        $this->tr = array(
            "А" => "A", "Б" => "B", "В" => "V", "Г" => "G",
            "Д" => "D", "Е" => "E", "Ё" => "Yo", "Ж" => "Zh",
            "З" => "Z", "И" => "I", "Й" => "J", "К" => "K",
            "Л" => "L", "М" => "M", "Н" => "N", "О" => "O",
            "П" => "P", "Р" => "R", "С" => "S", "Т" => "T",
            "У" => "U", "Ф" => "F", "Х" => "Kh", "Ц" => "Ts",
            "Ч" => "Ch", "Ш" => "Sh", "Щ" => "Sch", "Ъ" => "",
            "Ы" => "Y", "Ь" => "", "Э" => "E", "Ю" => "Yu",
            "Я" => "Ja", "а" => "a", "б" => "b", "в" => "v",
            "г" => "g", "д" => "d", "е" => "e", "ё" => "yo",
            "ж" => "zh", "з" => "z", "и" => "i", "й" => "j",
            "к" => "k", "л" => "l", "м" => "m", "н" => "n",
            "о" => "o", "п" => "p", "р" => "r", "с" => "s",
            "т" => "t", "у" => "u", "ф" => "f", "х" => "kh",
            "ц" => "ts", "ч" => "ch", "ш" => "sh", "щ" => "sch",
            "ъ" => "", "ы" => "y", "ь" => "", "э" => "e",
            "ю" => "yu", "я" => "ja", "—" => "", "–" => "-"
        );
    }

    function load_xml($xml) {
        $this->doc = new DOMDocument('1.0', 'utf-8');
        $this->doc->formatOutput = true;
        $xml = strtr($xml, $this->tr);
        $this->doc->loadXML($xml);
    }

    function getInfo($xml) {
        $this->load_xml($xml);
        $info = $this->toArray($this->doc);
        return $info;
    }

    function getHead() {
        return $this->head;
    }

    function getTail() {
        return $this->tail;
    }

    function fastStart($xml) {
        $this->xml = $xml;
        $this->productindx = array();
        $this->cnt = 0;
        $pos = 0;
        $starttag = '<Product ';
        $startlngth = strlen($starttag);
        $endtag = '</Product>';
        $endtaglngth = strlen($endtag);

        // get startblok
        $pos = strpos($xml, $starttag, $pos);
        $this->head = substr($xml, 0, $pos);

        while ($start = strpos($xml, $starttag, $pos)) {
//            $start += $startlngth;
            $end = strpos($xml, $endtag, $start + $startlngth);
            $pos = $end + $endtaglngth;
            $lngth = $end + $endtaglngth - $start;
            $this->productindx[] = array($start, $lngth);
            $x = substr($xml, $start, $lngth);
        }
        $this->tail = substr($xml, $pos);
        $this->load_xml($this->head . $this->tail);
        $this->senderName = $this->doc->getElementsByTagName("SenderName")->item(0)->nodeValue;

    }

    function startFile() {
        $this->cnt = 0;
        $this->senderName = $this->doc->getElementsByTagName("SenderName")->item(0)->nodeValue;
        $this->products = $this->doc->getElementsByTagName("Product");
//        $this->shortOnly = true;
        $this->plength = $this->products->length;
    }

    function fastNext() {
        if ($this->cnt < count($this->productindx)) {
            $start = $this->productindx[$this->cnt][0];
            $lngth = $this->productindx[$this->cnt][1];
            $xml = substr($this->xml, $start, $lngth);
            $this->load_xml($xml);
            $info = $this->toArray($this->doc);
            $this->cnt++;
            return $info;
        }
    }

    function getNext() {
        if ($this->cnt < $this->plength) {
            $product = $this->products->item($this->cnt);
            $this->cnt++;

            $newdoc = new DOMDocument('1.0', 'utf-8');
            $newdoc->formatOutput = true;
            $newdoc->loadXML("<root></root>");
            $product = $newdoc->importNode($product, true);
            $newdoc->documentElement->appendChild($product);
            $xml = $newdoc->saveXML();
//            $xml = strtr($xml, $this->tr);
            $info = $this->toArray($newdoc);
            return $info;
        } else {
            return false;
        }
    }

    function getId() {
        return $this->id;
    }

    function getCnt() {
        return $this->cnt;
    }

    private function toArray(DOMDocument $newdoc) {
        $info = array();
        $info['senderName'] = $this->senderName;
        $this->workdoc = $newdoc;

        $e = $newdoc->getElementsByTagName('Product')->item(0);
        $info['datestamp'] = $e->getAttribute('datestamp');

        $info['RecordReference'] = $this->getValue($newdoc, 'RecordReference');
        if ($this->getValue($newdoc, 'NotificationType') == 5) {
            $info['status'] = 'NotActive';
        } else {
            $info['status'] = 'Active';
        }
        $ProductIdentifier = $newdoc->getElementsByTagName('ProductIdentifier')->item(0);
        $info['isbn13'] = $this->getProductIdentifier($ProductIdentifier);
        $this->id = $info['isbn13'];

        $DescriptiveDetail = $newdoc->getElementsByTagName('DescriptiveDetail')->item(0);
//        $info['DescriptiveDetail'] =
        $this->getDesc($DescriptiveDetail, $info);

//        if ($this->shortOnly) {
//            return $info;
//        }

        $CollateralDetail = $newdoc->getElementsByTagName('CollateralDetail')->item(0);
        $info['CollateralDetail'] = $this->getColl($CollateralDetail, $info);

        $PublishingDetail = $newdoc->getElementsByTagName('PublishingDetail')->item(0);
//        $info['PublishingDetail'] =
        $this->getPublishing($PublishingDetail, $info);

        $RelatedMaterial = $newdoc->getElementsByTagName('RelatedMaterial')->item(0);
        if ($RelatedMaterial) {
            throw new ONIXException('Hurrar  der er kommet en RelatedMaterial (FRBR)');
        }
        $RelatedProduct = $newdoc->getElementsByTagName('RelatedProduct')->item(0);
        if ($RelatedProduct) {
            throw new ONIXException('Hurrar  der er kommet en RelatedProduct (FRBR)');
        }

        $ProductSupply = $newdoc->getElementsByTagName('ProductSupply')->item(0);
//        $info['ProductSupply'] =
        $this->getProductSupply($ProductSupply, $info);

        return $info;
    }

    private function getProductIdentifier($p) {
        $this->testValue($p, 'ProductIDType', 15);
        return $p->getElementsByTagName('IDValue')->item(0)->nodeValue;
    }

    private function getDesc(DOMElement $d, &$info) {
        $this->testValue($d, 'ProductComposition', '00');
        $this->testValue($d, 'ProductForm', 'ED');

        $f81 = $this->getValue($d, 'PrimaryContentType');
        if (!array_key_exists($f81, $this->list81)) {
            throw new ONIXException(("Unknown PrimaryContentType: $f81"));
        }
        $info['ContentType'] = $this->list81[$f81];

        $info['form'] = '';
        $f175 = $this->getValue($d, 'ProductFormDetail');
        if ($f175) {
            if (!array_key_exists($f175, $this->list175)) {
                throw new ONIXException(("Unknown ProductFormDetail: $175"));
            }
            $info['form'] = $this->list175[$f175];
        }
        $info['title'] = $this->getTitle($d);
        $info['author'] = $this->getAuthor($d);
        $info['edition'] = $this->getEdition($d);
        $info['lang'] = $this->getLanguage($d);
        $info['extent'] = $this->getExtent($d);

//        return $info;
    }

    private function getExtent(DOMElement $d) {
        $extent = array();
        $ex = $d->getElementsByTagName('Extent');
        foreach ($ex as $e) {
            $f23 = $this->getValue($e, 'ExtentType');
            if (!array_key_exists($f23, $this->list23)) {
                throw new ONIXException(("Unknown ExtentType: $f23"));
            }
            $val = $this->getValue($e, 'ExtentValue');
            $f24 = $this->getValue($e, 'ExtentUnit');
            if (!array_key_exists($f24, $this->list24)) {
                throw new ONIXException(("Unknown ExtentUnit: $f24"));
            }
            $extent[] = array('type' => $this->list23[$f23], 'value' => $val, 'unit' => $this->list24[$f24]);
        }
        return $extent;
    }

    private function getProductSupply(DOMElement $d, &$info) {
//        $supply = array();
        $elements = $d->getElementsByTagName('Market');
        if (count($elements) != 1) {
            throw new ONIXException('count(<Market>)  != 1');
        }
        $e = $elements->item(0);
        $elements2 = $d->getElementsByTagName('SalesRestriction');
        if (count($elements2) != 1) {
            throw new ONIXException('count(<Salesrestriction>  != 1');
        }
        $e2 = $elements2->item(0);
        $this->testValue($e2, 'SalesRestrictionType', '00');
        $note = $this->getValue($e2, 'SalesRestrictionNote');
        if (!$note) {
            throw new ONIXException('Der er ikke nogen <SalesRestrictionNote>');
        }
        $type = false;
        switch ($note) {
        case 1:
            $info['lending'] = false;
            break;
        case 2:
            $info['lending'] = true;
            break;
        case 3:
            $info['lending'] = true;
            $IDValues = $e2->getElementsByTagName('IDValue');
            foreach ($IDValues as $IDValue) {
                $idv = $IDValue->nodeValue;
                switch ($idv) {
                case 1:
                    // måske skal 8/9 fjernes DEFF?
                case 8:
                case 9:
                    break;
                case 10:
                case  4:
                    $info['lendingType'] = 'eBookClick';
                    break;
                case  3:
                case 12:
                case 13:
                    $info['lendingType'] = 'eBookLicens';
                    break;
                case 14:
                case  5:
                    $info['lendingType'] = 'eAudioClick';
                    break;
                case 16:
                case 17:
                    $info['lendingType'] = 'eAudioLicens';
                    break;
                default:
                    throw new ONIXException("Ukendt SalesOutled IDValue:$idv");
                    break;
                }
            }
            break;
        default:
            throw new ONIXException("Ukendt SalesRestrictionNote:$note");
        }
//        $supply['lending'] = $lending;
//        $supply['lendingType'] = $type;
        $info['websiteLink'] = trim($d->getElementsByTagName('WebsiteLink')->item(0)->nodeValue, "\n\t ");
//        return $supply;
    }

    private function getPublishing(DOMElement $d, &$info) {
//        $publishing = array();
//        $imp = array();
//        $pub = array();
//        $date = array();
        $elements = $d->getElementsByTagName('Imprint');
        foreach ($elements as $e) {
            $info['ImprintName'] = $this->getValue($e, 'ImprintName');
        }
        $info['PublisherName'] = '';
        $elements = $d->getElementsByTagName('Publisher');
        foreach ($elements as $e) {
            $this->testValue($e, 'PublishingRole', '01');
            $info['PublisherName'] = $this->getValue($e, 'PublisherName');
        }
        $elements = $d->getElementsByTagName('PublishingDate');
        foreach ($elements as $e) {
            $this->testValue($e, 'PublishingDateRole', '01');
            $this->testValue($e, 'DateFormat', '00');
            $info['publishingDate'] = $this->getValue($e, 'Date');
        }
//        $publishing['imp'] = $imp;
//        $publishing['pub'] = $pub;
//        $publishing['date'] = $date;
//
//        return $publishing;
    }

    private function getColl(DOMElement $d, &$info) {
//        $coll = array();
//        $text = array();
//        $image = array();
        $elements = $d->getElementsByTagName('TextContent');
        foreach ($elements as $e) {
            $f153 = $this->getValue($e, 'TextType');
            if (!array_key_exists($f153, $this->list153)) {
                throw new ONIXException(("Unknown TextContent/TextType: $f153"));
            }
            $key = $this->list153[$f153];
            $this->testValue($e, 'ContentAudience', '00');
            $txt = $this->getValue($e, 'Text');
//            $text[$key] = $txt;
            $info[$key] = $txt;
        }
        $elements = $d->getElementsByTagName('SupportingResource');
        foreach ($elements as $e) {
            $this->testValue($e, 'ResourceContentType', '01');
            $this->testValue($e, 'ContentAudience', '00');
            $this->testValue($e, 'ResourceMode', '03');
            $elements2 = $d->getElementsByTagName('ResourceVersion');
            foreach ($elements2 as $e2) {
                $this->testValue($e2, 'ResourceForm', '01');
//                $image[] =
                $info['image'] = trim($this->getValue($e2, 'ResourceLink'), "\n\t ");
            }
        }
        // der vil være records uden image
//        if (!$image) {
//            echo "id:" . $this->id . " ingen image\n";
//            throw new ONIXException('Ingen Image');
//        }

//        $coll['text'] = $text;
//        $coll['resource'] = $image;
//        return $coll;
    }

    private
    function getEdition(DOMElement $d) {
        foreach ($d->getElementsByTagName('EditionStatement') as $e) {
            return $e->nodeValue;
        }
        return null;
    }

    private
    function getLanguage(DOMElement $d) {
        $lang = array();
        $elements = $d->getElementsByTagName('Language');
        foreach ($elements as $e) {
            $this->testValue($e, 'LanguageRole', 01);
            $val = $this->getValue($e, 'LanguageCode');
            $lang[] = $val;
        }
        return $lang;
    }

    private
    function getAuthor(DOMElement $d) {
        $author = array();
        $aele = $d->getElementsByTagName('Contributor');
        foreach ($aele as $a) {
            $f17 = $this->getValue($a, 'ContributorRole');
            if (!array_key_exists($f17, $this->list17)) {
                throw new ONIXException(("Unknown ContributorRole: $f17"));
            }
            $fname = $this->getValue($a, 'NamesBeforeKey');
            $lname = $this->getValue($a, 'KeyNames');
            $author[] = array('role' => $this->list17[$f17], 'first' => $fname, 'last' => $lname);
        }
        return $author;
    }

    private
    function getTitle(DOMElement $d) {
        $title = array();
        $this->testValue($d, 'TitleType', 01);
        $element = $d->getElementsByTagName('TitleElement');
        $title['partno'] = $this->getValue($element->item(0), 'PartNumber');
        $title['text'] = $this->getValue($element->item(0), 'TitleText');
        $title['subtitle'] = $this->getValue($element->item(0), 'Subtitle');
        return $title;

    }

    private
    function testValue(DOMElement $e, $name, $value) {
        $val = $e->getElementsByTagName($name)->item(0)->nodeValue;
        if ($val != $value) {
            throw new ONIXException(("Unknown $name: $val, should have been: $value"));
        }

    }


    private
    function getValue($p, $name) {
        if ($p) {
            if ($e = $p->getElementsByTagName($name)->item(0)) {
                return $e->nodeValue;
            }
        }
        return false;
    }

    function getXml() {
        return $this->workdoc->saveXML();
    }
}