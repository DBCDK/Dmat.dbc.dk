<?php
/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *
 * This file is part of Open Library System.
 * Copyright © 2009, Dansk Bibliotekscenter a/s,
 * Tempovej 7-11, DK-2750 Ballerup, Denmark. CVR: 15149043
 *
 * Open Library System is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Open Library System is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Open Library System.  If not, see <http://www.gnu.org/licenses/>.
 */

/**
 * \brief materialId singleton class for crunching material identification numbers:
 *          o ISBN (in this context: 10 character ISBN)
 *          o EAN (also known as 13 digit ISBN)
 *          o ISSN
 *          o Faust
 *
 * Usage:
 *  $normalizedIsbn = materialId::normalizeISBN($isbn);
 *  $normalizedEan = materialId::normalizeEAN($ean);
 *  $normalizedIssn = materialId::normalizeISSN($issn);
 *  $normalizedFaust = materialId::normalizeFaust($faust);
 *  $normalizedFaustWithSpaces = matrialId::normalizeFaust($faust,true);
 *  if (materialId::validateISBN($isbn)) { ... }
 *  if (materialId::validateEAN($ean)) { ... }
 *  if (materialId::validateISSN($issn)) { ... }
 *  if (materialId::validateFaust($faust)) { ... }
 *  $isbn13 = materialId::convertISBNToEAN($isbn);
 *
 */
class materialId {

    private function __construct() {

    }

    private function __destruct() {

    }

    private function __clone() {

    }

    /** \brief
     * @param $isbn string The ISBN number to normalize
     * @retval string The normalized ISBN number
     * */
    public static function normalizeISBN($isbn) {
        $res = array();
        foreach (preg_split('//', $isbn, 0, PREG_SPLIT_NO_EMPTY) as $c) {
            switch ($c) {
            case 'x':
            case 'X':
                if ((count($res) == 0) or (count($res) == 9)) {  // 'X' only allowed in first or last position
                    $res[] = 'X';
                }
                break;
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                $res[] = $c;
                break;
            default:
                if (!strpbrk(" \t-", $c)) {  // Whitespace and dash is legal
                    break 2;  // If any illegal characters are found, we stop searching => exit loop
                }
            }
        }
        return implode($res);
    }

    /** \brief
     * @param $isbn string The isbn to validate
     * @retval string The validated ISBN number if valid, otherwise 0 is returned
     * */
    public static function validateISBN($isbn) {
        $arr = array();
        if (strlen($isbn) != 10)
            return 0;
        $i = 0;
        foreach (str_split($isbn) as $c) {
            if (strtoupper($c) == 'X') {
                $arr[] = 10;
            }
            if (is_numeric($c)) {
                $arr[] = $c;
            }
        }
        if (count($arr) != 10)
            return 0;
        $sum = 0;
        for ($i = 10; $i; $i--) {
            $sum = $sum + ($arr[$i - 1] * $i);
        }
        if ($sum % 11)
            return 0;
        return $isbn;
    }

    /** \brief
     * @param $ean string The EAN number to normalize
     * @retval string The normalized EAN number
     * */
    public static function normalizeEAN($ean) {
        $res = array();
        foreach (preg_split('//', $ean, 0, PREG_SPLIT_NO_EMPTY) as $c) {  // Remove any characters except numbers
            switch ($c) {
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                $res[] = $c;
                break;
            default:
                if (!strpbrk(" \t-", $c)) {  // Whitespace and dash is legal
                    break 2;  // If any illegal characters are found, we stop searching => exit loop
                }
            }
        }
        return implode($res);
    }

