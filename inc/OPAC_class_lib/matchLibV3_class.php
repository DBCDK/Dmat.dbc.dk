<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/*
 * @file matchLibV3_class.php
 * @brief as input a xml from Publizon - output those records witch match in Basis and Phus
 *
 * @author Hans-Henrik Lund
 *
 * @date 18-09-2014
 */


$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../";

require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "GetMarcFromBasis_class.php";
//require_once 'mark_best_records.php';
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";

//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";

class matchLibV3 {

    private $getMarcFromBasis;
    private $doc;
    private $xmldata;
    private $verbose;
    private $debugstrng;

    function __construct($ociuser, $ocipasswd, $ocihost) {

        $pwuids = posix_getpwuid(posix_getuid());
        $workdir = "/tmp/PafWorkDir." . $pwuids['name'];
        verbose::log(TRACE, "workdir:$workdir");

        $this->doc = new DOMDocument();
        $this->getMarcFromBasis = new GetMarcFromBasis($workdir, $ociuser, $ocipasswd, $ocihost);
        $this->getMarcFromBasis->phuscredintials('sephus', 'sephus', 'dora11.dbc.dk');
        $this->marc = new marc();
        $this->verbose = false;


//        $this->libV3Api = new LibV3API($ociuser, $ocipasswd, $ocihost);
    }

    function getIsbnFromF21($marc) {
        $isbns = array();
        $f21s = $marc->findSubFields('f21', 'a');
        foreach ($f21s as $f21) {
            $path_parts = pathinfo($f21);
            $isbns[] = trim(basename($f21, $path_parts['extension']), '.');
        }

        return $isbns;
    }

    function getIsbns($marc) {
        $isbns = array();
        $f21s = $marc->findSubFields('021', 'e');
        foreach ($f21s as $f21) {
            $isbns[] = $f21;
        }
        $f21s = $marc->findSubFields('021', 'a');
        foreach ($f21s as $f21) {
            $isbns[] = $f21;
        }
        return $isbns;
    }

