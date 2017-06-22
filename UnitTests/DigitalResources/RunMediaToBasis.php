#!/usr/bin/php
<?php

    // insert faust
    call_php_script("php insertFaustMediaService.php");

    // sent records UpdateBasis to Basis
    call_php_script("ToBasisMediaService.php -s UpdateBasis");


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
