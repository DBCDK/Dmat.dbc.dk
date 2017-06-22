<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 06-07-2016
 * Time: 14:35
 */
class getFaust {

    private $faustcmd;

    function __construct($faustcmd) {
        $this->faustcmd = $faustcmd;
    }

    function setNrRulle($name) {
        $found = false;
        $arr = explode(' ', $this->faustcmd);
        foreach ($arr as $key => $val) {
            if ($val == '-f') {
                $arr[$key + 1] = $name;
                $found = true;
                break;
            }
        }
        if (!$found) {
            throwException('No parameter -f');
        }
        $this->faustcmd = implode(' ', $arr);
    }

    function getFaust() {
        $new_faust = system($this->faustcmd, $return_var);
        if ($return_var) {
            $strng = "The cmd:" . $this->faustcmd . " is in error: $return_var";
            echo $strng . "\n";
//            throwException($strng);
        }
        return $new_faust;
    }
}