    /**
     *
     * @param type $getMarcFromBasis
     * @param type $Identifier
     * @param type $candidates
     * @return type
     */
    function mark_best_records($getMarcFromBasis, $Identifier, $candidates) {

        $startscore = 100;
        if (!$candidates) {
            return $candidates;
        }
        $return = array();
        for ($i = 0; $i < count($candidates); $i++) {
            $score = $candidates[$i]['matchtype'] * $startscore;
            $base = $candidates[$i]['base'];
            $lokalid = $candidates[$i]['lokalid'];
            $bibliotek = $candidates[$i]['bibliotek'];
//        $libv3->set('withAuthor');
//            $libv3->set('hsb', false);
//            $getMarcFromBasis->set('hsb');
//            $getMarcFromBasis->set('withAuthor');
            $getMarcFromBasis->setDataBase($base);
            $records = $getMarcFromBasis->getMarcByLokalidBibliotek($lokalid, $bibliotek);

//            $fname = "data/" . str_replace(' ', '', $lokalid) . ".$bibliotek";
            if ($records) {
                for ($j = 1; $j < count($records); $j++) {
                    $records[0]['hsb'][] = $records[$j];
                }

                $rec = $this->mergemarc($records[0]);
                $strng = "";
                $marc = new marc();
                $cnt = 0;
                $j = 0;
//                foreach ($records as $record) {

//                $marc->fromIso($records[0]['DATA']);
                $marc->fromIso($rec);
//                $a = $marc->toLineFormat(78);
                $isbns1 = $this->getIsbnFromF21($marc);
                $isbns2 = $this->getIsbns($marc);
                $isbns = array_merge($isbns1, $isbns2);

                $subscore = 0;
                $f009as = $marc->findSubFields('009', 'a');
                foreach ($f009as as $f009a) {
                    if ($f009a != 'a') {
                        $subscore = 4;
                    }
                }
                $f009gs = $marc->findSubFields('009', 'g');
                foreach ($f009gs as $f009g) {
                    if ($f009g != 'xx' and $f009g != 'xe') {
                        $subscore = 4;
                    }
                }
                $f032xs = $marc->findSubFields('032', 'x');
                foreach ($f032xs as $f032x) {
                    if (substr($f032x, 0, 3) == 'BKM' or substr($f032x, 0, 3) == 'SFD') {
                        $f06s = $marc->findFields('f06');
                        if (!$f06s) {
                            $subscore = 4;
                        }
                    }
                }

//                $f06s = $marc->findFields('f06');
//                if (!$f06s) {
//                    $subscore = 4;
//                }
                $fs10s = $marc->findSubFields('s10', 'a');
                foreach ($fs10s as $fs10) {
                    if ($fs10 != 'DBC') {
                        $subscore = 4;
                    }
                }
                $score += $subscore;
                $f009gs = $marc->findSubFields('009', 'g');
                foreach ($f009gs as $f009g) {
                    if ($f009g == 'xe') {
                        $score += 16;
                        break;
                    }
                }
                foreach ($isbns as $isbn) {
                    $isbn = str_replace(array(' ', '-'), '', $isbn);
                    if (strlen($isbn) == 10) {
                        $isbn = '978' . substr($isbn, 0, -1) . substr($Identifier, -1);
                    }
                    if ($Identifier == $isbn) {
                        $score += 1;
                        break;
                    }
                }
                $f652s = $marc->findSubFields('652', 'm');
                foreach ($f652s as $f652) {
                    if ($f652 == 'NY TITEL') {
                        $score += 2;
                        break;
                    }
                }
                // hvis en post har felt s11 må den ikke automatisk matche
                $fs11as = $marc->findSubFields('s11', 'a');
                foreach ($fs11as as $f11a) {
                    $score = 85;
                }

                $subscore = 0;
                // hvis sprogkoden fra publizon ikke matcher vores må den ikke automatisk matche.
                $f008ls = $marc->findSubFields('008', 'l');
                foreach ($f008ls as $f008l) {
                    if ($this->xmldata['language']) {
                        if ($f008l != $this->xmldata['language']) {
                            $score = 90;
                        }
                    }
                }

//                }
//                if ($score == 417) {
//                    $score = 315;
//                }
                $candidates[$i]['score'] = $score;
            }
        }
        $more = true;
        while ($more) {
            $more = false;
            $highscore = -1;
            $best = -1;
            for ($i = 0;
                 $i < count($candidates);
                 $i++) {
                if ($candidates[$i]['score'] > 0) {
                    $more = true;
                    if ($candidates[$i]['score'] > $highscore) {
                        $best = $i;
                        $highscore = $candidates[$i]['score'];
                    }
                }
            }
            if ($more) {
                $return[] = $candidates[$best];
                $candidates[$best]['score'] = -1;
            }
        }
        return $return;
    }

    function mergemarc($marcarr) {
        $isomarc = $marcarr['DATA'];
        if (!array_key_exists('hsb', $marcarr)) {
            return $isomarc;
        }
        $marc = new marc();
        $n = new marc();
        $marc->fromIso($isomarc);
        foreach ($marcarr['hsb'] as $marchb) {
            $n->fromIso($marchb['DATA']);
//            $xx = $n->findFields('245');
//            $marc->insert($xx[0]);
//            $xx = $n->findFields('100');
//            $marc->insert($xx[0]);
//            $xx = $n->findFields('260');
//            $marc->insert($xx[0]);
//            $xx = $n->findFields('700');
//            $marc->insert($xx[0]);
//
            $marc->insert($n->findFields('009'));
            $marc->insert($n->findFields('245'));
            $marc->insert($n->findFields('100'));
            $marc->insert($n->findFields('260'));
            $marc->insert($n->findFields('700'));
            $marc->insert($n->findFields('720'));
//            $strng = $marc->toLineFormat();
        }
        $data = $marc->toIso();
        return $data;
    }

    /*
     * Takes a xml record from Pulison API an test it up against
     * Basis and Phus
     * @param string $xml
     * @return array of faust
     */

    function setVerbose() {
        $this->verbose = true;
        $this->debugstrng = "<div class='row'> "
            . "      <div id=recinfo class='medium-8 column'>";
        return;
    }

    function getVerbose() {
        $this->debugstrng .= "</div>\n</div>\n<br/><hr>";
        return $this->debugstrng;
    }

