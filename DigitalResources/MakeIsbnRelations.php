#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * @file MakeIsbnRelations.php
 *
 * @author Hans-Henrik Lund
 *
 * @date 20.12.2013
 *
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";


require_once "$inclnk/OLS_class_lib/inifile_class.php";
//require_once "$inclnk/OLS_class_lib/marc_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";
//require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
//require_once 'GetFaust_class.php';
require_once "$startdir/XmlDiff_class.php";

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
//    echo "\t-f datafile (marc iso format) \n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$inifile = $startdir . "/../DigitalResources.ini";
$nothing = false;

$options = getopt("hp:n");
if (!$options) {
    $options = array();
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

if (( $logfile = $config->get_value('logfile', 'setup')) == false)
    usage("no logfile stated in the configuration file");
if (( $verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    usage("no verboselevel stated in the configuration file");
//if (( $ociuser = $config->get_value('ociuser', 'setup')) == false)
//  usage("no ociuser stated in the configuration file");
//if (( $ocipasswd = $config->get_value('ocipasswd', 'setup')) == false)
//  usage("no ocipasswd stated in the configuration file");
//if (( $ocidatabase = $config->get_value('ocidatabase', 'setup')) == false)
//  usage("no ocidatabase stated in the configuration file");

verbose::open($logfile, $verboselevel);
verbose::log(TRACE, "**** START  " . __FILE__ . " ****");

$tablename = 'digitalresources';
$isbnrelationstable = 'isbnrelations';
$irelseq = $isbnrelationstable . 'seq';

$compare = new XmlDiff();
$old = new DOMDocument();
$cnd = new DOMDocument();

$connect_string = $config->get_value("connect", "setup");
try {
    $db = new pg_database($connect_string);
    $db->open();

    $sql = "select tablename from pg_tables where tablename = $1";
    $arr = $db->fetch($sql, array($isbnrelationstable));
    if ($arr) {
        $sql = "drop table $isbnrelationstable";
        $db->exe($sql);
    }
    $sql = "create table $isbnrelationstable (
            seqno integer primary key,
            title varchar(100),
            isbnA varchar(50),
            isbnB varchar(50),
            formatA varchar(20),
            formatB varchar(20),
            faustA varchar(11),
            faustB varchar(11),
            language varchar(3),
            contributors varchar(3),
            publisher varchar(3),
            description varchar(3),
            description2 varchar(3)
            )
            ";
    $db->exe($sql);
    verbose::log(TRACE, "table created:$sql");
    $sql = "
        drop sequence if exists $irelseq
       ";
    $db->exe($sql);
    $sql = "
      create sequence $irelseq
    ";
    $db->exe($sql);

    // this command does that we can make a rollback - no commit is done until
    // the commit command is executed
//  $db->exe('START TRANSACTION');
// search for records not in isbnrelations table
    $sql = "SELECT seqno, provider, format, isbn13, title, originalxml
  FROM digitalresources
  where (provider, format, isbn13) not in
  (select provider, format, isbn13 from $isbnrelationstable)
  and provider = 'Pubhub'
  and format in ('ebib','eReolen')
  ";
    $sql = "
  SELECT title
  FROM digitalresources
  where provider = 'Pubhub'
  and title != '__Unknown__'
    and format in ('ebib','eReolen')
    and status != 'd'
  group by title
  having count(*) > 1
  order by count(*) desc
";
//  echo $sql;
    verbose::log(TRACE, "sql:\n$sql");
    $arr = $db->fetch($sql);

    $cnt = 0;
    if ($arr) {
        foreach ($arr as $result) {
            $cnt++;
            $title = $result['title'];
            verbose::log(TRACE, "*********************** new title [$title] *******************");


            // Is there already a record in the table with the same ISBN and with a faust number?
            $sql = "
        select seqno, originalxml, faust, format, isbn13, title from $tablename
          where
          provider = 'Pubhub'
            and format in ('ebib','eReolen')
            and status != 'd'
            and title = $1
        ";
            echo "($title) $sql\n";
            $getCandidates = $db->fetch($sql, array($title));

            while ($cur = array_pop($getCandidates)) {
                if (count($getCandidates) == 0)
                    continue;
                /*
                  echo "cur\n";
                  print_r($cur);
                  echo "getCandidates\n";
                  print_r($getCandidates);
                 */
                $old->loadXML($cur['originalxml']);
                foreach ($getCandidates as $nxt) {
                    $cnd->loadXML($nxt['originalxml']);
                    if (!$compare->diff('product/contributors', $old, $cnd))
                        $sameContributor = 'yes';
                    else
                        $sameContributor = 'no';
                    if (!$compare->diff('product/language', $old, $cnd))
                        $sameLanguage = 'yes';
                    else
                        $sameLanguage = 'no';
                    if (!$compare->diff('product/publisher', $old, $cnd))
                        $samePublisher = 'yes';
                    else
                        $samePublisher = 'no';
                    if (!$compare->diff('product/description', $old, $cnd))
                        $sameDescription = 'yes';
                    else
                        $sameDescription = 'no';
                    if (!$compare->diff('product/description', $old, $cnd, true))
                        $sameDescription2 = 'yes';
                    else
                        $sameDescription2 = 'no';
                    $isbnA = $cur['isbn13'];
                    $faustA = $cur['faust'];
                    $formatA = $cur['format'];
                    $isbnB = $nxt['isbn13'];
                    $faustB = $nxt['faust'];
                    $formatB = $nxt['format'];
                    $insertSql = "
            insert into $isbnrelationstable
              (seqno, isbnA, isbnB, formatA, formatB,
              title, faustA, faustB, language, contributors,
              publisher, description, description2)
              values
              (nextval('$irelseq'), '$isbnA', '$isbnB', '$formatA', '$formatB', $1,
                '$faustA', '$faustB', '$sameLanguage', '$sameContributor',
                  '$samePublisher', '$sameDescription','$sameDescription2')
             ";
//          echo "$insertSql";
                    $db->query_params($insertSql, array($title));
                    $db->commit();
                }
            }
//      exit;
        }
    }
} catch (fetException $f) {
    echo $f;
    verbose::log(ERROR, "$f");
}
verbose::log(TRACE, "**** STOP  " . __FILE__ . " ****");
?>