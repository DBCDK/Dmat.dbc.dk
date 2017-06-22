<?php

// find all php files.
$dir = ".";

function check_php($f) {
    $cmd = "php -l $f 2>/dev/null";
//    echo $cmd . "\n";
    $var = exec($cmd, $output, $ret);
    if ($ret) {
        echo "-********** $var\n";
        return false;
    }
//    $cmd = "php $f -h 2>/dev/null";
//    $var = exec($cmd, $output, $ret);
//    if ($ret) {
//        echo "********** $var\n";
//        return false;
//    }
    return true;
}

function check_cmd($cmd) {
    $var = exec($cmd, $output, $ret);
    if ($ret == 255) {
        echo $cmd . "\n";
        echo "+********** [$var][$ret]\n";
        return false;
    }
    return true;
}

function check_dir($dir) {
    $err = 0;
// Open a known directory, and proceed to read its contents
    if (is_dir($dir)) {
        if ($dh = opendir($dir)) {
            while (($file = readdir($dh)) !== false) {
                if (substr($file, 0, 1) != '.') {
                    if (filetype($dir . "/" . $file) == 'dir') {
                        if ($file != 'simpletest' and $file != 'vlib' and $file != 'UnitTests') {
                            $err += check_dir($dir . "/" . $file);
                        }
                    }
                    if (filetype($dir . "/" . $file) == 'file') {

                        $path_parts = pathinfo($file);
                        if (array_key_exists('extension', $path_parts)) {
                            $ext = $path_parts['extension'];
                            if ($ext == 'php') {
                                if ( !check_php($dir . "/" . $file)) {
                                    $err++;
                                }
                            }
                        }
                    }
                }
            }
            closedir($dh);
        }
    }
    return $err;
}

$err = check_dir($dir);
echo "Number of errors: $err\n";
if ($err) {
    exit(10);
}

$fp = fopen('DigitalResources/RunDaily.php', 'r');
chdir('DigitalResources');
$err = 0;
while ($ln = fgets($fp)) {
    $ln = trim($ln);
    if ($pos = strpos($ln, 'all_php_script')) {
        if ($pos == 1) {
            $arr = explode('"', $ln);
            $tokens = explode(" ", $arr[1]);
            if ($tokens[0] == 'php') {
                $tokens[0] = $tokens[1];
            }
            $cmd = "php " . $tokens[0] . " -h";
            if ( !check_cmd($cmd)) {
                $err++;
            }
        }
    }
}
echo "Number of errors in php xxx -h: $err\n";
if ($err) {
    exit(10);
}

?>