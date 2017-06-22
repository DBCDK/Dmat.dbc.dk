<?php

/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

class matchMarcException extends Exception {

    public function __toString() {
        return 'matchmarchException -->' . $this->getMessage() . ' --- ' .
            $this->getFile() .
            ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}


class match_marc {

    private $mediadb;
    private $cnvFrom;
    private $cnvTo;
    private $matchResult;

    public function __construct(mediadb $mediadb) {
        $this->mediadb = $mediadb;
        $this->cnvFrom = array();
        $this->convTo = array();
        $this->matchResult = array();

        $this->cnvFrom[] = '¤';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = ',';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = '.';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = '?';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = ':';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = '-';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = ',';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = ' ';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = '@å';
        $this->cnvTo[] = 'aa';
        $this->cnvFrom[] = '@Å';
        $this->cnvTo[] = 'aa';
        $this->cnvFrom[] = 'Å';
        $this->cnvTo[] = 'å';
        $this->cnvFrom[] = '&';
        $this->cnvTo[] = 'og';
        $this->cnvFrom[] = 'é';
        $this->cnvTo[] = 'e';
        $this->cnvFrom[] = 'è';
        $this->cnvTo[] = 'e';
        $this->cnvFrom[] = 'ë';
        $this->cnvTo[] = 'e';
        $this->cnvFrom[] = 'ö';
        $this->cnvTo[] = 'ø';
        $this->cnvFrom[] = 'Ö';
        $this->cnvTo[] = 'ø';
        $this->cnvFrom[] = 'Ø';
        $this->cnvTo[] = 'ø';
        $this->cnvFrom[] = 'Å';
        $this->cnvTo[] = 'å';
        $this->cnvFrom[] = "'";
        $this->cnvTo[] = '';
        $this->cnvFrom[] = 'I';
        $this->cnvTo[] = '1';
        $this->cnvFrom[] = '@02C7';
        $this->cnvTo[] = '';
        $this->cnvFrom[] = '@02c7';
        $this->cnvTo[] = '';


    }


    public function match(marc $basismarc, marc $pulizonmarc) {
        $res = array();

        if (!$basismarc) {
            throw new matchMarcException('$basismarc is null');
        }

        if (!$pulizonmarc) {
            throw new matchMarcException('$publizonmarc is null');
        }

        $btitles = $this->getBasisTitles($basismarc);
        $ptitles = $this->getPublizonTitles($pulizonmarc);
        $this->titleCompare($btitles, $ptitles);

        $bauthors = $this->getBasisAuthors($basismarc);
        $pauthors = $this->getPublizonAuthors($pulizonmarc);
        $res['author'] = $this->compareAuthors($bauthors, $pauthors);

        $bisbns = $this->getIsbns($basismarc);
        $pisbns = $this->getIsbns($pulizonmarc);
        $res['isbn'] = $this->isbnCompare($bisbns, $pisbns);

        $res['publisher'] = $this->publishercompare($basismarc, $pulizonmarc);

        return $this->matchResult;

    }

    private function extractFields($m, $f, $s) {
        $ret = array();
        $fields = $m->findSubFields($f, $s);
        foreach ($fields as $r) {
            $ret[] = strtolower(trim(utf8_encode($r)));
        }
        return $ret;
    }

    private function getA($field, $subfield, $m) {
        $auth = array();
        while ($m->thisField($field)) {
//            $a = array();
            while ($m->thisSubfield($subfield)) {
                $sf = $m->subfield();
//                echo "Basis A:" . $this->CharNorm($sf) . "\n";
                $names = explode(' ', $sf);
                foreach ($names as $name) {
//                    $a[] = $this->CharNorm($name);
//                    $a[] = $name;
                    $auth[] = $name;
                }
            }
//            sort($a);
//            $auth[] = $a;
        }
        sort($auth);
        return $auth;
    }

    private function getBasisAuthors(marc $m) {
        $authors = array();
        $authors[] = $this->getA('100', 'a', $m);
        $authors[] = $this->getA('700', 'a', $m);
        $authors[] = $this->getA('710', 'a', $m);
        $authors[] = $this->getA('720', 'a', $m);
        $authors[] = $this->getA('720', 'o', $m);
        return $authors;
    }

    private function getPublizonAuthors(marc $m) {
        $authors = array();
        while ($m->thisField('720')) {
            $a = array();
            while ($m->thisSubfield('o')) {
                $sf = $m->subfield();
//                echo "Publi A:$sf\n";
//                $sf = $this->CharNorm($sf);
                $names = explode(' ', $sf);
                foreach ($names as $name) {
//                    $a[] = $this->CharNorm($name);
                    $a[] = $name;
                }
            }
            sort($a);
            $authors[] = ($a);
        }
        return $authors;
    }

