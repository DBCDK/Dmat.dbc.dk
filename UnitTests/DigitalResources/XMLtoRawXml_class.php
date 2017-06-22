<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

/**
 * @file ConvertXMLtoMarc_class.php
 * @brief A function to convert XML into danmarc2
 *
 * @author Hans-Henrik Lund
 *
 * @date 24-04-2012
 *
 */
class XMLtoRawXml {

    private $xmltype;
    private $doc;

    /**
     *
     * @param type $xmltype The name of the xmltype ('PubHub','eLib')
     * @throws Exception
     */
    function __construct($xmltype) {
        switch ($xmltype) {
        case 'RawXml':
            $this->xmltype = $xmltype;
        }
        if (!$this->xmltype) {
            throw new Exception('Unknown xmltype');
        }
    }

    /**
     *
     * @param type $xml
     * @throws Exception
     */
    function loadXML($xml) {
        if (!$xml)
            throw new Exception('The xml record is empty');
        $xml = str_replace('<product xmlns="http://pubhub.dk/">', '<product>', $xml);

        $this->doc = new DOMDocument();
        $this->doc->loadXML($xml);
    }

    function Convert2Raw($faust, $status, $product_format) {

        $doc = new DOMDocument('1.0', 'UTF-8');
        $doc->formatOutput = true;

        $faust = str_replace(' ', '', $faust);

        switch ($product_format) {
        case 'Netlydbog':
        case 'eReolen':
            break;
        case 'eReolenLicens':
            $product_format = 'eReolen';
            break;
        }

        $element = $doc->createElement('wroot');
        $doc->appendChild($element);
        $nxt = $doc->getElementsByTagName('wroot');
        $element = $doc->createElement('wfaust', $faust);
        $nxt->item(0)->appendChild($element);
        $element = $doc->createElement('status', $status);
        $nxt->item(0)->appendChild($element);
        $element = $doc->createElement('wformat', $product_format);
        $nxt->item(0)->appendChild($element);

//        $productelement = $this->doc->getElementsByTagName('product')->item(0);
//        $xx = $productelement->getAttribute('xmlns');
//        $res = $productelement->removeAttribute('xmlns');
//        $productelement->setAttribute('xmlns', '');
//        $xxml = $this->doc->saveXML();
        $node = $this->doc->getElementsByTagName('*')->item(0);

        $node = $doc->importNode($node, true);

        $nxt->item(0)->appendChild($node);
//        $xml = $doc->saveXML();
        // der bliver indsat et namespace "default" som jeg ikke kan spore hvor kommer
        // fra.  Derfor denne fjern default ting.
//        $element = $doc->childNodes->item(0);
//        $doc->removeAttributeNS('http://pubhub.dk/', 'default');

        $xml = $doc->saveXML();

        return $xml;
    }

}
