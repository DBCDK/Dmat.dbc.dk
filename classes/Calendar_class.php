<?php

/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 09-05-2016
 * Time: 10:19
 */
class Calendar_class {

    private $bankHoliday;
    private $dateArray;
    private $weekdays;
    private $oneDay;
    private $db;
    private $calendarExceptions;
    private $seqname;
    private $year;
    private $role;

//    private $dbfdpf;
    private $tableinfo;
    private $includeAllWeeks;

    function __construct(pg_database $db, $year, $role = '') {
        if ($year < 1970) {
            return;
        }
        $this->db = $db;
        $this->year = $year;
        $this->role = $role;
//        echo "ROLE:" . $this->role;
        $this->calendarExceptions = $tablename = 'calendarexp';
        $this->seqname = $seqname = $this->calendarExceptions . 'seq';
        $this->includeAllWeeks = false;

        $sql = "select tablename from pg_tables where tablename = $1";
        $arr = $db->fetch($sql, array($tablename));
        if (!$arr) {
            $sql = "create table $tablename (
              seqno integer primary key,
              color varchar(20),
              type varchar(20),
              status varchar(10),
              key integer,
              value timestamp with time zone,
              createdate timestamp with time zone
        )
        ";
            $db->exe($sql);
            verbose::log(TRACE, "table created:$sql");

            $sql = "drop sequence if exists $seqname";
            $db->exe($sql);
            $sql = "create sequence $seqname";
            $db->exe($sql);
        }

        $this->bankHoliday = array();
        $this->dateArray = array();
        $this->weekdays = array();
        $this->weekdays[1] = 'mandag';
        $this->weekdays[2] = 'tirsdag';
        $this->weekdays[3] = 'onsdag';
        $this->weekdays[4] = 'torsdag';
        $this->weekdays[5] = 'fredag';
        $this->weekdays[6] = 'lørdag';
        $this->weekdays[7] = 'søndag';

        $this->oneDay = 24 * 60 * 60;

        $b = strtotime("$year-01-01");
        $c = strtotime("last monday", $b);
        $startDate = strtotime("-20 weeks", $c);
        $b = strtotime("$year-12-31");
        $c = strtotime("last friday", $b);
        $endDate = strtotime("+5 weeks", $c);
        $this->makeHolidays($year - 1);
        $this->makeHolidays($year);
        $this->makeHolidays($year + 1);
        ksort($this->bankHoliday);

