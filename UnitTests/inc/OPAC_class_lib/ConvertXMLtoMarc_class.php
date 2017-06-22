<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/..";

require_once "$inclnk/OLS_class_lib/marc_class.php";

/**
 * @file ConvertXMLtoMarc_class.php
 * @brief A function to convert XML into danmarc2
 *
 * @author Hans-Henrik Lund
 *
 * @date 17-02-2012
 *
 */
class ConvertXMLtoMarc
{

    private $xmltype;
    private $doc;
    private $eReolenPhus = "eReolenPhus";
    private $eReolenWell = "eReolenWell";
    private $eReolenLicensPhus = "eReolenLicensPhus";
    private $eReolenLicensWell = "eReolenLicensWell";
    private $eReolenKlikPhus = "eReolenKlikPhus";
    private $eReolenKlikWell = "eReolenKlikWell";
    private $NetlydbogPhus = "NetlydbogPhus";
    private $NetlydbogWell = "NetlydbogWell";
    private $NetlydbogLicensPhus = "NetlydbogLicensPhus";
    private $NetlydbogLicensWell = "NetlydbogLicesnWell";
    private $ebibPhus = "ebibPhus";
    private $ebibWell = "ebibWell";
    private $DeffPhus = "DeffPhus";
    private $DeffWell = "DeffWell";
//    private $eReolenBasis = "eReolenBasis";
    private $publizonBasis = "publizonBasis";
    private $tr;
    private $acccode;
    private $calendar;

    /**
     *
     * contstruct - no parameters
     */
    function __construct(Calendar_class $calendar = null)
    {
        /*
          switch ($xmltype) {
          case $this->eReolenPhus:
          case $this->eReolenWell:
          case $this->NetlydbogPhus:
          case $this->NetlydbogWell:
          case $this->ebibPhus:
          case $this->ebibWell:
          $this->xmltype = $xmltype;

          }
         *
         */
//    if (!$this->xmltype)
//      throw new Exception('Unknown format');

        $this->calendar = $calendar;

        $this->tr = array(
            "А" => "A",
            "Б" => "B",
            "В" => "V",
            "Г" => "G",
            "Д" => "D",
            "Е" => "E",
            "Ё" => "Yo",
            "Ж" => "Zh",
            "З" => "Z",
            "И" => "I",
            "Й" => "J",
            "К" => "K",
            "Л" => "L",
            "М" => "M",
            "Н" => "N",
            "О" => "O",
            "П" => "P",
            "Р" => "R",
            "С" => "S",
            "Т" => "T",
            "У" => "U",
            "Ф" => "F",
            "Х" => "Kh",
            "Ц" => "Ts",
            "Ч" => "Ch",
            "Ш" => "Sh",
            "Щ" => "Sch",
            "Ъ" => "",
            "Ы" => "Y",
            "Ь" => "",
            "Э" => "E",
            "Ю" => "Yu",
            "Я" => "Ja",
            "а" => "a",
            "б" => "b",
            "в" => "v",
            "г" => "g",
            "д" => "d",
            "е" => "e",
            "ё" => "yo",
            "ж" => "zh",
            "з" => "z",
            "и" => "i",
            "й" => "j",
            "к" => "k",
            "л" => "l",
            "м" => "m",
            "н" => "n",
            "о" => "o",
            "п" => "p",
            "р" => "r",
            "с" => "s",
            "т" => "t",
            "у" => "u",
            "ф" => "f",
            "х" => "kh",
            "ц" => "ts",
            "ч" => "ch",
            "ш" => "sh",
            "щ" => "sch",
            "ъ" => "",
            "ы" => "y",
            "ь" => "",
            "э" => "e",
            "ю" => "yu",
            "я" => "ja",
            "—" => "",
            "–" => "-"
        );
        $this->acccode = 'ACC';
    }

    function setACCcode($code)
    {
        $this->acccode = $code;
    }

    private function getData($doc, $element)
    {
        if ($doc->getElementsByTagName($element)->item(0) != NULL) {

            return $doc->getElementsByTagName($element)->item(0)->nodeValue;
        } else {
            return "";
        }
    }