    function match($xml, $isbncandidate = '') {
        $this->doc->loadXML($xml);
//        $title = $this->getData('Title');
        $isbn13 = $this->getData('Identifier');
        $this->prepare();
        $mMarc = array();
        $records = array();


        //try to find marc by title
        if (count($mMarc) == 0) {
            $ccltitles = $this->makeTitleSearchString();
            $found = false;
            foreach ($ccltitles as $ccltitle) {
                verbose::log(DEBUG, "ccltitle:$ccltitle");
                $tstmarc = new marc();
                if ($this->verbose) {
                    $this->debugstrng .= "<div style='color:blue'>ccl search:" . utf8_encode($ccltitle) . "</div>";
                }
                $records = $this->fetchRecords($ccltitle, $records);
                foreach ($records as $base => $marcs) {
                    if ($marcs) {
                        foreach ($marcs as $marc) {
                            $marcrec = $this->mergemarc($marc);
                            $tstmarc->fromIso($marcrec);
                            $strng = $tstmarc->toLineFormat();
                            $tlokalids = $tstmarc->findSubFields('001', 'a');
                            if (array_key_exists($tlokalids[0], $mMarc)) {
                                $this->debugstrng .= " &nbsp;&nbsp;DUBLET $tlokalids[0]<br>";
                                continue;
                            }
                            if ($this->verbose) {
                                $ttitles = $tstmarc->findSubFields('245', 'a');
                                $this->debugstrng .= " &nbsp;&nbsp;Titel:<span style='color:green'>" . utf8_encode($ttitles[0]) . "</span>  - link:";
                                $this->debugstrng .= "<a href = http://dev.dbc.dk/seBasis/?lokalid="
                                    . str_replace(' ', '+', $tlokalids[0])
                                    . " target=_blank  > ";
                                $this->debugstrng .= $tlokalids[0];
                                $this->debugstrng .= "</a ><br>\n";

                            }
                            $pid = $this->compare($marcrec);
                            $this->debugstrng .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Result:";
                            if ($pid) {
                                $lokalid = $pid[0];
                                $bib = $pid[1];
                                $matchType = $pid[2];
                                $mMarc[$lokalid][$bib][$base]['type'] = 'title';
                                $mMarc[$lokalid][$bib][$base]['matchtype'] = $matchType;
                                $mMarc[$lokalid][$bib][$base]['lektoer'] = $pid[3];
                                if ($this->verbose) {
                                    $this->debugstrng .= "<span class='true'>TRUE</span><br>\n";
                                }
                                $found = true;
                            } else {
                                if ($this->verbose) {
                                    $this->debugstrng .= "<span class='false'>FALSE</span><br>\n";
                                }
                            }
                        }
                    }
                }
            }
        }

// try to find marc by author
        if (count($mMarc) == 0) {
//        if (count($mMarc) > 0) {
            $cclauthor = $this->makeAuthorSearchString();
            if ($this->verbose) {
                $this->debugstrng .= "<hr>ccl Author search:$cclauthor <br/>";
                $tstmarc = new marc();
            }
            $records = $this->fetchRecords($cclauthor, $records);
            foreach ($records as $base => $marcs) {
                if ($marcs) {
                    foreach ($marcs as $marc) {
                        $marcrec = $this->mergemarc($marc);
                        $tstmarc->fromIso($marcrec);
                        $tlokalids = $tstmarc->findSubFields('001', 'a');
                        if (array_key_exists($tlokalids[0], $mMarc)) {
                            $this->debugstrng .= " &nbsp;&nbsp;DUBLET $tlokalids[0]<br>";
                            continue;
                        }
                        if ($this->verbose) {
                            $tauthor = $tstmarc->findSubFields('100', 'a');
                            $this->debugstrng .= "&nbsp;&nbsp;Author:<span style='color:green'>" . utf8_encode($tauthor[0]) . "</span> - link:";
                            $this->debugstrng .= "<a href = http://dev.dbc.dk/seBasis/?lokalid="
                                . str_replace(' ', '+', $tlokalids[0])
                                . " target=_blank  > ";
                            $this->debugstrng .= $tlokalids[0];
                            $this->debugstrng .= "</a ><br>\n";
                        }
                        $pid = $this->compare($marcrec);
                        $this->debugstrng .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Result:";
                        if ($pid) {
                            $lokalid = $pid[0];
                            $bib = $pid[1];
                            $matchType = $pid[2];
                            $mMarc[$lokalid][$bib][$base]['type'] = 'author';
                            $mMarc[$lokalid][$bib][$base]['matchtype'] = $matchType;
                            $mMarc[$lokalid][$bib][$base]['lektoer'] = $pid[3];
                            if ($this->verbose) {
                                $this->debugstrng .= "<span class='true'>TRUE</span><br>\n";
                            }
                        } else {
                            if ($this->verbose) {
                                $this->debugstrng .= "<span class='false'>FALSE</span><br>\n";
                            }
                        }
                    }
                }
            }
        } else {
            if ($this->verbose) {
                $this->debugstrng .= "<hr>ccl NO Author search because Title was OK!<br/>";
            }
        }
// try to find marc by isbn13
        $records = $this->fetchRecords($isbn13, $records);
        if ($records) {
            if ($this->verbose) {
                $this->debugstrng .= "<hr><div style='color:blue'>ccl isbn13 search:$isbn13</div> <br>";
            }
            $tstmarc = new marc();
            foreach ($records as $base => $marcs) {
                if ($marcs) {
                    foreach ($marcs as $marc) {
                        $marcrec = $this->mergemarc($marc);
                        $tstmarc->fromIso($marcrec);
                        $tlokalids = $tstmarc->findSubFields('001', 'a');
                        if (array_key_exists($tlokalids[0], $mMarc)) {
                            $this->debugstrng .= " &nbsp;&nbsp;DUBLET $tlokalids[0]<br>";
                            continue;
                        }
                        if ($this->verbose) {
                            $this->debugstrng .= "&nbsp;&nbsp;ISBN <a href = http://dev.dbc.dk/seBasis/?lokalid="
                                . str_replace(' ', '+', $tlokalids[0])
                                . " target=_blank  > ";
                            $this->debugstrng .= $tlokalids[0];
                            $this->debugstrng .= "</a >";
                        }
                        $isbnmatch = true;
                        $pid = $this->compare($marcrec, $isbnmatch);
                        $this->debugstrng .= "&nbsp;&nbsp;Result:";
                        if ($pid) {
                            $lokalid = $pid[0];
                            $bib = $pid[1];
                            $matchType = $pid[2];
                            $mMarc[$lokalid][$bib][$base]['type'] = 'isbn13';
                            $mMarc[$lokalid][$bib][$base]['matchtype'] = $matchType;
                            $mMarc[$lokalid][$bib][$base]['lektoer'] = $pid[3];
                            if ($this->verbose) {
                                $this->debugstrng .= "<span class='true'>TRUE</span>\n";
                            } else {
                                if ($this->verbose) {
                                    $this->debugstrng .= "<span class='false'>FALSE</span><br>\n";
                                }
                            }
                        }
                    }
                }
            }
        }

        // try to find marc by printed isbn13 (source:$isbncandiate)
        $records = $this->fetchRecords($isbncandidate, $records);
        if ($records) {
            if ($this->verbose) {
                $this->debugstrng .= "<hr><div style='color:blue'>ccl printed isbn13 search:\"$isbncandidate\"</div><br>";
            }
            $tstmarc = new marc();
            foreach ($records as $base => $marcs) {
                if ($marcs) {
                    foreach ($marcs as $marc) {
                        $marcrec = $this->mergemarc($marc);
                        $tstmarc->fromIso($marcrec);
                        $tlokalids = $tstmarc->findSubFields('001', 'a');
                        $bibs = $tstmarc->findSubFields('001', 'b');
                        if (array_key_exists($tlokalids[0], $mMarc)) {
                            $this->debugstrng .= " &nbsp;&nbsp;DUBLET $tlokalids[0]<br>";
                            continue;
                        }
                        if ($this->verbose) {
                            $this->debugstrng .= "&nbsp;&nbsp;ISBN <a href = http://dev.dbc.dk/seBasis/?lokalid="
                                . str_replace(' ', '+', $tlokalids[0])
                                . " target=_blank  > ";
                            $this->debugstrng .= $tlokalids[0];
                            $this->debugstrng .= "</a >";
                        }
                        $isbnmatch = true;
//                        $pid = $this->compare($marcrec, $isbnmatch);
//                        $this->debugstrng .= "&nbsp;&nbsp;Result:";
//                        if ($pid) {
                        $lokalid = $tlokalids[0];
                        $bib = $bibs[0];

                        $lektoer = $tstmarc->HasLektoer();
                        $mMarc[$lokalid][$bib][$base]['type'] = 'isbn13';
                        $mMarc[$lokalid][$bib][$base]['matchtype'] = 3;
                        $mMarc[$lokalid][$bib][$base]['lektoer'] = $lektoer;
                        if ($this->verbose) {
                            $this->debugstrng .= "<span class='true'>TRUE</span>\n";
                        } else {
                            if ($this->verbose) {
                                $this->debugstrng .= "<span class='false'>FALSE</span><br>\n";
                            }
                        }
//                        }
                    }
                }
            }
        }

// get the finale score/point for each candidate
        foreach ($mMarc as $lokalid => $arr2) {
            foreach ($arr2 as $bib => $arr3) {
                foreach ($arr3 as $base => $arr4) {
                    $arr = array();
                    $arr['lokalid'] = $lokalid;
                    $arr['bibliotek'] = $bib;
                    $arr['base'] = $base;
                    $arr['matchtype'] = $arr4['matchtype'];
                    $arr['type'] = $arr4['type'];
                    $candidates = array();
                    $candidates[] = $arr;
                    $number = $this->mark_best_records($this->getMarcFromBasis, $isbn13, $candidates);
                    $mMarc[$lokalid][$bib][$base]['matchtype'] = $number[0]['score'];
                }
            }
        }
        return $mMarc;
    }

