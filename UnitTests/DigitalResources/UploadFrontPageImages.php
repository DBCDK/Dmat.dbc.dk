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
function verbose_error($arr)
{
    print_r($arr);
    $strng = implode("\n", $arr);
    verbose::log(ERROR, $strng);
    exit(12);
}

/**
 * getImage retrive the URL to the cover image from eLib
 * and fetch it by the http protocol
 *
 * @param DOMdocument $doc
 */
function getImage($doc, $workdir, $curl_proxy)
{


    try {

        $coverURLElement = $doc->getElementsByTagName('coverimage')->item(0);
        if ($coverURLElement) {
            $coverURL = $coverURLElement->nodeValue;
        } else {
            $coverURL = "Not found";
            return false;
        }
        // Send request to webservice
        if (!$curl = new cURL())
            verbose_error("new cURL errno:" . $curl->get_status('errno'));
        if ($curl_proxy)
            $curl->set_option(CURLOPT_PROXY, $curl_proxy);

        $curl->set_timeout(30);

        if (!$res = $curl->get($coverURL)) {
            var_dump("curl->get url:" . $curl->get_status('url') . " errno:" . $curl->get_status('errno'));
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

require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/curl_class.php";
require_once "$inclnk/OLS_class_lib/oci_class.php";

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
    echo "\t-n nothing happens - no effect right now \n";
    echo "\t-t transfile section (mandatory)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../" . "DigitalResources.ini";
$nothing = false;


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('t' => 'PubHubImages', 'y' => 'false', 'n' => 'true');
} else {
    $options = getopt("hp:t:n");
}

if (array_key_exists('t', $options))
    $transSection = $options['t'];
else
    usage('Transfile section (-t) is missing');

if (array_key_exists('h', $options))
    usage();
if (array_key_exists('p', $options))
    $inifile = $options['p'];

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
if (($fpwscanning_cmd = $config->get_value('fpwscanning_cmd', 'setup')) == false)
    usage("no fpwscanning_cmd stated in the configuration file");
if (($addi_cmd = $config->get_value('addi_cmd', 'setup')) == false)
    usage("no addi_cmd stated in the configuration file");
if (($format = $config->get_value('format', $transSection)) == false)
    usage("no format stated in the configuration file ($transSection)");

$curl_proxy = $config->get_value('curl_proxy', 'setup');

if (($ociuser = $config->get_value('ociuser', 'setup')) == false)
    usage("no ociuser stated in the configuration file");
if (($ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
    usage("no ocipasswd stated in the configuration file");
if (($ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
    usage("no ocidatabase stated in the configuration file");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START UploadFrontPageImages.php ****");

// is there a workdir ?
if (!file_exists($workdir)) {
    if (!mkdir($workdir)) {
        verbose::log(ERROR, "Couldn't make the directory:$workdir");
        exit(2);
    }
}

$tablename = "digitalresources";
$doc = new DOMDocument('');
$connect_string = $config->get_value("connect", "setup");
//$isofile = "isofile";
try {
    $oci = new oci($ociuser, $ocipasswd, $ocidatabase);
    $oci->connect();

    $db = new pg_database($connect_string);
    $db->open();

    $cnt = 0;
    $inputformats = explode(',', $format);
    $sqlformat = "";
    foreach ($inputformats as $iformat) {
        $sqlformat .= "'$iformat',";
    }
    $sqlformat = "and format in (" . rtrim($sqlformat, ',') . ")";

    $sql = "
      select seqno from $tablename
        where sent_to_covers is null
        and provider = 'Pubhub'
        $sqlformat
        and faust is not null
        and status != 'd'
  ";
    $seqnumbers = $db->fetch($sql);
//    $seqnumbers[0]['seqno'] = 48354;

    if ($seqnumbers) {
        foreach ($seqnumbers as $seqnos) {
            foreach ($seqnos as $seqno) {
                $err = "";
                $sent_to_covers_update = true;

                $cnt++;
//        echo "CNT:$cnt\n";
//        if ($cnt > 30)
//          exit;

                $sql = "
          select originalXML, isbn13, faust from $tablename
          where seqno = $seqno
        ";

                $arr = $db->fetch($sql);
                $faust = $arr[0]['faust'];
                $isbn13 = $arr[0]['isbn13'];
                $xml = $arr[0]['originalxml'];
                // is there a record in Basis with this faust number?
                $sql = "select id from poster where lokalid = '$faust' and bibliotek = 870970\n";
                $arr = $oci->fetch_into_assoc($sql);
                // no record with this faust - try next time to see if the book have turned up.
                if (!$arr) {
                    $sent_to_covers_update = false;
                    $err = "Faust not in database (" . date('Y-m-d') . ")";
//          continue;
                }
//                Prøver at slå det fra så man kan opdatere en post.
//                if (!$err) {
//                    $id = $arr['ID'];
//                    $sql = "select id from attachment where id = '$id' and attachment_type = 'forside_pic' \n";
//                    $arr = $oci->fetch_into_assoc($sql);
//                   //  there is already an image with this faust/isbn13
//                    if ($arr) {
//                        verbose::log(TRACE, "There is already an image with this faust/isbn13");
//           // $err = "'1800-04-04'";
//                        $err = "The image is already in the database";
//                    }
//                }
                if (!$err) {
                    $doc->loadXML($xml);
                    //echo $doc->saveXML() . "\n";
                    $cover_images_file = getImage($doc, $workdir, $curl_proxy);
                    if (!$cover_images_file) {
                        $err = "no images found in XML";
                    } else {
                        // convert from jpg to png
                        $id = str_replace(" ", "", $faust);
                        $output = $workdir . "/" . $id . ".png";
                        $cmd = str_replace('$input', "$cover_images_file", $convert_cmd);
                        $cmd = str_replace('$output', "$output", $cmd);
                        $return_string = system($cmd, $return_var);
                        if ($return_var) {
                            echo "The cmd:$cmd is in error: $return_var\n";
                            echo "return_string:$return_string\n";
                            verbose::log(ERROR, "The cmd:$cmd is in error: $return_var, return_string:$return_string");
                            $err = "cmd is in error:$cmd";
//            exit(2);
                        }
                    }
                }
                // wrap the png file into a addi file
                if (!$err) {
                    $cmd = $fpwscanning_cmd . " >$workdir/collector";
                    $cmd = str_replace('$workdir', "$workdir", $cmd);
                    $cmd = str_replace('$id', "$id", $cmd);
                    $cmd = str_replace('$long_id', "\"$faust\"", $cmd);

//          $cmd = $startdir . "/" . $cmd;
                    $return_string = system($cmd, $return_var);
                    if ($return_var) {
                        echo "The cmd:$cmd is in error: $return_var\n";
                        echo "return_string:$return_string\n";
                        verbose::log(ERROR, "The cmd:$cmd is in error: $return_var, return_string:$return_string");
                        $err = "cmd is in error:$cmd";
//            exit(3);
                    }
                }
                // send the image to the libV3 base
                if (!$err) {
                    $cmd = $addi_cmd;
                    if ($nothing) {
                        echo "NOTHING cmd:$cmd\n";
                    } else {
                        $return_string = system($cmd, $return_var);
                        if ($return_var) {
                            echo "The cmd:$cmd is in error: $return_var\n";
                            echo "return_string:$return_string\n";
                            verbose::log(ERROR, "The cmd:$cmd is in error: $return_var, return_string:$return_string");
                            $err = "cmd is in error:$cmd";
//            exit(4);
                        }
                    }
                }
                if (!$err)
                    $err = "OK";

                if ($sent_to_covers_update) {
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

                if ($nothing) {
                    echo "NOTHING update_sql:$update_sql \n";
                } else {
                    if (isset($cover_images_file)) {
                        if (is_file($cover_images_file)) {
                            unlink($cover_images_file);
//          var_dump($cover_images_file);
                        }
                    }
                    if (isset($output)) {
                        if (is_file($output)) {
                            unlink($output);
//          var_dump($output);
                        }
                    }
                    $db->exe($update_sql);
                    $db->exe('commit');
                    verbose::log(TRACE, "faust:$faust, isbn13:$isbn13, cover_status:$err");
//        echo "update_sql:$update_sql";
//        if ($set_sent_to_covers)
//          exit;
                }
            }
        }
    }
} catch (ociException $ocie) {
    echo $ocie . "\n";
    exit(1);
} catch (Exception $e) {
    echo $e . "\n";
    exit(2);
}

verbose::log(TRACE, "**** STOP UploadFrontPageImages.php ****");
echo "finido"
?>