    private function CharNorm($txt) {

        $r = trim(utf8_encode($txt));
        $r = strtolower($r);
        $r = str_replace($this->cnvFrom, $this->cnvTo, $r);

        return $r;
    }

    private function getPublizonTitles(marc $m) {
        $titles = array();
        $f245as = $m->findSubFields('245', 'a');
        foreach ($f245as as $r) {
//            $titles[] = $this->CharNorm($r);
            $titles[] = $r;
//            if ($pos = strpos($r, strtolower(' - Lyt&læs'))) {
//                $titles[] = substr($r, 0, $pos);
//            }

        }
        $f245cs = $m->findSubFields('245', 'c');
        if ($f245cs) {
            foreach ($f245cs as $r) {
//                $r = $this->CharNorm($r);
                $t = array();
                foreach ($titles as $ti) {
                    $titles[] = $ti . $r;
                }
            }
        }
        return $titles;
    }

    private function getBasisTitles(marc $m) {
        $titles = array();
        // only 245*a
        while ($m->thisField('245')) {
            $t = '';
            while ($m->thisSubfield('a')) {
//                $t .= $this->CharNorm($m->subfield()) . '|';
                $t .= $m->subfield() . '|';
            }
            if ($t) {
                $titles[] = rtrim($t, '|');
            }
        }
        $m->clearThisField();
        while ($m->thisField('245')) {
            $a = array();
            while ($m->thisSubfield('a')) {
                $a[] = $m->subfield();
            }
            while ($m->thisSubfield('c')) {
                foreach ($a as $key => $val) {
                    $a[$key] = $a[$key] . $m->subfield() . '|';
                }
            }
        }
        foreach ($a as $t) {
            $titles[] = rtrim($t, '|');

        }
        $f440as = $m->findSubFields('440', 'a');
        foreach ($f440as as $r) {
            $titles[] = $r;
        }

        $f5012ts = $m->findSubFields('512', 't');
        foreach ($f5012ts as $r) {
//            $titles[] = $this->CharNorm($r);
            $titles[] = $r;
        }
        return $titles;
    }


    /**
     *
     */
    function fuzzydiff() {
        echo "data1:\n";
        print_r($this->data1);
        echo "data2:\n";
        print_r($this->data2);


    }

    /**
     * @param $arr1
     * @param $arr2
     * @return int
     */
    private function titleCompare($arr1, $arr2) {
        $res = $all = array();
        $nomatch = 0;

        $match = 1;
        $maxfuz = 3;
        foreach ($arr1 as $key => $txt1) {
            foreach ($arr2 as $txt2) {
                $dat1 = $this->CharNorm($txt1);
                $dat2 = $this->CharNorm($txt2);
                $all[] = $txt1 . " | " . $txt2;
                $pos = strpos($dat1, $dat2);
                if ($pos !== false) {
                    $res['type'] = $match;
                    $res['match'] = $all;
                    $this->matchResult['title'] = $res;
                    return $match;
                }
                $pos = strpos($dat2, $dat1);
                if ($pos !== false) {
                    $res['type'] = $match;
                    $res['match'] = $all;
                    $this->matchResult['title'] = $res;
                    return $match;
                }
            }
        }
        $all = array();
        foreach ($arr1 as $key => $txt1) {
            foreach ($arr2 as $txt2) {
                $dat1 = $this->CharNorm($txt1);
                $dat2 = $this->CharNorm($txt2);
                $all[] = $txt1 . " | " . $txt2;
                $fuz = levenshtein($dat1, $dat2);
                if (strlen($dat1) > 30 and strlen($dat2) > 30) {
                    $maxfuz = 4;
                }
                if ($fuz < $maxfuz) {
                    $res['type'] = $fuz + 1;
                    $res['match'] = $all;
                    $this->matchResult['title'] = $res;
                    return $fuz + 1;
                }
                $res['type'] = $nomatch;
                $res['match'] = $all;
                $this->matchResult['title'] = $res;
                return $nomatch;
            }
        }
    }


    private function isbnCompare($arr1, $arr2) {
        $nomatch = 0;
        $res = $all = array();
        foreach ($arr1 as $key => $dat1) {
            foreach ($arr2 as $dat2) {
                $all[] = $dat1 . " | " . $dat2;
                if ($dat1 == $dat2) {
//                    $res['txt1'] = $res['dat1'] = $dat1;
//                    $res['txt2'] = $res['dat2'] = $dat2;
                    $res['type'] = 1;
                    $res['match'] = $all;
                    $this->matchResult['isbn'] = $res;
                    return 1;
                }
            }
        }
        $res['type'] = $nomatch;
        $res['match'] = $all;
        $this->matchResult['isbn'] = $res;
        return $nomatch;
    }

