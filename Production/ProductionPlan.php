<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";

//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/Calendar_class.php";
require_once "session_class.php";

$inifile = '../DigitalResources.ini';
//var_dump($_REQUEST);

$oneDay = 24 * 60 * 60;

$config = new inifile($inifile);
if ($config->error) {
    die($config->error);
}
if (!array_key_exists('order', $_REQUEST)) {
    $_REQUEST['order'] = 'extraction';
}
$connect_string = $config->get_value("connect", 'setup');
$db = new pg_database($connect_string);
$db->open();

$login = new session($db, 'pro_plan');
$username = $login->getUserName();
$username = $_SESSION[$username];
$role = $_SESSION['role'];

$order = $_REQUEST['order'];

session_start();
if ($order == 'newYear') {
    $_SESSION['year'] = $_REQUEST['pickAyear'];
    $_REQUEST['order'] = 'extraction';
    header("Refresh:0; url=?order=extraction");
}

if ($_SESSION['year']) {
    $year = $_SESSION['year'];
}
else {
    $year = date('Y');
}
//$year = 2015;
$role = $_SESSION['role'];
//echo "role:$role";
$calendar = new Calendar_class($db, $year, $role);

if ($order == 'canUncan') {
    $set = $_SESSION['setTable'];
    $calendar->setTable($set);
    $type = $_REQUEST['type'];
    $key = $_REQUEST['key'];
    $newdate = $_REQUEST['newdate'];
    $geteven = $_REQUEST['geteven'];
    $calendar->updateCanceled($type, $key, $newdate);
    $wc = $calendar->getWeekCode($key);
    $days = $calendar->getDays($wc, 1);
    $rowHTML = $calendar->toHtml($days, $raw = false, $geteven);

    echo $rowHTML;
    exit;
}
if ($order == 'getNewWeek') {
    $set = $_SESSION['setTable'];
    $calendar->setTable($set);
    $type = $_REQUEST['type'];
    $key = $_REQUEST['key'];
    $newdate = $_REQUEST['newdate'];
    $geteven = $_REQUEST['geteven'];
    $iaw = $_REQUEST['iaw'];
    $calendar->updateOneDate($type, $key, $newdate);
    $wc = $calendar->getWeekCode($key);
//    $wc = $year . "01";
    if ($iaw) {
        $calendar->IncludeAllWeeks();
    }
    $days = $calendar->getDays($wc, 2, false);
    $rowHTML = $calendar->toHtml($days, $raw = false, $geteven);

    echo $rowHTML;
    exit;
}
if ($order == 'newWeekCode') {
    $set = $_SESSION['setTable'];
    $calendar->setTable($set);
    $type = $_REQUEST['type'];
    $key = $_REQUEST['key'];
    $color = $_REQUEST['color'];
    $weekcode = $_REQUEST['weekcode'];
    $iaw = $_REQUEST['iaw'];
    $calendar->updateOneDate($type, $key, $weekcode, $staus = '', $color);
    $wc = $calendar->getWeekCode($key);
//    $wc = $year . "01";
    if ($iaw) {
        $calendar->IncludeAllWeeks();
    }
    $days = $calendar->getDays($wc, 2);
    $rowHTML = $calendar->toHtml($days, $raw = false, $geteven);

    echo $rowHTML;
    exit;
}

$page = new view('html/ProductionPlan.phtml');
$page->set('order', $order);
$page->set('year', $year);
$page->set('username', $username);
$page->set('loginfailure', $_SESSION['loginfailure']);
$page->set('role', $role);

$startweek = $year . "01";

if ($order == 'extraction') {
    $days = $calendar->getDateArray();
    $startweek = $year . "01";
    foreach ($days as $key => $d) {
        if ($d['weekcode'] == $startweek) {
            $startkey = $key;
            break;
        }
    }
    $utd = array(); // udtræksdage
    $week = array();
    foreach ($days as $key => $d) {
        if ($key < $startkey) {
            continue;
        }
        $diw = $d['dayInWeek'];
        if ($diw == 1) {
            $week['weekcode'] = "<td width='10%' title='Ugekode'>" . $d['weekcode'] . "</td>";
        }
        if ($diw == 3) {
            $to = date('d-m-Y', $d['currentDay']);
            $from = date('d-m-Y', $d['currentDay'] - 6 * $oneDay);
        }

        if ($diw < 6) {
            $title = $d['weekDay'];
            $cls = "";
            if ($d['bhd'] != 'workday') {
                $cls = "class='bankholiday'";
                $title .= ", " . $d['bhd'];
            }
            $week[$d['weekDay']] = "<td width='10%' title='$title' $cls>" . date('d-m-Y', $d['currentDay']) . "</td>";
            if ($diw == 5) { // new week
                $week[6] = "<td width='1%' title='torsdag'>$from</td>";
                $week[7] = "<td width='1%' >til</td>";
                $week[8] = "<td width='1%' title='onsdag'>$to</td>";
                $utd[$d['weekcode']] = $week;
                $week = array();
            }
        }
    }
    $page->set('weekcodes', $utd);
}

