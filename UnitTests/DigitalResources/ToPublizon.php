<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";
$mockupclasses = $startdir . "/../UnitTests/classes";

require_once "$classes/mediadb_class.php";
require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";

//require_once "$inclnk/OLS_class_lib/oci_class.php";
//require_once "$inclnk/OPAC_class_lib/updateMediaDB_class.php";

function PutClient($data, $url, $curl_proxy) {
    try {
        $data_json = json_encode($data);

        $ch = curl_init();
        if ($curl_proxy) {
            curl_setopt($ch, CURLOPT_PROXY, $curl_proxy);
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data_json)
        ));
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_json);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        if ($response) {
//            $ln = implode("\n", $repsonse);
            verbose::log(ERROR, "wrong response from Publizon\n$response");
            die("wrong response:\n$response");
        }
        curl_close($ch);
    }
    catch (Exception $ex) {
        verbose::log(ERROR, "ex:" . $ex);
        echo "ex: " . $ex . "\n";
        exit(4);
    }
}

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
    echo "\t-t use test database ([testcase]connect) \n";
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-N don't send to Publizon\n";
    echo "\t-s seqno\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = "../DigitalResources.ini";
$nothing = FALSE;
$cnt = 0;

$withCommit = true;
if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('n' => 'true', 'x' => 10223, 'x' => 'true');
    $withCommit = false;
}
else {
    $options = getopt("hp:ns:tN");
}

if (array_key_exists('p', $options)) {
    $inifile = $options['p'];
}
if (array_key_exists('h', $options)) {
    usage();
}

if (array_key_exists('n', $options)) {
    $nothing = true;
    $withCommit = false;
}
$publizonNothing = $nothing;
if (array_key_exists('N', $options)) {
    $publizonNothing = true;
}

if (array_key_exists('s', $options)) {
    $sno = $options['s'];
}
$max = 50;


$setup = 'setup';
if (array_key_exists('t', $options)) {
    $setup = 'testcase';
    $withCommit = true;
    $publizonNothing = true;
//    $nothing = true;
    $max = 12;
}
// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error) {
    usage($config->error . " inifile:$inifile");
}


if (($logfile = $config->get_value('logfile', 'setup')) == false) {
    usage("no logfile stated in the configuration file");
}
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false) {
    usage("no verboselevel stated in the configuration file");
}

if (($ociuser = $config->get_value('ociuser', 'setup')) == false) {
    usage("no ociuser (seBasis) stated in the configuration file");
}
if (($ocipasswd = $config->get_value('ocipasswd', 'setup')) == false) {
    usage("no ocipasswd (seBasis) stated in the configuration file");
}
if (($ocidatabase = $config->get_value('ocidatabase', 'setup')) == false) {
    usage("no ocidatabase (seBasis) stated in the configuration file");
}

if (($cataloguinginfoUrl = $config->get_value('cataloguinginfoUrl', 'Pligtaflevering')) == false) {
    usage("no cataloguinginfoUrl (Pligtaflevering) stated in the configuration file");
}

if (($workdir = $config->get_value('workdir', 'setup')) == false) {
    usage("no workdir stated in the configuration file");
}

if (($basedir = $config->get_value('basedir', 'setup')) == false) {
    usage("no basedir  stated in the configuration file [setup]");
}

$curl_proxy = $config->get_value('curl_proxy', 'setup');

$connect_string = $config->get_value("connect", $setup);

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START " . __FILE__ . "****");

