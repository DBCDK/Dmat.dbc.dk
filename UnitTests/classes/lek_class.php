<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 16-06-2016
 * Time: 14:02
 *
 * PCL : Purcharsing Consultant for Libraries
 * Danish : Lektør
 */
class lek_class {

    private $mediadb;
    private $basis;
    private $lektorbasen;
    private $nothing;
    private $updates;
    private $marc;
    private $newMarc;
    private $lekrec;
    private $calendar;
    private $gfaust;
    private $marclns;
    private $newmarclns;
//    private $lekcreatedate;
//    private $lekajourdate;
    private $max;

//    private $basisrec;

    function __construct(mediadb $mediadb, $basis, $LektorBasen, Calendar_class $calendar, getFaust $gfaust, $nothing = false) {
        $startdir = dirname(__FILE__);
        $inclnk = $startdir . "/../inc";
        require_once "$inclnk/OLS_class_lib/marc_class.php";

        $this->marclns = array();
        $this->newmarclns = array();
        $this->mediadb = $mediadb;
//        $this->mediadb->startNewFaust('39000001');
        $this->basis = $basis;
        $this->lektorbasen = $LektorBasen;
        $this->nothing = $nothing;
        $this->marc = new marc();
        $this->newMarc = new marc();
        $this->lekrec = new marc();
        $this->calendar = $calendar;
        $this->gfaust = $gfaust;
        $this->gfaust->setNrRulle('PROMAT');

        $this->max = 999999999;
//        $set = array();
//        $set[] = array('name' => 'publish', 'type' => 'change');
//        $this->calendar->setTable($set);
    }

    /**
     * @param bool $retro
     */
    function findUpdates() {
        // find all records that has to been updated
        $rows = $this->mediadb->getUpdatesToLek($this->max);
        $this->updates = array();
        if ($rows) {
            foreach ($rows as $row) {
                $this->updates[$row['seqno']] = $row;
            }
        }
    }

    /**
     * @return mixed
     */
    function getUpdates() {
        return $this->updates;
    }

    function setUpdates($updates) {
        $this->updates = $updates;
        $this->marclns = array();
        $this->newmarclns = array();
    }

    /**
     * @return array|bool
     * @throws exception
     *
     * This function is where the works begin.   Before calling processUpdates one have
     * to call findUpdates.  It is split in two because it's easier to make
     * an unittest.
     */
    function processUpdates() {

        if ( !count($this->updates)) {
            return false;
        }

        foreach ($this->updates as $seqno => $nxt) {
            $ToBeDeleted = false;
            switch ($nxt['type']) {
                case 1:
                    $fromfaust = $nxt['newfaust'];
                    $tofaust = $nxt['faust'];
                    break;
                case 2:
                    $fromfaust = $nxt['newfaust'];
                    $tofaust = $nxt['newfaustprinted'];
                    break;
                case 3:
                    $fromfaust = $nxt['printedfaust'];
                    $tofaust = $nxt['newfaust'];
                    break;
                case 4:
                    $fromfaust = $nxt['newfaustprinted'];
                    $tofaust = $nxt['newfaust'];
                    break;
                case 5:
                    $fromfaust = $nxt['printedfaust'];
                    $tofaust = $nxt['faust'];
                    break;
                case 6:
                    $fromfaust = $nxt['printedfaust'];
                    $tofaust = $nxt['newfaust'];
                    break;
                case 'd':
                    $ToBeDeleted = true;
                    break;
                default:
                    throw new exception ("Unknown type:" . $nxt['type']);
                    break;
            }

            if ($ToBeDeleted) {
                $this->DeleteLek($seqno);
            } else {
                if ($this->BasisOk($fromfaust, $seqno)) {
                    if ($this->lekInBase($fromfaust, $seqno)) {
                        if ($this->NewBasisOk($tofaust, $seqno)) {
//                        Der skal ikke testes på om newfaust har en valid DBF. (se user story 127)
//                        if ($this->DBFdateOK()) {
                             $this->updateLek($seqno, $tofaust);
                            $this->updateNew($fromfaust, $seqno);
                            // dette for at se om der er nogen der kommer af den slags!
//                        if ($nxt['type'] == 1) {
//                            mail('hhl@dbc.dk', "type 1 ($seqno) ToLek.php", "type 1: seqno:$seqno, fromfaust:$fromfaust, tofaust:$tofaust ??");
//                continue;
//                        }
//                        }
                        }
                    }
                }
            }
        }
        return ($this->marclns);
    }