if ($order == 'external') {
    $set = array();
    $set[] = array(
        'name' => 'calendarweek',
        'size' => 2,
        'type' => 'text',
        'dateformat' => 'o:W',
        'head' => 'Kalenderuge'
    );
    $set[] = array(
        'name' => 'extdate',
        'size' => 1,
        'type' => 'change',
        'head' => 'Udtræksdato',
        'show' => 'noCancel'
    );
    $set[] = array(
        'name' => 'onPoffice',
        'size' => 1,
        'type' => 'change',
        'head' => 'På posthuset',
        'show' => 'noCancel',
        'class' => 'colgrey'
    );
    $set[] = array(
        'name' => 'newandupdate',
        'size' => 1,
        'type' => 'weekChange',
        'head' => 'Ugefortegnelse. Nye og ajourførte',
        'show' => 'NoCancel',
        'dateformat' => 'oW',
        'class' => 'colyellow'
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
        'name' => 'ArtAnm',
        'size' => 1,
        'type' => 'weekChange',
        'head' => "Nye fra ART og ANM",
        'show' => 'NoCancel',
        'clas' => 'colbeige',
        'dateformat' => 'oW'
    );
    $set[] = array(
        'name' => 'ajourdato',
        'size' => 1,
        'type' => 'weekChange',
        'head' => "Leverancer udtrukket på ajourdato",
        'show' => 'NoCancel',
        'class' => 'colligthblue',
        'dateformat' => 'oW'
    );
    $set[] = array(
        'name' => 'ifs',
        'size' => 1,
        'type' => 'weekChange',
        'head' => "IFS uge (cronjob)",
        'show' => 'NoCancel',
        'class' => 'colligthblue',
        'dateformat' => 'oW'
    );
    $set[] = array(
        'name' => 'fromextdate',
        'size' => 1,
        'type' => 'change',
        'head' => "Ajourføringsdatoer<hr>Fra",
        'show' => 'noCancel',
        'class' => 'colligthblue'
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
    $_SESSION['setTable'] = $set;
    $calendar->setTable($set);
    $startweek = $calendar->getFirstWeekcode();
    $calendar->IncludeAllWeeks();
//    $startweek = $year . "01";
    $days = $calendar->getDays($startweek);
    foreach ($days as $key => $oneDay) {
        break;
    }
    $width = 80;
    $caption = "Ugentlige Dataleverancer (eksterne) $year";
    $topHead = $calendar->getHead($oneDay, $width, $caption);
    $head = $calendar->getHead();
    $page->set('width', $width);
    $rowHTML = $calendar->toHtml($days);
    $page->set('head', $head);
    $page->set('topHead', $topHead);
    $page->set('rowHTML', $rowHTML);
    $page->set('display', 'default');
    $page->set('caption', $caption);
}

if ($order == 'dbfdpf') {
    $set = array();
    $set[] = array(
        'name' => 'title',
        'size' => 2,
        'type' => 'text',
        'onClick' => 'canceledUncanceled($name,$key)',
        'dateformat' => 'oW',
        'head' => 'Titel<hr>Klik = udgår',
        'before_text' => 'Dansk Bogfort.ugefort. '
    );
    $set[] = array(
        'name' => 'start',
        'size' => 1,
        'type' => 'change',
        'head' => 'Inddatering påbegyndes (ugekode skifter : DBF) Fredag',
        'class' => 'colbeige'
    );
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
        'name' => 'proof',
        'size' => 1,
        'type' => 'change',
        'head' => 'Udtræk til korrektur. Mandag kl. 15.00-16.00. <br/>Rød dato udtræk. kl. 9.00',
        'class' => 'colbeige'
    );
    $set[] = array(
        'name' => 'stop',
        'size' => 1,
        'type' => 'change',
        'head' => 'Slutredaktion. Tirsdag  kl. 17.30'
    );
    $set[] = array(
        'name' => 'second',
        'size' => 1,
        'type' => 'change',
        'head' => '2. udtræk Torsdag inden kl. 11.00'
    );
    $set[] = array(
        'name' => 'publish',
        'size' => 1,
        'type' => 'change',
        'head' => 'Udg.dato. Fredag'
    );
    $set[] = array(
        'name' => 'fromto',
        'size' => 2,
        'type' => 'text',
        'head' => 'Omfatter datoer (går fra fredag-torsdag)'
    );
    $_SESSION['setTable'] = $set;
    $calendar->setTable($set);
    $startweek = $year . "01";
    $days = $calendar->getDays($startweek);
    foreach ($days as $key => $oneDay) {
        break;
    }

    $width = 80;
    $caption = "DBF (+DPF) $year";
    $topHead = $calendar->getHead($oneDay, $width, $caption);
    $head = $calendar->getHead();
    $rowHTML = $calendar->toHtml($days);
    $page->set('width', $width);
    $page->set('head', $head);
    $page->set('rowHTML', $rowHTML);
    $page->set('topHead', $topHead);
    $page->set('head', $head);
    $page->set('display', 'default');
    $page->set('caption', $caption);
}