    /** \brief
     * @param $ean string The EAN number to validate
     * @retval string The validated EAN number if valid, otherwise 0 is returned
     * */
    public static function validateEAN($ean) {
        if (strlen($ean) != 13)
            return 0;
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            if (!is_numeric($ean[$i]))
                return 0;
            $sum += (($i & 1) ? 3 : 1) * ($ean[$i] - '0');
        }
        if ($sum % 10)
            return 0;
        return $ean;
    }

    /** \brief
     * @param $issn string The ISSN number to normalize
     * @retval string The normalized ISSN number
     * */
    public static function normalizeISSN($issn) {
        $res = array();
        foreach (preg_split('//', $issn, 0, PREG_SPLIT_NO_EMPTY) as $c) {
            switch ($c) {
            case 'x':
            case 'X':
                if (count($res) == 7) {  // 'X' only allowed in last position
                    $res[] = 'X';
                }
                break;
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                $res[] = $c;
                break;
            default:
                if (!strpbrk(" \t-", $c)) {  // Whitespace and dash is legal
                    break 2;  // If any illegal characters are found, we stop searching => exit loop
                }
            }
        }
        return implode($res);
    }

    /** \brief
     * @param $issn string The ISSN number to validate
     * @retval string The validated ISSN number if valid, otherwise 0 is returned
     * */
    public static function validateISSN($issn) {
        if (strlen($issn) != 8)
            return 0;
        $sum = 0;
        $vgt = 8;
        for ($i = 0; $i < 7; $i++) {
            $abe = ($issn[$i] - '0') * ($vgt - $i);
            $sum += ($issn[$i] - '0') * ($vgt - $i);
        }
        if ($issn[7] == 'X') {
            $sum += 10;
        } else {
            $sum += $issn[7] - '0';
        }
        if ($sum % 11)
            return 0;
        return $issn;
    }

    /** \brief
     * @param $faust string The Faust number to normalize
     * @param $withSpaces boolean
     * @retval string The normalized Faust number
     * */
    public static function normalizeFaust($faust, $withSpaces = false) {
        $res = array();
        foreach (preg_split('//', $faust, 0, PREG_SPLIT_NO_EMPTY) as $c) {  // Remove any characters except numbers
            switch ($c) {
            case '0':
            case '1':
            case '2':
            case '3':
            case '4':
            case '5':
            case '6':
            case '7':
            case '8':
            case '9':
                $res[] = $c;
                break;
            default:
                if (!strpbrk(" \t-", $c)) {  // Whitespace and dash is legal
                    break 2;  // If any illegal characters are found, we stop searching => exit loop
                }
            }
        }
        $ln = count($res);
        if ($ln < 7 || $ln > 8)
            return false;
        if ($withSpaces) {
            $res[$ln - 1] = ' ' . $res[$ln - 1];
            $res[$ln - 4] = ' ' . $res[$ln - 4];
            $res[$ln - 7] = ' ' . $res[$ln - 7];
        }
        if (count($res) == 7) {
            return '0' . implode($res);
        } else {
            return implode($res);
        }
    }

    /** \brief
     * @param $faust string The Faust number to validate
     * @retval mixed The validated Faust number if valid, otherwise 0 is returned
     * */
    public static function validateFaust($faust) {
        if (strlen($faust) != 8)
            return 0;
        $vgt = 2;
        for ($i = 0; $i < 8; $i++) {
            @ $sum += ($faust[$i] - '0') * ($vgt - $i);
            if ($i == 0) {
                $vgt = 8;
            }
        }
        if ($sum % 11) {
            return 0;
        } else {
            return $faust;
        }
    }

    /** \brief
     * @param $isbn string The isbn to convert
     * @retval mixed The ISBN as an EAN (ISBN13) number
     * */
    public static function convertISBNToEAN($isbn) {
        if (strlen($isbn) != 10)
            return 0;
        $ean = "978" . $isbn;
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            if (strtoupper($ean[$i]) == 'X') {
                $sum += 10;
            } else {
                $sum += (($i & 1) ? 3 : 1) * ($ean[$i] - '0');
            }
        }
        $checkciffer = ($ean[12] - '0') - ($sum % 10);
        if ($checkciffer < 0) {
            $checkciffer += 10;
        }
        $ean[12] = $checkciffer;
        return $ean;
    }

    /** \brief convert a ean (ISBN13) to a isbn (ISBN10)
     * @param $ean string To isbn
     * @retval mixed - integer or boolean
     */
    public static function convertEANToISBN($ean) {
        if (strlen($ean) != 13)
            return 0;
        $isbn = substr($ean, 3, 9);
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            if (strtoupper($isbn[$i]) == 'X') {
                $sum += 10;
            } else {
                $sum += $isbn[$i] * ($i + 1);
            }
        }
        $checkciffer = ($sum % 11);
        if ($checkciffer == 10)
            $checkciffer = 'x';
        $isbn[9] = $checkciffer;
        return $isbn;
    }

}

?>