    function fetchRecords($search, $records) {
        $noDublets = true;
        $hsb = true;
        $this->getMarcFromBasis->setDatabase('Basis');
        $records['Basis'] = $this->getMarcFromBasis->getMarc($search, $noDublets, $hsb);
        $this->getMarcFromBasis->setDatabase('Phus');
        $records['Phus'] = $this->getMarcFromBasis->getMarc($search);
        return $records;
    }

    private
    function insertWithNoDubs($titles, $title, $expchar) {
        $arr = explode($expchar, $title);
        foreach ($arr as $ti) {
//            $ti = str_replace("og", "'og'", $ti);
//            $ti = str_replace("elle", "'eller'", $ti);
//            $ti = str_replace("ikke", "'ikke'", $ti);
//            $ti = str_replace("og", "'og'", $ti);
            if (!array_key_exists($ti, $titles)) {
                $titles[$ti] = 'y';
            }
        }
        return $titles;
    }

    private
    function InsertOg($txt) {
        $placholder = chr(5);
        $ret = "";
        $isint = false;
//        $laengde = strlen($txt);
        for ($i = 0; $i < strlen($txt); $i++) {
            $chr = $txt[$i];
            if ($isint) {
                if (!is_numeric($chr)) {
                    $ret .= $placholder;
                    $isint = false;
                }
            }
            if (is_numeric($chr)) {
                if (!$isint) {
                    $isint = true;
                    if ($i > 1) {
                        $ret .= $placholder;
                    }
                }
            }
            $ret .= $chr;
        }
        $ret = rtrim($ret, $placholder);
        $ret = str_replace($placholder, ' og ', $ret);

        return $ret;
    }

