<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


/**
 * @file ToBasis.php
 * @brief Convert eLib xml to danmarc2 and sent the marc-records to BASIS.
 * Convert the xml from publizons "elib" API to danmarc2 iso-2709 format.
 * The converted file is sent to dbc-cat/BASIS.
 * The mard-records will later be updated by the cataloguer.
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

//require_once "makeMarc.php";
require_once "$inclnk/OPAC_class_lib/ConvertXMLtoMarc_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$inclnk/OLS_class_lib/weekcode_class.php";
require_once "$classes/merge_class.php";

/**
 *
 * @param type $seqno
 */
function getFormatFromXml($db, $cxtm, $seqno)
{
    global $tablename;
    $sql = "
                  select originalXML as xml, status from $tablename
                    where
                       seqno = $seqno
                       and provider = 'Pubhub'
                ";
//            echo "$sql";
    $arr = $db->fetch($sql);
    $from = array("\n", '&#x2013;', '&#x201C;', '&#x201D;', '&#x2019;', '&#x2026;');
    $to = array("  ", '-', '"', '"', "'", "...");
    $xml = str_replace($from, $to, $arr[0]['xml']);
    $cxtm->loadXML($xml);
    $ft = $cxtm->getFormatType();
    return $ft;
}

/**
 * This function takes the data files ($datafile[$destination] and transfer the
 * via FTP to "dbcposthus.dbc.dk/datain"
 *
 * @global type $nothing - when true no update of the database or sending of files via FTP
 * @param type $config - the config (ini file) pointer
 * @param type $destination -  PubHubPhus or PubHubBasis - determin which database to sent to.
 * @param type $datafile - an array of datafiles - indexed by $destination
 * @param type $workdir - where to put temporary files
 * @param type $ts - ts, so alle files got the same timestamp
 */
function ftp_transfer($config, $destination, $datafile, $workdir, $ts)
{
    global $nothing;

    if (($transline = $config->get_value('transline', $destination)) == false)
        usage("no transline stated in the configuration file ($destination)");
    if (($transfile = $workdir . "/" . $config->get_value('transfile', $destination)) == false)
        usage("no transfile stated in the configuration file ($destination)");

    $transfile = str_replace('$ts', $ts, $transfile);
    $transline = str_replace('$datafile', basename($datafile[$destination]), $transline);

    if (!$fptrans = fopen($transfile, "w")) {
        echo "couldn't open the file:" . $transfile . " for writing\n";
        verbose::log(ERROR, "couldn't open the file:" . $transfile . " for writing");
        exit(3);
    }
    fwrite($fptrans, $transline);
    fwrite($fptrans, "\nslut\n");
    fclose($fptrans);

    $ftp_server = $config->get_value('ftp_server', 'setup');
    $ftp_user_name = $config->get_value('ftp_user_name', 'setup');
    $ftp_user_pass = $config->get_value('ftp_user_pass', 'setup');

    if ($nothing) {
        echo "nothing: No ftp transfer\n";
    } else {

// send the file using FTP
// set up basic connection
        if (!$conn_id = ftp_connect($ftp_server)) {
            verbose::log(ERROR, "FTP connection has failed ($ftp_server)");
            die("FTP connection has failed ($ftp_server)!\n");
        }
// login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);

// check connection
        if ((!$conn_id) || (!$login_result)) {
            verbose::log(ERROR, "FTP connection has failed ($ftp_server,$ftp_user_name,$ftp_user_pass)!");
            die("FTP connection has failed ($ftp_server,$ftp_user_name,$ftp_user_pass)!");
        }

// try to change the directory to datain
        if (!ftp_chdir($conn_id, "datain")) {
            echo "Couldn't change directory to datain\n";
            verbose::log(ERROR, "Couldn't change directory to datain");
            exit(5);
        }
        if (!ftp_put($conn_id, basename($datafile[$destination]), $datafile[$destination], FTP_BINARY)) {
            echo "There was a problem while uploading " . $datafile[$destination] . "\n";
            verbose::log(ERROR, "There was a problem while uploading " . $datafile[$destination]);
            exit(6);
        }
        if (!ftp_put($conn_id, basename($transfile), $transfile, FTP_BINARY)) {
            echo "There was a problem while uploading $transfile\n";
            verbose::log(ERROR, "There was a problem while uploading $transfile");
            exit(6);
        }

// close the connection
        ftp_close($conn_id);

        unlink($transfile);
        unlink($datafile[$destination]);
    }
}

