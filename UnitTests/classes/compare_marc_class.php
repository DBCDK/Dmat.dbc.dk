<?php

/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
class compareMarcException extends Exception {

    public function __toString() {
        return 'mediadbException -->' . $this->getMessage() . ' --- ' . $this->getFile() . ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}


class compare_marc {

    private $data1;
    private $data2;
    private $diff1;
    private $diff2;
    private $pattern;
    private $replace;

    public function __construct() {
        $danish = utf8_decode('æøå');
        $this->pattern = array("/[^a-zA-Z0-9$danish]/");
        $this->replace = '';
    }

    public function input(marc $marc1, marc $marc2) {
        $this->data1 = array();
        $this->data2 = array();
        $this->extractData($this->data1, $marc1);
        $this->extractData($this->data2, $marc2);
        return;
    }

    private function extractData(&$data, $marc) {
        $this->getFieldSub($data, $marc, '245', 'ax', 't');
        //        $this->getFieldSub($data, $marc, '245', 'x', 't');

        //        $this->getFieldSub($data, $marc, '100', '56ah', 'a:');
    }

    private function getFieldSub(&$data, $marc, $field, $subfield, $pre) {
        $arr = $marc->findSubFields($field, $subfield);
        $dat = array();
        if ($arr) {
            foreach ($arr as $org) {
                $strng = str_replace(' & ', ' og ', $org);
                $strng = preg_filter($this->pattern, $this->replace, strtolower($strng));
                $dat['org'] = $org;
                $dat['compare'] = $strng;
                $data[$pre][] = $dat;
            }
        }

    }


    /**
     *
     */
    function fuzzydiff() {
        echo "data1:\n";
        print_r($this->data1);
        echo "data2:\n";
        print_r($this->data2);

        //        foreach($this->data1 as $dat1) {


    }

    private function compare($orgdat1, $orgdat2) {
        $nomatch = true;
        foreach ($orgdat1 as $key => $dat1) {
            if (array_key_exists($key, $orgdat2)) {
                $dat2 = $orgdat2[$key];
                foreach ($dat1 as $no1 => $val1) {
                    foreach ($dat2 as $no2 => $val2) {
                        if ($val1['compare'] == $val2['compare']) {
                            $nomatch = false;
                            break;
                        } else {
                            if (strlen($val1['compare']) > strlen($val2['compare'])) {
                                $pos = strpos($val1['compare'], $val2['compare']);
                            } else {
                                $pos = strpos($val2['compare'], $val1['compare']);
                            }
                            if ($pos !== false) {
                                $nomatch = false;
                                break;
                            }
                        }
                    }
                }
            }
        }
        return $nomatch;
    }

    function diff() {
        $this->diff1 = array();
        $this->diff2 = array();

        if ($this->compare($this->data1, $this->data2)) {
            $this->diff1 = $this->data1;
        }
        if ($this->compare($this->data2, $this->data1)) {
            $this->diff2 = $this->data2;
        }
        if (count($this->diff1) > 0 || count($this->diff2) > 0) {
            return false;
        } else {
            return true;
        }
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

