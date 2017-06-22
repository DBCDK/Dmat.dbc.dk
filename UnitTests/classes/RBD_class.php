<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 10-03-2017
 * Time: 15:06
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/marc_class.php";

class RBD_class {

    private $breakpoint;

    function __construct() {
        $this->breakpoint = date('Y') - 6;
    }

    function isRBD(marc $marc) {

        $type = $marc->findSubFields('004', 'a', 1);
        if ($type == 'h' or $type == 'b' or $type == 's') {
            // multi volume
            return false;
        }

        $publishingyear = $marc->findSubFields('008', 'a', 1);
        if (!ctype_digit($publishingyear)) {
            return false;
        }
        if (strlen($publishingyear != 4)) {
            return false;
        }
        if ($publishingyear > $this->breakpoint) {
            return false;
        }
        return true;
    }
}