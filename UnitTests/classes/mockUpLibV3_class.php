<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 09-03-2016
 * Time: 13:15
 */
class mockUpLibV3API {

    private $dir;

    function __construct($basedir, $subdir) {
        $this->dir = $basedir . "/UnitTests/" . $subdir;
//        $cnt = 0;
//        $her = getcwd();
//        $b = basename($her);
//        while (strtolower(substr(basename($her), 0, 7)) != 'posthus') {
//            $her = dirname($her);
//            $dir = '../';
//            $cnt++;
//            if ($cnt > 10) {
//                echo "her:$her\n";
//                echo "cdir:$dir\n";
//                exit(100);
//            }
//        }
//        $this->dir = $dir . 'UnitTests/' . $subdir;
//        if (file_exists('data')) {
//            if (is_dir('data')) {
//                $this->dir = 'data';
//            }
//        }


//        $this->dir = $dir;
    }

    function set($txt) {
        return true;
    }


    function getMarcByLokalidBibliotek($lokalid, $bibliotek) {
        $this->return = array();
        $result = $this->getMarcByLB($lokalid, $bibliotek, "", 'Basis');
        if (!$result) {
            return $result;
        }
        $this->return[] = $result[0];

        return $this->return;
    }

    function writeMarc($lokalid, $bibliotek, $lnmarc) {
        $file = $this->dir . '/' . $bibliotek . '/' . str_replace(' ', '', $lokalid) . ".ln";
        $fp = fopen($file, 'w');
        fwrite($fp, $lnmarc);
        fclose($fp);
    }


    function getMarcByLB($lokalid, $bibliotek, $wh = '', $base = '') {
//        $lokalid = str_replace(' ', '', $lokalid);
        $file = $this->dir . '/' . $bibliotek . '/' . str_replace(' ', '', $lokalid) . ".ln";
        $her = getcwd();
//        echo "file:$file<br/>her:$her<br/>>";


        if (file_exists($file)) {
            $fp = fopen($file, 'r');
//            touch($file);
            $data = fread($fp, filesize($file));
            fclose($fp);
        } else {
            return false;
        }
        $data = str_replace("\r", '', $data);
        $data = utf8_decode($data);
//        echo $data;
        $marc = new marc;
        $marc->fromString($data);
        $ts = date('YmdHms');

        $res = array('ID' => 1, 'DANBIBID' => 2, 'LOKALID' => $lokalid, 'BIBLIOTEK' => '870970', 'OPRET' => $ts, 'AJOUR' => $ts);
        $res['DATA'] = $marc->toIso();
        $result[] = $res;
//        print_r($result);
        return $result;
    }

    function getLekNoViaRel($lokalid, $bibliotek) {
        $lokalid = str_replace(' ', '', $lokalid);
        $relfile = $this->dir . '/' . $bibliotek . '/' . 'postrelationer.ln';
        $fp = fopen($relfile, 'r');
        $ret = array();
        if ($fp) {
//            touch($relfile);
            while (($ln = fgets($fp)) !== false) {
                $fromto = explode(':', trim($ln));
                if ($fromto[1] == $lokalid) {
                    $m = $this->getMarcByLB($fromto[0], $bibliotek);
                    $ret[] = $m[0];

                }
            }
        }
        if (count($ret)) {
            return $ret;
        }
        return false;
    }
}