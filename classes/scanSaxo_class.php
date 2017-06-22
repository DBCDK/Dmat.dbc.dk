<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 01-09-2016
 * Time: 13:35
 */
class scanSaxo_class {

    private $ch;
    private $baseurl;
    private $curl_proxy;
    private $oldDebian;

    public function __construct($config, $setup) {

        $hostname = php_uname('n');
        if ($hostname == 'kiska' or $hostname == 'guesstimate') {
            $this->oldDebian = true;
        } else {
            $this->oldDebian = false;
        }

        $this->ch = curl_init();
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($this->ch, CURLOPT_HEADER, 1);
        curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, 1);
        if ($setup == 'testcase') {
            // wiremocck should be up running with proxy-all="https://www.saxo.com"
            $this->baseurl = "http://devel7.dbc.dk:2613/dk/soeg/boeger";
        } else {
            $this->baseurl = "https://www.saxo.com/dk/soeg/bog";

            $this->curl_proxy = $config->get_value('curl_proxy', 'setup');
            if ($this->curl_proxy) {
                curl_setopt($this->ch, CURLOPT_PROXY, $this->curl_proxy);
            }
        }
    }

    private function getBookDataElements($xml, $class, $endtxt) {
        $xmls = array();
        $offset = 0;
        $newxml = '';
        while ($start = strpos($xml, $class, $offset)) {
            if ($end = strpos($xml, $endtxt, $start)) {
                $start = strpos($xml, '<', $start);
                $xmls[] = "\n<div class='product'><div>" . substr($xml, $start, $end - $start) . "</div>\n";
                $offset = $end;
            }
        }
        return $xmls;
    }


    private function DEPgetELements(domdocument $doc, $elementname, $elvalue) {
        $elements = $doc->getElementsByTagName($elementname);
        foreach ($elements as $element) {
            $val = $element->nodeValue;
            if ($val == $elvalue) {
                return $element;
            }
        }
        return false;
    }

    private function DEPgetData($doc, $element) {
        if ($doc->getElementsByTagName($element)->item(0) != NULL) {

            return $doc->getElementsByTagName($element)->item(0)->nodeValue;
        } else {
            return "";
        }
    }


    public function getPrintedDirect($ebogisbn) {

        $html = $this->getDoc($this->baseurl . "/?query=", $ebogisbn);
//        echo "html:$html\n";
        $xmls = $this->getBookDataElements($html, 'data-product-id', '<script');
        foreach ($xmls as $xml) {
            if ($url = $this->getUrlFromPrintedBook($xml)) {

                $html = $this->getDoc("https:" . $url);

                $xml2s = $this->getBookDataElements($html, 'product__meta', '<hr');

                foreach ($xml2s as $xml2) {
                    if ($bookinfo = $this->getBookInfo($xml2)) {
                        return $bookinfo;
                    }
                }
            }
        }

    }

    function getDoc($url, $rest = '') {
        $url .= urlencode($rest);
        curl_setopt($this->ch, CURLOPT_URL, $url);

        $html = curl_exec($this->ch);
        return $html;
    }

    /**
     * @param $xml
     * @param $title
     * @return bool
     */
    function getUrlFromPrintedBook($xml, $title = '') {
        $doc = new DOMDocument('1.0', 'utf-8');
        $xml = mb_convert_encoding($xml, 'HTML-ENTITIES', 'UTF-8');

        if ($this->oldDebian) {
            @$doc->loadHTML($xml);
        } else {
            @$doc->loadHTML($xml, LIBXML_NOWARNING);
        }
        $xpath = new DOMXPath($doc);


        if ($title) {
            // check if it is the right title
            $query = "//h2[@class='title']";
            $query = "//h2";

            $xtitles = $xpath->query($query);
            foreach ($xtitles as $xtitle) {
                $val = html_entity_decode($xtitle->nodeValue);
                if ($val != $title) {
                    return false;
                }
            }
        }

        $q = "//p[@class='product__type']";
        $ptypes = $xpath->query($q);
        foreach ($ptypes as $ptype) {
            $val = $ptype->nodeValue;
            $is_a_book = strpos($val, 'Bog,');
            if (strpos($val, 'brugt')) {
                $is_a_book = false;
            }
            if ($is_a_book) {
                $aelements = $xpath->query('.//a', $entry);
                foreach ($aelements as $aelement) {
                    $txt = strtolower($aelement->nodeValue);
                    if ($txt == 'se mere om bogen') {
                        $url = $aelement->getAttribute('href');
                        return $url;
                    }
                }
            }
        }
    }


    function getBookInfo($xml) {
        $info = array();
        $doc = new DOMDocument();
        $xml = mb_convert_encoding($xml, 'HTML-ENTITIES', 'UTF-8');
        if ($this->oldDebian) {
            @$doc->loadHTML($xml);
        } else {
            @$doc->loadHTML($xml, LIBXML_NOWARNING + LIBXML_NOERROR);
        }

        $xpath = new DOMXPath($doc);
        $query = "//dl";
        $dls = $xpath->query($query);

        foreach ($dls as $dl) {
            $query = ".//dt | ./dd";
            $dtdds = $xpath->query($query, $dl);
            $odd = false;
            $key = '';
            foreach ($dtdds as $dtdd) {
                if ($odd) {
                    $info[$key] = $dtdd->nodeValue;
                    $odd = false;
                } else {
                    $key = trim($dtdd->nodeValue, "\n\t :");
                    $odd = true;
                }

            }
        }
        return $info;
    }

    public function getByTitleAuthor($title, $author) {


        $html = $this->getDoc($this->baseurl . "/?query=", $title . ' ' . $author);
//        var_dump($this->baseurl);
//        echo "html2: $html\n";
        $xmls = $this->getBookDataElements($html, 'data-product-id', '<script');

        foreach ($xmls as $xml) {
            if ($url = $this->getUrlFromPrintedBook($xml, $title)) {

                $html = $this->getDoc("https:" . $url);

                $xml2s = $this->getBookDataElements($html, 'product__meta', '<hr');

                foreach ($xml2s as $xml2) {
                    if ($bookinfo = $this->getBookInfo($xml2)) {
                        return $bookinfo;
                    }
                }
            }
        }
    }


    function getByValue($xml, $elementname, $elementvalue, $txt = false) {
        $elements = $this->get_all_elements($xml, $elementname);
        if ($elements) {
            if ( !$txt) {
                $doc = new DOMDocument();
                $strng = "<root>\n";
                $newdoc = new DOMDocument;
                $newdoc->formatOutput = true;
            }
            foreach ($elements as $element) {
                if (strpos($element, $elementvalue)) {
                    $strng .= $element . "\n";
                }

            }
            if ( !$txt) {
                $strng .= "</root>\n";
                $strng = str_replace('&', '&amp;', $strng);
                $doc->loadXML("$strng");
                return $doc;
            }
            return $strng;

        }
    }

    function get_all_elements($xml, $elementname) {
        $elements = array();

        $sttxt = "<" . $elementname;
        $endtxt = "</" . $elementname . ">";
        $stlngth = strlen($sttxt);
        $endlength = strlen($endtxt);
        $offset = 0;
        while ($start = strpos($xml, $sttxt, $offset)) {
            if ($end = strpos($xml, $endtxt, $start + $stlngth)) {
                $offset = $end + $endlength;
                $elem = substr($xml, $start, $offset - $start) . "\n";
//                if (strpos($elem, $elementvalue)) {
                $elements[] = $elem;
//                    $strng .= $elem;
//                }
            } else {
                break;
            }
        }

        return $elements;
    }
}