        for ($thisday = $startDate; $thisday <= $endDate; $thisday = $thisday + $this->oneDay) {
            $ts = strtotime(date('m/d/Y', $thisday));
            $res = $this->getDefault($ts);
            $this->dateArray[$ts] = $res;
        }
    }

    private function makeHolidays($year) {


        $easterT = date('Y.m.d', easter_date($year) - ($this->oneDay * 3));
        $this->bankHoliday[$easterT] = 'Skærtorsdag';
        $easterF = date('Y.m.d', easter_date($year) - ($this->oneDay * 2));
        $this->bankHoliday[$easterF] = 'Langfredag';
        $easterM = date('Y.m.d', easter_date($year) + ($this->oneDay * 1));
        $this->bankHoliday[$easterM] = '2. påskedag';

        $kluft = date('Y.m.d', easter_date($year) + ($this->oneDay * 39));
        $this->bankHoliday[$kluft] = 'Kristi himmelfartsdag';

        $ts = strtotime('+40 day', easter_date($year));
        if (date('w', $ts) == 5) { // fredag efter kristi luftfart
            $d = date('Y.m.d', $ts);
            $this->bankHoliday[$d] = 'indeklemt fredag';
        }
        $pinse = date('Y.m.d', easter_date($year) + ($this->oneDay * 50));
        $this->bankHoliday[$pinse] = '2. pinsedag';

        $stbededag = date('Y.m.d', strtotime('+26 day', easter_date($year)));
        $this->bankHoliday[$stbededag] = 'Store bededag';
        $ts = strtotime('+27 day', easter_date($year));
        if (date('w', $ts) == 5) { // fredag efter store bededag
            $d = date('Y.m.d', $ts);
            $this->bankHoliday[$d] = 'indeklemt fredag';
        }
        $this->bankHoliday["$year.01.01"] = 'Nytårsdag';  # 1. nytårsdag
        $ts = strtotime("02.01.$year");
        if (date('w', $ts) == 5) {
            $this->bankHoliday["$year.01.02"] = 'indeklemt fredag';
        }
        $this->bankHoliday["$year.05.01"] = '1. maj';
        if (date('w', strtotime("02.05.$year")) == 5) {
            $this->bankHoliday["$year.05.02"] = 'indeklemt fredag';
        }
        $this->bankHoliday["$year.06.05"] = 'Grunlovsdag';
//        $this->bankHoliday["$year.12.23"] = 'Lille juleaften';  # lillejuleaften
        $this->bankHoliday["$year.12.24"] = 'Juleaftensdag';  #  juleaften
        $this->bankHoliday["$year.12.25"] = '1. juledag';  # 1. juledag
        $this->bankHoliday["$year.12.26"] = '2. juledag';  # 2. juledag
        if (date('w', strtotime("27.12.$year")) == 5) {
            $this->bankHoliday["$year.12.27"] = 'indeklemt fredag';
        }
        $this->bankHoliday["$year.12.31"] = 'Nytårsaftensdag';  #  nytårsaften

    }

    function setTable($tableinfo) {
        $this->tableinfo = $tableinfo;
    }

    function getWeekCode($ts) {
        return date('oW', $ts);
    }

    private
    function getDefault($thisDay) {
        $weekcode = date('oW', $thisDay);
        $currentDay = date('Y.m.d', $thisDay);
        $bhd = 'workday';

        $i = date('N', $thisDay);
        $weekday = $this->weekdays[$i];
        if ($i == 6 or $i == 7) {
            $bhd = $weekday;
        }
        if (array_key_exists($currentDay, $this->bankHoliday)) {
            $bhd = $this->bankHoliday[$currentDay];
        }
        $ret = array(
            'currentDay' => $thisDay,
            'bhd' => $bhd,
            'weekDay' => $weekday,
            'dayInWeek' => $i,
            'weekcode' => $weekcode
        );
        return $ret;
    }

    function getDateArray() {
        return $this->dateArray;
    }


    function info($par, $col) {
        $onClick = $show = $size = $class2 = $beforetxt = $class = $title = '';
        $indx = $par[0];
        $user = $par[1];
        $color = 'nonecolor';
        if (array_key_exists(2, $par)) {
            if ($par[2]) {
                $color = $par[2];
            }
        }
        $type = 'NoChange';
        if (array_key_exists('type', $col)) {
            $type = $col['type'];
        }
//        $onClick = '';
        if (array_key_exists('onClick', $col)) {
            $onClick = $col['onClick'];
        }
        if ($type != 'text' and $type != 'text2') {
            if (array_key_exists($indx, $this->dateArray)) {
                if ($this->dateArray[$indx]['bhd'] == 'workday') {
                    $class = 'workday';
                    $title = $this->dateArray[$indx]['weekDay'];
                } else {
                    $class = 'bhd';
                    $title = $this->dateArray[$indx]['bhd'];
                }

            } else {
                $class = 'bhd';
                if ($user) {
                    $class = 'user';
//                    $title = $this->dateArray[$indx]['weekDay'] . ", manuelt";
                }
            }

            if ($indx != 'blank' and $indx != 'Udgår') {
//                echo "indx:$indx -";
                $data = date('d-m-Y', $indx);
            }
        }
//        $size = '';
        if (array_key_exists('size', $col)) {
            $size = $col['size'];
        }
//        $class2 = '';
        if (array_key_exists('class', $col)) {
            $class2 = $col['class'];
        }
        if (array_key_exists('show', $col)) {
            $show = $col['show'];
        }
        if ($color != 'nonecolor') {
            $class = "user" . $color;
        }

//        if (array_key_exists($indx, $exp)) {
//            if (array_key_exists($col['name'], $exp[$indx])) {
//                if ($exp[$indx][$col['name']['color']] != 'nonecolor') {
//                    $class = 'user' . $exp[$indx][$col['name']['color']];
//                }
//            }
//        }
//        $beforetxt = '';
        if ($type == 'weekChange') {
            $dformat = $col['dateformat'];
            if ($indx == 'blank') {
                $data = '';
            } else {
                if ($indx == 'Udgår') {
                    $data = 'Udgår';
                } else {
                    $data = date($dformat, $indx);
                }
            }

            if (array_key_exists('before_text', $col)) {
                $beforetxt = $col['before_text'];
            }
        }

        if ($type == 'text' or $type == 'text2') {
            $data = "";
            $class = 'text';
            if (array_key_exists('before_text', $col)) {
                $data .= $col['before_text'];
                $class = 'text';
            }
            if (array_key_exists('dateformat', $col)) {
//                echo "indx:";
//                print_r($indx);
                $dformat = $col['dateformat'];
                $data .= date($dformat, $indx);
                $class = 'text';
            } else {
                $data .= $indx;
            }
        }
        $orgts = 0;
        if (array_key_exists(3, $par)) {
            $orgts = $par[3];
        }
        return
            array(
                'class' => $class,
                'colclass' => $class2,
                'data' => $data,
                'type' => $type,
                'size' => $size,
                'ts' => $indx,
                'title' => $title,
                'beforetxt' => $beforetxt,
                'onClick' => $onClick,
                'show' => $show,
                'orgts' => $orgts
            );
    }

    function corr($ts, $type, $exp, $key) {
        if (array_key_exists($key, $exp)) {
            if (array_key_exists($type, $exp[$key])) {
                $newts = $exp[$key][$type]['ts'];
                if ($exp[$key][$type]['status'] == 'blank') {
                    $newts = 'blank';
                }
                if ($exp[$key][$type]['status'] == 'gone') {
                    $newts = 'Udgår';
                }
                $color = $exp[$key][$type]['color'];
//                $this->dateArray[$newts]['bhd'] = 'Bruger skabt';
                return array($newts, true, $color, $ts);
            }
        }
        return array($ts, false, '', $ts);
    }

    function toHtml($rows, $raw = true, $evenUneven = 'evenrow') {
        $strng = "";
        if ($this->includeAllWeeks) {
            $iaw = 'iaw=ok';
        } else {
            $iaw = '';
        }
        foreach ($rows as $key => $row) {
            if (!$raw) {
                $strng .= "#row$key|";
            } else {
                $strng .= "<tr class='$evenUneven' id='row$key'>\n";
            }
            foreach ($row as $type => $info) {
                $size = $info['size'];
                $onClick = $info['onClick'];
                if ($onClick) {
                    $onClick = str_replace('$name', "'$type'", $onClick);
                    $onClick = str_replace('$key', "'$key'", $onClick);
                    $onClick = "id='$type" . $key . "' onClick=\"$onClick\" "
                        . "value='" . date('d-m-Y', $info['ts']) . "' "
                        . "geteven='$evenUneven' ";

                }
                $colclass = $info['colclass'];
                $class = $title = '';
                if ($info['type'] == 'NoChangeColor') {
                    $class = $info['class'];
                    $title = "title='" . $info['title'] . "' ";
                }
                $strng .= "<td class='$colclass dbf $class' $onClick $title >\n";
                if ($info['type'] == 'change') {
                    $strng .= "    <input class='datecell $colclass $evenUneven " . $info['class'] . "' "
                        . "value='" . $info['data'] . "' "
                        . "geteven='$evenUneven' "
                        . " $iaw "
                        . "title='" . $info['title'] . "' "
                        . "id='$type" . $key . "' "
                        . "size='12' "
                        . "onchange=\"updateDate('$type','$key')\" "
                        . "/>\n";
                }
                if ($info['type'] == 'weekChange') {
                    $strng .= $info['beforetxt'] . "    <input class='datecell3 $colclass $evenUneven " . $info['class'] . "' "
                        . "value='" . $info['data'] . "' "
                        . "geteven='$evenUneven' "
                        . " $iaw "
                        . "title='" . $info['title'] . "' "
                        . "id='$type" . $key . "' "
                        . "size='12' "
//                        . "onchange=\"updateDate('$type','$key')\" "
                        . "onClick=\"newWeekCode('$type','$key')\" "
                        . "/>\n";
                }

                if ($info['type'] == 'text' or $info['type'] == 'NoChange' or $info['type'] == 'NoChangeColor') {
                    $strng .= " " . $info['data'] . "\n";
                }

                $strng .= "</td>\n";
            }
            if ($raw) {
                $strng .= "</tr>\n";
            } else {
                $strng .= "+";
            }
            if ($evenUneven != 'unevenrow') {
                $evenUneven = 'evenrow';
            } else {
                $evenUneven = 'unevenrow';
            }
        }


        $strng = rtrim($strng);
        return $strng;
    }

    function toHtml2($rows, $raw = true, $evenUneven = 'evenrow') {
        if ($raw) {
            $nxt = '';
        } else {
            $nxt = "+";
        }
        $strng = "";
        foreach ($rows as $key => $row) {

            if ($raw) {
                $strng .= "<div class='row $evenUneven' id='row$key'>\n";
                $idkey = '';
            } else {
                $idkey = "#row$key|";
            }
            $cols = count($row);
            $end = '';
            $cnt = 0;
            $strng .= "$idkey<div class='medium-12 columns '>\n";
            foreach ($row as $type => $info) {
                $cnt++;
                if ($cnt == $cols) {
                    $end = 'end';
                }
                $size = $info['size'];
                $onClick = $info['onClick'];
                if ($onClick) {
                    $onClick = str_replace('$name', "'$type'", $onClick);
                    $onClick = str_replace('$key', "'$key'", $onClick);
                    $onClick = "id='$type" . $key . "' onClick=\"$onClick\" "
                        . "value='" . date('d-m-Y', $info['ts']) . "' "
                        . "geteven='$evenUneven' ";

                }
                $colclass = $info['colclass'];
                $strng .= "  <div class='small-$size column datecell2 $colclass $end' $onClick>\n";
                if ($info['type'] == 'change') {
                    $strng .= "    <input class='datecell $colclass $evenUneven " . $info['class'] . "' "
                        . "value='" . $info['data'] . "' "
                        . "geteven='$evenUneven' "
                        . "title='" . $info['title'] . "' "
                        . "id='$type" . $key . "' "
                        . "size='12' "
                        . "onchange=\"updateDate('$type','$key')\" "
                        . "/>\n";
                }
                if ($info['type'] == 'text' or $info['type'] == 'NoChange' or $info['type'] == 'NoChangeColor') {
                    $strng .= "    " . $info['data'] . "\n";
                }

                $strng .= "  </div>\n";
            }
            $strng .= "</div>\n$nxt";
            if ($raw) {
                $strng .= "</div>\n";
            }
            if ($evenUneven == 'unevenrow') {
                $evenUneven = 'evenrow';
            } else {
                $evenUneven = 'unevenrow';
            }
        }


        $strng = rtrim($strng, $nxt);
        return $strng;
    }

    function getHead2() {
        $strng = "\n";
        $strng .= "<div class='row evenrow '>\n";
        $strng .= "   <div class='medium-12 columns '>\n";
        $cnt = 0;
        $cols = count($this->tableinfo);
        $end = '';
        foreach ($this->tableinfo as $info) {
            $cnt++;
            if ($cnt == $cols) {
                $end = 'end';
            }
            $size = $info['size'];
            $head = $info['head'];
            $colclass = $info['class'];
            $strng .= "        <div class=\"medium-$size column $colclass headtxt $end\">$head</div>\n";
        }
        $strng .= "    </div>\n";
        $strng .= "</div>\n\n";
        return $strng;
    }

    function getHead($oneDay = '', $width = 80, $caption = 'xx') {
        $strng = "\n";
        if ($oneDay) {
            $strng .= "<div class='row'>\n";
            $strng .= "<table id='klistret' width='$width%' >\n";
            $strng .= "<thead>\n";
            $strng .= "  <tr><th class='pageName' colspan='22'>$caption</th>\n</tr>\n";
        } else {
            $strng .= "<thead class='print-only'>\n";
        }
        $strng .= "<tr class='evenrow '>\n";
        foreach ($this->tableinfo as $info) {
            $head = $info['head'];
            $colclass = $info['class'];
            $strng .= "        <th class=\"$colclass headtxt\">$head</th>\n";
        }
        $strng .= "</tr>\n";
        $strng .= "</thead>\n\n";
        if ($oneDay) {
            $strng .= "<tr>\n";
            foreach ($oneDay as $val) {
                $strng .= "<td class='gem dbf'>\n";
                $strng .= $val['beforetxt'];
                if ($val['class'] == 'text') {
                    $strng .= $val['data'] . "\n";
                } else {
                    $strng .= "<input class='gem' value='' size='12'/>\n";
                }
                $strng .= "</td>\n";
            }
            $strng .= "</tr>\n";
            $strng .= "</table>\n";
            $strng .= "</div>\n";
        }
        return $strng;
    }

    function IncludeAllWeeks() {
        $this->includeAllWeeks = true;
    }

    function getDBFday($startweek) {
//        $startweek = '201613';
//        $sta = 'canceled';
//        while ($sta == 'canceled') {
        $found = false;
        $exp = $this->getExp('dbfdpf');
        foreach ($this->dateArray as $key => $d) {
            if ($d['weekcode'] == $startweek) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $max = $this->getWeekCode($key);
            if ($max > $startweek) {
                return array(
                    'date' => '00001101',
                    'status' => null,
                    'title' => '',
                    'nxtweek' => ''
                );
            } else {
                return array(
                    'date' => '99999999',
                    'status' => null,
                    'title' => '',
                    'nxtweek' => ''
                );
            }
        }
//        $diw = $d['dayInWeek'];
        $proof = strtotime('-1 week', $key);
//        $p = date('Ymd', $proof);

        $wno = date('W', $key);
        if ($wno == '01') {
            $i = strtotime('-2 weeks', $key);
            $proof = strtotime('-1 week', $proof);
            $ww = date('W', $i);
            if ($ww == '52') {
                $proof = strtotime('-1 week', $proof);
            }
        }
        $proof = $this->corr($proof, 'proof', $exp, $key);
        $publish = strtotime('+4 days', $proof[0]);
        $publish = $this->corr($publish, 'publish', $exp, $key);
        $p = date('Ymd', $publish[0]);
        $info = $this->info($publish, $this->dateArray[$key]);

        $sta = '';
        if (array_key_exists($key, $exp)) {
            if (array_key_exists('title', $exp[$key])) {
                if (array_key_exists('status', $exp[$key]['title'])) {
                    $sta = $exp[$key]['title']['status'];
                }
            }
        }
        $newkey = strtotime('+1 week', $key);
        $startweek = $this->getWeekCode($newkey);
//        }
        return array(
            'date' => $p,
            'status' => $sta,
            'title' => $info['title'],
            'nxtweek' => $startweek
        );
    }

    /**
     * @param $startweek
     * @param int $max
     * @return array
     *
     *
     */
    function getDays($startweek, $max = 99999, $noexp = false) {
        if ($noexp) {
            $exp = array();
        } else {
            $exp = $this->getExp('dbfdpf');
        }
        $dbfdpf = array();
        foreach ($this->dateArray as $key => $d) {
            $diw = $d['dayInWeek'];
            if ($diw == 1) {
//                $zz = date('d-m-Y oW', $key); 1451862000
//                if ($key == 1495404000) {
//                    $zz = date('d-m-Y oW', $key);
//                }
                $info = array();
                $weekcode = $d['weekcode'];
//                $curD = $d['currentDay'];

                $extdate = strtotime('+3 days', $key);
                $bkmextdate = strtotime('+3 days', $key);
                $sfgextdate = strtotime('+0 days', $key);
                $newslist = strtotime('+0 days', $key);
                $onPoffice = strtotime('+4 days', $key);
                $newandupdate = strtotime('+1 week', $key);
                $bkmperiode = strtotime('+1 week', $key);
                $sfgperiode = strtotime('+0 week', $key);
                $otherDeliveries = strtotime('+1 week', $key);
                $ArtAnm = strtotime('+0 week', $key);
                $ajourdato = strtotime('+1 week', $key);
                $ifs = strtotime('+1 week', $key);
                $toextdate = strtotime('+2 days', $key);
                $fromextdate = strtotime('-4 days', $key);
                $xx = date('d-m-Y oW', $bkmextdate);
//                $yy = date('d-m-Y oW', $key);
//                $zz = date('d-m-Y oW', 1451186200);
                $close = strtotime('-10 days', $key);
                $proof = strtotime('-1 week', $key);
                $netpunkt = strtotime('-4 days', $key);
                $pdfproof = strtotime('-4 days', $key);
                $pdffile = $key;
                $wno = date('W', $key);
                if ($wno == '01') {
//                    if (date('Y', $key) != $this->year) {
                    $i = strtotime('-2 weeks', $key);
                    $close = strtotime('-1 weeks', $close);
                    $proof = strtotime('-1 week', $proof);
                    $netpunkt = strtotime('-1 week', $netpunkt);
                    $onPoffice = strtotime('-1 week', $onPoffice);
                    $pdfproof = strtotime('-1 week', $pdfproof);
                    $pdffile = strtotime('-1 week', $pdffile);
                    $ww = date('W', $i);
                    if ($ww == '52') {
                        $i = strtotime('-3 weeks', $key);
                        $close = strtotime('-2 weeks', $close);
                        $proof = strtotime('-1 week', $proof);
                        $netpunkt = strtotime('-1 week', $netpunkt);
                        $pdfproof = strtotime('-1 week', $pdfproof);
                        $pdffile = strtotime('-1 week', $pdffile);
                    }
//                    }
                } else {
                    $i = strtotime('-1 week', $key);
                }
                if (array_key_exists($i, $dbfdpf)) {
                    $start = $dbfdpf[$i]['close']['ts'];
                } else {
                    $start = null;
                }
                $close = $this->corr($close, 'close', $exp, $key);
                $calendarweek = $this->corr($key, 'calendarweek', $exp, $key);
                $extdate = $this->corr($extdate, 'extdate', $exp, $key);

//                $xx = date('d-m-Y oW', $start);
//                $xx = date('d-m-Y', $close[0]);

                $onPoffice = $this->corr($onPoffice, 'onPoffice', $exp, $key);
                $newandupdate = $this->corr($newandupdate, 'newandupdate', $exp, $key);
                $otherDeliveries = $this->corr($otherDeliveries, 'otherDeliveries', $exp, $key);
                $bkmperiode = $this->corr($bkmperiode, 'bkmperiode', $exp, $key);
                $sfgperiode = $this->corr($sfgperiode, 'sfgperiode', $exp, $key);
                $ArtAnm = $this->corr($ArtAnm, 'ArtAnm', $exp, $key);
                $ajourdato = $this->corr($ajourdato, 'ajourdato', $exp, $key);
                $ifs = $this->corr($ifs, 'ifs', $exp, $key);
                $fromextdate = $this->corr($fromextdate, 'fromextdate', $exp, $key);
                $bkmextdate = $this->corr($bkmextdate, 'bkmextdate', $exp, $key);
                $sfgextdate = $this->corr($sfgextdate, 'sfgextdate', $exp, $key);
                $newslist = $this->corr($newslist, 'newslist', $exp, $key);
                if ($bkmperiode[0] == 'blank') {
                    $bkmextdate = array('blank', true);
                }
                if ($sfgperiode[0] == 'blank') {
                    $sfgextdate = array('blank', true);
                }
//                $toextract = strtotime('+1 day', $fromextract[0]);
                $toextdate = $this->corr($toextdate, 'toextdate', $exp, $key);

                $title = $this->corr($key, 'title', $exp, $key);
                $start = $this->corr($start, 'start', $exp, $key);

                $period = array($weekcode, false);

                $bookcar = $this->corr($proof, 'bookcar', $exp, $key);
                $korr = strtotime('+2 days', $proof);
//                $xx = date('d-m-Y oW', $korr);

                $korr = $this->corr($korr, 'korr', $exp, $key);
//                $xx = date('d-m-Y', $korr[0]);

                $editionstop = strtotime('+2 days', $proof);

                $editionstop = $this->corr($editionstop, 'editionstop', $exp, $key);
                $opd990 = strtotime('+2 days', $proof);
                $opd990 = $this->corr($opd990, 'opd990', $exp, $key);
                $proof = $this->corr($proof, 'proof', $exp, $key);
                $stop = strtotime('+1 day', $proof[0]);
                $stop = $this->corr($stop, 'stop', $exp, $key);
                $second = strtotime('+3 days', $proof[0]);

                $netpunkt = $this->corr($netpunkt, 'netpunkt', $exp, $key);

                $pdfproof = $this->corr($pdfproof, 'pdfproof', $exp, $key);
                $second = $this->corr($second, 'second', $exp, $key);
                $publish = strtotime('+4 days', $proof[0]);
                $publish = $this->corr($publish, 'publish', $exp, $key);
                $pdffile = $this->corr($pdffile, 'pdffile', $exp, $key);

                $to = strtotime('-1 days', $close[0]);
                $to = date('d-m-Y', $to);
                $from = date('d-m-Y', $start[0]);
                $fromto = array("$from &nbsp; til &nbsp; $to", false);
//                $fromextract = date('d-m-Y', $fromextract[0]);
//                $toextract = date('d-m-Y', $toextract[0]);
//                $fromtoextract = array("$fromextract &nbsp; til &nbsp; $toextract", false);

                foreach ($this->tableinfo as $col) {
                    $name = $col['name'];
                    $info[$name] = $this->info($$name, $col);
                }

                if (array_key_exists($key, $exp)) {
                    if (array_key_exists('title', $exp[$key])) {
                        if (array_key_exists('status', $exp[$key]['title'])) {
                            $sta = $exp[$key]['title']['status'];
                            if ($sta == 'canceled') {
                                foreach ($info as $name => $val) {
                                    if ($val['type'] == 'change' or $val['type'] == 'NoChange' or
                                        $val['type'] == 'NoChangeColor'
                                    ) {
                                        if ($info[$name]['show'] != 'noCancel') {
                                            $info[$name]['data'] = '';
                                        }
                                    }
                                }
                            }
                        }
                    }
                }
//                if (!$this->includeAllWeeks) {
                if (!$this->includeAllWeeks and ($wno == '52' or $wno == '53')) {
                    ;
                } else {
                    $dbfdpf[$key] = $info;
                }
//                }
            }
        }
        $ret = array();
        $cnt = 0;
        foreach ($dbfdpf as $key => $val) {
            $thisweek = date('oW', $key);
            if ($thisweek >= $startweek) {
                $cnt++;
                if ($cnt > $max) {
                    break;
                }

                $ret[$key] = $val;
            }
        }
        return $ret;
    }

    function getFirstWeekcode() {
        $year = $this->year;
        $ts = strtotime("01-01-$year");
        if (date('w', $ts) != 5) {  // 5 == friday
            $ts = strtotime('next friday', $ts);
        }
        return date('oW', $ts);
    }

    function updateCanceled($type, $key, $newdate) {
        $select = "select status from " . $this->calendarExceptions . " "
            . "where type = '$type' "
            . "order by createdate desc "
            . "limit 1";
        $rows = $this->db->fetch($select);

        if ($rows) {
            $canceled = $rows[0]['status'];
        } else {
            $canceled = 'unCanceled';
        }
        if ($canceled == 'unCanceled') {
            $canceled = 'canceled';

        } else {
            $canceled = 'unCanceled';
        }

        $this->updateOneDate($type, $key, $newdate, $canceled);
    }

    function updateOneDate($type, $key, $newdate, $status = '', $color = 'nonecolor') {
        $keyInfo = $this->getDefault($key);
        $weekcode = $keyInfo['weekcode'];

        $dayInfo = $this->getDays($weekcode, 1, $noexp = true);
        $dayInfo2 = $this->getDays($weekcode, 1, $noexp = false);
        if (
            $type == 'stop'
            or $type == 'second'
            or $type == 'publish'
        ) {
            $ts = $dayInfo2[$key][$type]['orgts'];
            $dd = date('d-m-Y', $ts);
        } else {
            $dd = $dayInfo[$key][$type]['data'];
        }
//        if ($type == 'ArtAnm') {
//            $dd = '';
//        }
        if ($dd == $newdate) {  // same date as default date, remove exp
            $remove = "delete from " . $this->calendarExceptions . " "
                . "where type = '$type' "
                . "and key = $key";
            if ($this->role == 'Bruger') {
                $this->db->exe($remove);
            }
            return true;
        }
        $seq = $this->seqname;
        $dformat = 'DD-MM-YYYY';
        if (strlen($newdate) == 0 or strlen($newdate) == 6) {
            $dformat = 'IYYYIW';
        }

        if ($newdate == '') {
            $newdate = date('oW', $key);
            $status = 'blank';
        }
        if (strtolower($newdate) == 'udgår') {
            $newdate = date('oW', $key);
            $status = 'gone';
        }

        $insert = "insert into " . $this->calendarExceptions . " "
            . "(seqno, color, type, status, key, value, createdate) "
            . "values ("
            . "nextval('$seq'), "
            . "'$color', "
            . "'$type', "
            . "'$status', "
            . "$key, "
            . "to_date('$newdate','$dformat'), "
            . "current_timestamp) ";
//        echo "$insert\n";
//        echo " XXX Role:" . $this->role;
        if ($this->role == 'Bruger') {
            $this->db->exe($insert);
        }
    }

    function getExp($page) {
        $tablename = $this->calendarExceptions;
        $syear = $this->year - 1;
        $eyear = $this->year + 1;
        $sql = "select seqno, color, type, status, key, to_char(value,'DD-MM-YYYY') as date, createdate "
            . "from $tablename "
            . "where date_part('year',value) >= '$syear' "
            . "and date_part('year',value) <= '$eyear' "
//            . "and page = '$page' "
            . "order by seqno ";
        $rows = $this->db->fetch($sql);
        if ($rows) {
            $exp = array();
            foreach ($rows as $row) {
                $d = $row['date'];
                $row['ts'] = mktime(0, 0, 0, substr($d, 3, 2), substr($d, 0, 2), substr($d, 6, 4));
                $exp[$row['key']][$row['type']] = $row;
            }

            return $exp;
        }
        return array();
    }

    /**
     * @param string $day the format dd-mm-yyyy
     */
    function getWeekCodesOfTheDay($day = '') {
        $ret = array();
        if (!$day) {
            $day = date('d-m-Y');
        }
        $key = strtotime("$day");
        $ret['today'] = date('d-m-Y', $key);
//        $newdate = date('dmY Hms');
//        $newdate2 = date('dmY Hms', $key);

//        $exp = $this->getExp('dbfdpf');
        if (!array_key_exists($key, $this->dateArray)) {
            throw new Exception("Date out of scope $day");
        }
        $dateInfo = $this->dateArray[$key];
        $ret['CalendarWeekCode'] = $dateInfo['weekcode'];
        $set = array();
//        $set[] = array(
//            'name' => 'title',
//            'size' => 2,
//            'type' => 'text',
//            'onClick' => 'canceledUncanceled($name,$key)',
//            'dateformat' => 'oW',
//            'head' => 'Titel<hr>Klik = udgår',
//            'before_text' => 'Dansk Bogfort.ugefort. '
//        );
//        $set[] = array(
//            'name' => 'start',
//            'size' => 1,
//            'type' => 'change',
//            'head' => 'Inddatering påbegyndes (ugekode skifter : DBF) Fredag',
//            'class' => 'colbeige'
//        );
        $set[] = array(
            'name' => 'close',
            'size' => 1,
            'type' => 'change',
            'head' => 'Reg.slut Fredag',
            'class' => 'colbeige'
        );
        $set[] = array(
            'name' => 'period',
            'size' => 1,
            'type' => 'text',
            'head' => 'Periode (katalogkode)',
            'before_text' => 'DBF'
        );
        $set[] = array(
            'name' => 'toextdate',
            'size' => 1,
            'type' => 'change',
            'head' => "Ajourføringsdatoer<hr>Til",
            'show' => 'noCancel',
            'class' => 'colligthblue',
            'before_text' => ' &nbsp; til &nbsp; '
        );
        $set[] = array(
            'name' => 'otherDeliveries',
            'size' => 1,
            'type' => 'weekChange',
            'head' => "Øvrige ugentlige dataleverancer\nBoghandler forening, UTC",
            'show' => 'NoCancel',
            'clas' => 'colbeige',
            'dateformat' => 'oW'
        );
        $set[] = array(
            'name' => 'toextdate',
            'size' => 1,
            'type' => 'change',
            'head' => "Ajourføringsdatoer<hr>Til",
            'show' => 'noCancel',
            'class' => 'colligthblue',
            'before_text' => ' &nbsp; til &nbsp; '
        );
//
        $this->setTable($set);
        $wcods = $this->getDays($dateInfo['weekcode'], 10);
//        $wcods = $this->getDays('201701');
        $found = false;
        foreach ($wcods as $k => $inf) {
            $close = $inf['close'];
            if ($close['data'] == '') {
                continue;
            }
            $ts = $close['ts'];
            if ($ts > $key) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception("Could not find the right date");
        }
        $ret['DBF'] = $inf['period']['ts'];
        $ret['BKM'] = $inf['period']['ts'];

        $found = false;
        foreach ($wcods as $k => $inf) {
            if ($inf['otherDeliveries']['ts'] == 'Udgår') {
                continue;
            }
            $toextdate = $inf['toextdate'];
//            if ($toextdate['data'] == '') {
//                continue;
//            }
            $ts = $toextdate['ts'];
            if ($ts >= $key) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            throw new Exception("Could not find the right ext date");
        }
        $ret['DAT'] = $inf['otherDeliveries']['data'];
        $ret['eReol'] = $ret['DBF'];
        $ret['ACC'] = $ret['CalendarWeekCode'];

        return $ret;


    }
}