    private function replace_num_entity($ord)
    {

        $ord = $ord[1];
        if (preg_match('/^x([0-9a-f]+)$/i', $ord, $match)) {
            $ord = hexdec($match[1]);
        } else {
            $ord = intval($ord);
        }

        $no_bytes = 0;
        $byte = array();

        if ($ord < 128) {
            return chr($ord);
        } elseif ($ord < 2048) {
            $no_bytes = 2;
        } elseif ($ord < 65536) {
            $no_bytes = 3;
        } elseif ($ord < 1114112) {
            $no_bytes = 4;
        } else {
            return;
        }

        switch ($no_bytes) {
            case 2: {
                $prefix = array(31, 192);
                break;
            }
            case 3: {
                $prefix = array(15, 224);
                break;
            }
            case 4: {
                $prefix = array(7, 240);
            }
        }

        for ($i = 0; $i < $no_bytes; $i++) {
            $byte[$no_bytes - $i - 1] = (($ord & (63 * pow(2, 6 * $i))) / pow(2, 6 * $i)) & 63 | 128;
        }

        $byte[0] = ($byte[0] & $prefix[0]) | $prefix[1];

        $ret = '';
        for ($i = 0; $i < $no_bytes; $i++) {
            $ret .= chr($byte[$i]);
        }
        return $ret;
    }

    /**
     *
     * @param type $xml
     * @throws Exception
     */
    function loadXML($xml)
    {


        if (!$xml) {
            throw new Exception('The xml record is empty');
        }

        $from = array("\n", "\t", '&#x2013;', '&#x201C;', '&#x201D;', '&#x2019;', '&#x2026;', '*');
        $to = array(" ", " ", '-', '"', '"', "'", "...", '@*');
        $xml = str_replace($from, $to, $xml);
        $xml = preg_replace_callback('/&#([0-9a-fx]+);/mi', 'self::replace_num_entity', $xml);
        $xml = strtr($xml, $this->tr);
        $this->doc = new DOMDocument();
        $this->doc->loadXML($xml);
    }

    function getFormatType()
    {
        $formatelements = $this->doc->getElementsByTagName('formats');
        foreach ($formatelements as $formatelement) {
            $format_id = $this->getData($formatelement, 'format_id');
            if ($format_id == '50') {
                return 'pdf';
            }
            if ($format_id == '58') {
                return 'epub';
            }
        }
        return 'nly';
    }

//    function mediaservice2marc(mediadb $mediadb, $seqno, $status) {
//
//        $dd = date('Ymd');
//
//        $arr = $mediadb->getInfoData($seqno);
//        $faust = $arr[0]['faust'];
//        $newfaust = $arr[0]['newfaust'];
//        $newfaustprinted = $arr[0]['newfaustprinted'];
//        $printedfaust = $arr[0]['printedfaust'];
//        $publicationdate = $arr[0]['publicationdate'];
//        $isbn13 = $arr[0]['isbn13'];
//        $expisbn = $arr[0]['expisbn'];
//        $filetype = $arr[0]['filetype'];
//        $choice = $arr[0]['choice'];
//        $promat = $arr[0]['promat'];
//        $initials = $arr[0]['initials'];
//        $is_in_basis = $arr[0]['is_in_basis'];
////        $bkxwc = $arr[0]['bkxwc'];
//        $xmltype = 'publizonBasis';
//
//        $notes = $mediadb->getNoteData($seqno);
//        $f991 = "991 00";
//
//        $f07 = "";
//        $d08 = "d08 00";
//        if ($notes) {
//            foreach ($notes as $note) {
//                if ($note['type'] == 'f991') {
//                    $f991 .= '*o' . $note['text'];
//                }
//                else {
//                    $d08 .= '*a' . $note['text'];
//                }
//            }
//        }
//
//        $cho = explode(' ', $choice);
//        foreach ($cho as $val) {
//            if ($val == 'BKMV') {
//                $d08 .= '*aSat til BKM-vurdering';
//            }
//        }
//        if ($newfaustprinted and !$promat) {
//            $f07 .= 'f07 00*l' . $newfaustprinted;
//        }
//
//        if ($printedfaust) {
//            $f07 .= 'f07 00*l' . $printedfaust;
//        }
////            if ($status == 'ProgramMatch' || $status == 'Template' || $status == 'UpdateBasis') {
////                if ($faust) {
////                    $f07 .= '*l' . $faust;
////                }
////            }
//
//
//        if ($status == 'ProgramMatch') {
//            $d08 .= '*oProgramMatch';
//        }
//        else {
//            if ($initials) {
//                $d08 .= "*o$initials";
//            }
//            else {
//                $d08 .= "*odmat";
//            }
//        }
//        if ($publicationdate > $dd) {
//            $pd = substr($publicationdate, 6, 2) . "." . substr($publicationdate, 4, 2) . "." . substr($publicationdate, 0, 4);
//            $d08 .= "*aforventet udgivelsesdato $pd";
//        }
//
//        if (count($arr[0]['originalxml'])) {
//            $this->loadXML($arr[0]['originalxml']);
//            $isomarc = $this->Convert2MarcMediaservice($faust, $xmltype, $d08, $f991, $f07, $choice, $arr[0]);
//            return array('isomarc' => $isomarc, 'info' => $arr[0]);
//        }
//        return false;
//    }

