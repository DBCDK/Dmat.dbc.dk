<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$classes/mediadb_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OPAC_class_lib/GetMarcFromBasis_class.php";
require_once "$classes/mediadb_class.php";
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "") {
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-f inputfile (iso2709 dump)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/..//DigitalResources.ini";

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('f' => 'phusPoster.iso');
} else {
    $options = getopt("hp:f:");
}
//$options = getopt("hp:f:");
if (array_key_exists('h', $options)) {
    usage();
}
if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}

$filename = 'phusPoster.iso';
if (array_key_exists('f', $options)) {
    $filename = $options['f'];
}


// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);


$connect_string = $config->get_value("connect", "setup");
$getMarcFromBasis = new GetMarcFromBasis('work', 'sebasis', 'sebasis', 'dora11.dbc.dk');

$db = new pg_database($connect_string);
$db->open();

$md = new mediadb($db, 'findRecsThereIsInDmat', false);

$Basismarc = new marc();
$marc = new marc();
$cnt = 0;

$marc->OpenMarcFile($filename);
while ($marc->readNextMarc()) {
    $cnt++;
    if ($res = $marc->findSubFields('021', 'e')) {
        foreach ($res as $isbn) {
            $sql = "select e.seqno as seqno, status, faust, newfaust from mediaebooks e, mediaservice s "
                . "where isbn13 = '$isbn' "
//                . "and active not in ('NotActive','DigitalR') "
                // . "and status not in ('Drop','Afventer') "
                . "and e.seqno = s.seqno ";
            $rows = $db->fetch($sql);

            if ($rows) {
                $strngPhus = $marc->toLineFormat();
                foreach ($rows as $row) {
                    $status = $row['status'];
                    $seqno = $row['seqno'];
                    $faust = $row['faust'];
                    $newfaust = $row['newfaust'];
//                    echo "($seqno)isbn:$isbn, status:$status, faust:$faust, newfaust:$newfaust ";
                    // posten findes som dmat
                    //findes den i basis?
//                    $Basismarc = $getMarcFromBasis->getMarc('9788711477281');
                    $recs = $getMarcFromBasis->getMarc($isbn);
                    if ($recs) {
                        $Basismarc->fromIso($recs[0]['DATA']);
                        $strng = $Basismarc->toLineFormat();
//                        echo " posten findes i Basis";
//                        echo $marc->toLineFormat() . "\n";

                    }

                    if ($status == 'DigtalR' or $status == 'matchDigitalR' or $status == 'OldTemplate') {
                        $md->newStatus($seqno, 'Pending');
//                        echo "Update status\n";
                    }
                    if ($status == 'matchDigitalR' or $status == 'OldTemplate' or $status = 'Done' or $status = 'Pending') {
                        echo "$strngPhus";
                    }
//                    echo "\n";
                }
            } else {
//                echo "Ikke fundet: $isbn\n";
//                echo "\n$strngPhus\n";
            }
        }
    }
//    echo "cnt:$cnt\n";
}

?>