    private
    function makeTitleSearchString() {
        $title = $this->getData('Title');
        $ltitle = str_replace('"', '', $title);
        $ltitle = "lti = \"" . utf8_decode($ltitle) . "\"";
        $title = str_replace(' og ', ' "og" ', $title);
        $title = str_replace(' ikke ', ' "ikke" ', $title);
        $title = str_replace(' eller ', ' "eller" ', $title);

        $ltitle = str_replace(' ? ', '', $ltitle);
        $title = strtolower($title);
        $title = str_replace('½', ' 1 2 ', $title);
        $title = str_replace('#', '', $title);

//        $title = str_replace('a' . chr(204) . chr(138), 'å', $title);
//        for ($j = 0; $j < strlen($title); $j++) {
//            $tgn = $title[$j];
//            $ord = ord($title[$j]);
//        }

        $pos = strpos($title, 'aa');
        if ($pos !== false) {
            $title .= ' - ' . str_replace('aa', 'å', $title);
        }
        $title = utf8_decode($title);
        $title = str_replace('?', '', $title);
        $stitle = $this->InsertOg($title);
//        $title = "LTI=\"" . $title . "\"?";
        $titles = array();
        $titles[$title] = 'y';
        $titles[$ltitle] = 'y';
        $titles = $this->insertWithNoDubs($titles, $stitle, ',');
        $titles = $this->insertWithNoDubs($titles, $stitle, ':');
        $titles = $this->insertWithNoDubs($titles, $stitle, '/');
        $titles = $this->insertWithNoDubs($titles, $stitle, '-');
//        $titles = $this->insertWithNoDubs($titles, $stitle, '#');

        $newtitles = array();
        foreach ($titles as $key => $value) {
            if (strlen($key) > 3) {
                $newtitles[] = $key;
            }
//            $newtitles[] = "LTI=\"" . $key . "?\"";
        }
        return $newtitles;
    }