/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "")
{
    global $argv, $inifile;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-f bases (ebib,Netlydbog,eReolen) \n";
    echo "\t-w numbers-of-weeks PRODUCTION \n\t\t(if stated the week-code will be the current week-code + numbers-of-week, \n\t\tif not stated the week-code will be current week + 1 (only for basis updates))\n";
    echo "\t-W numbers-of-weeks DAT \n\t\t(if stated the DAT code will be the current week-code + 4 days + numbers-of-week, \n\t\tif not stated the week-code will be current week + 4 days + 1 (only for basis updates))\n";
    echo "\t-T total - all records (ignoring sent_to_basis)\n";
    echo "\t-s seqno - make a marc-record from this seqno\n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = FALSE;
$allRecords = false;
//$configsection = "eLibToBasis";
$Phus = 'PubHubToPhus';
$Basis = 'PubHubToBasis';
$seqno = false;

if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('s' => 60953, 'n' => 'nothing', 'f' => 'Netlydbog,NetlydbogLicens,eReolen,eReolenLicens,Deff');
//    $options = array('s' => 55860, 'n' => 'nothing', 'f' => 'Netlydbog,NetlydbogLicens,eReolen,eReolenLicens');
} else {
    $options = getopt("hp:nf:w:W:Ts:");
}

if (array_key_exists('s', $options)) {
    $seqno = $options['s'];
}
if (array_key_exists('h', $options))
    usage();

if (array_key_exists('n', $options))
    $nothing = true;

if (array_key_exists('T', $options))
    $allRecords = true;

if (array_key_exists('p', $options))
    $inifile = $options['p'];

if (!array_key_exists('f', $options) && !$seqno) {
    usage('bases is missing');
}
$foption = $options['f'];
$arr = explode(',', $foption);
$formats = "";
foreach ($arr as $format) {
    $formats .= "'" . $format . "',";
}
$formats = trim($formats, ',');

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");