    function publishercompare($basismarc, $publizonmarc) {
        $pubs = $basismarc->findSubFields('260', 'b');
        $pubsCand = $publizonmarc->findSubFields('260', 'b');
        $res = $all = array();
        $found = 0;
        foreach ($pubs as $pub) {
            foreach ($pubsCand as $pubCand) {
                $all[] = $pub . " | " . $pubCand;
                $pub = str_replace(array('[', ']'), '', $pub);
                $pubsCand = str_replace(array('[', ']'), '', $pubsCand);
                if ($pub == $pubCand) {
                    $res['type'] = 1;
                    $res['match'] = $all;
                    $this->matchResult['publisher'] = $res;
                    return 1;
                }
            }
        }
        $publishers = $publishersCand = array();
        $isbns = $this->getIsbns($basismarc);
        $isbnsCand = $this->getIsbns($publizonmarc);
        if ($isbns and $isbnsCand) {
            if ($isbns[0] == $isbnsCand[0]) {
                $res['type'] = 3;
                $res['match'] = $all;
                $this->matchResult['publisher'] = $res;
                return 3;
            }
        }
        if ($isbns) {
            $publishers = $this->getPubsIds($isbns[0]);
            if (!$publishers) {
//            echo "Publisher not found i p-base\n";
                $res['err'] = "Isbn " . $isbns[0] . " not found in publisher base";
                $res['type'] = 0;
                $res['match'] = $all;
                $this->matchResult['publisher'] = $res;
                return 0;
//            } else {
//                $publishers[0]['idnr'] = 'tomt1';
            }
        }

        if ($isbnsCand) {
            $publishersCand = $this->getPubsIds($isbnsCand[0]);
            if (!$publishersCand) {
                $res['err'] = "Isbn " . $isbns[0] . "not found in publisher base";
                $res['type'] = 0;
                $res['match'] = $all;
                $this->matchResult['publisher'] = $res;
                return 0;
//            } else {
//                $publishersCand[0]['idnr'] = 'tomt2';
            }
        }
        $f = false;
        foreach ($publishers as $pub) {
            foreach ($publishersCand as $pubc) {
                if ($pub['idnr'] == $pubc['idnr']) {
                    $f = true;
                }
            }
        }
//        if ($publishers[0]['idnr'] == $publishersCand[0]['idnr']) {
//                echo "FORSKELLIG NAVN ENS ISBN: $pub <-> $pubCand\n";
        if ($f) {
            $res['type'] = 2;
            $res['match'] = $all;
            $this->matchResult['publisher'] = $res;
            return 2;
        }

        $res['type'] = $found;
        $res['match'] = $all;
        $this->matchResult['publisher'] = $res;
        return $found;
    }

    function getPubsIds($nr) {
        $countrycode = substr($nr, 3, 2);
        $nr = str_replace(array(' ', '-'), '', $nr);
        $nr = substr($nr, -8);
        $publishers = array();
//        if ($countrycode != '87') {
//            return $publishers;
//        }
        while (strlen($nr) > 1) {
            $nr = substr($nr, 0, strlen($nr) - 1);
            $hits = $this->mediadb->getPublisherId($nr);
//            $sql = "select * from forlag where forlagisbn = $nr";
//            $hits = $db->fetch($sql);
            if ($hits) {
                $publishers[] = $hits[0];
                break;
            }
        }
        return $publishers;
    }

    function getIsbns(marc $marc) {
        $isbns = array();
        $fs = $marc->findSubFields('021', 'aex');
        foreach ($fs as $f) {
            $f = materialId::normalizeISBN($f);
            if (strlen($f) == 10) {
                $f = materialId::convertISBNToEAN($f);
            }
            $isbns[] = $f;
        }
        return $isbns;
    }


    function compareAuthors($bauthors, $pauthors) {
        $res = $all = array();
        foreach ($bauthors as $bauthor) {
            $txt1 = implode(' ', $bauthor);
            foreach ($pauthors as $pauthor) {
                $txt2 = implode(' ', $pauthor);
                $all[] = $txt1 . ' | ' . $txt2;
                foreach ($bauthor as $bname) {
                    $cnvBname = $this->CharNorm($bname);
                    foreach ($pauthor as $pname) {
                        $cnvPname = $this->CharNorm($pname);
                        $fuz = levenshtein($cnvBname, $cnvPname);
//                        echo "fuz:$fuz, $bname, $pname\n";
                        if ($fuz < 2) {
                            $res['type'] = $fuz + 1;
                            $res['match'] = $all;
                            $this->matchResult['author'] = $res;
                            return $fuz + 1;
                        }
                    }
                }
            }
        }
        $res['type'] = 0;
        $res['match'] = $all;
        $this->matchResult['author'] = $res;
        return 0;
    }


    /**
     *
     */
    function printDiff() {
        echo "diff1:\n";
        print_r($this->diff1);
        echo "diff2:\n";
        print_r($this->diff2);
    }
}