try {

    if ($setup == 'testcase') {
        require_once "$mockupclasses/mockUpLibV3_class.php";
        $libV3API = new mockUpLibV3API($basedir,'data');
    }
    else {
        require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
        $libV3API = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
    }


    $db = new pg_database($connect_string);
    $db->open();
    $mediadb = new mediadb($db, basename(__FILE__), $nothing);

    $tot = array();
    $tabels = "'UpdatePublizon','Afventer','igitalR','Drop'";
    while ($seqnumbers = $mediadb->getPubSeqnos($tabels, $max, $sno = 0)) {

        $mediadb->startTransaction();

        $data = array();
//        echo "first seqno = " . $seqnumbers[0]['seqno'] . "\n";
        for ($seqnum = 0; $seqnum < count($seqnumbers); $seqnum++) {
            $cnt++;
            $seqno = $seqnumbers[$seqnum]['seqno'];
            $status = $seqnumbers[$seqnum]['status'];

            $faust = $newfaust = $choice = "";
            if ($status == 'UpdatePublizon') {
                $newfaust = $seqnumbers[$seqnum]['newfaust'];
                $faust = $seqnumbers[$seqnum]['faust'];
                $choice = $seqnumbers[$seqnum]['choice'];
                $is_in_basis = $seqnumbers[$seqnum]['is_in_basis'];
            }

            $isbn13 = $seqnumbers[$seqnum]['isbn13'];
//            $faust = str_replace(' ', '', $lokalid);

            $marc = new marc();
//            $insertFaust = false;
            $dbf = $bkm = $bkmv = $bkmb = $bkms = $lu = false;
            $lekFaust = null;

            $fromDB = false;
            $fromBasis = false;

            if ($choice) {
                $fromDB = true;
            }
            else {
                if ($faust) {
                    $fromBasis = true;
                }
            }
            if ($faust and $is_in_basis) {
                $fromBasis = true;
            }

            if ($fromDB) {
                $dbf = true;
                if ($choice) {
                    $arr = explode(' ', $choice);
                    foreach ($arr as $cho) {
                        switch ($cho) {
                            case 'BKM':
                                $bkm = true;
                                break;
                            case 'V':
                                $bkmv = true;
                                break;
                            case 'B':
                                $bkmb = true;
                                break;
                            case 'S':
                                $bkms = true;
                                break;
                            case 'BKMV':
                                $bkm = true;
                            case 'L':
                                $lu = true;
                                $lekFaust = $newfaust;
                                break;
                        }
                    }
                }
            }

            if ($fromBasis) {
                $m = $libV3API->getMarcByLB($faust, '870970');
                if ($m) {
                    $marc->fromIso($m[0]['DATA']);
                    $f06bs = $marc->findSubFields('f06', 'b');
                    foreach ($f06bs as $f06b) {
                        $f06b = strtolower($f06b);
                        switch ($f06b) {
                            case 'l':
                                $lu = true;
                                $lekFaust = $faust;
                                break;
                            case 'v':
                                $bkmv = true;
                                break;
                            case 'b':
                                $bkmb = true;
                                break;
                            case 's':
                                $bkms = true;
                                break;
                        }
                    }
                    $dbf = true;

                    $f032xs = $marc->findSubFields('032', 'x');
                    foreach ($f032xs as $f032x) {
                        $code = substr($f032x, 0, 3);
                        if ($code == 'BKM') {
                            $bkm = true;
                        }
                    }
                }
            }


            switch ($status) {
                case 'UpdatePublizon':
                    $statusType = 1;
                    break;
                case 'DigitalR':
                    $statusType = 2;
                    break;
                case 'Afventer':
                    $statusType = 3;
                    $dbf = $bkm = $bkmv = $bkmb = $bkms = $lu = false;
                    break;
                case 'Drop':
                    $statusType = 4;
                    $dbf = $bkm = $bkmv = $bkmb = $bkms = $lu = false;
                    break;
                default :
                    $statusType = 1;
            }

            $lekFaust = str_replace(' ', '', $lekFaust);
            $dat = array(
                'Isbn' => $isbn13,
                'Dbf' => $dbf,
                'Bkm' => $bkm,
                'BkmV' => $bkmv,
                'BkmS' => $bkms,
                'BkmB' => $bkmb,
                'Lu' => $lu,
                'StatusType' => $statusType,
                'LektorPointer' => $lekFaust
            );
            if ($nothing or $publizonNothing) {
                $txt = "\nseqno:$seqno, ";
                foreach ($dat as $key => $val) {
                    $txt .= $key . ":" . $val . ", ";
                }
                echo "$txt";
                if ($setup == 'testcase') {
                    $mediadb->insertToPublizon($seqno, trim($txt));
                }
            }
            $data[] = $dat;
            $mediadb->updatePub($seqno, $status);
        }

        if ($data) {
            verbose::log(TRACE, "PUT a block to Publizon, cnt:$cnt");
            if (!$nothing and !$publizonNothing) {
//                PutClient($data, $cataloguinginfoUrl, $curl_proxy);
            }
            $tot[] = $data;
            if ($withCommit) {
                $mediadb->endTransaction();
            }
            verbose::log(TRACE, "Count:$cnt");
            if ($sno) {
                die("only one record #:$sno");
            }
        }
    }
//    print_r($tot);
    $cdbf = $cbkm = $cbkmv = $cbkmb = $cbkms = $clu = $cfaust = 0;
    $csta[1] = 0;
    $csta[2] = 0;
    $csta[3] = 0;
    $csta[4] = 0;

    foreach ($tot as $nxt) {
        foreach ($nxt as $d) {
            foreach ($d as $key => $val) {
                switch ($key) {
                    case 'Dbf':
                        if ($val) {
                            $cdbf++;
                        }
                        break;
                    case 'Bkm':
                        if ($val) {
                            $cbkm++;
                        }
                        break;
                    case 'BkmV':
                        if ($val) {
                            $cbkmv++;
                        }
                        break;
                    case 'BkmS':
                        if ($val) {
                            $cbkms++;
                        }
                        break;
                    case 'BkmB':
                        if ($val) {
                            $cbkmb++;
                        }
                        break;
                    case 'Lu':
                        if ($val) {
                            $clu++;
                        }
                        break;
                    case 'StatusType':
                        $csta[$val]++;
                        break;
                    case 'LektorPointer':
                        if ($val) {
                            $cfaust++;
                        }
                        break;
                }
            }
        }
    }
    // nedenstående er til testformål.
//    foreach ($csta as $key => $val) {
//        echo "sta[$key]:$val, ";
//    }
    if ($nothing or $publizonNothing) {
        echo "\n";
        echo "cnt:$cnt, dbf: $cdbf, bkm:$cbkm, bkmV:$cbkmv, bkmB:$cbkmb, bkmS:$cbkms, lu=$clu, faust:$cfaust\n";
    }
}
catch
(Exception $ex) {
    echo $ex . "\n";
    exit;
}




