#!/usr/bin/php
<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

    /**
    * @file imageAlreadyInBasis.php
    * @brief 
    * 
    * @author Hans-Henrik Lund
    * 
    * @date 16-06-2011
    * 
    */

    $startdir = dirname(realpath($argv[0]));
    $inclnk = $startdir . "/../inc";

    require_once "$inclnk/OLS_class_lib/inifile_class.php";
    //require_once "$inclnk/marc_class_lib/marc_class.php";
    require_once "$inclnk/OLS_class_lib/pg_database_class.php";
    //require_once "$inclnk/OLS_class_lib/material_id_class.php";

    /**
    * This function tells how to use the program
    * 
    * @param string $str
    */
    function usage($str="")
    {
        global $argv, $inifile;

        if ( $str != "" ) {
            echo "-------------------\n";
            echo "\n$str \n";
        }

        echo "Usage: php $argv[0]\n";
        echo "\t-p initfile (default:\"$inifile\") \n";
        //echo "\t-t table type ('Biblioteksvaesner' or 'Betjeningssteder' or 'Hjaelpetabel')\n";
        //echo "\t-d datafilename (the program will extract the table type from the datafilename) \n";
        //echo "\t-n nothing happens (der bliver ikke opdateret)\n";
        //echo "\t-v verbose level\n";
        echo "\t-h help (shows this message)\n";
        exit;
    }

    $inifile = $startdir . "posthus.ini";
    $nothing = false;

    $options = getopt("?hs:v:p:d:");
    if ( array_key_exists('h',$options) ) usage();

    // Fetch ini file and Check for needed settings
    $config = new inifile($inifile);
    if ($config->error)
        usage($config->error);

    $tablename = 'digitalresources';
    $connect_string = $config->get_value("connect","setup");
    try {
        $db = new pg_database($connect_string);
        $db->open();
        $fpfaust = fopen("faust_with_images.lst","r");
        if ( $fpfaust ) {
            while ( $faust = fgets($fpfaust,4096) ) {
                $faust = trim($faust);
                echo "$faust  ";
                $sql = "select isbn13 from $tablename where faust = '$faust'";
                $arr = $db->fetch($sql);
                if ($arr) {
                    print_r($arr);
                    exit;
                }
                else {
                    echo "ok\n";
                    exit;
                }
            }
        }
    }
    catch (Exception $e) {
        echo $e . "\n";
        exit;
    }


?>