    /**
     * @param $seqno
     * @return lek_class
     */
    private function DeleteLek($seqno) {
        $this->marclns = array();
        $info = $this->mediadb->getInfoData($seqno);
        $lekfaust = $info[0]['lekfaust'];
        $leks = $this->lektorbasen->getMarcByLokalidBibliotek($lekfaust, '870976');
        if ($leks) {
            $this->lekrec->fromIso($leks[0]['DATA']);
            $this->lekrec->substitute('004', 'r', 'd');
            $this->marclns[] = $this->lekrec->toIso();
        }
        if ( !$this->nothing) {
            $this->mediadb->updateLekStatus($seqno, 'Deleted');
        }
        return $this->marclns;
    }

    function setMax($max) {
        $this->max = $max;
    }

    private function updateNew($fromfaust, $seqno) {
        $updated = false;
//        $strng = $this->newMarc->toLineFormat(78);
        $f07as = $this->newMarc->findSubFields('f07', 'a');
        if ($f07as) {
            $f07a = str_replace(' ', '', $f07as[0]);
            if ($f07a != substr(str_replace(' ', '', $fromfaust), 0, 8)) {
                $this->mediadb->leklog($seqno, 'ERROR', "f07*a indeholder forkert faust:$f07a ", $fromfaust);
                return false;
            }
        } else {
            $this->newMarc->insert_subfield($fromfaust, 'f07', 'a');
            $f260cs = $this->marc->findSubFields('260', 'c');
            if ($f260cs) {
                $this->newMarc->insert_subfield($f260cs[0], 'f07', 'c');
            }
            $this->newMarc->insert_subfield('autoLU', 'f07', 'n');
            $updated = true;
        }

        $found = false;
        $f06bs = $this->newMarc->findSubFields('f06', 'b');
        if ($f06bs) {
            foreach ($f06bs as $f06b) {
                if (strtolower($f06b) == 'l') {
                    $found = true;
                }
            }
        }
        if ( !$found) {
            $this->newMarc->insert_subfield('l', 'f06', 'b');
            $updated = true;
        }
        $found = false;
        $f990bs = $this->newMarc->findSubFields('990', 'b');

        if ($f990bs) {
            foreach ($f990bs as $f990b) {
                if (strtolower($f990b) == 'l') {
                    $found = true;
                }
            }
        }
        if ( !$found) {
            $x = $this->newMarc->findFields('990');
            if ($x) {
                $x = $x[0];
                $insert = false;
                $subfields = array();
                foreach ($x['subfield'] as $s) {
                    if (substr($s, 0, 1) == 'b') {
                        $subfields[] = 'bl';
                        $insert = true;
                    }
                    $subfields[] = $s;
                }
                if ($insert) {
                    $this->newMarc->remField('990');
                    $x['subfield'] = $subfields;
                    $this->newMarc->insert($x);
                } else {
                    $this->newMarc->insert_subfield('l', '990', 'b');
                }
//            } else {
//                $this->newMarc->insert_subfield('l', '990', 'b');
            }
            $updated = true;
        }
//        $strng = $this->newMarc->toLineFormat(78);

        if ($updated) {
            $strng = $this->newMarc->toLineFormat(78);
            $this->newmarclns[] = $this->newMarc->toIso();
        }

    }

    private function DBFdateOK() {
        $strng = $this->newMarc->toLineFormat();
//        $f032s = $this->marc->findSubFields('032', 'a');
        $f032s = $this->newMarc->findSubFields('032', 'a');
        foreach ($f032s as $f032) {
            if (strtoupper(substr($f032, 0, 3)) == 'DBF') {
                $sta = 'canceled';
                $startweek = substr($f032, 3, 6);
//                $startweek = '201705';
                while ($sta == 'canceled') {
                    $dateinfo = $this->calendar->getDBFday($startweek);
                    $startweek = $dateinfo['nxtweek'];
                    $sta = $dateinfo['status'];
                }
                $date = $dateinfo['date'];
                $tdate = date('Ymd');
                if ($date <= $tdate) {
                    return true;
                } else {
                    return false;
                }
            }
        }
        return false;
    }

