<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file insertFaust.php
 * @brief The program will look for records in the database which don't have a faust.
 * It will retrive a faust from the "no-roll", using the program Rck_send, and insert it in the database.
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2011
 *
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
$classes = $startdir . "/../classes";

require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/marc_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$classes/mediadb_class.php";

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
    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;


if (array_key_exists('XDEBUG_SESSION_START', $_REQUEST)) {
    $options = array('x' => 'nothing');
} else {
    $options = getopt("hp:n");
}
if (array_key_exists('h', $options))
    usage();
if (array_key_exists('n', $options))
    $nothing = true;
if (array_key_exists('p', $options))
    $inifile = $options['p'];

// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    usage($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START insertFaust.php " . __FILE__ . "****");

$tablename = 'digitalresources';
$connect_string = $config->get_value("connect", "setup");
$getFaust_cmd = $config->get_value('getFaust_cmd', 'setup');

$cnt = 0;
try {
    $db = new pg_database($connect_string);
    $db->open();
    $mediadb = new mediadb($db, basename(__FILE__), $nothing);

//    (provider = 'eLib' and format = 'Lydbog') or

    $sql = "select isbn13, format,title, originalxml from $tablename 
        where provider = 'Pubhub' 
        and status = 'n' 
       /* and format not in ('Netlydbog','Deff','NetlydbogLicens') */
        and faust is null";

    $arr = $db->fetch($sql);
    $doc = new DOMDocument();
//  print_r($arr);
//  exit;
    $new_faust = "tom";
    if ($arr) {
        foreach ($arr as $isbns) {
            $cnt++;
            $isbn13 = $isbns['isbn13'];
            $isDrop = false;
            $sql = "select s.status , s.seqno from mediaservice s, mediaebooks e
                       where
                          isbn13 = '$isbn13'
                          /*and s.status = 'Drop'*/
                          and s.seqno = e.seqno";
            $rows = $db->fetch($sql);
            // HACK --- husk at fjerne "!"
            if ($rows) {
                if ($rows[0]['status'] == 'Drop') {
                    $isDrop = true;
                    $s = $rows[0]['seqno'];
                    $update = "update mediaservice
                        set status = 'kunEreol' 
                        where seqno = '$s'";
                    $db->exe($update);
                }
            } else {
                // the record is not i mediaebooks. Make a "kunEreol" record in Dmat
//                $format = $isbns['format'];
//                if (substr($format, 0, 7) == 'eReolen') {
//                    $booktype = 'Ebog';
//                } else {
//                    $booktype = 'Lydbog';
//                }
//                $xml = $isbns['originalxml'];
//                $doc->loadXML($xml);
//                $el = $doc->getElementsByTagName('link');
//                $link = $el->item(0)->nodeValue;
//                $path_parts = pathinfo($link);
//                $filetype = $path_parts['extension'];
//                $el = $doc->getElementsByTagName('coverimage');
//                $link = $el->item(0)->nodeValue;
//                $path_parts = pathinfo($link);
//                $bookid = $path_parts['filename'];
//                $title = $isbns['title'];
//                $el = $doc->getElementsByTagName('first_published');
//                $p = $el->item(0)->nodeValue;
//                $ptime = strtotime($p);
//                $published = date('d-m-Y', $ptime);
//                $mediadb->updateBooks($booktype, $filetype, $bookid, $isbn13, $title, $published, $xml, 'kunEreol');
                $isDrop = true;

            }
//            if ($isbn13 == '9788799878130') {
//                $rows = true;
//            }
            $format = $isbns['format'];
            if (($format == 'Netlydbog' or $format == 'NetlydbogLicens') or $isDrop) {
//            if ($rows or $isbns['format'] =  ) {

                $cmd = $startdir . "/" . $getFaust_cmd;
                $cmd = $getFaust_cmd;
                if ($nothing) {
                    echo "cmd:$cmd\n";
                    $new_faust = 'x xxx xxx x';
                } else {
                    $new_faust = system($cmd, $return_var);
                    if ($return_var) {
                        $strng = "The cmd:$cmd is in error: $return_var";
                        echo $strng . "\n";
                        verbose::log(ERROR, $strng);
                        exit(2);
                    }
                }
                echo "new_faust:[$new_faust]\n";
                $sql = "update $tablename set faust = '$new_faust'
              where isbn13 = '$isbn13'
             ";
                if ($nothing)
                    echo $sql;
                else
                    $db->exe($sql);
            }
//            $db->commit();
        }
    }
} catch (Exception $e) {
    echo $e . "\n";
    exit;
}

verbose::log(TRACE, "$cnt faust numbers inserted");
verbose::log(TRACE, "**** STOP insertFaust.php ****");
?>
