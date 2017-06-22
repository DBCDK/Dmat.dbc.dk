<?php

/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
class mediadbException extends Exception
{

    public function __toString() {
        return 'mediadbException -->' . $this->getMessage() . ' --- ' . $this->getFile() . ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}


class mediadb
{


    private $db;
    private $mediaservicetable;
    private $seqname;
    private $reftable;
    private $notetable;
    private $nseq;
    private $digitalresources;
    private $dbrecs;
    private $first;
    private $nothing;
    private $cnt;
    private $upd;
    private $ins;
    private $provider;
    private $tablerecover;
    private $recoverseq;
    private $program_name;
    private $timeconvert;
    private $timestamp;
    private $newfaust;
    private $mediaebookstable;
    private $userinfotable;
    private $marctable;
    private $leklogtable;
    private $leklogseq;
    private $to_publizon;
    private $mediaonixtable;
    private $tot;
    private $del;
    private $skp;
    private $maxdatestamp;
    private $pubhubFtp;
    private $epub;
    private $ONIXonOff;
    private $mediainfo;
    private $medialukarens;
    private $publishers;

    /**
     * mediadb constructor.
     * @param pg_database $db
     * @param $program_name
     * @param bool|false $nothing
     */
    public function __construct(pg_database $db, $program_name, $nothing = false, $pubhubFtp = null, epub_class $epub = null) {

//        verbose::log(TRACE, "mediadb_class __construct");
        $this->tot = 0;
        $this->skp = 0;
        $this->ins = 0;
        $this->upd = 0;
        $this->del = 0;
        $this->ONIXonOff = false;

        if (strlen($program_name) < 2) {
            $txt = "No program name in mediadb_class constructor!!\n";
            throw new mediadbException($txt);
        }
        $this->program_name = $program_name;
        $this->timeconvert = 'YYYYMMDDHH24MISS';
        $this->db = $db;
        $this->epub = $epub;
        $this->pubhubFtp = $pubhubFtp;
        $this->first = true;
        $this->nothing = $nothing;
        $this->newfaust = 9211110;
        $this->setStarttime();

        // tables used by DR++
        $this->mediaservicetable = 'mediaservice';
        $this->seqname = $this->mediaservicetable . 'seq';
        $this->reftable = $reftable = 'mediaservicedref';
        $this->notetable = $notetable = 'mediaservicenote';
        $this->nseq = $this->notetable . "seq";
        $this->digitalresources = 'digitalresources';
        $this->tablerecover = $tablerecover = "mediaservicerecover";
        $this->recoverseq = $recovernseq = $this->tablerecover . "seq";
        $this->mediaebookstable = 'mediaebooks';
        $this->userinfotable = 'mediaserviceuserinfo';
        $this->marctable = 'unittestsmarcrecords';
        $this->to_publizon = "mediatopublizon";
        $this->leklogtable = "medialeklog";
        $this->leklogseq = $this->leklogtable . "seq";
        $this->mediaonixtable = 'mediaonix';
        $this->mediainfo = 'mediainfo';
        $this->medialukarens = "medialukarens";
        $this->publishers = 'forlag';

        $this->provider = 'PubHubMediaService';

        // find all tables with prefix media
        $sql = "SELECT tablename FROM pg_tables " . "WHERE tablename LIKE 'media%' ";
        $arr = $this->db->fetch($sql);
//        var_dump($arr);
        $tablenames = array();
        if ($arr) {
            foreach ($arr as $table) {
                $tablenames[$table['tablename']] = true;
            }
        }

        $userinfotable = $this->userinfotable;
        if (!array_key_exists($userinfotable, $tablenames)) {
            $sql = "CREATE TABLE $userinfotable
                  (
                    username character varying(50),
                    createdate timestamp with time zone,
                    update timestamp with time zone,
                    role character varying(20),
                    passwd character varying(20)
                  )";
            $db->exe($sql);
            $insert = "insert into $userinfotable 
                        values( 'admin',current_timestamp, current_timestamp,
                                 'admin', 'admin')";
            $db->exe($insert);
            echo "$insert";
        }

        $tablename = $this->mediaservicetable;
        $seqname = $this->seqname;
//        $sql = "select tablename from pg_tables where tablename = $1";
//        $arr = $db->fetch($sql, array($tablename));
//        if (!$arr) {
        if (!array_key_exists($tablename, $tablenames)) {
            $sql = "create table $tablename (
            seqno integer primary key,
            status varchar(20),
            createdate timestamp with time zone,
            update timestamp with time zone,
            faust varchar(11),
            newfaust varchar(11),
            newfaustprinted varchar(11),
            printedfaust varchar(11),
            expisbn varchar(13),
            base varchar(10),
            promat timestamp with time zone,
            is_in_basis timestamp with time zone,
            updatepub timestamp without time zone,
            updatelekbase varchar(20),
            lekfaust varchar(11),
            printedpublished varchar(10),
            bkxwc varchar(8), 
            choice varchar(30),
            initials varchar(20),
            username varchar(20),
            lockdate timestamp with time zone
            
        )
            ";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");

            $sql = "drop sequence if exists $seqname";
            $db->exe($sql);
            $sql = "create sequence $seqname";
            $db->exe($sql);
        }

        $reftable = $this->reftable;
//        $select = "select tablename from pg_tables where tablename = '$reftable'";
//        $arr = $db->fetch($select);
//        if (!$arr) {
        if (!array_key_exists($reftable, $tablenames)) {
            $create = "create table $reftable (" . "seqno integer," . "createdate timestamp," . "base varchar(50)," . "lokalid varchar(20)," . "bibliotek varchar(20)," . "type varchar(20), " . "matchtype integer, " . "lektoer boolean " . ")";
            $db->exe($create);
        }

        $nseq = $this->nseq;
        $notetable = $this->notetable;
//        $sql = "select tablename from pg_tables where tablename = $1";
//        $arr = $db->fetch($sql, array($notetable));
//        if (!$arr) {
        if (!array_key_exists($notetable, $tablenames)) {
            $sql = "create table $notetable ( " . "seqno integer, " . "createdate timestamp with time zone, " . "updated timestamp with time zone, " . "type varchar(20), " . "text varchar(500), " . "initials varchar(20), " . "status varchar(10), " . "CONSTRAINT mediaservicednote_pkey PRIMARY KEY (seqno, text) )";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");
            $sql = "drop sequence if exists $nseq";
            $db->exe($sql);
            $sql = "create sequence $nseq";
            $db->exe($sql);
        }

        $tablerecover = $this->tablerecover;
        $recovernseq = $this->recoverseq;
//        $sql = "select tablename from pg_tables where tablename = $1";
//        $arr = $db->fetch($sql, array($tablerecover));
//        if (!$arr) {
        if (!array_key_exists($tablerecover, $tablenames)) {
            $sql = "create table $tablerecover ( " . "recoverseqno integer, " . "recovercreatedate timestamp with time zone, " . "seqno integer, " . "status varchar(20), " . "update timestamp with time zone, " . "faust varchar(11), " . "newfaust varchar(11), " . "expisbn varchar(13), " . "newfaustprinted varchar(11), " . "printedfaust varchar(11), " . "base varchar(10), " . "choice varchar(30)," . "promat timestamp with time zone, " . "initials varchar(20), " . "program varchar(250), " . "is_in_basis timestamp with time zone, " . "username character varying(20), " . "updatepub timestamp with time zone, " . "updatelekbase varchar(20), " . "lekfaust varchar(11), " . "printedpublished varchar(10), " . "bkxwc varchar(8), " . "CONSTRAINT recover_pkey PRIMARY KEY (recoverseqno) )";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");

            $sql = "drop sequence if exists $recovernseq; " . "create sequence $recovernseq ";

            $db->exe($sql);
        }

        $ebookstable = $this->mediaebookstable;
//        $sql = "select tablename from pg_tables where tablename = $1";
//        $arr = $db->fetch($sql, array($ebookstable));
//        if (!$arr) {
        if (!array_key_exists($ebookstable, $tablenames)) {
            $sql = "create table $ebookstable ( " . "seqno integer, " . "active varchar (20), " . "provider varchar(50), " . "createdate timestamp with time zone, " . "update timestamp with time zone, " . "booktype varchar(50), " . "filetype varchar(25), " . "bookid varchar(50), " . "ext varchar(10), " . "isbn13 varchar(50), " . "filesize integer, " . "checksum bigint, " . "publicationdate timestamp with time zone, " . "pubhubfiledate timestamp with time zone , " . "title varchar(100), " . "publisher varchar(100), " . "renditionlayout varchar(50), " . "source varchar(50), " . "originalXML text, " . "CONSTRAINT mediaebooks_pkey PRIMARY KEY (seqno))";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");
//            $insert = "insert into $ebookstable
//                    (seqno,provider,status,createdate,update,booktype,filetype,bookid,
//                       isbn13,title,originalXML,
//                       publicationdate,checksum)
//                select
//                    seqno, provider, status, createdate, update, booktype, filetype, bookid,
//                       isbn13, title, originalXML,
//                       publicationdate, checksum
//                  from " . $this->tablename;
//            $db->exe($insert);
            $indx = "create index bookid_idx on $ebookstable (bookid)";
            $db->exe($indx);
            verbose::log(TRACE, "index created:$indx");

            $indx = "CREATE UNIQUE INDEX providerisbn13_idx ON $ebookstable " . "USING btree " . "(provider, isbn13)";
            $db->exe($indx);
            verbose::log(TRACE, "index created:$indx");
        }


        $marctable = $this->marctable;
        $select = "select tablename from pg_tables where tablename = '$marctable'";
        $arr = $db->fetch($select);
        if (!$arr) {
            $create = "create table $marctable (" . "lokalid varchar(20)," . "library varchar(20)," . "marcln text)";
            $db->exe($create);
        }

        $topublizon = $this->to_publizon;
//        $select = "select tablename from pg_tables where tablename = '$topublizon'";
//        $arr = $db->fetch($select);
//        if (!$arr) {
        if (!array_key_exists($topublizon, $tablenames)) {
            $create = "create table $topublizon (" . "seqno integer," . "txt varchar(500))";
            $db->exe($create);
        }