    private function BasisOk($faust, $seqno) {
        $recs = $this->basis->getMarcByLokalidBibliotek($faust, '870970');
        if ($recs) {
            $this->marc->fromIso($recs[0]['DATA']);
            $strng = $this->marc->toLineFormat();

            $del = $this->deleteRec();
            if ($del) {
                return false;
            }

            $isNotNT = $this->is_not_NY_TITEL();
            $lek = $this->marc->HasLektoer();


            if ( !$isNotNT) {
                $this->mediadb->leklog($seqno, 'WAIT', 'NY TITEL', $faust);
                return false;
            }


            if ( !$lek) {
                $this->mediadb->updateLekStatus($seqno, 'Done2');
                return false;
            }
        } else {
            if ($this->toOld($seqno)) {
                $this->mediadb->leklog($seqno, 'WAIT', 'To Old', $faust);
                $this->mediadb->updateLekStatus($seqno, 'Done3');
                return false;
            }
            $this->mediadb->leklog($seqno, 'WAIT', 'not Found i Basis', $faust);
            return false;

        }

        return true;
    }

    private function toOld($seqno) {
        $info = $this->mediadb->getInfoData($seqno);
        $createdate = $info[0]['createdate'];
        $xMonthsAgo = strtotime("-3 month");
        $cdate = strtotime($createdate);
        if ($cdate < $xMonthsAgo) {
            return true;
        }
        return false;
    }


    private function NewBasisOk($tofaust, $seqno) {
        $recs = $this->basis->getMarcByLokalidBibliotek($tofaust, '870970');
        if ($recs) {
            $this->newMarc->fromIso($recs[0]['DATA']);
            $strng = $this->newMarc->toLineFormat();
            $fs10s = $this->newMarc->findSubFields('s10', 'a');
            if (count($fs10s)) {
                if (strtoupper($fs10s[0]) != 'DBC') {
                    $this->mediadb->updateLekStatus($seqno, 'Done');
                    return false;
                }
            }

            // check if the basis record has got a valid BKM date!
            if ($bkm = $this->HasItBKM($this->newMarc)) {
                $bkmweek = substr($bkm, 3, 6);
                $d = $this->calendar->getDBFday($bkmweek);
                if ($d['date'] <= date('Ymd')) {
                    return true;
                }

            }
            if ($bkm) {
                $this->mediadb->leklog($seqno, 'WAIT', 'BKM date not due', $tofaust);
            } else {
                $this->mediadb->leklog($seqno, 'WAIT', 'No BKM', $tofaust);
            }
            return false;
        } else {
            $this->mediadb->leklog($seqno, 'WAIT', 'not Found i Basis', $tofaust);
            return false;
        }
    }

    private function HasItBKM($marc) {
        $f032xs = $marc->findSubFields('032', 'x');
        if ( !$f032xs) {
            return false;
        }
        foreach ($f032xs as $f032x) {
            $t = substr($f032x, 0, 3);
            if ($t == 'BKX') {
                return $f032x;
            }
        }
        foreach ($f032xs as $f032x) {
            $t = substr($f032x, 0, 3);
            if ($t == 'SFD' or $t == 'BKM') {
                return $f032x;
            }
        }

        return false;
    }

    private function lekInBase($faust, $seqno) {
        $leks = $this->lektorbasen->getLekNoViaRel($faust, '870976');
        if ($leks) {
            $found = false;
            foreach ($leks as $lek) {
                $go = true;
                $this->lekrec->fromIso($lek['DATA']);
//                $this->lekcreatedate = str_replace(' ', '', $lek['OPRET']);
//                $this->lekajourdate = str_replace(' ', '', $lek['AJOUR']);
                $f700fs = $this->lekrec->findSubFields('700', 'f');
                if ($f700fs) {
                    foreach ($f700fs as $f700f) {
                        if (strtolower($f700f) == 'skole') {
                            $go = false;
                        }
                    }
                }
                if ($go) {
                    $found = true;
                    break;
                }
            }
            if ($leks and !$found) {
                $this->lekrec->fromIso($leks[0]['DATA']);
//                $this->lekcreatedate = str_replace(' ', '', $lek['OPRET']);
//                $this->lekajourdate = str_replace(' ', '', $lek['AJOUR']);
            }
//            $strng = $this->lekrec->toLineFormat();
            return true;
        } else {
            // posten findes ikke i lektørbasen endnu - vi venter
            $this->mediadb->leklog($seqno, 'WAIT', 'not Found i lektørbasen', $faust);
            return false;
        }
    }