if ($order == 'dbfdpf2') {
    $set = array();
    $set[] = array(
        'name' => 'title',
        'size' => 2,
        'type' => 'text',
        'onClick' => 'canceledUncanceled($name,$key)',
        'dateformat' => 'oW',
        'head' => 'Titel',
        'before_text' => 'Dansk Bogfort.ugefort. '
    );
    $set[] = array(
        'name' => 'start',
        'size' => 1,
        'type' => 'change',
        'head' => 'Inddatering påbegyndes (ugekode skifter : DBF) Fredag',
        'class' => 'colbeige'
    );
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
        'name' => 'proof',
        'size' => 1,
        'type' => 'change',
        'head' => 'Udtræk til korrektur. Mandag kl. 15.00-16.00',
        'class' => 'colbeige'
    );
    $set[] = array(
        'name' => 'stop',
        'size' => 1,
        'type' => 'change',
        'head' => 'Slutredaktion. Tirsdag  kl. 17.30'
    );
    $set[] = array(
        'name' => 'second',
        'size' => 1,
        'type' => 'change',
        'head' => '2. udtræk Torsdag  kl. 9.00-10.00'
    );
    $set[] = array(
        'name' => 'publish',
        'size' => 1,
        'type' => 'change',
        'head' => 'Udg.dato. Fredag'
    );
    $set[] = array(
        'name' => 'fromto',
        'size' => 2,
        'type' => 'text',
        'head' => 'Omfatter datoer (går fra fredag-torsdag)'
    );
    $_SESSION['setTable'] = $set;
    $calendar->setTable($set);
    $startweek = $year . "04";
    $days = $calendar->getDays($startweek);

    $head = $calendar->getHead2();
    $rowHTML = $calendar->toHtml2($days);
    $page->set('head', $head);
    $page->set('rowHTML', $rowHTML);
    $page->set('display', 'default2');
}

if ($order == 'ratings') {
    $set = array();
    $set[] = array(
        'name' => 'title',
        'size' => 2,
        'type' => 'text',
        'head' => 'Titel',
        'before_text' => 'Materialevurderinger ',
        'dateformat' => 'o:W'
    );
    $set[] = array(
        'name' => 'netpunkt',
        'size' => 1,
        'type' => 'change',
        'head' => 'Redaktionslut samt publicering til Netpunkt i Data (torsdage)',
        'class' => 'colbeige'
    );
//    $set[] = array('name' => 'pdfproof', 'size' => 1, 'type' => 'change',
//        'head' => 'Udtræk til korrektur til PDF-fil (torsdag)');
    $set[] = array(
        'name' => 'newslist',
        'size' => 1,
        'type' => 'change',
        'type' => 'change',
        'head' => 'Publicering af Nyhedsliste'
    );
//    $set[] = array('name' => 'pdffile', 'size' => 1, 'type' => 'change',
//        'head' => 'PDF-fil Mandag');
    $_SESSION['setTable'] = $set;
    $calendar->setTable($set);
    $startweek = $year . "01";
    $days = $calendar->getDays($startweek);
    foreach ($days as $key => $oneDay) {
        break;
    }
    $width = 50;
    $caption = "Materialevurderinger $year";
    $topHead = $calendar->getHead($oneDay, $width, $caption);
    $head = $calendar->getHead();
    $rowHTML = $calendar->toHtml($days);
    $page->set('width', $width);
    $page->set('head', $head);
    $page->set('topHead', $topHead);
    $page->set('rowHTML', $rowHTML);
    $page->set('display', 'default');
    $page->set('caption', $caption);
}

