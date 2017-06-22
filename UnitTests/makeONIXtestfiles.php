<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 04-10-2016
 * Time: 12:29
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";


require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
//require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$startdir/XmlDiff_class.php";
//require_once "$classes/mediadb_class.php";
require_once "$classes/soapClient_class.php";
require_once "$classes/ONIX_class.php";
//require_once "$classes/dmat_db_class.php";
require_once "$classes/pubhubFtp_class.php";
//require_once "$classes/epub_class.php";

putenv('http_proxy');


function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-f xml data file (use this file as input/output)\n";
    echo "\t-t use test database ([testcase]connect) \n";
    echo "\t-r read from file (default: product.xml)\n";
    echo "\t-n nopthing happens ( no update in the database) \n";
    echo "\t-h help (shows this message)\n";
    exit(100);
}


$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('x' => true, 'r' => true, 'f' => 'products.xml');
//    $options = array('f' => 'pubhubMediaService.xml');
} else {
    $options = getopt("hp:nf:tr");
}

//$aa = getcwd();

if (array_key_exists('h', $options)) {
    usage();
}
if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}

$datafile = 'products.xml';
if (array_key_exists('f', $options)) {
    $datafile = $options['f'];
}

$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
}
if (array_key_exists('n', $options)) {
    $nothing = true;
}

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error);
}


$soapClient = new soapClient_class($config, 'ONIXpubhub');
if ($er = $soapClient->getError()) {
    usage($er);
}

if (array_key_exists('r', $options)) {
    $soapClient->setReadFromFile();
}

if (($ftp_server = $config->get_value('pubhub_ftp_server', 'setup')) == false)
    die("no pubhub_ftp_server stated in the configuration file");

if (($ftp_user = $config->get_value('pubhub_ftp_user', 'setup')) == false)
    die("no pubhub_ftp_user stated in the configuration file");

if (($ftp_pass = $config->get_value('pubhub_ftp_passwd', 'setup')) == false)
    die("no pubhub_ftp_passwd stated in the configuration file");

if (($workdir = $config->get_value('workdir', 'setup')) == false)
    die("no [setup] workdir stated in the configuration file");

$pubhubFtp = new pubhubFtp($ftp_server, $ftp_user, $ftp_pass, $workdir);

$files['products.01.xml'][] = '9788711321706';
$files['products.01.xml'][] = '9788773326626';
$files['products.01.xml'][] = '9788793098268';
$files['products.01.xml'][] = '9788779346321';
$files['products.01.xml'][] = '9788764505481';
$files['products.01.xml'][] = '9788771622003';
$files['products.01.xml'][] = '9788711349755';
$files['products.01.xml'][] = '9788711378076';
$files['products.01.xml'][] = '9788792241801';
$files['products.01.xml'][] = '9788774575375';
$files['products.01.xml'][] = '9788771289787';
$files['products.01.xml'][] = '9788711354759';
$files['products.01.xml'][] = '9788776917371';
$files['products.01.xml'][] = '9788711406267';
$files['products.01.xml'][] = '9788792922106';
$files['products.01.xml'][] = '9788771246292';
$files['products.01.xml'][] = '9788711394908';
$files['products.01.xml'][] = '9788793308251';
$files['products.01.xml'][] = '9788758812120';
$files['products.01.xml'][] = '9788758813356';
$files['products.01.xml'][] = '9788711437155';
$files['products.01.xml'][] = '9788758826509';
$chng['products.01.xml']['x9788773326626'][] = '<Date>20100507</Date>:<Date>30100507</Date>';

$files['products.02.xml'][] = '9788711321706';
$files['products.02.xml'][] = '9788773326626';
$files['products.02.xml'][] = '9788793098268';
$files['products.02.xml'][] = '9788779346321';
$files['products.02.xml'][] = '9788764505481';
$files['products.02.xml'][] = '9788771622003';
$files['products.02.xml'][] = '9788711349755';
$files['products.02.xml'][] = '9788711378076';
$files['products.02.xml'][] = '9788792241801';
$files['products.02.xml'][] = '9788774575375';
$files['products.02.xml'][] = '9788771289787';
$files['products.02.xml'][] = '9788711354759';
$files['products.02.xml'][] = '9788776917371';
$files['products.02.xml'][] = '9788711406267';
$files['products.02.xml'][] = '9788792922106';
$files['products.02.xml'][] = '9788771246292';
$files['products.02.xml'][] = '9788711394908';
$files['products.02.xml'][] = '9788793308251';
$files['products.02.xml'][] = '9788758812120';
$files['products.02.xml'][] = '9788758813356';
$files['products.02.xml'][] = '9788711437155';
$chng['products.02.xml']['x9788758812120'][] = '<NotificationType>03</NotificationType>:<NotificationType>05</NotificationType>';
$chng['products.02.xml']['x9788758812120'][] = 'datestamp="2:datestamp="3';
$chng['products.02.xml']['x9788773326626'][] = 'datestamp="2:datestamp="3';
$chng['products.02.xml']['x9788779346321'][] = 'datestamp="2:datestamp="3';
//$chng['products.02.xml']['x9788773326626'] = 'datestamp="2:datestamp="3';
//$chng['products.02.xml']['x9788773326626'] = 'datestamp="2:datestamp="3';
//$chng['products.02.xml']['x9788773326626'] = 'datestamp="2:datestamp="3';

