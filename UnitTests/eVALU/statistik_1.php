<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc/";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/view.php";
require_once "$classes/mediadb_class.php";
include("phpgraphlib/phpgraphlib.php");

function getData($db, $status, $noOfWeeks) {
    $sql = "select date_trunc('week',createdate) as week,count(*) as count
        from mediaebooks
        where seqno in (
            SELECT distinct seqno
                 FROM mediaservicerecover
                    where status = '$status'
        )
   group by  week
   order by  week";
    $arr = $db->fetch($sql);
    $data = array();
    $dd = new DateTime();
    $dd->modify("-$noOfWeeks weeks");
    $minimumdate = date_format($dd, 'Y-m-d');

    foreach ($arr as $row) {
        $week = substr($row['week'], 0, 10);
        if ($week > $minimumdate) {
            $data[$week] = $row['count'];
        }
    }

//    echo "status:$status\n";
//    print_r($data);
    return $data;
}

function insert($template, $data) {
    $newdata = array();

    foreach ($template as $key => $val) {
        $newdata[$key] = 0;
    }
    foreach ($data as $key => $val) {
        $newdata[$key] = $val;
    }
    return $newdata;
}

$inifile = '../DigitalResources.ini';

$config = new inifile($inifile);
if ($config->error)
    die($config->error);

$connect_string = $config->get_value("connect", "setup");

//$tablename = $config->get_value("tablename", "pubhubMediaService");
//$reftable = $config->get_value("reftable", "pubhubMediaService");

$page = new view('html/statistik_1.phtml');

try {
    $db = new pg_database($connect_string);
    $db->open();

    $maxweeks = 15;
    $page->set('maxweeks', $maxweeks);
    $dataPen = getData($db, 'Pending', $maxweeks);
    $dataPMatch = insert($dataPen, getData($db, 'ProgramMatch', $maxweeks));
    $dataeVa = insert($dataPen, getData($db, 'eVa', $maxweeks));
    $dataeLu = insert($dataPen, getData($db, 'eLu', $maxweeks));
//    $dataTemp = insert($dataPen, getData($db, 'Template', $maxweeks));

    $graph = new PHPGraphLib(800, 800, "statistik.png");
//    $graph->addData($dataTemp);
    $graph->addData($dataPMatch);
    $graph->addData($dataeLu);
    $graph->addData($dataeVa);
    $graph->addData($dataPen);


//$graph->setBackgroundColor('white');
    //$graph->setTitle("De sidste $maxweeks uger");
//$graph->setTextColor("blue");
    $graph->setBarColor('#61b6d9', '#90ee90', '#f5f5dc', 'green');
    $graph->setLegend(true);
    $graph->setLegendTitle('ProgramMatch', 'eLu', 'eVa', 'Nye poster');
    $graph->createGraph();


} catch (Exception $err) {
    echo "The exception code is: " . $err->getCode() . "\n";
    echo $err . "\n";
    $err_txt = $err->getMessage();
    $getLine = "Line  number:" . $err->getLine();
    $getTrace = $err->getTraceAsString();
//    mail($err_mail_to, "Exception utc-process.php", "----------------------\n$err_txt\n---------------------\n
//      $getLine\n$getTrace\n");
    exit;
}