    private
    function makeAuthorSearchString() {
        $placholder = chr(5);
        $authors = str_replace(',', '', utf8_decode($this->getData('Authors')));

//        $authors = str_replace(',', '', $this->getData('Authors'));
        verbose::log(DEBUG, $authors);
        if (strlen($authors) > 100) {
            verbose::log(TRACE, "Authors field longer than 100 char.  Truncated to ZERO");
            verbose::log(TRACE, $authors);
            $authors = '';
        }
        $names = explode(' ', $authors);
        $cclauthors = "";
        foreach ($names as $name) {
            $newname = $this->Aa2amstrongA($name);
            if ($newname != $name) {
                $name = "($newname eller $name) ";
            }
            $cclauthors .= "fo=" . $name . $placholder;
        }
        $ret = rtrim($cclauthors, $placholder);
        $ret = str_replace($placholder, ' og ', $ret);
        return $ret;
    }

    private
    function Aa2amstrongA($txt) {
        $cnvfrom = array("aa", "Aa");
        $aa = utf8_decode('å');
        $txt = str_replace($cnvfrom, $aa, $txt);
        return $txt;
    }

    private
    function PubConvertChars($txt) {
        $cnvFrom = array('é', 'è');
        $cnvTo = array('e', 'e');
        $txt = str_replace($cnvFrom, $cnvTo, $txt);
        return $txt;
    }

    private
    function convertChars($txt) {
        $aa = utf8_decode('å');
        $Aa = utf8_decode('Å');
        $egrave = utf8_decode('è');
        $eaigu = utf8_decode('é');
        $cnvFrom = array("@$Aa", "@$aa", $egrave, $eaigu);
        $cnvTo = array('Aa', 'aa', 'e', 'e');
        $txt = str_replace($cnvFrom, $cnvTo, $txt);
        $pos = strpos($txt, '@U');
        while ($pos !== false) {
            $txt = substr($txt, 0, $pos) . substr($txt, $pos + 4);
            $pos = strpos($txt, '@U');
        }
        $startpos = 0;
        $pos = strpos($txt, '@');
        while ($pos !== false) {
            $frstchar = strtolower(substr($txt, $pos + 1, 1));
            switch ($frstchar) {
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
            case 'a':
            case 'b':
            case 'c':
            case 'd':
            case 'e':
            case 'f':
                $txt = substr($txt, 0, $pos) . substr($txt, $pos + 5);
                break;
            default:
                $startpos = $pos + 1;
                break;
            }
            $pos = strpos($txt, '@', $startpos);
        }

        return $txt;
    }

    private
    function getData($name) {
        $elements = $this->doc->getElementsByTagName($name);
        $txt = "";
        foreach ($elements as $element) {
            $txt .= $element->nodeValue . "\n";
        }
        return rtrim($txt);
    }

