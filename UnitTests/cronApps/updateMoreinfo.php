#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file UploadFrontPageImages.php
 * @brief
 *
 * @author Hans-Henrik Lund
 *
 * @date 16-06-2011
 * @date 07-06-2012
 */

/**
 * write the errors from cURL to tracefile
 *
 * @param array $arr
 */
function verbose_error($arr) {
    print_r($arr);
    $strng = implode("\n", $arr);
    verbose::log(ERROR, $strng);
    exit(12);
}

/**
 * getImage fetch the image via the $coverURL
 * and return it.
 *
 */
function getImage($coverURL, $workdir, $curl_proxy) {


    try {

        // Send request to webservice
        if ( !$curl = new cURL())
            verbose_error("new cURL errno:" . $curl->get_status('errno'));
        if ($curl_proxy)
            $curl->set_option(CURLOPT_PROXY, $curl_proxy);

        $curl->set_timeout(30);

        if ( !$res = $curl->get($coverURL)) {
//            var_dump("curl->get url:" . $curl->get_status('url') . " errno:" . $curl->get_status('errno'));
            verbose_error($curl->get_status('errno'));
        }

        $curl->close();

        $path_parts = pathinfo($coverURL);

        $cover_image_file = $workdir . "/" . "CoverImage." . $path_parts['filename'] . "." . $path_parts['extension'];
        if ($fp = fopen($cover_image_file, "w")) {
            fwrite($fp, $res);
            fclose($fp);
        } else {
            verbose::log(ERROR, "Couldn't open imagefile:$cover_image_file");
            echo "Couldn't open imagefile:$cover_image_file\n";
            exit;
        }

        return $cover_image_file;
    } catch (DOMException $DOMe) {
        verbose::log(ERROR, "DOMe:" . $DOMe);
        echo "DOMe: " . $DOMe . "\n";
        exit(4);
    }
}

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$classes/mediadb_class.php";
require_once "$classes/soapClient_class.php";
require_once "$classes/ONIX_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";

/**
 * This function tells how to use the program
 *
 * @param string $str
 */
function usage($str = "") {
    global $argv, $inifile, $maximages;

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php $argv[0]\n";
    echo "\t-p initfile (default:\"$inifile\") \n";
    echo "\t-m max numbers of images to update (default $maximages)\n";
    echo "\t-n nothing happens - no effect right now \n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../" . "DigitalResources.ini";
$nothing = false;
$maximages = 5000;


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('t' => 'PubHubImages', 'y' => 'false', 'n' => 'true');
} else {
    $options = getopt("hp:m:n");
}

if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];
if (array_key_exists('m', $options)) {
    $maximages = $options['m'];
}

if (array_key_exists('n', $options))
    $nothing = true;

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
if (($workdir = $config->get_value('workdir', 'setup')) == false)
    usage("no workdir stated in the configuration file");
if (($convert_cmd = $config->get_value('convert_cmd', 'setup')) == false)
    usage("no convert_cmd stated in the configuration file");

$curl_proxy = $config->get_value('curl_proxy', 'setup');

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START" . __FILE__ . "****");

$ONIX = new ONIX();
$marc = new marc();

$connect_string = $config->get_value("connect", "setup");
try {
    $db = new pg_database($connect_string);
    $db->open();
    $mediadb = new mediadb($db, basename(__FILE__), $nothing);
    $soapClient = new soapClient_class($config, 'rawrepro', '', $noCredentials = true);
    if ($er = $soapClient->getError()) {
        usage($er);
    }
    $updMoreInfo = new soapClient_class($config, 'moreinfoupdate', '', $noCredentials = true);
    if ($er = $updMoreInfo->getError()) {
        usage($er);
    }
    $cnt = 0;
    $mediadb->setONIXonOff();

    $seqnumbers = $mediadb->getCoverCandidates();

    if ($seqnumbers) {
        foreach ($seqnumbers as $seqnos) {
            $seqno = $seqnos['seqno'];
            $err = "";

            $cnt++;
            if ($cnt > $maximages)
                exit;
//            echo "CNT:$cnt - $seqno\n";
            $infos = $mediadb->getInfoData($seqno);
            $info = $infos[0];
            if ($info['newfaust']) {
                $faust = $info['newfaust'];
                $alternativfaust = $info['faust'];
            } else {
                $faust = $info['faust'];
                $alternativfaust = $info['faust'];
            }
            $faust = str_replace(' ', '', $faust);
            $alternativfaust = str_replace(' ', '', $alternativfaust);
            $isbn13 = $info['isbn13'];
            $xml = $info['originalxml'];

            $ret = fetchFromRaw($soapClient, $seqno, $faust);
            if ($ret['status']) {
                $faust = $alternativfaust;
                $ret = fetchFromRaw($soapClient, $seqno, $faust);
            }
            $err = $ret['status'];
            $marcExchange = $ret['marc'];

            if ( !$err) {
                $marc->fromMarcExchange($marcExchange);
                $strng = $marc->toLineFormat();
                $ids = $marc->findSubFields('001', 'a');
                $id = $ids[0];
                // just in case!!
                if ($id != $faust) {
//                    throw new soapClientException("Ikke den rigtige marcExchange post vi har hentet ($seqno)");
                    $err = "Ikke den rigtige marcExchange post vi har hentet";
                }
            }
            if ( !$err) {
                $cover_images_files = $ONIX->getInfo($xml);
                $cover_images_file = $cover_images_files['image'];
                if ( !$cover_images_file) {
                    $err = "no images found in XML";
                } else {
                    $image = file_get_contents($cover_images_file);
                }
            }

            // send the image to API
            if ( !$err) {
                $morxml = $updMoreInfo->callMoreInfo($image, $faust);
                $pos = strpos($morxml, '<miu:requestAccepted>');
                if ( !$pos) {
                    $txt = "ERRROR when updating more info:\n$morxml\n\n";
                    verbose::log(ERROR, $txt);
                    die ($txt);
                }
                if ( !$nothing) {
                    if (isset($cover_images_file)) {
                        if (is_file($cover_images_file)) {
                            unlink($cover_images_file);
                        }
                    }
                }
                $err = "OK";
            }
            $mediadb->updateSentToCovers($seqno, $err);
            verbose::log(TRACE, "faust:$faust, isbn13:$isbn13, cover_status:$err");
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit(2);
}

verbose::log(TRACE, "**** STOP" . __FILE__ . "****");
echo "finido";


function fetchFromRaw($soapClient, $seqno, $faust) {
    // is there a record in Basis with this faust number?
    if ($faust) {
        $marcExchange = $soapClient->query("marc.001a=$faust");
        if ( !$marcExchange) {
            $err = "Faust not in database (" . date('Y-m-d') . ")";
        }
        if ($pos = strpos($marcExchange, '<hitCount>0</hitCount>')) {
            $err = "Posten ($faust/$seqno) findes ikke i Basis!";
            return array('status' => $err, 'marc' => $marcExchange);
        }
    }
    return array('status' => '', 'marc' => $marcExchange);
}

?>
