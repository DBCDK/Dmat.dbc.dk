<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


function usage($filename, $str = "") {

    if ($str != "") {
        echo "-------------------\n";
        echo "\n$str \n";
    }

    echo "Usage: php " . $_SERVER['SCRIPT_NAME'] . "\n";
    echo "\t-f file (default:\"$filename\") \n";
//    echo "\t-n nothing happens (der bliver ikke opdateret)\n";
//    echo "\t-s seqno\n";
    echo "\t-h help (shows this message)\n";
    exit;
}

$filename = 'tunnelReady';

$options = getopt("hf:");

if (!$options) {
    $options = array();
}
if (array_key_exists('h', $options))
    usage($filename);
if (array_key_exists('f', $options)) {
    $filename = $options['f'];
}

while (true) {
    if (file_exists($filename)) {
        break;
    }
    sleep(1);
}