        $leklog = $this->leklogtable;
        $leklogseq = $this->leklogseq;
        if (!array_key_exists($leklog, $tablenames)) {
            $sql = "create table $leklog ( " . "lekseqno integer, " . "createdate timestamp with time zone, " . "seqno integer, " . "message varchar(100), " . "faust varchar(11), " . "status varchar(20), " . "update timestamp with time zone) ";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");

            $sql = "drop sequence if exists $leklogseq; " . "create sequence $leklogseq ";

            $db->exe($sql);
        }
        $lukarens = $this->medialukarens;
        if (!array_key_exists($lukarens, $tablenames)) {
            $sql = "create table $lukarens ( 
                     lokalid varchar(11),
                     created timestamp with time zone,
                     opretdato timestamp with time zone,
                     ajour timestamp with time zone,
                     status varchar(20)
                   )";
            $db->exe($sql);
        }

        if (!array_key_exists($this->mediainfo, $tablenames)) {
            $sql = "CREATE TABLE " . $this->mediainfo . " (" . "name VARCHAR(50), " . "value VARCHAR(50), " . "ts TIMESTAMP WITH TIME ZONE " . ")";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");
            $sql = "INSERT INTO " . $this->mediainfo . " " . "(name,value,ts) " . "VALUES ('lastOnixUpdate','ts','19700101T00:00:00Z') ";
            $db->exe($sql);
            verbose::log(TRACE, "inserted:$sql");
        }

        if (!array_key_exists($this->mediaonixtable, $tablenames)) {
            $sql = " CREATE TABLE " . $this->mediaonixtable . " (" . "seqno INTEGER, " . "active VARCHAR(20)," . "datestamp TIMESTAMP WITH TIME ZONE, " . "provider VARCHAR(50), " . "createdate TIMESTAMP WITH TIME ZONE, " . "update TIMESTAMP WITH TIME ZONE, " . "booktype VARCHAR(50), " . "filetype VARCHAR(25), " . "bookid VARCHAR(50), " . "isbn13 VARCHAR(50), " . "publicationdate TIMESTAMP WITH TIME ZONE, " . "title VARCHAR(100), " . "publisher VARCHAR(100), " . "renditionlayout VARCHAR(50), " . "source VARCHAR(50), " . "originalXML TEXT, " . "CONSTRAINT media_onix_pkey PRIMARY KEY (seqno))";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");
            $indx = "CREATE UNIQUE INDEX media_isbn13_idx ON " . $this->mediaonixtable . " " . "USING BTREE " . "(isbn13)";
            $db->exe($indx);
            verbose::log(TRACE, "index created:$indx");
        }
