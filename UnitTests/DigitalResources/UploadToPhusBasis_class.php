<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";

class UploadToPhusBasisException extends Exception {

    public function __toString() {
        return 'UploadToPhusBasisException -->' . $this->getMessage() . ' --- ' .
                $this->getFile() .
                ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}

/**
 * Description of UploadToPhusBasis
 *
 * @author hhl
 */
class UploadToPhusBasis {

    private $upload_cmd;
    private $startdir;
    private $tablename;
    private $libv3log;
    private $marc;
    private $db;
    private $oci;
    private $libV3s;

    function __construct($connect_string, $upload_cmd) {

        $this->tablename = "PhusBasisUpdateLog";
        $this->libv3log = 'Libv3log';

        $this->startdir = dirname(__FILE__);
        $this->marc = new marc();
        $this->upload_cmd = str_replace('@', '"', $upload_cmd);
        $this->libV3s = array();

//        Login to the postgres database
        try {
            $this->db = new pg_database($connect_string);
            $this->db->open();
            $tablename = $this->tablename;

//          look for the "$tablename" - if none, make one.
            $sql = "select tablename from pg_tables where tablename = $1";
            $arr = $this->db->fetch($sql, array(strtolower($tablename)));


            if (!$arr) {
                $sql = "
                create table $tablename (
                    seqno integer,
                    createdate timestamp,
                    faust varchar(11),
                    isbn13 varchar(13),
                    title varchar(100),
                    fromBase varchar(20),
                    PhusOrBasis varchar(1),
                    marc text
                )
            ";
                $this->db->exe($sql);
            }

            $tablename = $this->libv3log;
            $sql = "select tablename from pg_tables where tablename = $1";
            $arr = $this->db->fetch($sql, array(strtolower($tablename)));

            if (!$arr) {
                $sql = "
                create table $tablename (
                    seqno integer,
                    dbuser varchar(15),
                    ajour timestamp,
                    opret timestamp,
                    id integer,
                    danbibid integer,
                    lokalid varchar(20),
                    bibliotek varchar(15),
                    data text
                )
            ";
                $this->db->exe($sql);

                $sql = "create sequence lognumber";
                $this->db->exe($sql);
            }

            $this->db->exe("SET CLIENT_ENCODING TO 'LATIN1'");
        } catch (UploadToPhusBasisException $u) {
            echo $u . "\n";
            exit;
        }
    }

    function ociconnections($ociuser, $ocipasswd, $ocidatabase) {
        $arr['user'] = $ociuser;
        $arr['connection'] = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
        $this->libV3s[] = $arr;
    }

    function upload($isomarc, $toBase, $nothing = false) {

        $this->marc->fromIso($isomarc);
        $lokalid = $this->marc->findSubFields('001', 'a', 1);
        $faust = $this->marc->findSubFields('001', 'a', 1);
        $isbn13s = $this->marc->findSubFields('021', 'e');
        $title = $this->marc->findSubFields('245', 'a', 1);
        $strng = $this->marc->toLineFormat();
        if (count($isbn13s) > 1) {
            echo "More than 1 (one) isbn13 in the record - first occurence choosen\n";
        }
        $isbn13 = $isbn13s[0];
// get seqno
        $lognumbers = $this->db->fetch("select nextval('lognumber')");
        $lognumber = $lognumbers[0]['nextval'];
        $sql = "
                insert into $this->tablename
                (seqno, createdate, faust, isbn13, title, PhusOrBasis, marc)
                VALUES
                ($lognumber,
                current_timestamp,
                '$faust',
                '$isbn13',
                $1,
                '$toBase',
                $2)
        ";
//        echo $sql;
        $this->db->query_params($sql, array($title, $strng));
//        print_r($this->libV3s);
        foreach ($this->libV3s as $con) {
            $user = $con['user'];
            $libv3 = $con['connection'];
            $marcrecord = $libv3->getMarcByLokalidBibliotek($lokalid, '870970');
//            echo "count:" . count($marcrecord) . "\n";
//            print_r($marcrecord);
            if (count($marcrecord)) {
                $sql = "
                 insert into $this->libv3log
                     (seqno,dbuser,ajour, opret, id, danbibid, lokalid, bibliotek,
                        data)
                     VALUES (
                     $lognumber,
                     '$user',
                     to_timestamp($1,'YYYYMMDD HH24MISS'),
                     to_timestamp($2,'YYYYMMDD HH24MISS'),
                     $3,$4,$5,$6,$7
                     )
                 ";
//                echo $sql;
                $par = array();
                $par[] = $marcrecord[0]['AJOUR'];
                $par[] = $marcrecord[0]['OPRET'];
                $par[] = $marcrecord[0]['ID'];
                $par[] = $marcrecord[0]['DANBIBID'];
                $par[] = $marcrecord[0]['LOKALID'];
                $par[] = $marcrecord[0]['BIBLIOTEK'];
                $oldmarc = new marc();
                $oldmarc->fromIso($marcrecord[0]['DATA']);
                $par[] = $oldmarc->toLineFormat();
                $this->db->query_params($sql, $par);
            }
        }
//        exit;
//        $nothing = true;


        $cmd = $this->startdir . "/" . $this->upload_cmd;
        $cmd = str_replace('$PhusOrBasis', "$toBase", $cmd);
        $fpiso = fopen('isofile', 'w');
        if ($fpiso) {
            fwrite($fpiso, $isomarc);
            fclose($fpiso);
            if ($nothing) {
                $fout = fopen('marc.iso', 'a');
                fwrite($fout, $isomarc);
                fclose($fout);
                echo "\ncmd:$cmd\n";
            } else {
                echo "[" . $cmd . "]\n";
                $return_string = system($cmd, $return_var);
                if ($return_var) {
                    $strng = "The cmd:$cmd is in error: $return_var\n";
                    $strng .= "return_string:$return_string\n";
                    throw new UploadToPhusBasisException($strng);
                }
            }
        }
    }

}