    private
    function prepare() {
        $arr['Title'] = str_replace('½', '1/2', $this->getData('Title'));
        $Authors = $this->getData('Authors') . ', ';
        $Authors .= $this->getData('EditedBy');
        $arr['Authors'] = rtrim($Authors, ', ');
        $imprints = $this->getData('ImprintName') . "\n";
        $imprints .= $this->getData('PublisherName');
        $arr['ImprintName'] = trim($imprints, "\n");
        $arr['language'] = trim($this->getData('Language'));

        $this->xmldata = $arr;
    }

    private
    function extractAuthor($marc, $field, $authors) {
        while ($marc->thisField($field)) {
            $author = "";
            while ($marc->thisSubfield('h')) {
                $author .= $marc->subfield() . ' ';
            }
            while ($marc->thisSubfield('a')) {
                $author .= $marc->subfield() . ' ';
            }
            while ($marc->thisSubfield('o')) {
                $author .= $marc->subfield() . ' ';
            }
            if (trim($author)) {
                $authors[] = $this->convertChars($author);
            }
        }
        return $authors;
    }

    private
    function fetchAuthorsFromAutReg($marc, $field, $authors) {
        while ($this->marc->thisField($field)) {
            while ($this->marc->thisSubfield('5')) {
                $bibliotek = $this->marc->subfield();
            }
            while ($this->marc->thisSubfield('6')) {
                $lokalid = $this->marc->subfield();
            }

            if ($bibliotek && $lokalid) {

//                $autMarcs = $this->libV3Api->getMarcByLokalidBibliotek($lokalid, $bib);
                $this->getMarcFromBasis->setDatabase('Basis');
                $autmMarcs = $this->getMarcFromBasis->getMarcByLokalidBibliotek($lokalid, $bibliotek);
                $autmarc = new marc();
                $autmarc->fromIso($autMarcs[0]['DATA']);
//                $lns = $autmarc->toLineFormat();
//                echo "aut post\n$lns\n";
                $authors = $this->extractAuthor($autmarc, '100', $authors);
                $authors = $this->extractAuthor($autmarc, '400', $authors);
            }
        }
        return $authors;
    }

    private
    function fetchTitleFromMarc() {
        $f245a = $this->marc->findSubFields('245', 'a');
        $f745a = $this->marc->findSubFields('745', 'a');
        $f440a = $this->marc->findSubFields('440', 'a');
        $f512t = $this->marc->findSubFields('512', 't');
        $mtitles = array_merge($f245a, $f745a, $f440a, $f512t);
        return $mtitles;
    }

    private
    function fetchAuthorsFromMarc() {
        $authors = array();
        $ln = $this->marc->toLineFormat();


        $authors = $this->extractAuthor($this->marc, '100', $authors);
        $authors = $this->extractAuthor($this->marc, '700', $authors);
        $authors = $this->extractAuthor($this->marc, '720', $authors);
        $authors = $this->extractAuthor($this->marc, '900', $authors);
//        $authors = $this->fetchAuthorsFromAutReg($this->marc, '100', $authors);
//        $authors = $this->fetchAuthorsFromAutReg($this->marc, '700', $authors);
//        $authors = $this->fetchAuthorsFromAutReg($this->marc, '900', $authors);

        return $authors;
    }

