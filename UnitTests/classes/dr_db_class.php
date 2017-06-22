<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

class drdbException extends Exception {

    public function __toString() {
        return 'drdbException -->' . $this->getMessage() . ' --- ' .
        $this->getFile() .
        ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}


class dr_db {


    private $db;
    private $tablename;
    private $seqname;
    private $provider;
    private $program_name;
    private $timestamp;
    private $infodata;

    /**
     * mediadb constructor.
     * @param pg_database $db
     * @param $program_name
     * @param bool|false $nothing
     */
    public function __construct(pg_database $db, $program_name, $nothing = false) {

        verbose::log(TRACE, "drdb_class __construct");

        if (strlen($program_name) < 2) {
            $txt = "No program name in mediadb_class constructor!!";
            throw new drdbException($txt);
        }
        $this->program_name = $program_name;
        $this->db = $db;

//        $this->tablename = 'dr';
        $this->tablename = 'digitalresources';
        $this->seqname = $this->tablename . 'seq';
        $this->provider = 'Pubhub';
        $this->nothing = $nothing;

        $this->setStarttime();

        $tablename = $this->tablename;
        $seqname = $this->seqname;
        // look for the "$tablename" - if none, make one.
        $sql = "select tablename from pg_tables where tablename = $1";
        $arr = $db->fetch($sql, array($tablename));
        if (!$arr) {
            $sql = "create table $tablename "
                . "(seqno integer primary key, "
                . "provider varchar(50), "
                . "createdate timestamp, "
                . "updated timestamp, "
                . "format varchar(50), "
                . "costfree varchar(5), "
                . "idnumber varchar(50), "
                . "title varchar(100), "
                . "originalXML text, "
                . "marc text, "
                . "faust varchar(11), "
                . "isbn13 varchar(13), "
                . "sent_to_basis timestamp, "
                . "sent_to_well timestamp, "
                . "sent_to_covers timestamp, "
                . "sent_xml_to_well timestamp, "
                . "status char(1), "
                . "deletedate timestamp, "
                . "cover_status character varying(250)) ";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");
            $sql = "drop sequence if exists $seqname";
            $db->exe($sql);
            $sql = "create sequence $seqname";
            $db->exe($sql);
        }

    }

    /**
     * @return mixed
     */
    function setStarttime() {
        $ts = $this->db->fetch("select current_timestamp as ts");
        $this->timestamp = $ts[0]['ts'];
        return $this->timestamp;
    }

    /**
     *
     */
    function startTransaction() {
        $this->db->start_transaction();
    }

    /**
     * @throws fetException
     */
    function endTransaction() {
        $this->db->end_transaction();
    }

    /**
     * @param $isbn13
     * @param $current_format
     * @return bool
     */
    function getInfo($isbn13, $current_format) {
        $provider = $this->provider;
        $tablename = $this->tablename;
        $where = "where idnumber = '$isbn13' and provider ='$provider' and format = '$current_format'";
        $sql = "select seqno, idnumber, status, originalxml, costfree from $tablename $where \n";
        $arr = $this->db->fetch($sql);
        if (!$arr) {
            return false;
        }
        $this->infodata = $arr[0];

        return $this->infodata;
    }

    /**
     * @param $seqno
     * @param $nullVars
     * @param $cf
     * @param $title
     * @param $xml
     * @throws fetException
     */
    function UpdateRow($seqno, $nullVars, $cf, $title, $xml) {
        $tablename = $this->tablename;
        $set = "";
        foreach ($nullVars as $key => $val) {
            $set .= " $key = null, ";
        }
        $upd = "update $tablename set "
            . "$set "
            . "status = 'n', "
            . "costfree = '$cf', "
            . "title = $1, "
            . "originalXML = $2, "
            . "updated = $3 "
            . "where seqno = $seqno";

        if ($this->nothing) {
            echo "($upd) Nothing SQL:$upd\n";
        } else {
            verbose::log(TRACE, "record updated:$seqno  - $title");
            verbose::log(DEBUG, $upd);
            $this->db->query_params($upd, array($title, $xml, $this->timestamp));
        }
    }

    /**
     * @param $current_format
     * @param $id
     * @param $title
     * @param $isbn13
     * @param $cf
     * @param $xml
     * @throws fetException
     */
    function insertRow($current_format, $id, $title, $isbn13, $cf, $xml) {
        $tablename = $this->tablename;
        $seqname = $this->seqname;
        $provider = $this->provider;
        $timestamp = $this->timestamp;
        $sql = "
                insert into $tablename
                    (seqno,provider,createdate,updated,format,idnumber,title,originalXML,isbn13,status,costfree)
                values
                    (nextval('$seqname'),
                     '$provider',
                     '$timestamp',
                     '$timestamp',
                     '$current_format',
                     '$id',
                     $1,
                     $2,
                     '$isbn13',
                     'n',
                     '$cf')
                 ";
        if ($this->nothing) {
            echo "Nothing SQL:$sql\n";
        } else {
//            verbose::log(TRACE, "record inserted:$id - $current_format - $title");
            $this->db->query_params($sql, array($title, $xml));
        }
    }

    /**
     * @param $current_format
     * @throws fetException
     */
    function setToDelete($current_format) {
        $delrecs = "update " . $this->tablename . " "
            . "set sent_to_basis = null, "
            . "sent_to_well = null, "
            . "sent_xml_to_well = null, "
            . "status = 'd', "
            . "deletedate = '" . $this->timestamp . "' "
            . "where format = '$current_format' and "
            . "provider = '" . $this->provider . "' "
            . "and status != 'd' "
            . "and updated < '" . $this->timestamp . "' ";
        $this->db->exe($delrecs);

        verbose::log(TRACE, $delrecs);

    }
}