if ($order == 'distribution') {
    $hd1 = "<p>Posterne normalt tilgængelige i Dataleverancer og</p>
            <p>Kombileverancer FREDAG i kalenderugen før leverancernes</p>
            <p>periodenummer.</p>";
    $hd2 = "<p>Fortegnelserne publiceres normalt FREDAG i</p>
            <p>kalenderugen før fortegnelsernes periodenummer.</p>
            <p>&nbsp;</p>";

    $htmlstrng =
        "<table>
    <thead>
    <tr>
        <td colspan='3' style='width: 600px;'>
            \$hdtxt
        </td>
    </tr>
    <tr>
        <td>
            <p></p>
            <p style='text-align: center;'>Ugenr.</p>
        </td>
        <td>
            <p></p>
            <p style='text-align: center;'>Dato</p>
        </td>
        <td>
        <td>
            <p></p>
            <p style='text-align: center;'>Undtagelse</p>
        </td>
    </tr>
    </thead>
    <tbody>
    ";

    $nxtweek = $year . '01';
    while (true) {
        $arr = $calendar->getDBFday($nxtweek);
        $date = $arr['date'];
        $thisweek = $nxtweek;
        $nxtweek = $arr['nxtweek'];
        if (substr($date, 0, 4) > $year) {
            break;
        }
        if (substr($date, 0, 4) != $year) {
            continue;
        }

        $dateInfo = '';
        $dInfo = explode(',', $arr['title']);
        if ($dInfo[0] != 'fredag') {
            $dateInfo = $dInfo[0];
        }
//        var_dump($thisweek);
//        var_dump($arr);
        $wc = substr($thisweek, 0, 4) . ':' . substr($thisweek, 4, 2);
        $d = substr($date, 6, 2) . '-' . substr($date, 4, 2) . '-' . substr($date, 0, 4);
        if ($arr['status'] == 'canceled') {
            $d = 'Udgår';
        }
        $htmlstrng .= " <tr>
          <tr>
            <td style=\"text-align: center;\">
              <p>$wc</p>
            </td>
            <td style=\"text-align: center; \">
              <p>$d</p>
            </td>
            <td style=\"text - align: center; \">
              <p>$dateInfo</p>
            </td>
          </tr>";
    }
    $htmlstrng .= "
          </tbody>
        </table>";
//    echo $htmlstrng;
    $page->set('display', 'distribution');
    $strng = str_replace('$hdtxt', $hd1, $htmlstrng);
    $page->set('html1', $strng);
    $strng = str_replace('$hdtxt', $hd2, $htmlstrng);
    $page->set('html2', $strng);
    $page->set('hd1', $hd1);
    $page->set('hd2', $hd2);
}

if ($order == 'istribution') {
    $set[] = array(
        'name' => 'title',
        'size' => 2,
        'type' => 'text',
        'dateformat' => 'o:W',
        'head' => 'Oversigt over udgivelsestidspunkter<br/> <br/>Ugenr'
    );
    $set[] = array(
        'name' => 'publish',
        'size' => 2,
        'type' => 'NoChange',
        'head' => '<br/><br/>Dato'
    );
    $_SESSION['setTable'] = $set;
    $calendar->setTable($set);
    $startweek = $year . "02";
    $days = $calendar->getDays($startweek);

    $head = $calendar->getHead(true);
    $rowHTML = $calendar->toHtml($days);
    $page->set('head', $head);
    $page->set('rowHTML', $rowHTML);
    $page->set('display', 'default');
}