    private
    function compare($marc, $isbnmatch = false) {
        $match = false;
        $this->marc->fromIso($marc);
//        $strng = $this->marc->toLineFormat(78);
        $faust = $this->marc->findSubFields('001', 'a');
        $bib = $this->marc->findSubFields('001', 'b');
        if ($bib[0] != '870970') {
            return false;
        }
        $lektoer = $this->marc->HasLektoer();
//        $choice = $lektoer['choice'];
//        $lektoer = $lektoer['status'];
        $lns = $this->marc->toLineFormat();
        verbose::log(DEBUG, "Marc: \n$lns\n");
        $mtitles = $this->fetchTitleFromMarc();

        $ptitles = explode("\n", $this->xmldata['Title']);
//    $loose = true;
        $loose = false;

        $matchType = 0;
        $cnt = 0;
        foreach ($mtitles as $mtitle) {
            $mtitle = $this->convertChars($mtitle);
            foreach ($ptitles as $ptitle) {
                $cnt++;
                $ptitle = $this->PubConvertChars($ptitle);
                verbose::log(DEBUG, "\$mtitle: $mtitle, ptitle:$ptitle\n");
                if ($this->verbose) {
                    $this->debugstrng .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
                        . "Publizon Title:\"$ptitle\", Basis Title:\"" . utf8_encode($mtitle) . "\"";
                }
                $res = $this->marc->FuzzyCompare($mtitle, $ptitle, $loose);
                if ($res) {
                    $this->debugstrng .= " <span class='true'>true</span> <br>";
                    $match = true;
                    $matchType = 1;
                    break;
                } else {
                    $this->debugstrng .= " <span class='false'>false</span> <br>";
                }
            }
        }


        if ($match) {
            // is there at least one author in common?
            $match = false;
            $loose = true;
            $authors = $this->fetchAuthorsFromMarc();
            $pauthors = explode(',', $this->xmldata['Authors']);
            foreach ($authors as $author) {
                if (!$match) {
                    foreach ($pauthors as $pauthor) {
                        $pauthor = $this->PubConvertChars($pauthor);
                        verbose::log(DEBUG, "pauthor: $pauthor, author:$author\n");
                        if ($this->verbose) {
                            $this->debugstrng .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
                                . "PubAuthor:\"$pauthor\", mauthor:" . utf8_encode($author) . "\"";
                        }
                        $res = $this->marc->FuzzyCompare($author, $pauthor, $loose);
                        if ($res) {
                            $this->debugstrng .= " <span class='true'>true</span><br>";
                            $match = true;
                            $matchType = 2;
                            break;
                        } else {
                            $this->debugstrng .= " <span class='false'>false</span><br>";
                        }
                    }
                }
            }
        }
        if ($match) {
            // have they a publisher in common?
            $match = false;
            $loose = true;
            $mpublishers = $this->marc->findSubFields('260', 'b');
            $ppublishers = explode("\n", $this->xmldata['ImprintName']);
            foreach ($mpublishers as $mpublisher) {
                $mpublisher = $this->convertChars($mpublisher);
                foreach ($ppublishers as $ppublisher) {
                    $ppublisher = $this->PubConvertChars($ppublisher);
                    verbose::log(DEBUG, "\$mpublisher: $mpublisher, ppublisher:$ppublisher\n");
                    if ($this->verbose) {
                        $this->debugstrng .= "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;"
                            . "PubPublisher:\"$ppublisher\", mpublisher:" . utf8_encode($mpublisher) . "\"";
                    }
                    $res = $this->marc->FuzzyCompare($mpublisher, $ppublisher, $loose);
                    if ($res) {
                        $this->debugstrng .= " <span class='true'>true</span><br>";
                        $match = true;
                        $matchType = 3;
                        break;
                    } else {
                        $this->debugstrng .= " <span class='false'>false</span><br>";
                    }
                }
            }
        }

        if ($matchType > 0) {
            verbose::log(DEBUG, "matchLib>V3::matchType:$matchType");
            return array($faust[0], $bib[0], $matchType, $lektoer);
        } else {
            if ($isbnmatch) {
                // only the isbn is in common
                $f009gs = $this->marc->findSubFields('009', 'g');
                if (!array_key_exists(0, $f009gs)) {
                    // er sikkert en bindpost, hent 009 fra hovedpost.
//                    $f014s = $this->marc->findSubFields('014','a');
//                    foreach($f014s as $f014) {


                    $strng = $this->marc->toLineFormat();
                    echo "Denne post har ikke noget 009 *g !!!\n";
                    echo $strng;
                    verbose::log(TRACE, "Denne post har ikke noget 009 *g !!!\n$strng");
//                    exit;
                } else {
                    if ($f009gs[0] == 'xe') {
                        $matchType = 4;
                        verbose::log(DEBUG, "matchLib>V3::matchType:$matchType");
                        return array($faust[0], $bib[0], $matchType, $lektoer);
                    }
                }
            } else {
                verbose::log(DEBUG, "matchLib>V3::matchType: No match");
                return false;
            }
        }
    }

}