//            $start = 1;
//            if ($this->transform) {
//                $sql = "select last_value from mediaserviceseq";
//                $rows = $db->fetch($sql);
//                $start = $rows[0]['last_value'] + 1;
//            }
//            $sql = "drop sequence if exists " . $this->onixseq . " ";
//            $db->exe($sql);
//            $sql = "create sequence " . $this->onixseq . " start with $start";
//            $db->exe($sql);


        $this->maxdatestamp = 0;
        $sql = "SELECT ts  AS maxdatestamp FROM " . $this->mediainfo . "
                WHERE name = 'lastOnixUpdate'";
        $arr = $db->fetch($sql);
        if ($arr) {
            $txt = $arr[0]['maxdatestamp'];
            $this->maxdatestamp = strtotime($txt);
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
     * @return string isbn's
     */
    function getIsbns() {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $sql = "select isbn13, expisbn from $tablename m, $ebookstable e  \n" . "where status in ('Template','UpdateBasis','ProgramMatch','UpdatePromat') \n" . "and newfaust is null \n" . "and is_in_basis is null \n" . "and m.seqno = e.seqno \n" . "order by m.seqno \n";

        return $this->db->fetch($sql);
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
        $this->db->exe('commit');
    }

    /**
     * @param $status
     * @param $max
     * @param int $seqno
     * @return array
     */
    function getPubSeqnos($status, $max, $seqno = 0) {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $and = "and status in ($status) ";
        $updatepub = "and updatepub is null ";
        if ($seqno) {
            $and = "and m.seqno = $seqno ";
            $updatepub = "";
        }
        $sql = "select m.seqno, status, isbn13, faust, newfaust, choice, is_in_basis " . "from $tablename m, $ebookstable e " . "where provider = 'PubHubMediaService' " . "$and " . "$updatepub " . "and m.seqno = e.seqno " . "order by seqno " . "limit $max ";

        $ret = $this->db->fetch($sql);
        return $ret;
    }

    function updateStatusOnly($seqno, $status) {
        $tablename = $this->mediaservicetable;
        $update = "update $tablename " . "set status = '$status' " . "where seqno = $seqno ";
        $this->updateRecover($seqno);
        if (!$this->nothing) {
            $this->db->exe($update);
        }
    }

    /**
     * @param $seqno
     * @param $status
     * @throws fetException
     */
    function updatePub($seqno, $status) {
        $tablename = $this->mediaservicetable;
//        $ebookstable = $this->ebookstable;


        $sta = "";
        if ($status == 'UpdatePublizon') {
            $sta = ", status = 'Done' ";
        }
        $update = "update $tablename m " . "set updatepub = current_timestamp $sta " . "where seqno = $seqno ";
        $this->updateRecover($seqno);
        $this->db->exe($update);
    }

    function setONIXonOff() {
        $this->ONIXonOff = true;
    }

    /**
     * @param $status
     * @param string $onlyThisSeqno
     * @return array
     */
    function getCandidates($status, $onlyThisSeqno = '') {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $provider = $this->provider;
        if ($this->ONIXonOff) {
            $ebookstable = $this->mediaonixtable;
            $provider = 'Publizon';
        }

        if ($onlyThisSeqno) {
            $sql = "select m.seqno, newfaust, choice, title " . "from $tablename m, $ebookstable e " . "where m.seqno = $onlyThisSeqno " . "and m.seqno = e.seqno ";
        } else {
            $sql = "select m.seqno, newfaust, choice, title " . "from $tablename m, $ebookstable e " . "where provider = '$provider' " . "and status = '$status' " //            . "and newfaust is  null  ";
                . "and newfaust is not  null " . "and m.seqno = e.seqno " . "order by publicationdate desc";
        }
        return $this->db->fetch($sql);
    }

    /**
     * @return array
     */
    function getAllBookids() {
        $tablename = $this->mediaebookstable;
//        $tablename = $this->tablename;
        $sql = "select isbn13, bookid, filetype, to_char(pubhubfiledate,'Mon DD YYYY') as fdate " . "from $tablename " . "order by bookid ";
        $arr = $this->db->fetch($sql);
        return $arr;
    }


    /**
     * @param $bookid
     * @param $info
     * @throws fetException
     */
    function updateEbook($bookid, $info) {
        $timestamp = $this->timestamp;
        $ebookstable = $this->mediaebookstable;
        $sql = "update $ebookstable set " . "ext = '" . $info['ext'] . "', " . "filesize = " . $info['filesize'] . ", " . "pubhubfiledate = to_timestamp('" . $info['fdate'] . "','Mon DD YYYY'), " . "update = '$timestamp', " . "publisher = $1, " . "renditionlayout  = '" . $info['renditionlayout'] . "', " . "source = '" . $info['source'] . "' " . "where bookid = '" . $info['bookid'] . "' ";
        $this->db->query_params($sql, array($info['publisher']));
    }

    /**
     * @param $arr_status
     * @param $directseqno
     * @return array
     */
    function getMatchObjects($arr_status, $directseqno = 0) {
        if ($directseqno) {
            $cond = "and m.seqno = $directseqno ";
        } else {
//            $arr = explode(',', $str_status);
            $cond = "and status in (";
            foreach ($arr_status as $sta => $val) {
                $cond .= "'$sta',";
            }
            $cond = rtrim($cond, ',') . ") ";
            $cond .= " and publicationdate < current_date + integer '30' ";
        }

        $provider = $this->provider;
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $select = "select m.seqno from $tablename m, $ebookstable e " . "where provider = '$provider' " . "and booktype = 'Ebog' " . "$cond " . "and m.seqno = e.seqno " . "order by m.seqno desc ";
        $rows = $this->db->fetch($select);
        return $rows;
    }

    /**
     * @param $seqno
     * @param $newstatus
     * @param bool|false $clear Only used in the program "newstatus.php"
     * @throws fetException
     */
    function newStatus($seqno, $newstatus, $clear = false) {

        $tablename = $this->mediaservicetable;
        $setToNull = "";
        if ($clear) {
            $setToNull = "promat = null, updatepub = null, ";
        }
        $update = "update $tablename " . "set status = '$newstatus', " . "$setToNull " . "update = current_timestamp " . "where seqno = $seqno ";
        $this->updateRecover($seqno);
        if ($this->nothing) {
            echo "Update:$update\n";
        } else {
            $this->db->exe($update);
        }
    }

    /**
     * @param $seqno
     * @return array
     */
    function getNoteData($seqno) {
        $notetable = $this->notetable;
        $sql = "select text, initials, type " . "from $notetable " . "where seqno = $seqno " . "and type in ('waiting','note','f991','waitingelu') ";
//            . "and status == 'active' ";
        return $this->db->fetch($sql);
    }

    /**
     * @param $seqno
     * @return array
     */
//    function getInfoBookData($seqno) {
//        $tablename = $this->ebookstable;
//        $fetch = "select originalxml, isbn13, booktype, filetype, title, "
//            . "active, createdate, update, "
//            . "to_char(publicationdate,'YYYYMMDD') as publicationdate "
//            . "from $tablename  "
//            . "where seqno = $seqno ";
//        $records = $this->db->fetch($fetch);
//        return $records;
//    }


    function getInfo($seqno) {

    }

    /**
     * @param $seqno
     * @return array
     */
    function getInfoData($seqno) {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        if ($this->ONIXonOff) {
            $ebookstable = $this->mediaonixtable;
        }
        $fetch = "select originalxml, isbn13, expisbn, booktype, filetype, title, " . "status, faust, newfaust, newfaustprinted, printedfaust, lekfaust, printedpublished, m.createdate, m.update, " . "e.createdate as ecreatedate, e.update as eupdate, source, " . "to_char(publicationdate,'YYYYMMDD') as publicationdate, choice, " . "promat, initials, is_in_basis, updatelekbase, lekfaust, bkxwc  " . "from $tablename m, $ebookstable e " . "where m.seqno = $seqno and m.seqno = e.seqno ";


        $records = $this->db->fetch($fetch);
        if ($records) {
            foreach ($records as $key => $inf) {
                $sisbn = '';
                if ($inf['source']) {
                    $sisbn = '';
                    $txt = $inf['source'];
                    for ($i = 0; $i < strlen($txt); $i++) {
                        if (ctype_digit(substr($txt, $i, 1))) {
                            $sisbn .= substr($txt, $i, 1);
                        }
                    }
                }
                $records[$key]['source'] = $sisbn;
            }
        }

        return $records;
    }

    /**
     *
     * @param type $isbn13
     * @return type
     */
    function IsItInDigitalResources($isbn13) {
        $dr = $this->digitalresources;
        $mMarc = array();
        $select = "select faust from $dr 
            where isbn13 = '$isbn13' 
            and provider = 'Pubhub' ";
        $res = $this->db->fetch($select);
        if ($res) {
            $mMarc[$res[0]['faust']]['870970']['basis']['matchtype'] = 0;
            $mMarc[$res[0]['faust']]['870970']['basis']['lektoer'] = false;
            return $mMarc;
        }
        return false;
    }

    /**
     * @param $seqno
     * @return array|bool
     */
    function getRefDataNmarc($seqno) {
        $sql = "select lokalid, bibliotek, base, type, matchtype, lektoer
                from " . $this->reftable . " where seqno = $seqno ";
        $rows = $this->db->fetch($sql);
        if (!$rows) {
            return false;
        }
        $nMarc = array();
        foreach ($rows as $row) {
            $nMarc[$row['lokalid']][$row['bibliotek']][$row['base']]['type'] = $row['type'];
            $nMarc[$row['lokalid']][$row['bibliotek']][$row['base']]['matchtype'] = $row['matchtype'];
            $nMarc[$row['lokalid']][$row['bibliotek']][$row['base']]['lektoer'] = $row['lektoer'];
        }
        return $nMarc;
    }

    function getRefData($seqno) {
        $select = "select seqno, base, lokalid, bibliotek, type, matchtype " . "from " . $this->reftable . " " . "where seqno = $seqno " . "order by matchtype desc";
        $refrows = $this->db->fetch($select);
        return $refrows;
    }

    /**
     *
     * @param type $seqno
     * @param type $mMarc
     * @brief
     * Parameter $mMarch shall have the following structure
     * $mMarch['lokalid']['bibliotek']['base']['type'] => value of type
     * $mMarch['lokalid']['bibliotek']['base']['matchtype'] => value of Matchtype
     *
     */
    function updateRefData($seqno, $mMarc) {
        $this->db->exe('BEGIN');
        $del = "delete from " . $this->reftable . " " . "where type != 'manuelt' and seqno = $seqno";
        $this->db->exe($del);
        if ($mMarc) {
            foreach ($mMarc as $lokalid => $val1) {
                foreach ($val1 as $bibliotek => $val2) {
                    foreach ($val2 as $base => $val3) {
                        $type = $val3['type'];
                        $matchtype = $val3['matchtype'];
                        $lektoer = $val3['lektoer'];
                        $this->insertReftable($seqno, $base, $lokalid, $bibliotek, $type, $matchtype, $lektoer);
                    }
                }
            }
        }
        $this->db->exe('COMMIT');
    }

    /**
     *
     * @param type $seqno
     * @param type $base
     * @param type $lokalid
     * @param type $bibliotek
     * @param type $type
     * @param type $matchtype
     */
    function insertReftable($seqno, $base, $lokalid, $bibliotek, $type, $matchtype, $lektoer) {
        if ($lektoer) {
            $lektoer = 'true';
        } else {
            $lektoer = 'false';
        }
        $insert = "insert into " . $this->reftable . " " . "(seqno, createdate, base, lokalid, bibliotek, type, matchtype,lektoer) values" . "($seqno, current_timestamp, '$base', '$lokalid', '$bibliotek', '$type', " . "'$matchtype','$lektoer')";
        $this->db->exe($insert);
    }


    /**
     *
     * @param type $seqno
     * @param type $mMarc
     * @param type $found
     *
     * The function finds the best candidate (mMarc) and this candidate determent which
     * status the record will be updated to.
     * if "found" is true, the record is already in DigitalResources (eReolen) and will
     * therefore be updated to DigitalR, which means no further actions.
     */
    function updateStatus($seqno, $mMarc, $found, $record, $old = '') {
        $tablename = $this->mediaservicetable;
//        $lokalid = '';
        $faust = "";
        $best_match = array();
        if ($mMarc) {
            foreach ($mMarc as $lokalid => $res) {
                foreach ($res as $bib => $dataset) {
                    foreach ($dataset as $base => $data) {
                        $matchtype = $data['matchtype'];
                        if (!array_key_exists($matchtype, $best_match)) {
                            $best_match[$matchtype]['cnt'] = 0;
                        }
                        if ($base == 'Phus') {
                            $matchtype = 50;
                        }
                        $best_match[$matchtype]['cnt']++;
                        $best_match[$matchtype]['lokalid'] = $lokalid;
                        $best_match[$matchtype]['bib'] = $bib;
                        $best_match[$matchtype]['base'] = $base;
                    }
                }
            }
        }
        if ($found) {
            $status = 'matchDigitalR';
            $akeys = array_keys($found);
            $lokalid = $akeys[0];
            $faust = "faust = '$lokalid', ";
        } else {
            $value['cnt'] = 1000;
            krsort($best_match);
            foreach ($best_match as $key => $value) {
                break;
            }

            if ($value['cnt'] > 1) {
                // more than one best_match with the same matchType - a libarien will look at the record.
                $status = 'eVa';
            } else {
                $lokalid = $value['lokalid'];
                $base = $value['base'];
                switch ($key) {
                    case 317:
                    case 319:
                    case 323:
                    case 353:
                        $status = 'UpdatePublizon';
                        $faust = "faust = '$lokalid', base = '$base', ";
                        break;
                    case 102:
                    case 106:
                    case 202:
                    case 206:
//                    case 300:
                    case 302:
                    case 306:
                        // ændret fra ProgramMatch til eLu da vi skal tage stilling til om posten skal have Lektør
//                    $status = 'ProgramMatch';
                        $status = 'eVa';
                        $faust = "faust = '$lokalid', base = '$base', ";
                        break;
                    case 50:
                        $status = 'Hold';
                        break;
                    case 100:
                    case 101:
                    case 103:
                    case 104:
                    case 116:
                    case 117:
                    case 118:
                    case 119:
                    case 120:
                    case 122:
                    case 123:
                    case 200:
                    case 201:
                    case 203:
                    case 204:
                    case 216:
                    case 217:
                    case 218:
                    case 220:
                    case 222:
                    case 223:
                    case 301:
                    case 303:
                    case 304:
                    case 305:
                    case 315:
                    case 316:
                    case 318:
                    case 320:
                    case 322:
                    case 350:
                    case 417:
                    case 419:
                    case 85:
                    case 90:
                        $status = 'eVa';
                        break;
                    default:
                        $status = 'eVa';
                }
            }
        }
        if (array_key_exists('status', $record)) {

            if ($record['status'] == 'OldTemplate' and $status == 'Template' and $old = 'OldTemplate') {
                $status = 'OldTemplate';
            }
        }
//        if ($record['status'] != $status and $record['lokalid'] != $lokalid and $record['base'] != $base) {
        $update = "update $tablename set status = '$status', " . "$faust " . "update = current_timestamp " . "where seqno = $seqno";
        $this->updateRecover($seqno);
        $this->db->exe($update);
//        }
    }

    /**
     * @param $seqno
     * @throws fetException
     */
    private function updateRecover($seqno) {
        $recover = $this->tablerecover;
        $seq = $this->recoverseq;
        $program = $this->program_name;
        $tablename = $this->mediaservicetable;

        $insert = "INSERT INTO $recover(
            recoverseqno, recovercreatedate, seqno, status, update, faust, newfaust, expisbn,
            newfaustprinted, printedfaust, 
            base, choice, promat, initials, username, 
            is_in_basis, updatepub, updatelekbase, lekfaust, printedpublished, bkxwc, program)
                (   select  nextval('$seq'), current_timestamp,
                    seqno, status, update, faust, newfaust, expisbn, 
                    newfaustprinted, printedfaust, base, choice,
                    promat, initials, username, is_in_basis, updatepub, 
                    updatelekbase, lekfaust, printedpublished, bkxwc, '$program'
                    from $tablename where seqno = $seqno)";
        $this->db->exe($insert);
    }


    function getUpdateCnts() {
        return array('tot' => $this->tot, 'ins' => $this->ins, 'upd' => $this->upd, 'del' => $this->del);
    }

    function getInfoByIsbn($isbn13) {
        $sql = "select * from " . $this->mediaonixtable . " " . "where isbn13 = '$isbn13' ";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            return $rows[0];
        }
        return false;
    }

    function updateONIX($info, $xml) {

        $seqname = $this->seqname;
        $this->tot++;
        $datestamp = $info['datestamp'];
//        $ds = strtotime($datestamp);
        if (strtotime($datestamp) <= $this->maxdatestamp) {
            $this->skp++;
            return;
        }

        $status = $info['status'];
        $provider = $info['senderName'];
        $ts = $this->timestamp;
        $booktype = $info['ContentType'];
        $pathparts = pathinfo($info['websiteLink']);
        $filetype = $pathparts['extension'];
        $bookid = $info['RecordReference'];
        $isbn13 = $info['isbn13'];
        $publicationdate = $info['publishingDate'];
        $title = mb_strcut($info['title']['text'], 0, 99, "UTF-8");
        $publisher = mb_strcut($info['PublisherName'], 0, 99, "UTF-8");
//        if (array_key_exists('form', $info)) {
        $renditionlayout = $info['form'];
//        } else {
//            $renditionlayout = '';
//        }
        $epubinfo = array('source' => null);
        if ($this->epub and $this->pubhubFtp) {
            $ftplink = basename($info['websiteLink']);
            $filename = $this->pubhubFtp->getEbookFromPubhub(array('ext' => $filetype, 'server_file' => $ftplink));
            // if filename is false, the publication is not an epub
            if ($filename) {
                if ($this->epub->initEpub($filename)) {
                    $epubinfo['publisher'] = mb_strcut($this->epub->getElement('publisher'), 0, 99, "UTF-8");
                    $epubinfo['renditionlayout'] = $this->epub->getLayout();
                    $epubinfo['source'] = $this->epub->getElement('source');
                    $epubinfo['title'] = $this->epub->getTitle();;
                } else {
                    verbose::log(ERROR, "how did we end up here?");
//                    exit(1);
                }
                $this->pubhubFtp->removeFile($filename);
            }
        }
        $source = $epubinfo['source'];

        $this->db->start_transaction();

        // is there a record in the ONIX table with this isbn
        if ($dbinfo = $this->getInfoByIsbn($info['isbn13'])) {
            if ($status == 'NotActive') {
                $this->del++;
            } else {
                $this->upd++;
            }
            // if a records change from NotActive to Active - make it Pending
            $sta = 'Pending';
            if ($status == 'NotActive') {
                $sta = 'NotActive';
            }
            $upd = "update " . $this->mediaservicetable . " " . "set status = '$sta', " . "update = '$ts' " . "where seqno = " . $dbinfo['seqno'] . " ";
            if ($this->nothing) {
                echo "Nothing SQL:$upd\n";
            } else {
                $this->db->exe($upd);
                verbose::log(DEBUG, $upd);
            }
            $update = "update " . $this->mediaonixtable . " set active = '$status', datestamp = '$datestamp', update = '$ts', booktype = '$booktype', 
                      filetype = '$filetype', publicationdate = to_timestamp('$publicationdate','YYYYMMDD'), title = $1, 
                      publisher = $2, renditionlayout = '$renditionlayout', source = '$source', originalxml = $3 
                      where seqno = " . $dbinfo['seqno'] . " ";
            if ($this->nothing) {
                echo "Nothing SQL:$update\n";
            } else {
                verbose::log(TRACE, "record updated (" . $this->upd . ") - $booktype - $filetype - $title");
                $this->db->query_params($update, array($title, $publisher, $xml));
            }
        } else {
            // there is not a record in the ONIX table with this isbn
            $this->ins++;
//            $oldseqnos = $this->db->fetch("select seqno from " . $this->mediaebookstable . " where bookid = '$bookid'");
            $oldseqnos = $this->db->fetch("select seqno from " . $this->mediaebookstable . " where isbn13 = '$isbn13'");
            if (!$oldseqnos) {  // Does not exsist in mediaebooks table
                // Pull a sequence number
                $newseqnos = $this->db->fetch("SELECT nextval('mediaserviceseq') as seqno");
                $newseqno = $newseqnos[0]['seqno'];
                // Insert in mediaonix table
                $insert = "insert into " . $this->mediaonixtable . " (seqno,active,datestamp,provider,createdate,update,booktype,filetype,bookid,
                       isbn13,publicationdate,title,publisher,renditionlayout,source,originalxml)
                       values(
                       '$newseqno',
                       '$status',
                       '$datestamp',
                       '$provider',
                       '$ts',
                       '$ts',
                       '$booktype',
                       '$filetype',
                       '$bookid',
                       '$isbn13',
                        to_timestamp('$publicationdate','YYYYMMDD'),
                       $1,
                       $2,
                       '$renditionlayout',
                       '$source',
                       $3
                       )";
                if ($this->nothing) {
                    echo "Nothing SQL:$insert\n";
                } else {
                    verbose::log(TRACE, "record inserted in onix (" . $this->ins . ") - $booktype - $filetype - $title");
                    $this->db->query_params($insert, array($title, $publisher, $xml));
                }

                // Insert in mediaservice table with status = 'PendingOnix'
                $sql = "insert into " . $this->mediaservicetable . "(seqno, status, createdate, update) " . "select seqno, 'PendingOnix', createdate, update from " . $this->mediaonixtable . " where seqno = $newseqno";
                if ($this->nothing) {
                    echo "Nothing SQL:$sql\n";
                } else {
                    verbose::log(TRACE, "record inserted in mediaservice (" . $this->ins . ") - $booktype - $filetype - $title");
                    $this->db->exe($sql);
                }

                // Check if found in the digitalresources table
                if (!$this->IsItInDigitalResources($isbn13)) {
                    $txt = "Ingen post i digitalresources med dette nummer $status $bookid, $isbn13, $title\n";
                    echo $txt . "\n";
                    verbose::log(ERROR, $txt);
                }
            } else {  // Does exist in mediaebooks table
                if (count($oldseqnos) != 1) {
                    throw new mediadbException("Number of seqno belonging to $bookid is not 1 (one)");
                }
                $oldseqno = $oldseqnos[0]['seqno'];

                // Insert in mediaonix table with the sequence number found in ebooks
                $insert = "insert into " . $this->mediaonixtable . " (seqno,active,datestamp,provider,createdate,update,booktype,filetype,bookid,
                       isbn13,publicationdate,title,publisher,renditionlayout,source,originalxml)
                       values(
                       '$oldseqno',
                       '$status',
                       '$datestamp',
                       '$provider',
                       '$ts',
                       '$ts',
                       '$booktype',
                       '$filetype',
                       '$bookid',
                       '$isbn13',
                        to_timestamp('$publicationdate','YYYYMMDD'),
                       $1,
                       $2,
                       '$renditionlayout',
                       '$source',
                       $3
                       )";
                if ($this->nothing) {
                    echo "Nothing SQL:$insert\n";
                } else {
                    verbose::log(TRACE, "record inserted (" . $this->ins . ") - $booktype - $filetype - $title");
                    $this->db->query_params($insert, array($title, $publisher, $xml));
                }
            }
        }
        $this->db->exe('COMMIT');
    }

    /**
     * Todo: Update according to PendingOnix status
     * @param type $booktype
     * @param type $filetype
     * @param type $bookid
     * @param type $isbn13
     * @param type $title
     * @param string $publicationdate DD-MM-YYYY
     * @param type $xml
     */
    function updateBooks($booktype, $filetype, $bookid, $isbn13, $title, $publicationdate, $xml, $provider = '') {
        if ($this->first) {
            $this->init();
        }
        $ts = $this->timestamp;
        $status = 'Pending';
        if (!$provider) {
            $provider = $this->provider;
        } else {
            $status = 'kunEreol';
        }
        $seqname = $this->seqname;
        $checksum = crc32($xml);

        $this->cnt++;
        $this->db->start_transaction();

        if (array_key_exists($bookid, $this->dbrecs)) {
            $arr = $this->dbrecs[$bookid];
            $seqno = $arr['seqno'];
            $active = $arr['active'];
//            $this->currentSeqno = $seqno;
            $this->dbrecs[$bookid]['found'] = true;
            $chksum = $arr['checksum'];
            if ($active == 'NotActive') {
                $upd = "update " . $this->mediaservicetable . " " . "set status = 'Pending', " . "update = '$ts' " . "where seqno = $seqno ";
                if ($this->nothing) {
                    echo "Nothing SQL:$upd\n";
                } else {
                    //verbose::log(TRACE, "record updated:(" . $this->upd . ") $isbn13 - $booktype - $filetype - $title");
//                    $this->updateRecover($seqno);
                    $this->db->exe($upd);
                    verbose::log(DEBUG, $upd);
                }
            }
            // the new xml and the one stored in database are identical if
            // chksum and checksum are alike!
            if ($checksum != $chksum or $active == 'NotActive') {
                $this->upd++;
                $this->dbrecs[$bookid]['checksum'] = $checksum;
                $sql = "update " . $this->mediaebookstable . " " . "set booktype = '$booktype', " . "active = 'Active', " . "filetype = '$filetype', " . "publicationdate = to_timestamp('$publicationdate','DD-MM-YYYY'), " . "update = '$ts', " . "title = $1, " . "originalXML = $2, " . "checksum = $checksum " . "where seqno = $seqno ";
                if ($this->nothing) {
                    echo "Nothing SQL:$sql\n";
                } else {
                    verbose::log(TRACE, "record updated:(" . $this->upd . ") $isbn13 - $booktype - $filetype - $title");
//                    $this->updateRecover($seqno);
                    $this->db->query_params($sql, array($title, $xml));
                    verbose::log(DEBUG, $sql);
                }
            }
        } else { // the record is not known in the database
            $this->ins++;

            $sql = "
                insert into " . $this->mediaebookstable . "
                    (seqno,provider,active,createdate,update,booktype,filetype,bookid,
                       isbn13,title,originalXML,
                       publicationdate,checksum)
                values
                    (nextval('$seqname'),
                     '$provider',
                     'Active',
                     '$ts',
                     '$ts',
                     '$booktype',
                     '$filetype',
                     '$bookid',
                     '$isbn13',
                     $1,
                     $2,
                     to_timestamp('$publicationdate','DD-MM-YYYY'),
                     $checksum
                     )
                 ";
            if ($this->nothing) {
                echo "Nothing SQL:$sql\n";
            } else {
                verbose::log(TRACE, "record inserted (" . $this->ins . ") - $booktype - $filetype - $title");
                $this->db->query_params($sql, array($title, $xml));
                $sql = "select currval('$seqname') as seqno";
                $arr = $this->db->fetch($sql);
                $seqno = $arr[0]['seqno'];
//                $this->currentSeqno = $seqno;
                $this->dbrecs[$bookid] = array('seqno' => $seqno, 'bookid' => $bookid, 'checksum' => $checksum, 'found' => true);
                verbose::log(DEBUG, $sql);
            }
            $sql = "insert into " . $this->mediaservicetable . "
                  (seqno, status, createdate, update)
              select
                  seqno, '$status' , createdate, update
                   from " . $this->mediaebookstable . "
                   where seqno = $seqno ";
            if ($this->nothing) {
                echo "Nothing SQL:$sql\n";
            } else {
                $this->db->exe($sql);
            }
        }
        $this->db->exe('COMMIT');
    }

    /**
     *
     */
    private function init() {
        $this->first = false;
        $this->dbrecs = array();
        $sql = "select seqno, active, bookid, checksum, '0' as found " . "from " . $this->mediaebookstable . " ";
        $arr = $this->db->fetch($sql);
        if ($arr) {
            foreach ($arr as $b) {
                $this->dbrecs[$b['bookid']] = $b;
            }
        }
    }

    /**
     * @param $isbn13
     * @param $new_faust
     * @throws fetException
     */
    function insertNewFaust($isbn13, $new_faust, $newfaustprinted) {
        $nothing = $this->nothing;
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;

        if ($newfaustprinted) {
            $newfp = ", newfaustprinted = '$newfaustprinted' ";
        } else {
            $newfp = "";
        }

        // is there more than one ISBN13, if not get the seqno
        $find = "select seqno from $ebookstable " . "where isbn13 = '$isbn13' ";
        $seqnos = $this->db->fetch($find);
        if ($seqnos) {
            if (count($seqnos) != 1) {
                vebose::log(ERROR, "Forkert antal isbn's i $tablename (isbn: $isbn13");
                return;
            }
        }
        $seqno = $seqnos[0]['seqno'];
        $sql = "update $tablename " . "set newfaust = '$new_faust' $newfp" . "where seqno = $seqno ";
        verbose::log(TRACE, "Nothing:($nothing), isbn13: $isbn13, seqno:$seqno, new_faust:$new_faust");
        if ($nothing) {
            echo $sql;
        } else {
            $this->updateRecover($seqno);
            $this->db->exe($sql);
        }

    }

    function setNotActive() {
        //$timeconvert = $this->timec

        foreach ($this->dbrecs as $dbrec) {
            $this->db->start_transaction();
            if ($dbrec['found'] == false) {
                $sql = "update " . $this->mediaebookstable . " " . "set active = 'NotActive' " . "where seqno = " . $dbrec['seqno'] . " " . "and active != 'NotActive' ";
                if ($this->nothing) {
                    echo "Nothing SQL:$sql\n";
                } else {
                    verbose::log(TRACE, "records altered to notActive ($sql)");
//                    $this->updateRecover($dbrec['seqno']);
                    $this->db->exe($sql);
                }
                // Nedenstående fjernes når vi har rettet op hele vejen igennem.
                $find = "SELECT status FROM " . $this->mediaservicetable . " " . "WHERE seqno = " . $dbrec['seqno'] . " ";
                $sta = $this->db->fetch($find);
                if ($sta[0]['status'] != 'NotActive') {
                    $sql = "update " . $this->mediaservicetable . " " . "set status = 'NotActive' " . "where seqno = " . $dbrec['seqno'] . " " . "and status != 'NotActive' ";
                    if ($this->nothing) {
                        echo "Nothing SQL:$sql\n";
                    } else {
                        verbose::log(TRACE, "records altered to notActive ($sql)");
                        $this->updateRecover($dbrec['seqno']);
                        $this->db->exe($sql);
                    }
                }
            }
            $this->db->end_transaction();
        }
    }

    /**
     * @return string
     */
    function getSta($seqno = 0) {
        if ($seqno) {
            $and = " and m.seqno = $seqno ";
        } else {
            $and = "";
        }
        $sql = "select status, booktype, filetype, count(*) as cnt
                  from " . $this->mediaservicetable . " m, " . $this->mediaebookstable . " e
                  where m.seqno = e.seqno $and
                  group by status, booktype, filetype
                  order by status, booktype, filetype";
        $sta = $this->db->fetch($sql);

        $txt = "";
        if (!$sta) {
            return $txt;
        }
        foreach ($sta as $s) {
            foreach ($s as $key => $val) {
                $txt .= $key . ":" . $val . "|";
            }
        }
        return $txt;
    }

    /**
     * @return string
     */
    function getBookSta() {
        $sql = "SELECT status, booktype, filetype, count(*) AS cnt
                  FROM " . $this->mediaservicetable . " m, " . $this->mediaebookstable . " e
                  WHERE m.seqno = e.seqno
                  GROUP BY status, booktype, filetype
                  ORDER BY status, booktype, filetype";
        $sta = $this->db->fetch($sql);

        $txt = "";
        if (!$sta) {
            return $txt;
        }
        foreach ($sta as $s) {
            foreach ($s as $key => $val) {
                $txt .= $key . ":" . $val . "|";
            }
        }
        return $txt;
    }

    /**
     * @return string
     */
    function getStaRecover() {

//        $sql = "select sum(seqno) as sum, count(status) as cnt from mediaservicerecover";
        $sql = "SELECT program, status, count(*) AS cnt
                    FROM mediaservicerecover
                    GROUP BY program, status
                    ORDER BY program, status";
        $sta = $this->db->fetch($sql);

        $txt = "";
        if ($sta) {
            foreach ($sta as $s) {
                foreach ($s as $key => $val) {
                    $txt .= $key . ":" . $val . "|";
                }
            }
        }
        return $txt;
    }

    /**
     * @return string
     */
    function getRefSta($big = false) {
        if ($big) {
            $sql = "SELECT matchtype, count(*) AS cnt
                  FROM mediaservicedref
                  GROUP BY matchtype
                  ORDER BY matchtype";
        } else {


            $sql = "SELECT seqno, type, matchtype , count(*) AS cnt
                FROM " . $this->reftable . "
                GROUP BY seqno, type, matchtype
                ORDER BY seqno, type, matchtype";
        }
        $sta = $this->db->fetch($sql);

        $txt = "";
        if ($sta) {
            foreach ($sta as $s) {
                foreach ($s as $key => $val) {
                    $txt .= $key . ":" . $val . "|";
                }
            }
        }
        return $txt;
    }


