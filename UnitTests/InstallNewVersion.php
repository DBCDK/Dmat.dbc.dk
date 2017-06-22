<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

// find the current posthus/Digitalresources

$current_dir = getcwd();
echo "Do you want '~danbib/posthus' to point at: $current_dir ? (y/n):";
$ln = strtolower(trim(fgets(STDIN)));
if ($ln == 'y' or $ln == 'yes') {
    chdir('..');
    $cmd = "rm posthus; ln -s $current_dir posthus ";
    echo "$cmd\n";
    system($cmd);
}

echo "\n\nDo you want '/data/www/posthus' to point at: $current_dir ? (y/n):";
$ln = strtolower(trim(fgets(STDIN)));
if ($ln == 'y' or $ln == 'yes') {
    chdir('/data/www');
    echo "getcwd:" . getcwd() . "\n";
    $cmd = "rm posthus; ln -s $current_dir posthus ";
    echo "$cmd\n";
    system($cmd);
}