$files['products.03.xml'][] = '9788711321706';
$files['products.03.xml'][] = '9788773326626';
$files['products.03.xml'][] = '9788793098268';
$files['products.03.xml'][] = '9788779346321';
$files['products.03.xml'][] = '9788764505481';
$files['products.03.xml'][] = '9788771622003';
$files['products.03.xml'][] = '9788711349755';
$files['products.03.xml'][] = '9788711378076';
$files['products.03.xml'][] = '9788792241801';
$files['products.03.xml'][] = '9788774575375';
$files['products.03.xml'][] = '9788771289787';
$files['products.03.xml'][] = '9788711354759';
$files['products.03.xml'][] = '9788776917371';
$files['products.03.xml'][] = '9788711406267';
$files['products.03.xml'][] = '9788792922106';
$files['products.03.xml'][] = '9788771246292';
$files['products.03.xml'][] = '9788711394908';
$files['products.03.xml'][] = '9788793308251';
$files['products.03.xml'][] = '9788758812120';
$files['products.03.xml'][] = '9788758813356';
$files['products.03.xml'][] = '9788711483084';
$files['products.03.xml'][] = '9788793059160';
$files['products.03.xml'][] = '9788702216257';

$chng['products.03.xml']['x9788773326626'][] = '<Date>20100507</Date>:<Date>30100507</Date>';
$chng['products.03.xml']['x9788773326626'][] = 'datestamp="2:datestamp="3';
$chng['products.03.xml']['x9788764505481'][] = 'datestamp="2:datestamp="3';
$chng['products.03.xml']['x9788771622003'][] = 'datestamp="2:datestamp="3';
$chng['products.03.xml']['x9788771622003'][] = '<NotificationType>03</NotificationType>:<NotificationType>05</NotificationType>';
$chng['products.03.xml']['x9788758812120'][] = 'datestamp="2:datestamp="3';


$isb = array();
$i = 0;
foreach ($files as $filename => $isbns) {
    foreach ($isbns as $key => $isbn) {
        $isb['x' . $isbn][$i]['filename'] = $filename;
        $isb['x' . $isbn][$i]['noinfile'] = $key;
        $i++;
    }
}

try {
    $xml = $soapClient->soapClient($datafile);
    $output = array();
    $ONIX = new ONIX();
//    $ONIX->load_xml($xml);
//    $ONIX->startFile();
    $ONIX->fastStart($xml);
    $i = 0;
//    while (($info = $ONIX->getNext())) {
    while (($info = $ONIX->fastNext())) {
        $isbn = 'x' . $info['isbn13'];
        if (array_key_exists($isbn, $isb)) {
            $xml = $ONIX->getXml();
            foreach ($isb[$isbn] as $key => $filename) {
                $output[$filename['filename']][$filename['noinfile']] = $xml;
//                $fp = fopen($filename, 'a');
//                fwrite($fp, $xml);
//                fclose($fp);
            }
        }
        $i++;
        if ($i == 100) {
            $i = 0;
            $cnt = $ONIX->getCnt();
            echo "Behandlet $cnt -- " . mb_strcut($info['title']['text'], 0, 99, "UTF-8") . "\n";
        }
    }

    $head = $ONIX->getHead();
    $foot = $ONIX->getTail();
    foreach ($output as $filename => $files) {
        $fp = fopen($filename, 'w');
        fwrite($fp, $head);
        ksort($files);
        $xtag = '<?xml ersion="1.0"?>';
        foreach ($files as $xml) {
            $pos = strpos($xml, $xtag);
            $xml = substr($xml, $pos + strlen($xtag) + 2);
            $pos = strpos($xml, '<IDValue>');
            $isbn = 'x' . substr($xml, $pos + 9, 13);
            if (array_key_exists($filename, $chng)) {
                if (array_key_exists($isbn, $chng[$filename])) {
                    foreach ($chng[$filename][$isbn] as $ch) {
                        $arr = explode(':', $ch);
                        $xml = str_replace($arr[0], $arr[1], $xml);
                    }
                }
            }
            fwrite($fp, $xml);
        }
        fwrite($fp, $foot);
        fclose($fp);
    }

    $cnt = $ONIX->getCnt();
    echo "Behandlet $cnt Product(s)\n";

} catch (Exception $e) {
    verbose::log(ERROR, "ID:($cnt)$id, e:" . $e);
    echo $e . "\n";
    echo "Number in file:$cnt, Record Identifier:$id\n---------------------------------\n";
    echo $xml . "\n";
    exit(1);
} catch (DOMException $DOMe) {
    verbose::log(ERROR, " DOMe:" . $DOMe);
    echo "DOMe: " . $DOMe . "\n";
    exit(4);
}