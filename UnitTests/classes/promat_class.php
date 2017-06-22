<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 25-02-2016
 * Time: 10:45
 */
class promat_class {

    private $nothing;
    private $promat;
    private $setup;
//    private $fp;
//    private $vars;
    private $error;

    public function __construct(inifile $config, $nothing) {

        $this->nothing = $nothing;
        $this->error = "";

        if (($promatuser = $config->get_value('promatuser', 'pubhubMediaService')) == false) {
            $this->error = "no promatuser (pubhubMediaService) stated in the configuration file";
            return;
        }
        if (($promatpasswd = $config->get_value('promatpasswd', 'pubhubMediaService')) == false) {
            $this->error = "no promatpasswd (pubhubMediaService) stated in the configuration file";
            return;
        }
        if (($promatatabase = $config->get_value('promatdatabase', 'pubhubMediaService')) == false) {
            $this->error = "no promatdatabase (pubhubMediaService) stated in the configuration file";
            return;
        }

        $this->promat = new oci($promatuser, $promatpasswd, $promatatabase);
        $this->promat->set_charset('WE8ISO8859P1');
        $this->promat->connect();

    }

    function error() {
        return $this->error;
    }

    function insertCandidate($faust, $weekcode, $note, $title, $instruction) {
        // is there a record with this faust in Promat?
        $faust = str_replace(' ', '', $faust);
        $select = "select candidateid from candidate "
            . "where faustno = '$faust' ";
        $res = $this->promat->fetch_into_assoc($select);
        $title = str_replace("'", '', $title);
        if ($res) {
            $candidateid = $res['CANDIDATEID'];
            $sql = "update candidate "
                . "set dateentered = current_timestamp, "
                . "title = '$title', "
                . "instruction = '$instruction', "
                . "weekcode = '$weekcode', "
                . "note = '$note' "
                . "where candidateid = $candidateid ";
        } else {
            $sql = "insert into candidate "
                . "(candidateid, faustno, class, dateentered, title, "
                . "instruction, weekcode, note) "
                . "values ("
                . "candidateseq.NEXTVAL, "
                . "'$faust', "
                . "'e_book_2014', "
                . "current_timestamp, "
                . "'$title', "
                . "'$instruction', "
                . "'$weekcode', "
                . "'$note') ";
        }
        if ($this->nothing) {
            echo "sql:$sql\n";
        } else {
            $this->promat->set_query($sql);
            $this->promat->commit();
            verbose::log(TRACE, "update/insert:$sql");

        }
    }
}