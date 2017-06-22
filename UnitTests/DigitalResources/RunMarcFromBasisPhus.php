#!/usr/bin/php
<?php
    /**
    * @file RunMarcFromBasisPhus.php
    * @brief This script will execute those scripts nessecarry for maintaining DigitalResources
    */

    // get all records from Phus which have been updated since date stated in table "lastupdated"
    call_php_script("FetchFromLibv3.php -f sePhus ");

    // get all records from Basis which have been updated since date stated in table "lastupdated"
    call_php_script("FetchFromLibv3.php -f seBasis ");

    call_php_script("FetchFromLibv3.php -f seLektor ");

    /**
    * Call a php script and see if anything has gone wrong!
    *
    * @param string $cmd
    */
    function call_php_script($cmd) {
        //echo "cmd:$cmd\n";
        if ( substr($cmd,0,4) != 'php ' ) $cmd = "php " . $cmd;
        $ret = system($cmd,$return_var);
        if ( $return_var ) {
            echo "RundMarcFromBasisPhus: " . date('c') . " -- $cmd -- return_var:$return_var, ret:$ret\n";
            exit(10);
        }
    }
?>