//    function startNewFaust($startfaust) {
//        $this->newfaust = $startfaust;
//    }

    /**
     * @return string
     */
    function getFakeFaust() {

        $checkc = 10;
        while ($checkc == 10) {
            $this->newfaust++;
//            echo "checkc"
            $ts = substr($this->newfaust, 0);
            $vgt = 2;
            $sum = 0;
            for ($i = 0; $i < 7; $i++) {
                $sum += ($ts[$i] - '0') * ($vgt - $i);
                $gan = $vgt - $i;
                if ($i == 0) {
                    $vgt = 8;
                }
            }
            $checkc = 11 - ($sum % 11);
            if ($checkc == 11) {
                $checkc = 0;
            }
        }
        return substr($ts, 0, 1) . " " . substr($ts, 1, 3) . " " . substr($ts, 4, 3) . " " . $checkc;
    }

    /**
     * @param $seqno
     * @throws fetException
     */
    function releaseLck($seqno, $username = '') {
        if ($username) {
            $sql = "update " . $this->mediaservicetable . " set lockdate = null, " . "username = null " . "where username = '$username' ";
            $this->db->exe($sql);
        }
        if ($seqno) {
            $sql = "update " . $this->mediaservicetable . " set lockdate = null, " . "username = null " . "where seqno = $seqno ";
            $this->db->exe($sql);
        }
    }

    /**
     * @param $req
     * @throws fetException
     */
    function updateDB($req) {
        $initials = getInitials();
        // bliver sat så xDebug kan finde udaf det.
        $cmd = $promat = $lokalid = $base = $score = $seqno = $type = $matchtype = $bibliotek = $NewRec = '';

        $choice = '';
        $BKM = $BKMV = false;

        foreach ($req as $key => $value) {
            ${$key} = "$value";
            if ($key == 'eVA-BKX' or $key == 'eVA-L' or $key == 'eVA-KMV') {
                if ($value) {
                    $promat = "promat = current_timestamp, ";
                }
            }
            if ($key == 'eVA-BKM' and $value) {
                $BKM = true;
            }
            if ($key == 'eVA-BKMV' and $value) {
                $BKMV = true;
            }
            if (substr($key, 0, 4) == 'eVA-') {
                if ($value) {
                    $choice .= substr($key, 4) . ' ';
                }
            }
        }
        $is_in_basis = $req['is_in_basis'];
        if ($cmd == 'Lektør' and $BKMV) {
            $promat = "promat = current_timestamp, ";
        }
//        echo "cmd:$cmd";

//        if ( $req['oldstatus'] == 'RETRO_eLu') {
//            $cmd = 'RetroLektør';
//        }

        switch ($cmd) {
            case 'LekFinal':
            case 'Lektør':

                if ($is_in_basis) {
//                    $info = $this->getInfoData($seqno);
                    // if the record is in basis then ?

//                    $lekfaust = $req['lekfaust'];
//                    if ($lekfaust) {
//                        $status = ", status = 'UpdateBasis' ";
//                    } else {
                    if ($promat) {
                        $status = ", status = 'UpdatePromat', " . "newfaust = faust ";
                    } else {
                        $status = ", status = 'UpdatePublizon', " . "newfaust = null ";
                    }
//                    }
                } else {
                    if ($req['oldstatus'] == 'RETRO_eLu') {
                        $get = "select dmatstat from retroresult 
                          where seqno = $seqno";
                        $retrostats = $this->db->fetch($get);
                        if ($retrostats) {
                            $retrostat = $retrostats[0]['dmatstat'];
                            $status = ", status = '$retrostat' ";
                            $req['published'] = '';
                        }
                    } else {
                        $status = ", status = 'UpdateBasis' ";
                    }
                }
                $expisbn = $req['expisbn'];
                if ($expisbn) {
                    $insetexp = "expisbn = '$expisbn', ";
                    $status = ", status = 'UpdateBasis' ";
                }
                $published = $req['published'];
                if ($published) {
                    $insetpublished = "printedpublished = '$published', ";
                    $status = ", status = 'UpdateBasis' ";
                }
                $bkxwc = $req['BKXWC'];
                if ($bkxwc) {
                    $insertbkxwc = "bkxwc = '$bkxwc', ";
                    $status = ", status = 'UpdateBasis' ";
                }
                $lekfaust = $req['lekfaust'];
                if ($lekfaust) {
                    $lfaust = "printedfaust = '$lekfaust', ";
//                $info = $this->getInfoData($seqno);
//                if ($info[0]['faust'] == null) {
//                    $lfaust .= "faust = '$lekfaust', ";
//                }
                    $lekfaust = $lfaust;
                }

                $update = "update " . $this->mediaservicetable . " set " . "update = current_timestamp, " //                        . "initials = '$initials', "
                    . "$promat " . "$insetexp " . "$lekfaust " . "$insetpublished " . "$insertbkxwc " . "updatepub = null, " . "choice = '$choice' " . "$status " . "where seqno = $seqno";
                $this->updateRecover($seqno);
                $this->db->exe($update);
                break;
            case 'RetroLektør':
                $lekfaust = $req['lekfaust'];
                if ($lekfaust) {
                    $lekfaust = "printedfaust = '$lekfaust', ";
//                $lekfaust = $lfaust;
                }
                $update = "update " . $this->mediaservicetable . " set " . "update = current_timestamp, " . "$lekfaust " . "status = 'Done' " . "where seqno = $seqno";
                $this->updateRecover($seqno);
                $this->db->exe($update);
                break;
            case 'Ny registrering':
                $status = ", status = 'Template' ";
                if ($BKM or $BKMV) {
                    $status = ", status = 'eLu' "; //test seqno 15
                }

                $update = "update " . $this->mediaservicetable . " set " . "update = current_timestamp, " . "faust =  '" . $req['lokalid'] . "', " . "initials = '$initials', " . "$promat " . "updatepub = null, " . "choice = '$choice' " . "$status " . "where seqno = $seqno";
//                    echo $update;
                $this->updateRecover($seqno);
                $this->db->exe($update);
                break;

            case 'Er registreret':
                $leks = $this->getLektoerCandidates($seqno);
                $f = "faust =  '" . $req['lokalid'] . "' ";
                $is_in_basis = "is_in_basis = current_timestamp, ";
                if ($_SESSION['to_elu'] and $BKM) {
//                    $is_in_basis = "is_in_basis = current_timestamp, ";
                    $status = ", status = 'eLu' "; // test seqno 16
                } else {
                    if ($leks) {
                        $status = ", status = 'eLu' "; //test seqno 17
                    } else {
                        $status = ", status = 'UpdatePublizon' ";//test seqno 13
                    }
                }
                if ($BKMV) {
                    $status = ", status = 'eLu' "; //test seqno 8
                }
                $update = "update " . $this->mediaservicetable . " set " . "update = current_timestamp, " . "initials = '$initials', " . "$is_in_basis " . "$promat " . "updatepub = null, " . "choice = '$choice', " . "$f " . "$status " . "where seqno = $seqno";
                $this->updateRecover($seqno);
                $this->db->exe($update);

                break;
            case 'Done':
            case 'Next':
            case 'Back':
            case 'OK' :
                $f = "faust = null, ";
//            case 'Ny registrering':
                if ($NewRec == 'NewRec') {

                    if ($BKM) {
                        $status = ", status = 'eLu' ";//test seqno 18
                    } else {
                        $status = ", status = 'UpdateBasis' "; //test seqno 9
                    }
                    $update = "update " . $this->mediaservicetable . " set " . "update = current_timestamp, " . "initials = '$initials', " . "$promat " . "updatepub = null, " . "$f " . "choice = '$choice' " . "$status " . "where seqno = $seqno";
                    $this->updateRecover($seqno);
                    $this->db->exe($update);
                }
                break;
            case 'Afvent' :
                if ($req['oldstatus'] == 'eLu') {
                    $status = "status = 'AfventerElu', ";
                } else {
                    $status = "status = 'Afventer', ";
                }
                $update = "update " . $this->mediaservicetable . " set " . "update = current_timestamp, " . "initials = '$initials', " . "$status " . "updatepub = null " . "where seqno = $seqno";
                $this->updateRecover($seqno);
                $this->db->exe($update);
                break;
            case 'Drop':
                $update = "update " . $this->mediaservicetable . " set " . "update = current_timestamp, " . "initials = '$initials', " . "status = 'Drop'," . "updatepub = null " . "where seqno = $seqno";
                $this->updateRecover($seqno);
                $this->db->exe($update);
                break;
//            case 'choose':
//                $mMarc = array();
//                $mMarc[$lokalid][$bibliotek][$base]['matchtype'] = $matchtype;
//                $this->updateStatusFromEva($seqno, $mMarc, $choice);
//                break;
        }
    }


    /**
     * @param $cmd
     * @param $seqno
     * @param $site
     * @return array
     * @throws fetException
     */
    function getData($cmd, $seqno) {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $initials = getInitials();
        if ($_SESSION['username']) {
            $initials = $_SESSION['username'];
            $username = $_SESSION['username'];
            $role = $_SESSION['role'];
        }
        $list = getlist();

        $this->db->exe('BEGIN');
        $this->db->exe("select pg_advisory_lock($seqno)");
        if ($list == 'Alle') {
            $status = " ";
        } else {
            $status = "and status in ('$list') ";
        }
        $wseqno = "and m.seqno = $seqno and m.seqno = e.seqno ";
        $lck = " ";
        switch ($cmd) {
            case 'Note' :
            case 'Afventer' :
            case 'faust' :
            case 'expisbn':
                $status = ' ';
            case 'none':
            case 'secondChoice':
                $status = ' ';
                break;
            case 'direct' :
                $status = ' ';
                $lck = "and ( lockdate is null or " . "(lockdate < current_timestamp - interval '1 hour' or " . "username = '$username'))";
                break;
            case 'next' :
                $desc = 'desc';
                if ($seqno == 0) {
                    $wseqno = " ";
                } else {
                    $wseqno = "and m.seqno < $seqno  and m.seqno = e.seqno";
//                    $wseqno = "and m.update <= (select update from $tablename where seqno = $seqno) and m.seqno != $seqno and m.seqno = e.seqno ";
                }
                $lck = "and ( lockdate is null or " . "(lockdate < current_timestamp - interval '1 hour' or " . "username = '$username'))";
                break;
            case 'back' :
                $wseqno = "and m.update > (select update from $tablename where seqno = $seqno)";
                $desc = ' ';
                $lck = "and ( lockdate is null or " . "(lockdate < current_timestamp - interval '1 hour' or " . "username = '$username'))";
                break;
        }

        $select = "select m.seqno, to_char(m.createdate, 'dd-mm-yyyy') as cdate, " . "to_char(m.update, 'dd-mm-yyyy') as udated, " . "booktype, filetype, bookid, title, originalxml, isbn13, expisbn, " . "status, choice, is_in_basis, newfaust, newfaustprinted, renditionlayout, source, faust, bkxwc " . "from $tablename m, $ebookstable e " . "where booktype = 'Ebog' " . "$lck " . "+seqno+ " . "$status " //            . "order by m.update +desc+, publicationdate +desc+, m.seqno "
            . "order by m.seqno desc  " . "limit 1";

//        select * from mediaservice m, mediaebooks e
//where m . status in('eLu')
//        and m . seqno < 30988
//        and (lockdate is null or (lockdate < current_timestamp - interval '1 hour' or username = 'eVa'))
//and m . seqno = e . seqno
//        and booktype = 'Ebog'
//order by m . seqno desc
//limit 1;


        $sql = str_replace('+seqno+', $wseqno, $select);
        $sql = str_replace('+desc+', $desc, $sql);
        verbose::log(TRACE, "SQL:$sql\n");
//        echo "$sql";
        $rows = $this->db->fetch($sql);

        if (!$rows) {
            header('Location: ?cmd=Locked');
        }
        foreach ($rows as $key => $inf) {
            if ($inf['source']) {
                $sisbn = '';
                $txt = $inf['source'];
                for ($i = 0; $i < strlen($txt); $i++) {
                    if (ctype_digit(substr($txt, $i, 1))) {
                        $sisbn .= substr($txt, $i, 1);
                    }
                }
            }
            $rows[$key]['source'] = $sisbn;
        }
        $_SESSION['newstatus'] = false;
        if ($rows) {
            if ($role == 'eVa') {
                $updinitials = "initials = '$initials', ";
                switch ($rows[0]['status']) {
                    case 'UpdatePublizon':
                    case 'UpdatePromat':
                        $newfaust = $rows[0]['newfaust'];
                        if (!$newfaust) {
                            $_SESSION['newstatus'] = 'eVa';
                        }
                        break;
                    case 'OldEva':
                    case 'OldTemplate':
                    case 'UpdateBasis':
                    case 'Template':
                    case 'eLu':
                    case 'Afventer':
                        $_SESSION['newstatus'] = 'eVa';
                        break;
                    default:
                        $_SESSION['newstatus'] = false;
                }
            }
            if ($role == 'eLu') {
                switch ($rows[0]['status']) {
                    case 'UpdateBasis':
                    case 'AfventerElu':
                    case 'Template':
                        $_SESSION['newstatus'] = 'eLu';
                        break;
                    case 'eLu':
                        $_SESSION['newstatus'] = 'eVa';
                        break;
                    default:
                        $_SESSION['newstatus'] = false;
                }
            }
            if ($initials and $username) {
                $sql = "update $tablename set lockdate = current_timestamp, " . "$updinitials " . "username = '$username' " . "where seqno = " . $rows[0]['seqno'];
                $_SESSION['seqno'] = $rows[0]['seqno'];
//                $this->updateRecover($seqno);
                $this->db->exe($sql);
            }
        }
        $this->db->exe("select pg_advisory_unlock($seqno)");
        $this->db->exe('COMMIT');
        return $rows;
    }

    /**
     * @param $seqno
     * @param $req
     * @throws fetException
     */
    function updateNote($seqno, $req) {

        $initials = getInitials();
        if ($_SESSION['username']) {
            $initials = $_SESSION['username'];
        }
        $notetable = $this->notetable;

        $notetype = $req['notetype'];
        $notetext = $req['notetext'];
        $this->db->exe('BEGIN');
        $find = "select seqno from $notetable " . "where seqno = $seqno and type = '$notetype'";
        $noteseqnos = $this->db->fetch($find);
        if ($noteseqnos) {
            $del = false;
            if ($notetype == 'f991' or $notetype == 'note') {
                if ($notetext == '') {
                    $del = true;
                }
            }
            if ($del) {
                $sql = "delete from $notetable " . "where type = '$notetype' " . "and seqno = $seqno ";
                $this->db->exe($sql);
            } else {
                $update = "update $notetable " . "set text = $1, " . "initials = '$initials', " . "$del" . "updated = current_timestamp " . "where seqno = $seqno and type = '$notetype'";

                $this->db->query_params($update, array($notetext));
            }
        } else {
            $ins = true;
            if ($notetype == 'f991' or $notetype == 'note') {
                if ($notetext == '') {
                    $ins = false;
                }
            }
            $insert = "insert into $notetable ( " . "seqno, createdate, updated, type, text, initials, status ) " . "values ( " . "$seqno, " . "current_timestamp, " . "current_timestamp, " . "'$notetype', " . "$1, " . "'$initials', " . "'active' )";
            if ($ins) {
                $this->db->query_params($insert, array($notetext));
            }
        }
        $this->db->exe('COMMIT');
    }


    /**
     * @param $seqno
     * @param $direction
     * @param $req
     * @param $site
     * @return array
     */
    function getTableData($seqno, $direction, $req, $site) {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $pagesize = 10;
        $page = 1;
        if ($_SESSION['page']) {
            $page = $_SESSION['page'];
        }

        $type = $site;
        if (array_key_exists('type', $req)) {
            $type = $req['type'];
        }
        if ($type != 'Alle' and $type != 'Last') {
            $status = " and status = '$type' ";
        }

        $start = ($page - 1) * $pagesize;
        $stop = $pagesize;

        if (array_key_exists('cmd', $req)) {
            $cmd = $req['cmd'];
        }
        $title = "";
        if ($site == 'eLu') {
            $status = " and status = 'eLu' ";
        }

        $order = "order by m.seqno desc ";
        switch ($cmd) {
            case 'onlyone':
                if ($type != 'Alle' and $type != 'Last') {
                    $status = "and status = '" . $type . "' ";
                }
                if ($type == 'Last') {
                    $order = "order by m.update desc, publicationdate desc, m.seqno ";
                }
                break;
            case 'titlesearch':
                $title = $req['titlesearch'];
                $title = "and lower(title) like '" . strtolower($title) . "' ";
                $status = ' ';
                break;
        }
        if ($direction == 1 || $direction == -1) {
            if ($direction == 1) {
                $operator = ">";
                $desc = 'desc';
            } else {
                $operator = "<=";
                $desc = '';
            }

            // find new seqno
            $select = "select seqno " . "from $tablename m, $ebookstable e " . "where booktype = 'Ebog' " . "and m.seqno $operator $seqno " . "$status " . "$title " . "order by update $desc, m.seqno $desc " . "limit 101 ";

//            echo "select:$select\n";
            exit;
//            $rows = $this->db->fetch($select);
            if ($rows) {
                $nrows = count($rows);
                if ($nrows) {
                    $seqno = $rows[$nrows - 1]['seqno'];
                }
            } else {
                $seqno = 1;
            }
        }
        $seqno--;
        if ($seqno < 0) {
            $seqno = 0;
        }

        $sql = "select count(*) as cnt from $tablename m, $ebookstable e " . "where booktype = 'Ebog' " //            . "and seqno > $seqno "
            . "$status " . "and m.seqno = e.seqno " . "$title ";
        $count = $this->db->fetch($sql);
        $cnt = $count[0]['cnt'];
        $pages = 0;
        if ($cnt) {
            $pages = (int)(($cnt + $pagesize - 1) / $pagesize);
        }
        $select = "" . "select " . "m.seqno, provider, to_char(m.createdate,'dd-mm-yyyy') as cdate, initials, " . "booktype, filetype, choice, " . "bookid, title, originalxml, isbn13, faust, to_char(lockdate,'DD-MM-YY HH24:MI:SS') as lockdate," . "to_char(publicationdate,'DD-MM-YYYY') as publicationdate, m.status," . "to_char(m.update,'DD-MM-YYYY HH24:MI:SS') as updated " . "from $tablename m, $ebookstable e " . "where booktype = 'Ebog' " //            . "and seqno > $seqno "
            . "$status " . "$title " . "and m.seqno = e.seqno "
//            . "order by m.update desc, publicationdate desc, m.seqno "
//            . "order by m.seqno desc "
            . "$order " . "offset $start " . "limit $stop ";

//        echo $select;
        $rows = $this->db->fetch($select);
        $_SESSION['page'] = $page;
        $rows[] = $pages;
        return $rows;
    }


    /**
     * @param $prime
     * @return array
     *
     */
    function getSummary($prime) {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        if ($prime) {
            $not = "";
        } else {
            $not = "not";
        }
        $prime = "('Afventer','AfventerElu','eLu','eVa')";
        $select = "select status, booktype, count(*) as cnt " . "from $tablename m, $ebookstable e   " . "where status $not in $prime " . "and m.seqno = e.seqno  " . "group by status, booktype " . "order by booktype, status ";
        $rows = $this->db->fetch($select);
        return $rows;
    }

    /**
     * @return mixed
     */
    function getStat1Data() {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $select = "select booktype, to_char(m.createdate,'YYYYMMDD') as cdate, count(*) as cnt " . "from $tablename m, $ebookstable e " . "where booktype = 'Ebog' " . "and m.seqno = e.seqno " . "group by booktype, cdate " . "order by booktype, cdate desc " . "limit 10";

        $rows = $this->db->fetch($select);
        return $rows;
    }

    /**
     * @param $from_date
     * @return array
     */
    function getStatMediaservice($from_date) {
        $tablename = $this->mediaservicetable;
        $ebookstable = $this->mediaebookstable;
        $select1 = "select m.seqno, status, m.update " . "from $tablename m, $ebookstable e " . "where to_char(m.update,'YYYYMMDD') > " . "TO_CHAR(current_date + integer '$from_date','YYYYMMDD') " . "and booktype = 'Ebog'  " . "and m.seqno = e.seqno ";
        $rows = $this->db->fetch($select1);
        return $rows;
    }

    function getRecoverInfo($seqno) {
        $tablerecover = $this->tablerecover;
        $select = "select * from $tablerecover " . "where seqno = $seqno " . "order by update desc ";
        $info = $this->db->fetch($select);
        if ($info) {
            return $info;
        }
        return array();
    }

    /**
     * @param $seqno
     */
    function getRecoverStatus($seqno, $mstatus, $update) {
        $tablerecover = $this->tablerecover;
        $select = "select status from $tablerecover " . "where seqno = $seqno " . "and status != '$mstatus' " . "and update < '$update' " . "order by update desc " . "limit 1";
        $froms = $this->db->fetch($select);
        return $froms;
    }

    /**
     * @param $from_date
     * @return array
     */
    function getStaFromRecover($from_date) {
        $tablerecover = $this->tablerecover;
        $select = "select seqno, status, update from $tablerecover " . "where to_char(update,'YYYYMMDD') > " . "TO_CHAR(current_date + integer '$from_date','YYYYMMDD') ";
        $rows = $this->db->fetch($select);
        return $rows;
    }

    function getStat2Recover($seqno, $mstatus, $update) {
        $tablerecover = $this->tablerecover;
        $select = "select status from $tablerecover " . "where seqno = $seqno " . "and status != '$mstatus' " . "and update < '$update' " . "order by update desc " . "limit 1";
        $froms = $this->db->fetch($select);
        return $froms;
    }

    /**
     * @param $seqno
     * @return array
     */
    function getXml($seqno) {
        $ebookstable = $this->mediaebookstable;
        $select = "select originalxml as xml from $ebookstable " . "where seqno = $seqno";
        return $this->db->fetch($select);
    }

    /**
     * @param $isbn13
     */
    function fetchByIsbn($isbn13) {
        $sql = "select bookid, filetype, title, isbn13, seqno from " . $this->mediaebookstable . " " . "where isbn13 = '$isbn13' " . "and provider = 'PubHubMediaService' " . "limit 1";
        return $this->db->fetch($sql);
    }

    /**
     * @param $cat
     * @return array
     */
    function getLinkData($cat) {
        if ($cat == 1) {
            $sql = "select seqno, faust, newfaust " . "from " . $this->mediaservicetable . " " . "where " . "(faust is not null and faust != '') " . "and expression is null " . "and (newfaust is not null and newfaust != '') " . "order by seqno ";
        }
        if ($cat == 2) {
            $sql = "select m.seqno, faust, newfaust, source, isbn13 " . "from " . $this->mediaservicetable . " m, " . $this->mediaebookstable . " e " . "where " . "expression is null " . "and source is not null " . "and source != '' " . "and m.status not in ('OldEva','OldTemplate','NotActive')" . "and e.seqno = m.seqno " . "order by m.seqno ";
        }

        return $this->db->fetch($sql);
    }

    /**
     * @param $seqno
     * @param $faust
     * @throws fetException
     */
    function updateExpression($seqno, $faust) {
        $update = "update " . $this->mediaservicetable . " " . "set expression = '$faust' " . "where seqno = $seqno ";
        $this->db->exe($update);
    }


    /**
     * @param $seqno
     * @param $lekfaust
     */
    function updateLekFaust($seqno, $lekfaust) {
        $update = "update " . $this->mediaservicetable . " " . "set lekfaust = '$lekfaust' " . "where seqno = $seqno ";
        $this->db->exe($update);
    }

    /**
     * @param $seqno
     * @param $status
     */
    function updateLekStatus($seqno, $status) {

        $update = "update " . $this->mediaservicetable . " " . "set updatelekbase = '$status', " . "update = current_timestamp " . "where seqno = $seqno ";
        $this->db->exe($update);
    }

    /**
     * @param $seqno
     * @param $faust
     * @throws fetException
     */
    function updatePrintedFaust($seqno, $faust) {
        $update = "update " . $this->mediaservicetable . " " . "set printedFaust = '$faust' " . "where seqno = $seqno ";
        $this->db->exe($update);
    }


    function DropTable($name, $postfix = '') {
        $name = $name . $postfix;
        $this->db->exe("drop table IF EXISTS $name");
    }

    private function restoreTable($name, $postfix) {
        $from = $name . $postfix;
        $this->db->exe("create table $name as (select * from $from)");
    }

    private function backupTable($name, $postfix) {
        $to = $name . $postfix;
        $this->db->exe("create table $to as (select * from $name)");
    }

    function deleteTables($postfix) {
        $this->DropTable($this->mediaservicetable, $postfix);
        $this->DropTable($this->mediaebookstable, $postfix);
        $this->DropTable($this->mediaonixtable, $postfix);
        $this->DropTable($this->reftable, $postfix);
        $this->DropTable($this->notetable, $postfix);
        $this->DropTable($this->tablerecover, $postfix);
        $this->DropTable($this->leklogtable, $postfix);
        $this->DropTable($this->to_publizon, $postfix);
    }

    function copyTablesOut($postfix) {
//        $this->DropTable($this->tablename, $postfix);
//        $this->DropTable($this->ebookstable, $postfix);
//        $this->DropTable($this->reftable, $postfix);
//        $this->DropTable($this->notetable, $postfix);
//        $this->DropTable($this->tablerecover, $postfix);
//        $this->DropTable($this->leklogtable, $postfix);
//        $this->DropTable($this->to_publizon, $postfix);
        $this->deleteTables($postfix);

        $this->backupTable($this->mediaservicetable, $postfix);
        $this->backupTable($this->mediaebookstable, $postfix);
        $this->backupTable($this->mediaonixtable, $postfix);
        $this->backupTable($this->reftable, $postfix);
        $this->backupTable($this->notetable, $postfix);
        $this->backupTable($this->tablerecover, $postfix);
        $this->backupTable($this->leklogtable, $postfix);
        $this->backupTable($this->to_publizon, $postfix);
    }

    function copyTablesIn($postfix) {
        $this->deleteTables('');

        $this->restoreTable($this->mediaservicetable, $postfix);
        $this->restoreTable($this->mediaebookstable, $postfix);
        $this->restoreTable($this->mediaonixtable, $postfix);
        $this->restoreTable($this->reftable, $postfix);
        $this->restoreTable($this->notetable, $postfix);
        $this->restoreTable($this->tablerecover, $postfix);
        $this->restoreTable($this->leklogtable, $postfix);
        $this->restoreTable($this->to_publizon, $postfix);
    }

    function IsbnInExpISBN($isbn) {
        $table = $this->mediaservicetable;
        $sql = "select seqno from $table " . "where expisbn = '$isbn' ";
        $rows = $this->db->fetch($sql);
        return $rows;
    }

    function getLektoerCandidates($seqno) {
        $reftable = $this->reftable;
        $sql = "select seqno,lokalid, bibliotek, base from $reftable " . "where seqno = $seqno " . "and lektoer is true ";
        $rows = $this->db->fetch($sql);
        return $rows;
    }


    function getUpdatesToLek($max = 0) {
        $tablename = $this->mediaservicetable;
        $lim = '';
        if ($max) {
            $lim = " limit $max ";
        }
        // type 1 udgår.  Det var en hvor man giver L til e-bogen og vil automatisk opdatere forlægget.
        $sql = "
                select seqno, faust, newfaust, newfaustprinted, printedfaust, '2' as type
	              from mediaservice 
		          where promat is not null
		          and newfaustprinted is not null
		          and status not in ('eVa','eLu','Pending','UpdateBasis')
		          and updatelekbase is null
		          and COALESCE (faust,'empty') != COALESCE (newfaust,'empty')
                union
                select seqno, faust, newfaust, newfaustprinted, printedfaust, '3' as type
	              from mediaservice 
		          where printedfaust is not null
		          and newfaust is not null
		          and status not in ('eVa','eLu','Pending','UpdateBasis')
		          and updatelekbase is null
		        union
                select seqno, faust, newfaust, newfaustprinted, printedfaust, '4' as type
	              from mediaservice 
		          where promat is  null
		          and newfaustprinted is not null
		        and status not in ('eVa','eLu','Pending','UpdateBasis')
		            and updatelekbase is null
		          and COALESCE (faust,'empty') != COALESCE (newfaust,'empty')
                union
                select seqno, faust, newfaust, newfaustprinted, printedfaust, '5' as type
	              from mediaservice 
		          where printedfaust is not null
		          and newfaust is null
		          and status not in ('eVa','eLu','Pending','UpdateBasis')
		          and updatelekbase is null
		          and COALESCE (faust,'empty') != COALESCE (newfaust,'empty')
                union
                select seqno, faust, newfaust, newfaustprinted, printedfaust, 'd' as type
	              from mediaservice 
		          where updatelekbase = 'ToBeDeleted'
		          and lekfaust is not null
            order by seqno
            $lim ";

        $rows = $this->db->fetch($sql);
        if (!$rows) {
            return array();
        }
        return $rows;
    }

    function insertIntoMarcTable($lokalid, $library, $marcln) {
        if (strlen($lokalid) == 8) {
            $lokalid = substr($lokalid, 0, 1) . ' ' . substr($lokalid, 1, 3) . ' ' . substr($lokalid, 4, 3) . ' ' . substr($lokalid, 7, 1);
        }
        $marcln = utf8_encode($marcln);
        $sql = "select lokalid from " . $this->marctable . " " . "where lokalid = '$lokalid' " . "and library = '$library'";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            $sql = "update " . $this->marctable . " " . "set marcln = $1 " . "where lokalid = '$lokalid' " . "and library = '$library'";
            $this->db->query_params($sql, array($marcln));
        } else {
            $sql = "INSERT INTO " . $this->marctable . "(lokalid, library, marcln) " . "VALUES ( $1,$2,$3) ";
            $this->db->query_params($sql, array($lokalid, $library, $marcln));
        }
    }

    function getFromMarcTable($lokalid, $library) {
        $sql = "select marcln from " . $this->marctable . " " . "where lokalid = '$lokalid' " . "and library = '$library'";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            $marcln = $rows[0]['marcln'];
//            $marcln = utf8_decode($rows[0]['marcln']);
            return $marcln;
        } else {
            return false;
        }

    }

    function insertToPublizon($seqno, $txt) {
        $sql = "select seqno from " . $this->to_publizon . " " . "where seqno = $seqno ";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            $sql = "update " . $this->to_publizon . " " . "set txt = $1 " . "where seqno = $seqno ";
            $this->db->query_params($sql, array($txt));
        } else {
            $sql = "INSERT INTO " . $this->to_publizon . "(seqno, txt) " . "VALUES ( $1,$2) ";
            $this->db->query_params($sql, array($seqno, $txt));
        }
    }

    function getToPublizon($seqno) {
        $sql = "select txt from " . $this->to_publizon . " " . "where seqno = $seqno ";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            $txt = $rows[0]['txt'];
            return $txt;
        } else {
            return false;
        }

    }

    function leklog($seqno, $status, $txt, $lokalid) {
        $nothing = $this->nothing;
//        $nothing = false;
        $lokalid = str_replace(' ', '', $lokalid);
        $timestamp = $this->timestamp;
        $sql = "select lekseqno from " . $this->leklogtable . " 
                where seqno = $seqno and message = '$txt' ";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            $update = "update " . $this->leklogtable . " 
            set update = '$timestamp' 
            where lekseqno = " . $rows[0]['lekseqno'];
            if ($nothing) {
                echo "$update";
            } else {
                $this->db->exe($update);
            }
        } else {
            $insert = "insert into " . $this->leklogtable . " " . "(lekseqno,createdate,seqno, message,faust,status,update) " . "values (" . "nextval('" . $this->leklogseq . "'), " . "'$timestamp', " . "$seqno, " . "$1, " . "'$lokalid', " . "'$status', " . "'$timestamp' ) ";
            if ($nothing) {
                echo "$insert";
            } else {
                $this->db->query_params($insert, array($txt));
            }
        }
        if ($status == 'ERROR') {
            $sql = "update " . $this->mediaservicetable . " " . "set updatelekbase = '$status' " . "where seqno = $seqno ";
            if ($this->nothing) {
                echo "$sql";
            } else {
                $this->db->exe($sql);
            }
        }
    }

    function lastOnixUpdate() {
        $update = "update " . $this->mediainfo . " set ts = (
            select max (datestamp) from mediaonix)
            where name = 'lastOnixUpdate';
          ";
        if (!$this->nothing) {
            $this->db->exe($update);
        } else {
            echo "Nothing: $update\n";
        }
    }

    function getUpdateSta() {
        return array('total' => $this->tot, 'skip' => $this->skp, 'inserted' => $this->ins, 'updated' => $this->upd);
    }

    function getLekWaiting() {
        $sql = "SELECT lekseqno, seqno, message, faust, to_char(createdate,'DD-MM-YYYY HH24:MI') AS oprettet,
               to_char(update,'DD-MM-YYYY HH24:MI') AS opdateret, update - createdate AS waited 
              FROM " . $this->leklogtable . "
              WHERE update IN ( 
              SELECT max(update) FROM " . $this->leklogtable . "
              )
              ORDER BY createdate ";
        $rows = $this->db->fetch($sql);
        return $rows;


    }

    function getInfoViaWhere($where) {
        $select = "select seqno from " . $this->mediaservicetable . "
               $where ";
        $rows = $this->db->fetch($select);
        if (!$rows) {
            return false;
        }
        $info = array();
        foreach ($rows as $row) {
            $inf = $this->getInfoData($row['seqno']);
            $info[] = $inf[0];
        }
        return $info;
    }

    function getDBtime() {
        $ret = $this->db->fetch("select current_timestamp");
        sleep(1);
        return $ret[0]['now'];
    }

    function getPar($par) {
        $sql = "select name, value, ts, to_char(ts,'DDMMYYYY HH24MISS') as lastupdated from " . $this->mediainfo . " " . "where '$par' = name";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            return $rows[0];
        } else {
            return false;
        }
    }

    function setPar($par, $val1, $val2) {
        $exists = $this->getPar($par);
        if ($exists) {
            $sql = "update " . $this->mediainfo . " " . "set value = '$val1', " . "ts = '$val2' " . "where name = '$par' ";
        } else {
            $sql = "insert into " . $this->mediainfo . " " . "( name, value, ts) values ( " . " '$par', '$val1', '$val2')";
        }
        $this->db->exe($sql);
    }

    function updateKarens($lokalid, $opretdato, $ajour, $status = '') {
        $lukarens = $this->medialukarens;
        $sql = "select lokalid from $lukarens 
                  where lokalid = '$lokalid'
              ";
        $exists = $this->db->fetch($sql);
        if ($exists) {
            $sql = "update $lukarens 
               set status = '$status'
               where lokalid = '$lokalid'
               ";
        } else {
            $sql = "insert into $lukarens  
                    (lokalid,created,opretdato,ajour)
                    values (
                    '$lokalid',
                    current_timestamp,
                    '$opretdato',
                    '$ajour'
                    ) 
             ";
        }
        $this->db->exe($sql);
    }

    function getLekUpdates($karensdato) {
        $lukarens = $this->medialukarens;
        $sql = "select lokalid, to_char(ajour,'DDMMYYYY HH24MISS') as dato 
           from $lukarens
           where opretdato < to_date('$karensdato','YYYYMMDD')
           and status is null
           ";
        $updates = $this->db->fetch($sql);
        if ($updates) {
            return $updates;
        }
        return array();
    }

    function useradm($action, $user, $role = '') {
        $userinfotable = $this->userinfotable;
        $sql = '';
        switch ($action) {
            case 'Slet':
                $sql = "delete from $userinfotable
                        where username = '$user' ";
                break;
            case 'Nulstil passwd':
                $sql = "update $userinfotable 
                     set passwd = '$role' 
                     where username = '$user' ";
                break;
            case 'Opdater':
                $sql = "update $userinfotable
                   set role = '$role'
                   where username = '$user'
            ";
                break;
            case 'Opret':
                $sql = "insert into $userinfotable
                   (username, createdate, update, role, passwd)
                   VALUES 
                   ('$user',current_timestamp,current_timestamp, '$role', '$role')
            ";
                break;

        }
        if ($sql) {
            $this->db->exe($sql);
            return;
        }
        switch ($action) {
            case 'Hent':
                $sql = "SELECT username, 
                    to_char(createdate,'DD-MM-YYYY') as createdate,
                    to_char(update,'DD-MM-YYYY') as update,
                    role,
                    passwd
                FROM $userinfotable
                  where username = '$user' 
            ";
                break;
            case 'list':
                $sql = "SELECT username, 
                     to_char(createdate,'DD-MM-YYYY') as createdate,
                    to_char(update,'DD-MM-YYYY') as update,
                    role 
                FROM $userinfotable
                 order by username
            ";
                break;
        }
        if ($sql) {
            $ret = $this->db->fetch($sql);
            return $ret;
        }
        return false;
    }

    function getPublisherId($isbn) {
        $sql = "select * from forlag where forlagisbn = $isbn";
        return $this->db->fetch($sql);
    }

    function getNextOlds($count) {
        $tablename = $this->mediaservicetable;
        $select = "select seqno " . "from $tablename " . "where status = 'OldTemplate' " . "or status = 'OldEva' " . "limit $count";
        $rows = $this->db->fetch($select);
        $values = array();
        if ($rows) {
            foreach ($rows as $i => $value) {
                $values[] = $value['seqno'];
            }
        }
        return $values;
    }

    function getCoverCandidates() {
        $sql = "select seqno from " . $this->mediaservicetable . " 
          where sent_to_covers is null 
          and (COALESCE(faust,'') != '' or COALESCE(newfaust,'' ) != '')
          order by seqno
          ";
        return $this->db->fetch($sql);
    }

    function updateSentToCovers($seqno, $err) {
        $tablename = $this->mediaservicetable;
        if ($err == 'OK') {
            $set_sent_to_covers = "sent_to_covers = current_timestamp,";
        } else {
            $set_sent_to_covers = "";
        }
        $update_sql = "
          update $tablename
          set $set_sent_to_covers
          cover_status = '$err'
          where seqno = $seqno
        ";

        if ($this->nothing) {
            echo "NOTHING update_sql:$update_sql \n";
        } else {
            $this->db->exe($update_sql);
        }
    }
}