if (($Bociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser (seBasis) stated in the configuration file");
if (($Bocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd (seBasis) stated in the configuration file");
if (($Bocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase (seBasis) stated in the configuration file");

if (($Pociuser = $config->get_value('ociuser', 'sePhus')) == false)
    usage("no ociuser (sePhus) stated in the configuration file");
if (($Pocipasswd = $config->get_value('ocipasswd', 'sePhus')) == false)
    usage("no ocipasswd (sePhus)stated in the configuration file");
if (($Pocidatabase = $config->get_value('ocidatabase', 'sePhus')) == false)
    usage("no ocidatabase (sePhus) stated in the configuration file");

if (($workdir = $config->get_value('workdir', 'setup')) == false)
    usage("no workdir stated in the configuration file");

$datafile = array();
if (($datafile[$Basis] = $workdir . "/" . $config->get_value('datafile', $Basis)) == false)
    usage("no datafile stated in the configuration file ($Basis)");

if (($datafile[$Phus] = $workdir . "/" . $config->get_value('datafile', $Phus)) == false) {
    usage("no datafile stated in the configuration file ($Phus)");
}

$ts = date('YmdHi');
$datafile[$Basis] = str_replace('$ts', $ts, $datafile[$Basis]);
$datafile[$Phus] = str_replace('$ts', $ts, $datafile[$Phus]);

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");

$cases = array(1 => 0, 1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0, 6 => 0, 'force' => 0);

try {
    $tablename = "digitalresources";
//  $tablename = "diff";

    $cxtm = new ConvertXMLtoMarc();

    $libV3API = new LibV3API($Bociuser, $Bocipasswd, $Bocidatabase);
    $PhuslibV3API = new LibV3API($Pociuser, $Pocipasswd, $Pocidatabase);
    $merge = new merge_class();

    $connect_string = $config->get_value("connect", "setup");

    $marcBasis = new marc();
    $marcPhus = new marc();
    $marcFromXml = new marc();
//  echo "connect:$connect_string\n";
    $db = new pg_database($connect_string);
    $db->open();

    $wc = new weekcode($db);
    $orgweekcode = $wc->getweekcode();
    $orgdatcode = $wc->getDatWeekcode();

    $fourdays = 4 * 24 * 60 * 60;
    $sevendays = 7 * 24 * 60 * 60;
    if (array_key_exists('w', $options)) {
        $numbers = $options['w'];
        $sec = (60 * 60 * 24 * 7) * $numbers;
        $orgweekcode = date('YW', time() + $sevendays + $sec);
    }
    if (array_key_exists('W', $options)) {
        $numbers = $options['W'];
        $sec = (60 * 60 * 24 * 7) * $numbers;
        $orgdatcode = date('YW', time() + $fourdays + $sec);
    }

//    echo "orgweekcoe:$orgweekcode\n";
//    echo "orgdatcode:$orgdatcode\n";
//    exit;

// this command does that we can make a rollback - no commit is done until
// the commit command is executed
//    $db->exe('START TRANSACTION');

    $cnt = $gencnt = $updcnt = $igncnt = $delcnt = 0;
    if ($allRecords)
        $toBasisNot = "not"; else
        $toBasisNot = "";

    if ($seqno) {
        $sql = "select seqno, format, faust, updated, status " . "from $tablename " . "where seqno = $seqno";
    } else {
        $sql = "select seqno, format, faust, updated, status " . "from $tablename " . "where sent_to_basis is $toBasisNot null " . "and provider = 'Pubhub' " . "and format in ($formats) " . "and faust is not null " . "and left(faust,2) != 'd:' " . "union " . "select seqno, format, faust, updated, status " . "from $tablename " . "where (faust) in " . "(select faust from $tablename " . "where sent_to_basis is $toBasisNot null " . "and provider = 'Pubhub' " . "and format in ($formats) " . "and faust is not null " . "and left(faust,2) != 'd:') " . "and sent_to_basis is $toBasisNot null " . "and provider = 'Pubhub' " . "and format in ($formats) " . "order by status, seqno desc";
    }
    $rows = $db->fetch($sql);

    // remove dublleter fra poster med både epub og pdf - tag kun den nyeste.
    $buf = $seqnumbers = array();
    if ($rows) {
        foreach ($rows as $row) {
            if (array_key_exists($row['format'], $buf)) {
                if (array_key_exists($row['faust'], $buf[$row['format']])) {
                    $status1 = $buf[$row['format']][$row['faust']]['status'];
                    $status2 = $row['status'];
                    if ($status1 == 'n' && $status2 == 'n') {
                        $ebookformat1 = getFormatFromXml($db, $cxtm, $buf[$row['format']][$row['faust']]['seqno']);
//                        $ebookformat2 = getFormatFromXml($db, $cxtm, $row['seqno']);
                        if ($ebookformat1 == 'epub') {
                            $row['seqno'] = $buf[$row['format']][$row['faust']]['seqno'];
                        }
                    }
                }
            }
            $buf[$row['format']][$row['faust']] = array('seqno' => $row['seqno'], 'status' => $row['status']);
        }
        foreach ($buf as $fausts) {
            foreach ($fausts as $row) {
                $seqnumbers[] = $row;
            }
        }
    }

    $totalMarcRecords = array();

    if ($seqnumbers) {

        for ($seqnum = 0; $seqnum < count($seqnumbers); $seqnum++) {

            $seqno = $seqnumbers[$seqnum]['seqno'];
            $sql = "
                  select originalXML, faust , isbn13, status, format, costfree from $tablename
                    where
                       seqno = $seqno
                       and format in ($formats)
                ";
//            echo "$sql";
            $arr = $db->fetch($sql);
            $faust = $arr[0]['faust'];
            $status = $arr[0]['status'];
            $orgformat = $arr[0]['format'];
            $costfree = $arr[0]['costfree'];
            $isbn13 = $arr[0]['isbn13'];
            if ($nothing) {
                echo "---------------------------------------------------------------\n";
                echo "faust:$faust, status:$status, format:$orgformat, isbn:$isbn13\n";
                echo "---------------------------------------------------------------\n";
            }
            $xmltype = "unknown";
            $weekLetterCode = "";
            switch ($orgformat) {
                case "eReolenLicens":
                    $xmltype = "eReolenLicensPhus";
                    $weekLetterCode = "ERL";
                    $urlPart = 'ereolen.dk';
                    break;
//                case "eReolenKlik":
//                    $xmltype = "eReolenKlikPhus";
//                    $weekLetterCode = "ERE";
//                    $urlPart = 'ereolen.dk';
//                    break;
                case "eReolen":
                    $xmltype = "eReolenPhus";
                    $weekLetterCode = "ERE";
                    $urlPart = 'ereolen.dk';
                    break;
                case "Netlydbog":
                    $xmltype = "NetlydbogPhus";
                    $weekLetterCode = "NLY";
                    $urlPart = 'Netlydbog.dk';
                    break;
                case "NetlydbogLicens":
                    $xmltype = "NetlydbogLicensPhus";
                    $weekLetterCode = "NLL";
                    $urlPart = 'Netlydbog.dk';
                    break;
//                case "ebib":
//                    $xmltype = "ebibPhus";
//                    $weekLetterCode = "EBI";
//                    $urlPart = 'www.ebib.dk';
//                    break;
                case "Deff":
                    $xmltype = "DeffPhus";
                    $weekLetterCode = "non";
                    $urlPart = "deff.dk";
            }
//            verbose::log(TRACE, '---------------------------------------------------------');
//            verbose::log(TRACE, "**** New record: isbn:$isbn13, faust:$faust, status:$status, orgformat:$orgformat, xmltype:$xmltype");

            $cnt++;

            if (count($arr[0]['originalxml'])) {
// in the desctiption element, there is some UTF-8 encoding that we have to deal with manually
                $from = array("\n", '&#x2013;', '&#x201C;', '&#x201D;', '&#x2019;', '&#x2026;');
                $to = array("  ", '-', '"', '"', "'", "...");
                $xml = str_replace($from, $to, $arr[0]['originalxml']);
                verbose::log(DEBUG, "xml:$xml");
                $cxtm->loadXML($xml);
                $isomarc = $cxtm->Convert2Marc($faust, 'n', $xmltype);
                $marcFromXml->fromIso($isomarc);
                $strng = $marcFromXml->toLineFormat(78);
                verbose::log(DEBUG, "marc from xml:\n$strng");
            }

            if (array_key_exists($faust, $totalMarcRecords)) {
//          $marcRecords[0]['DATA'] = $totalMarcRecords[$faust]['isomarc'];
            } else {
                $marcRecords = $libV3API->getMarcByLokalidBibliotek($faust, '870970');
                if (count($marcRecords) == 1) {
                    $totalMarcRecords[$faust]['orgmarc'] = $marcRecords[0]['DATA'];
                    $marcRecords[0]['DATA'] = $merge->clean($marcRecords[0]['DATA']);
                    if (substr($marcRecords[0]['DATA'], 5, 1) != 'd') {
                        $totalMarcRecords[$faust]['marc'] = $marcRecords[0]['DATA'];
                        $totalMarcRecords[$faust]['destination'] = $Basis;
                    } else {
                        $totalMarcRecords[$faust]['marc'] = $isomarc;
                        $totalMarcRecords[$faust]['destination'] = $Phus;
                    }
                } else {
                    /*  hent fra phus */
                    $marcRecords = $PhuslibV3API->getMarcByLokalidBibliotek($faust, '870970');
                    if (count($marcRecords) == 1) {
                        $totalMarcRecords[$faust]['orgmarc'] = $marcRecords[0]['DATA'];
                        $marcRecords[0]['DATA'] = $merge->clean($marcRecords[0]['DATA']);
//                        if (substr($marcRecords[0]['DATA'], 5, 1) != 'd') {
                        if (substr($marcRecords[0]['DATA'], 5, 1) == 'x') {

                            $totalMarcRecords[$faust]['marc'] = $marcRecords[0]['DATA'];
                            $totalMarcRecords[$faust]['destination'] = $Phus;
                        } else {
                            $totalMarcRecords[$faust]['marc'] = $isomarc;
                            $totalMarcRecords[$faust]['destination'] = $Phus;
//                            $totalMarcRecords[$faust]['marc'] = $isomarc;
//                            $totalMarcRecords[$faust]['destination'] = $Phus;
//                            $totalMarcRecords[$faust]['orgmarc'] = "empty";
                        }
                    } else {
                        // her sender vi poster til Phus - det vil vi så ikke mere da vi vil kun sende
                        // netlydbog videre.
//                        Ikke nødvendigt da jeg i stedet har slået insertFaust.php fra.
//                        Så kommer der ikke "nye" poster ind i systemet.  Der skal så køre en 
//                        UpdateFaustFromBasis.php + ToBasis.php hver time.

//                        if ($weekLetterCode == 'ERE' or $weekLetterCode == 'ERL') {
//
//                            continue;
//                        }
                        $totalMarcRecords[$faust]['marc'] = $isomarc;
                        $totalMarcRecords[$faust]['destination'] = $Phus;
                        $totalMarcRecords[$faust]['orgmarc'] = "empty";

                    }
//                    print_r($totalMarcRecords);
                }
            }

            $totalMarcRecords[$faust]['updated'] = false;
            if (count($marcRecords) > 1) {
                verbose::log(ERROR, "There are to many records in basis with this faust number:[$faust]");
                continue;
            }

            $marcBasis->fromIso($totalMarcRecords[$faust]['marc']);
            $strng = $marcBasis->toLineFormat();
            verbose::log(DEBUG, "marc from basis:\n$strng");
            if ($nothing) {
//                $strng = $marcBasis->toLineFormat();
                echo "$strng\n";
            }

            $totalMarcRecords[$faust]['Drop'] = false;
            if ($totalMarcRecords[$faust]['destination'] == $Phus) {

                // er det en "Drop" post?
                $sql = "select s.status as status from mediaservice s, mediaebooks e
                          where s.seqno = e.seqno
                          and isbn13 = '$isbn13' ";
                $dropOrNot = $db->fetch($sql);
                if ($dropOrNot) {
                    if ($dropOrNot[0]['status'] == 'Drop' or $dropOrNot[0]['status'] == 'kunEreol') {
                        $totalMarcRecords[$faust]['destination'] = $Basis;
                        $totalMarcRecords[$faust]['Drop'] = true;
                    } else {
                        $wcode = '999999';
                        $dcode = '999999';
                    }
                } else {
                    // posten skal der tages stilling til.
                    $strng = $marcBasis->toLineFormat();
                    $message = "Der er blevet lagt en post i Phuset, som ikke findes i Dmat\n" . "\n$strng\n";
                    mail('hhl@dbc.dk', 'Post i PHUSET', $message);

//                    $totalMarcRecords[$faust]['destination'] = $Basis;
//                    $totalMarcRecords[$faust]['Drop'] = true;
                }
            }
            if ($totalMarcRecords[$faust]['destination'] != $Phus) {
                $wcode = $orgweekcode;
                $dcode = $orgdatcode;
//                $alterWcode = false;
//
//                while ($marcBasis->thisField('652')) {
//                    while ($marcBasis->thisSubfield('m')) {
//                        if ($marcBasis->subfield() == 'NY TITEL')
//                            $alterWcode = true;
//                    }
//                }
                // se hjemmesiden http://wiki.dbc.dk/bin/view/Data/AendringsForslag
                // for baggrunden til nedenstående kode.
                $DBF_DLF_BKM = false;
                $W999999 = false;
//                $BKM = '';
                $MAX = '000000';
                while ($marcBasis->thisField('032')) {
                    while ($marcBasis->thisSubfield('a')) {
                        $code = substr($marcBasis->subfield(), 0, 3);
                        if ($code == 'DBF' or $code == 'DLF') {
                            if (substr($marcBasis->subfield(), 3, 2) != '99') {
                                $DBF_DLF_BKM = true;
                                if (substr($marcBasis->subfield(), 3) > $MAX) {
                                    $MAX = substr($marcBasis->subfield(), 3);
                                }
                            } else {
                                $W999999 = true;
                            }
                        }
                    }
                    while ($marcBasis->thisSubfield('x')) {
                        $code = substr($marcBasis->subfield(), 0, 3);
                        if ($code == 'BKM') {
                            if (substr($marcBasis->subfield(), 3, 2) != '99') {
                                $DBF_DLF_BKM = true;
                                if (substr($marcBasis->subfield(), 3) > $MAX) {
                                    $MAX = substr($marcBasis->subfield(), 3);
                                }
                            } else {
                                $W999999 = true;
                            }
                        }
                    }
                }
                $drop = $totalMarcRecords[$faust]['Drop'];
                if ($W999999) {
                    $wcode = '999999';
                } else {
                    if ($DBF_DLF_BKM) {
                        if ($MAX > $wcode) {
                            $wcode = $MAX;
                        }
                    } else {
                        if (!$drop) {
                            $wcode = '999999';
                        }
                    }
                }
            }

            if (!$costfree) {
                $costfree = 'false';
            }
            if ($status == 'n') {
                $returnMerge = $merge->merge($marcBasis, $marcFromXml, $orgformat, $weekLetterCode, $urlPart, $wcode, $dcode, $costfree, $drop);
            } else {
                $returnMerge = $merge->removeData($marcBasis, $marcFromXml, $weekLetterCode, $urlPart, $orgformat, $wcode, $dcode, $costfree, $drop);
            }
            if ($returnMerge['updated']) {
                $totalMarcRecords[$faust]['updated'] = true;
                $marcBasis = $returnMerge['marc'];
                $totalMarcRecords[$faust]['marc'] = $marcBasis->toIso();

                if ($nothing) {
                    echo "updated:" . $totalMarcRecords[$faust]['destination'] . "\n";
                    $marcBasis->fromIso($totalMarcRecords[$faust]['marc']);
                    $strng = $marcBasis->toLineFormat();
                    echo "$strng\n";
                }
            } else {
                if ($nothing) {
                    echo "No update of this record\n";
                }
            }
            $sql = "
              update $tablename set sent_to_basis = current_timestamp
                 where seqno = $seqno
           ";
            if ($nothing) {
                echo "sql:$sql\n";
            } else {
                verbose::log(DEBUG, "sql:$sql");
                $db->exe($sql);
            }
        }
    }


    $cnt = array();
    $cnt[$Phus] = 0;
    $cnt[$Basis] = 0;
    if (count($totalMarcRecords)) {
        foreach ($totalMarcRecords as $tmarc) {
//      $updated = $tmarc['updated'];
//      if (!$updated)
//        continue;
            $PhusOrBasis = $tmarc['destination'];
            $isomarc = $tmarc['marc'];
            $org = $tmarc['orgmarc'];
//            echo $org;
            if ($PhusOrBasis == $Phus) {
                $isomarc = $merge->afterPhus($isomarc);
            }
            $Drop = $tmarc['Drop'];
            $isomarc = $merge->AfterAll($isomarc, $Drop, $wcode);
            $mm = new marc();
            $mm->fromIso($isomarc);
            $ss = $mm->toLineFormat();

            $orgmarc = $tmarc['orgmarc'];
            $alike = $merge->alike($isomarc, $orgmarc);
            if (!$alike) {
//                $isomarc = $merge->insertDAT($isomarc, $orgdatcode);
                $cnt[$PhusOrBasis]++;
                if (!$fp = fopen($datafile[$PhusOrBasis], "a")) {
                    echo "couldn't open the file:" . $datafile[$PhusOrBasis] . " for writing\n";
                    verbose::log(ERROR, "couldn't open the file:" . $datafile[$PhusOrBasis] . " for writing");
                    exit(3);
                }
                fwrite($fp, $isomarc);
                fclose($fp);
                $cnt[$PhusOrBasis]++;

                if ($nothing) {
                    if (!$fp = fopen($datafile[$PhusOrBasis] . ".org", "a")) {
                        echo "couldn't open the file:" . $datafile[$PhusOrBasis] . ".org for writing\n";
                        verbose::log(ERROR, "couldn't open the file:" . $datafile[$PhusOrBasis] . ".org for writing");
                        exit(3);
                    }
                    fwrite($fp, $orgmarc);
                    fclose($fp);
                    $cnt[$PhusOrBasis]++;
                }
            }
        }
    }
    if ($cnt[$Basis])
        ftp_transfer($config, $Basis, $datafile, $workdir, $ts);
    if ($cnt[$Phus])
        ftp_transfer($config, $Phus, $datafile, $workdir, $ts);

    if (!$nothing) {
// only if no errors the database will be updated.
        $db->exe('commit');
    }
} catch (marcException $me) {
    echo "Fanget $me";
    exit;
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}
$bcnt = $cnt[$Basis];
$pcnt = $cnt[$Phus];
verbose::log(TRACE, "basis:$bcnt  phus:$pcnt  records has been processed");
verbose::log(TRACE, "**** STOP ToBasis.php ****");
?>