    /**
     * @param $faust
     * @param $xmltype
     * @param $d08
     * @param $f991
     * @param $choice
     * @param $dbstatus
     * @return string
     * @throws Exception
     */
    function Convert2MarcMediaservice($faust, $xmltype, $d08, $f512, $f991, $f07, $choice, $info)
    {

        $status = 'n';

        if ($xmltype) {
            $this->xmltype = "";
            switch ($xmltype) {
                case $this->publizonBasis:
                    $this->xmltype = $xmltype;
            }
            if (!$this->xmltype) {
                throw new Exception('Unknown format');
            }
        }

        $doc = $this->doc;
        $pubdate = $this->getData($doc, 'PublicationDate');
        if (!$pubdate) {
            $pubdate = date('d-m-Y');
        }
        $arr = explode('-', $pubdate);
        $publicationdate = $arr[2];
//        $pdate = str_replace('-', '', substr($pubdate, 0, 10));

        $weekcode = date('YW');
//        $dbfdbrcode = date('YW');
        if ($this->calendar) {
            $dbfdbrarr = $this->calendar->getWeekCodesOfTheDay();
            $dbfdbrcode = $dbfdbrarr['DBF'];
        }

        $more032 = $f06txt = "";
        $f008v = '8';
        $bkm = false;
        $dbr = false;
        $parts = explode(' ', $choice);
        foreach ($parts as $part) {
            if ($part == 'DBR') {
                $dbr = true;
                $more032 = "*aDBR999999";
            }
            if ($part == 'BKMV') {
                $bkm = true;
            }
            if ($part == 'V' or $part == 'B' or $part == 'S' or $part == 'L') {
                $f06txt .= '*b' . strtolower($part);
                $bkm = true;
            }
        }
        if ($bkm) {
            $dbr = false;
        }
        if ($dbr) {
            $more032 = "*aDBR$dbfdbrcode";
            $f008v = '9';
        }

        if (array_key_exists('bkxwc', $info)) {
            if ($info['bkxwc']) {
                if ($this->acccode == 'ACT') {
                    $more032 .= "*xBKX" . '999999';
                } else {
                    $more032 .= "*xBKX" . $info['bkxwc'];
                }
            }
        }

        $language = $this->getData($doc, 'Language');
        if (!$language) {
            $language = 'und';
        }

        $isbn13 = $this->getData($doc, 'Identifier');
        $getbook = "http://dmat.dbc.dk/eVALU/get.php?isbn=$isbn13";

        $title = $this->getData($doc, "Title");
        $subtitle = trim($this->getData($doc, 'SubTitle'));

        $seriestitle = $this->getData($doc, 'NameOfBookSeries');
        $partnumber = $this->getData($doc, 'PartNumber');
        $edition = $this->getData($doc, 'Edition');

        $frontpageurl = '';
        $images = $doc->getElementsByTagName('Images');
        foreach ($images as $image) {
            $eimages = $doc->getElementsByTagName('Image');
            foreach ($eimages as $eimage) {
                $att = $eimage->getAttribute('Type');
                if ($att == 'Forside') {
                    $frontpageurl = '*f' . trim($eimage->nodeValue);
                }
            }
        }

        $teaser = $this->getData($doc, 'SampleUrl');

        $contribs = array();
        $contributors = $doc->getElementsByTagName('Contributor');
        foreach ($contributors as $contributor) {
            $con = array();
            $con['type'] = $this->getData($contributor, 'ContributorRoleName');
            $con['h'] = $this->getData($contributor, 'NamesBeforeKey');
            $con['a'] = $this->getData($contributor, 'KeyNames');
            $contribs[] = $con;
        }

        $format = $this->getData($doc, 'FileType');

        $publishername = $this->getData($doc, 'PublisherName');
        $imprintname = $this->getData($doc, 'ImprintName');
        if ($publishername && $imprintname == "") {
            $imprintname = $publishername;
        }
        if ($imprintname == $publishername) {
            $publishername = "";
        }

        $description = $this->getData($doc, 'ShortDescription');
        $description = mb_substr($description, 0, 200, 'UTF-8');

        $subjects = array();

        $numberofpages = $this->getData($doc, 'NumberOfPages');

        $marcdatetime = date('Ymdhis');
        $marcdate = date('Ymd');

        if ($this->acccode == 'ACT') {
            $f21c = "*chf.";
        } else {
            $f21c = '';
        }

        try {
            $marcclass = new marc;
            $string = "001 00*a$faust*b870970*c$marcdatetime*d$marcdate*fa\n";
            $string .= "004 00*r$status*ae\n";
            $string .= "008 00*tm*uf*a$publicationdate*bdk*l$language*nb*v$f008v\n";
            $string .= "009 00*aa*gxe\n";
            $string .= "021 00*e$isbn13$f21c\n";
            $string .= "032 00*x" . $this->acccode . "$weekcode$more032\n";
            foreach ($subjects as $subject) {
                $string .= "088 00*a" . trim($subject) . "\n";
            }

            $strngtitle = "245 00";
//            if ($partnumber) {
//                $strngtitle .= "*n$partnumber";
//            }

//            $strngtitle .= '*a' . str_replace('*', '@*', $title);
            $strngtitle .= "*a$title";
            if ($subtitle) {
//                $strngtitle .= '*c' . str_replace('*', '@*', $subtitle);
                $strngtitle .= "*c$subtitle";
            }
            if ($seriestitle) {
//                $strngtitle .= '*b' . str_replace('*', '@', $seriestitle);
                $strngtitle .= "*b$seriestitle";
            }
//            $strngtitle = str_replace('*', '@*', $strngtitle);
            $string .= "$strngtitle\n";

            if ($edition) {
                $string .= "250 00*a$edition\n";
            }

            $string .= "260 00*b$imprintname";
//            if ($publishername) {
//                $string .= "*t$publishername";
//            }
            $string .= '*g[sælges på internettet]';
            $string .= "*c$publicationdate";
            $string .= "\n";
            if ($this->acccode == 'ACT') {
                $string .= "300 00*asider\n";
            }
//            if ($description) {
//                $string .= "504 00*a" . $description . "\n";
//            }


            if ($f512) {
                $string .= "$f512\n";
            } else {
                if ($format == 'epub') {
                    $string .= "512 00*aDownloades i EPUB-format\n";
                    $suby = "*yEPUB-format";
                }
                if ($format == 'pdf') {
                    $string .= "512 00*aDownloades i PDF-format\n";
                    $suby = "*yPDF-format";
                }
            }
            if ($dbr) {
//                $string .= "512 00*aMaskinelt dannet beskrivelse baseret på distributørdata og beskrivelsen af det fysiske forlæg\n";
                $string .= "512 00*aMaskinelt dannet beskrivelse baseret på distributørdata\n";
            }
            if (!$dbr) {
                $string .= "652 00*mNY TITEL\n";
            } else {
                $string .= "652 00*mUden klassemærke\n";
            }


            $contriburtor_names = array(
                'By (author)',
                'Read by',
                'Translated by',
                'Edited by',
                'Illustrated by',
                'revised by',
                'Revised by'
            );
            foreach ($contribs as $con) {
                $type = str_replace($contriburtor_names, array(
                    'aut',
                    'dkind',
                    'trl',
                    'edt',
                    'ill',
                    'edt',
                    'edt'
                ), $con['type']);
//                if ($dbr) {
//                    $string .= "720 00*o" . $con['h'] . " " . $con['a'] . "\n";
//                } else {
                $string .= "720 00*o" . $con['h'] . " " . $con['a'] . "*4" . $type . "\n";
//                }
            }

            if (strlen($f991) > 6) {
                $string .= "$f991\n";
            }


            if (strlen($d08) > 6) {
                $string .= "$d08\n";
            }
            switch ($this->xmltype) {
                case $this->publizonBasis:
                    $formatName = "Publizon";
            }
            if ($this->acccode != 'ACT') {
                $string .= "d70 00*b$formatName\n";
            }
            if ($f06txt) {
                $string .= "f06 00$f06txt\n";
            }

            if (strlen($f07) > 6) {
                $string .= "$f07\n";
            }
            $string .= "f21 00*a$getbook*l$formatName$frontpageurl*n$isbn13\n";

            $faust_uden = str_replace(' ', '', $faust);
//            $string .= "n51 00*a" . $pdate . "\n";
            $string .= "s10 00*aDBC\n";
            $string .= "z99 00*a$formatName\n";

            $string = utf8_decode($string);

            $marcclass->fromString($string);
            if (($size = $marcclass->isoSize() > 999999)) {
                echo "for stor\n";
            }
            $marc = $marcclass->toIso();

            //
        } catch (marcException $e) {
            echo $e . "\n";
            exit;
        }

        return $marc;
    }

