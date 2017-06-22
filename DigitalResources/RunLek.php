#!/usr/bin/php
<?php

    // sent records to Basis with f07 and f06 updates and sent records to lektør basen
    call_php_script("ToLek.php -m 1000");

    /**
    * Call a php script and see if anything has gone wrong!
    * 
    * @param string $cmd
    */

    function call_php_script($cmd) {
    $date = date('Ymd');
    if (substr($cmd, 0, 4) != 'php ')
        $cmd = "php " . $cmd;
//    echo "cmd:$cmd\n";
        $ret = system($cmd,$return_var);
    if ($return_var) {
        echo "RundDaily: " . date('c') . " -- $cmd -- return_var:$return_var, ret:$ret\n";
        exit(10);
    }
}?>  