if ($order == 'bkm') {
    $set = array();
    $set[] = array(
        'name' => 'title',
        'size' => 2,
        'type' => 'text',
        'head' => 'Titel',
        'before_text' => 'Bibliotekskat. mat. ',
        'dateformat' => 'oW'
    );
    $set[] = array(
        'name' => 'start',
        'size' => 1,
        'type' => 'NoChangeColor',
        'head' => 'Indatering påbegyndes ugekode skifter: Fredag',
        'class' => 'colbeige'
    );
    $set[] = array(
        'name' => 'close',
        'size' => 1,
        'type' => 'NoChangeColor',
        'head' => 'Reg.slut Fredag',
        'class' => 'colbeige'
    );
    $set[] = array(
        'name' => 'period',
        'size' => 1,
        'type' => 'text',
        'head' => 'Periode (katalogkode)',
        'before_text' => 'BKM'
    );
    $set[] = array(
        'name' => 'bookcar',
        'size' => 1,
        'type' => 'change',
        'head' => 'Bogvogns-gennemgang - mandag',
        'class' => 'colbeige'
    );
    $set[] = array(
        'name' => 'korr',
        'size' => 1,
        'type' => 'change',
        'head' => 'Korr. udtræk af de bib.kat. titler (bøger + lyd) onsdag kl. 8:00'
    );
    $set[] = array(
        'name' => 'editionstop',
        'size' => 1,
        'type' => 'change',
        'head' => 'Slutredaktion onsdag kl. 17:30'
    );
    $set[] = array(
        'name' => 'opd990',
        'size' => 1,
        'type' => 'change',
        'head' => '990 opd. onsdag kl. 18:00 =n51'
    );
    $set[] = array(
        'name' => 'publish',
        'size' => 1,
        'type' => 'NoChangeColor',
        'head' => 'Udg. dato. Fredag'
    );
    $_SESSION['setTable'] = $set;
    $calendar->setTable($set);
    $startweek = $year . "01";
    $days = $calendar->getDays($startweek);
    foreach ($days as $key => $oneDay) {
        break;
    }

    $head = $calendar->getHead();
    $rowHTML = $calendar->toHtml($days);

    $width = 90;
    $caption = "BKM $year";
    $topHead = $calendar->getHead($oneDay, $width, $caption);
    $page->set('width', $width);
    $page->set('topHead', $topHead);
    $page->set('head', $head);
    $page->set('rowHTML', $rowHTML);
    $page->set('display', 'default');
    $page->set('caption', $caption);
}

if ($order == 'frontpages') {
    $set = array();
    $set[] = array(
        'name' => 'calendarweek',
        'size' => 2,
        'type' => 'text',
        'dateformat' => 'o:W',
        'head' => 'Kalenderuge'
    );
    $set[] = array(
        'name' => 'bkmperiode',
        'size' => 1,
        'type' => 'weekChange',
        'head' => 'Periode (katalogkode)',
        'show' => 'NoCancel',
        'dateformat' => 'oW',
        'class' => 'colyellow',
        'before_text' => 'BKM'
    );
    $set[] = array(
        'name' => 'bkmextdate',
        'size' => 1,
        'type' => 'change',
        'head' => 'Udtræk torsdag (BKM)',
        'show' => 'noCancel',
        'class' => 'colyellow'
    );
    $set[] = array(
        'name' => 'sfgperiode',
        'size' => 1,
        'type' => 'weekChange',
        'head' => 'Periode (katalogkode)',
        'show' => 'NoCancel',
        'dateformat' => 'oW',
        'class' => 'colgrey',
        'before_text' => 'SFG'
    );
    $set[] = array(
        'name' => 'sfgextdate',
        'size' => 1,
        'type' => 'change',
        'head' => 'Udtræk mandag (SFG)',
        'show' => 'noCancel',
        'class' => 'colgrey'
    );
    $_SESSION['setTable'] = $set;
    $calendar->setTable($set);
    $startweek = $year . "01";
    $calendar->IncludeAllWeeks();
    $days = $calendar->getDays($startweek);
    foreach ($days as $key => $oneDay) {
        break;
    }

    $width = 50;
    $caption = "Forsideservice $year";
    $topHead = $calendar->getHead($oneDay, $width, $caption);
    $head = $calendar->getHead();
    $rowHTML = $calendar->toHtml($days);

    $page->set('width', $width);
    $page->set('head', $head);
    $page->set('topHead', $topHead);
    $page->set('rowHTML', $rowHTML);
    $page->set('display', 'default');
    $page->set('caption', $caption);
}

if ($order == 'changeYear') {
    $years = array();
    for ($i = 2015; $i < 2030; $i++) {
        $years[$i] = '';
    }
    $years[$year] = 'selected';
    $page->set('years', $years);
}


