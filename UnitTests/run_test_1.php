<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 04-08-2016
 * Time: 09:34
 */

$txt = "Running: " . __FILE__;
$strng = '';
for ($i = 0; $i < strlen($txt); $i++) {
    $strng .= '*';
}
echo "\n\n$strng\n$txt\n$strng\n\n";

chdir('UnitTests');

//echo "$iniInstall\n";
//file_put_contents('dr.ini', $iniInstall);

//MakeDir('workdir');
Makedir('work');
MakeDir('phpunitlog');

callPhpUnit('ftp_transfer');
callPhpUnit('dr_db');
callPhpUnit('soapClient');
callPhpUnit('epub');
callPhpUnit('weekcode');

function callPhpUnit($name) {
    $fphp = 'test_' . $name . '.php';
    $xml = $name . ".xml";
    $cmd = "phpunit --log-junit phpunitlog/$xml $fphp";
    $strng = '';
    for ($i = 0; $i < strlen($cmd); $i++) {
        $strng .= '-';
    }
    echo "$strng\n$cmd\n$strng\n";
    $res = exec($cmd, $output, $sta);
    foreach ($output as $ln) {
        echo "$ln\n";
    }
    echo "$strng\n\n";
    if ($sta) {
        die("Fundet en fejl, vi stopper\n\n");
    }
}

function MakeDir($dir) {
    if (!file_exists($dir)) {
        echo "Making dir:$dir\n";
        mkdir($dir);
    }
    chmod($dir, 0777);

}

?>