    /**
     * @param $faust
     * @param $status
     * @param string $xmltype
     * @return string
     * @throws Exception
     */
    function Convert2Marc($faust, $status, $xmltype = "")
    {

        if ($xmltype) {
            $this->xmltype = "";
            switch ($xmltype) {
                case $this->eReolenPhus:
                case $this->eReolenWell:
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
                case $this->NetlydbogPhus:
                case $this->NetlydbogWell:
                case $this->NetlydbogLicensPhus:
                case $this->ebibPhus:
                case $this->ebibWell:
                case $this->DeffPhus:
                case $this->DeffWell:
                    $this->xmltype = $xmltype;
            }
            if (!$this->xmltype) {
                throw new Exception('Unknown format');
            }
        }
        $doc = $this->doc;
        $pubdate = $this->getData($doc, "first_published");
        if (!$pubdate) {
            $pubdate = date('d-m-Y');
        }
        $arr = explode('-', $pubdate);
        $publicationdate = $arr[0];
        $pdate = str_replace('-', '', substr($pubdate, 0, 10));

        $language = 'dan';
        $la = $this->getData($doc, 'language');
        if ($la == 'en') {
            $language = 'eng';
        }
        if ($la == '') {
            $language = 'und';
        }


        $isbn = $isbn13 = "";
        $external_ids = $doc->getElementsByTagName('external_ids');
        foreach ($external_ids as $external_id) {
            $id_type = $this->getData($external_id, 'id_type');
            $id = $this->getData($external_id, 'id');
            if ($id_type == 'ISBN') {
                $isbn = $id;
            }
//            if ( $id_type == 'ISBN13') $isbn13 = $id;
            if ($id_type == 'ISBN13' || $id_type == 'GTIN13') {
                $isbn13 = $id;
            }
        }

        $title = $this->getData($doc, "title");

        $frontpageurl = str_replace('_', '@0332', $this->getData($doc, 'coverimage'));
        $frontpageurl = '*f' . strtolower($frontpageurl);
        $teaser = str_replace('_', '@0332', $this->getData($doc, 'link'));
        // https://download.pubhub.dk/samples/9788711414569.epub
        if (strlen($teaser) == 0) {
            $teaser = 'http://samples.pubhub.dk/dummy.xxxx';
        }

        $contribs = array();
        $contributors = $doc->getElementsByTagName('contributors');
        foreach ($contributors as $contributor) {
            $con = array();
            $con['type'] = $this->getData($contributor, 'type');
            $con['h'] = $this->getData($contributor, 'first_name');
            $con['a'] = $this->getData($contributor, 'family_name');
            $contribs[] = $con;
        }

        $formats = array();
        $format_ids = 0;
        $formatelements = $doc->getElementsByTagName('formats');
        foreach ($formatelements as $formatelement) {
            $form = array();
            $format_id = $this->getData($formatelement, 'format_id');

            if ($format_id == '50') {
                $format_ids += 1;
            }
            if ($format_id == '58') {
                $format_ids += 2;
            }

            $form['format_id'] = $format_id;
            $form['name'] = $this->getData($formatelement, 'name');
            $form['size_bytes'] = $this->getData($formatelement, 'size_bytes');
            $form['comment'] = $this->getData($formatelement, 'comment');
            $duration_minutes = $this->getData($formatelement, 'duration_minutes');
            $duration_hours = (int)($duration_minutes / 60);
            $duration_minutes = $duration_minutes - ($duration_hours * 60);
            $form['play_time'] = $duration_hours . " t., " . $duration_minutes . " min.";
            $formats[] = $form;
        }

        $publishername = '';
        $pubs = $doc->getElementsByTagName('publisher');
        foreach ($pubs as $pub) {
            $publishername = $this->getData($pub, 'name');
        }
        // Unicode 201D is 'RIGHT DOUBLE QUOTATION MARK'
        $description = $doc->getElementsByTagName('description')->item(1)->nodeValue;
        $description = str_replace(chr(9), '', str_replace("\n", " ", strip_tags(html_entity_decode($description))));
        if (strlen($description) > 9990) {
            $description = mb_substr($description, 0, 9990, 'UTF-8') . " ....";
        }

        $subjects = array();
        $categories = $doc->getElementsByTagName('categories');
        foreach ($categories as $category) {
            $type = $category->getAttribute('type');
            if ($type == "BIC_code") {
                $subjects[] = $category->nodeValue;
            }
        }

        $marcdatetime = date('Ymdhis');
        $marcdate = date('Ymd');
//        $ugekode = 'ACC' . date('YW');

        try {
            $marcclass = new marc;
            $string = "001 00*a$faust*b870970*c$marcdatetime*d$marcdate*fa\n";
            $string .= "004 00*r$status*ae\n";
            switch ($this->xmltype) {
                case $this->NetlydbogPhus:
                case $this->NetlydbogWell:
                case $this->NetlydbogLicensPhus:
                    $string .= "005 00*zp\n";
                    break;
            }
            switch ($this->xmltype) {
                case $this->eReolenPhus:
                case $this->eReolenWell:
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
                case $this->ebibPhus:
                case $this->ebibWell:
                case $this->DeffPhus:
                case $this->DeffWell:
                    $string .= "008 00*tm*uf*a$publicationdate*bdk*l$language*nb*v7\n";
                    break;
                case $this->NetlydbogPhus:
                case $this->NetlydbogWell:
                case $this->NetlydbogLicensPhus:
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
                case $this->ebibPhus:
                case $this->ebibWell:
                    $string .= "008 00*tm*uf*a$publicationdate*bdk*l$language*nb*v7\n";
            }
            switch ($this->xmltype) {
                case $this->eReolenPhus:
                case $this->eReolenWell:
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
                case $this->ebibPhus:
                case $this->ebibWell:
                case $this->DeffPhus:
                case $this->DeffWell:
                    $string .= "009 00*aa*gxe\n";
                    break;
                case $this->NetlydbogPhus:
                case $this->NetlydbogWell:
                    $string .= "009 00*ar*gxe\n";
                    break;
            }

            if ($isbn) {
                $string .= "021 00*a" . $isbn . "\n";
            }
            if ($isbn13) {
                $string .= "021 00*e" . $isbn13 . "\n";
            }


            switch ($this->xmltype) {
                case $this->eReolenPhus:
                case $this->eReolenWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
                    $string .= "032 00*xACC999999*xERE999999\n";
                    break;
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                    $string .= "032 00*xACC999999*xERL999999\n";
                    break;
//                case $this->ebibPhus:
//                case $this->ebibWell:
//                    $string .= "032 00*xACC999999*xEBI999999\n";
//                    break;
                case $this->NetlydbogPhus:
                case $this->NetlydbogWell:
                    $string .= "032 00*xACC999999*xNLY999999\n";
                    break;
                case $this->NetlydbogLicensPhus:
                case $this->NetlydbogLicensWell:
                    $string .= "032 00*xACC999999*xNLL999999\n";
                    break;
                case $this->DeffPhus:
                case $this->DeffWell:
                    $string .= "032 00*xACC999999*xDEF999999\n";
            }
            switch ($this->xmltype) {
                case $this->eReolenWell:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikWell:
                case $this->NetlydbogWell:
                case $this->NetlydbogLicensWell:
                case $this->ebibWell:
                case $this->DeffWell:
                    foreach ($subjects as $subject) {
                        $string .= "088 00*a" . trim($subject) . "\n";
                    }
                    break;
            }

            switch ($this->xmltype) {
                case $this->eReolenWell:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikWell:
                case $this->NetlydbogWell:
                case $this->NetlydbogLicensWell:
                case $this->ebibWell:
                case $this->DeffWell:
                    foreach ($contribs as $key => $con) {
                        if ($con['type'] == 'By (author)') {
                            $string .= "100 00*a" . $con['a'];
                            if (array_key_exists('h', $con)) {
                                $string .= "*h" . $con['h'];
                            }
                            $string .= "\n";
                            $contribs[$key]['type'] = 'used';
                            break;
                        }
                    }
                    break;
            }

            $string .= "245 00*a$title\n";

            switch ($this->xmltype) {
                case $this->eReolenPhus:
                case $this->eReolenWell:
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
//                case $this->ebibPhus:
//                case $this->ebibWell:
                case $this->DeffPhus:
                case $this->DeffWell:
                    $string .= "260 00*b$publishername*g[sælges på internettet]*c$publicationdate\n";
                    break;
                case $this->NetlydbogPhus:
                case $this->NetlydbogWell:
                case $this->NetlydbogLicensPhus:
                    $string .= "260 00*b$publishername*c$publicationdate\n";
            }
            if ($formats[0]['play_time']) {
                switch ($this->xmltype) {
                    case $this->NetlydbogPhus:
                    case $this->NetlydbogWell:
                    case $this->NetlydbogLicensPhus:
                        $string .= "300 00*l" . $formats[0]['play_time'] . "\n";
                        break;
                }
            }
            switch ($this->xmltype) {
                case $this->NetlydbogWell:
                case $this->DeffWell:
                    if ($description) {
                        $string .= "504 00*a" . $description . "\n";
                    }
                    break;
            }
            foreach ($formats as $format) {
                if ($format['format_id'] == '50') {
                    $string .= "512 00*aDownloades i PDF-format\n";
                    $suby = "*yPDF-format";
                }
                if ($format['format_id'] == '58') {
                    $string .= "512 00*aDownloades i EPUB-format\n";
                    $suby = "*yEPUB-format";
                }
            }

            switch ($this->xmltype) {
                case $this->NetlydbogPhus:
                case $this->NetlydbogWell:
                case $this->NetlydbogLicensPhus:
                    // ændret iflg. mail fra Line, den 23.4.2013, kl. 14.30
                    $string .= "512 00*aDownloades i MP3-format\n";
                    // $string .= "512 00*aVed køb: Downloades i MP3-format\n";
                    // $string .= "512 00*aVed lån: Downloades i WMA-format eller streames\n";
                    break;
            }

            switch ($this->xmltype) {
                case $this->eReolenWell:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikWell:
                case $this->NetlydbogWell:
                case $this->ebibWell:
                case $this->DeffWell:
                    foreach ($contribs as $con) {
                        //$type = str_replace(array(utf8_encode('f�rfattare'),utf8_encode('uppl�sare'),utf8_encode('�vers�ttare')),array('aut','dkind','trl'),$con['type']);
                        if ($con['type'] != 'used') {
                            $string .= "700 00*a" . $con['a'];
                            if (array_key_exists('h', $con)) {
                                if (strlen($con['h'])) {
                                    $string .= "*h" . $con['h'];
                                }
                            }
                            //                    echo "con[type] = [" . $con['type'] . "]\n";
                            if ($con['type'] == 'Revised by') {
                                $string .= "*b(bearb. af.)";
                            }
                            if ($con['type'] == 'Translated by') {
                                $string .= "*b(Oversat af)";
                            }
                            if ($con['type'] == 'Revised by') {
                                $string .= "*b(redigeret af)";
                            }
                            $string .= "\n";
                        }
                    }
                    break;
                case $this->NetlydbogPhus:
                case $this->NetlydbogLicensPhus:
                case $this->eReolenPhus:
                case $this->eReolenLicensPhus:
                case $this->eReolenKlikPhus:
                    $contriburtor_names = array('By (author)', 'Read by', 'Translated by');
                    foreach ($contribs as $con) {
                        $type = str_replace($contriburtor_names, array('aut', 'dkind', 'trl'), $con['type']);
                        $string .= "720 00*o" . $con['h'] . " " . $con['a'] . "*4" . $type . "\n";
                    }
                    break;
            }

            $faust_uden = str_replace(' ', '', $faust);

            switch ($this->xmltype) {
                case $this->eReolenPhus:
                case $this->eReolenWell:
//                    $string .= "856 00*zAdgang til lån hos eReolen.dk*uhttps://ereolen.dk/ting/object/150028:$faust_uden$suby\n";
//                    $string .= "d70 00*bDiv\n";
//                    break;
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
//                    $string .= "856 00*zAdgang til lån hos eReolen.dk*uhttps://ereolen.dk/ting/object/870970-basis:$faust_uden$suby\n";
                    $string .= "856 00*zAdgang til lån hos eReolen.dk*uhttps://ereolen.dk/ting/object/870970-basis:$faust_uden\n";
                    $string .= "d70 00*bDiv\n";
                    break;
//                case $this->ebibPhus:
//                case $this->ebibWell:
//                    $string .= "856 00*zAdgang til lån hos ebib.dk*uhttps://www.ebib.dk/Pages/BookDetails.html#$isbn13$suby\n";
//                    $string .= "d70 00*bDiv\n";
//                    break;
//        case $this->DeffPhus:
                case $this->DeffWell:
                    $string .= "856 00*zAdgang til lån hos Deff.dk*uhttps://www.ebib.dk/Pages/BookDetails.html#$isbn13$suby\n";
                    $string .= "d70 00*bDiv\n";
                    break;
                case $this->NetlydbogPhus:
                case $this->NetlydbogLicensPhus:
                case $this->NetlydbogWell:
                    $string .= "856 00*zAdgang til lån hos eReolen.dk*uhttps://ereolen.dk/ting/object/870970-basis:$faust_uden*yWMA-format eller streaming\n";
                    $string .= "d70 00*bDiv\n";
            }
            switch ($this->xmltype) {
                case $this->eReolenPhus:
                case $this->eReolenWell:
                case $this->eReolenLicensPhus:
                case $this->eReolenLicensWell:
                case $this->eReolenKlikPhus:
                case $this->eReolenKlikWell:
                    $formatName = "eReolen";
                    $string .= "f21 00*ahttp://dmat.dbc.dk/eVALU/get.php?isbn=$isbn13\n";
                    break;
                case $this->ebibPhus:
                case $this->ebibWell:
                    $formatName = "ebib";
                    break;
                case $this->DeffPhus:
                case $this->DeffWell:
                    $formatName = "Deff";
                    break;
                case $this->NetlydbogPhus:
                case $this->NetlydbogLicensPhus:
                case $this->NetlydbogWell:
                    $formatName = "Netlydbog";
            }
            $string .= "f21 00*a$teaser*l$formatName$frontpageurl*n$id\n";

            $string .= "n01 00*v" . $pdate . "\n";
            $string .= "s10 00*aDBC\n";
            $string .= "z99 00*a$formatName\n";
            $string = utf8_decode($string);

            $marcclass->fromString($string);
            if (($size = $marcclass->isoSize() > 999999)) {
                echo "for stor\n";
            }
            $marc = $marcclass->toIso();
        } catch (marcException $e) {
            echo $e . "\n";
            exit;
        }

        return $marc;
    }

}