    private function updateLek($seqno, $tofaust) {
        // har den et lektørnummer ellers opret et.
        $infos = $this->mediadb->getInfoData($seqno);
        if ($infos[0]['lekfaust'] != '') {
            $lekfaust = $infos[0]['lekfaust'];
        } else {
            $lekfaust = $this->gfaust->getFaust();
        }

        // tag lektør posten ændre 001 og 014
        $lf = str_replace(' ', '', $lekfaust);
        $this->lekrec->substitute('001', 'a', $lf);

//        $createdate = date('YmdHis');
//        $ajourdate = date('YmdHis');
//        $this->lekrec->substitute('001', 'd', $this->lekcreatedate);
//        $this->lekrec->substitute('001', 'c', $this->lekajourdate);

//        $lekstrng = $this->lekrec->toLineFormat();
//        $lokids = $this->marc->findSubFields('001', 'a');
//        $lokid = str_replace(' ', '', $lokids[0]);
//        $this->lekrec->substitute('014', 'a', $lokid);
        $this->lekrec->substitute('014', 'a', str_replace(' ', '', $tofaust));

        $weekcodes = $this->calendar->getWeekCodesOfTheDay();
        $lea = 'LEA' . $weekcodes['BKM'];
        $this->lekrec->remSubfieldText('032', 'x', 'LEK');
//        $this->lekrec->remSubfieldTe,t('032', 'x', 'LEX');
        $this->lekrec->remSubfieldText('032', 'x', 'LEA');
//        $this->lekrec->insert_field($lea, '032', 'x');
        $this->lekrec->insert_subfield($lea, '032', 'x');

//        $this->lekrec->insert_subfield('[autoLU]', '245', 'a');

        $oldBKM = $this->HasItBKM($this->marc);
        $newBKM = $this->HasItBKM($this->newMarc);
        if (substr($oldBKM, 3) < substr($newBKM, 3)) {
//            $createdate = $this->lekrec->findSubFields('001', 'd', 1);
            $createdate = $this->lekrec->findSubFields('008', 'a', 1);
//            $strng = $this->lekrec->toLineFormat(78);
            $year = substr($createdate, 0, 4);

            $mattxt = "Materialevurdering oprindeligt udarbejdet til udgave fra $year";
//        $this->lekrec->insertWithoutDublets($mattxt);

            $found = false;
            $f559as = $this->lekrec->findSubFields('559', 'a');
            if ($f559as) {
                foreach ($f559as as $f559a) {
                    if ($f559a == $mattxt) {
                        $found = true;
                        break;
                    }
                }
            }
            if ( !$found) {
                $this->lekrec->insert_field($mattxt, '559', 'a');
            }
        }
//        $lekstrng = $this->lekrec->toLineFormat();
        $this->marclns[] = $this->lekrec->toIso();
//        echo "Seqno:$seqno\n";
        if ($this->nothing) {
            echo "Nothing: Seqno:$seqno Update lekfaust:$lekfaust\n";
            echo "Nothing: Seqno:$seqno Set status:'Done'\n";
        } else {
            $this->mediadb->updateLekFaust($seqno, $lekfaust);
            $this->mediadb->updateLekStatus($seqno, 'Done');
        }
        return $lekfaust;

    }

    private function deleteRec() {
        $found = false;
        $f004rs = $this->marc->findSubFields('004', 'r');
        foreach ($f004rs as $f004r) {
            if ($f004r == 'd') {
                $found = true;
            }
        }
        return $found;
    }


    private function is_not_NY_TITEL() {
        $f652ms = $this->marc->findSubFields('652', 'm');
        foreach ($f652ms as $f652m) {
            if ($f652m == 'NY TITEL') {
                return false;
            }
        }
        return true;
    }

    function getBasisRecs() {
        return $this->newmarclns;
    }
}
