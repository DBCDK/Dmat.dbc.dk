<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/*
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/marc_class.php";

$m = new marc();

/**
 * Description of merge_class
 *
 * @author hhl
 */
class merge_class
{

    private $to_marc;
    private $from_marc;
    private $removetext;
    private $inserttext;

    function getRemoveText($orgformat, $date) {
        return "Fjernet fra $orgformat $date";
    }

    function getInsertText($orgformat, $date) {
        return utf8_decode("Genoplivet på $orgformat $date");
    }

    /**
     *
     * @param type $marcBasis
     * @return type
     */
    function clean($isomarc) {

        $updated = false;

        $marcBasis = new marc();
        $marcBasis->fromIso($isomarc);

//        $ret = $marcBasis->remFieldText('856', 'z', utf8_decode('Adgang til lån hos ebib.dk'));
//        if ($ret)
//            $updated = true;

        $ret = $marcBasis->remFieldText('856', 'z', utf8_decode('Adgang til lån hos eReolen'));
        if ($ret) {
            $updated = true;
        }

        $ret = $marcBasis->remFieldText('856', 'z', utf8_decode('Adgang til lån hos Netlydbog.dk'));
        if ($ret) {
            $updated = true;
        }

        $ret = $marcBasis->remFieldText('856', 'z', utf8_decode('Adgang til lån for biblioteksbrugere'));
        if ($ret) {
            $updated = true;
        }
        $ret = $marcBasis->remFieldText('856', 'z', utf8_decode('Adgang til lån hos www.ebib.dk for biblioteksbrugere i visse kommuner'));
        if ($ret) {
            $updated = true;
        }

        $ret = $marcBasis->remFieldText('f21', 'l', 'Publizon');
        if ($ret) {
            $updated = true;
        }

//    $ret = $marcBasis->remFieldText('856', 'u', 'http://ebog.dk/apc/direct.php');
//    if ($ret)
//      $update = true;

        return ($marcBasis->toIso());
//    return array('updated' => $updated, 'marc' => $marcBasis);
    }

    function substitutField($field) {
        global $marcBasis, $marcFromXml;
        $f = $marcFromXml->findFields($field);
        if ($f) {
            if ($marcBasis->thisField($field)) {
                $marcBasis->updateField($f[0]);
            } else {
                $marcBasis->insert($f[0]);
            }
        }
    }

    function substitutSubField($field, $subfield, $txt) {
        global $marcBasis;

        $marcBasis->thisField($field);
        $marcBasis->thisSubfield($subfield);
        $marcBasis->updateSubfield($txt);
    }

    function copyField($field) {
        global $marcBasis, $marcFromXml;
        $fs = $marcFromXml->findFields($field);
        foreach ($fs as $f) {
            $marcBasis->insert($f);
        }
    }

    function removeField($field) {
        global $marcBasis;
        while ($marcBasis->remField($field)) {
            ;
        }
    }

    function removeFieldIfnot($field, $subfield, $txt) {
        global $marcBasis;
        $txt = $subfield . $txt;
        $survives = array();
        while ($marcBasis->thisField($field)) {
            $f = $marcBasis->field();
            $sfs = $f['subfield'];
            foreach ($sfs as $sf) {
                if ($sf == $txt) {
                    $survives[] = $f;
                }
            }
        }
        $this->removeField($field);
        foreach ($survives as $survive) {
            $marcBasis->insert($survive);
        }
    }

    function removeSubField($field, $subfields, $txt = array()) {
        global $marcBasis;
        if (count($txt) == 0) {
            $txt[] = '';
        }
        $more = true;
        while ($more) {
            $more = false;
            while ($marcBasis->thisField($field)) {
                for ($i = 0; $i < strlen($subfields); $i++) {
                    $subfield = substr($subfields[$i], 0, 1);
                    while ($marcBasis->thisSubfield($subfield)) {
                        foreach ($txt as $t) {
                            if ($more) {
                                break;
                            }
                            $n = utf8_decode($t);
                            $l = strlen($n);
                            if ($n == substr($marcBasis->subfield(), 0, $l)) {
                                $marcBasis->remSubfield();
                                $more = true;
                                break;
                            }
                        }
                    }
                }
            }
        }
    }

    function copySubfield($field, $subfield, $toField = '', $toSubfield = '') {
        global $marcBasis, $marcFromXml;
        if (!$toField) {
            $toField = $field;
        }
        if (!$toSubfield) {
            $toSubfield = $subfield;
        }
        $subs = $marcFromXml->findSubFields($field, $subfield);
        foreach ($subs as $sub) {
            $marcBasis->insert_subfield($sub, $toField, $toSubfield);
        }
    }

    /**
     *
     * @param type $marcBasis
     * @param type $marcFromXml
     */
    function mergeSmallTemplate($marcBasis, $marcFromXml) {
//        $strB = $marcBasis->toLineFormat();
//        $strX = $marcFromXml->toLineFormat();

        $this->removeField('f06');
        $this->removeField('f21');

        $this->copyField('f06');
        $this->copyField('f21');

//        $strB = $marcBasis->toLineFormat();
//        $strX = $marcFromXml->toLineFormat();

        return $marcBasis;
    }

    /**
     *
     * @param type $marcBasis
     * @param type $marcFromXml
     * @return type
     */
    function mergeTemplate(marc $marcBasis, marc $marcFromXml, $status, $insertyear = '') {

        if ($insertyear) {
            $year = $marcBasis->findSubFields('260', 'c', 1);
            if ($year) {
                $marcFromXml->insert_subfield($year, 'f07', 'c');
            }
        }
        $strng = $marcBasis->toLineFormat();
//        verbose::log(TRACE, "merge_class: marcBasis:\n$strng\n");

        //is the template record (marcBasis) a electronic record
        $electronic = false;
        $f09s = $marcBasis->findSubFields('009', 'g');
        if ($f09s) {
            if ($f09s[0] == 'xe') {
                $electronic = true;
            }
        }


// is it a record with authority links
        $f100s = $marcBasis->findSubFields('100', '5');
        $f700s = $marcBasis->findSubFields('700', '5');

        if (!$f100s and !$f700s) {
            $this->copyField('720');
        }

        $this->substitutField('260');

        $this->removeField('002');
        $this->removeField('014');
        $this->removeField('021');
        $this->removeField('032');
        $this->removeField('250');
        $this->removeField('300');
        $this->removeFieldIfnot('520', '&', '1');
        $this->removeField('521');
        $this->removeField('990');
        $this->removeField('991');
        $this->removeField('d08');
        $this->removeField('d09');
        $this->removeField('d21');
        $this->removeField('d30');
        $this->removeField('d31');
        $this->removeField('d32');
        $this->removeField('d33');
        $this->removeField('d34');
        $this->removeField('d35');
        $this->removeField('d70');
        $this->removeField('d90');
        $this->removeField('d91');
        $this->removeField('f01');
        $this->removeField('n01');
        $this->removeField('n51');
        $this->removeField('s12');
        $this->removeField('z98');
        $this->removeField('z99');


        $this->removeSubField('008', 'a');
        $this->removeSubField('008', 'n');
        $this->removeSubField('008', 'u');
        $this->removeSubField('008', utf8_decode('å'));
        $this->removeSubField('008', '&');
        $this->removeSubField('008', 'z');

        if ($electronic) {
            $f017a = $marcBasis->findSubFields('017', 'a');
            if (!$f017a) {
                $f001s = $marcBasis->findSubfields('001', 'a');
                $marcBasis->insert_subfield($f001s[0], '017', 'a');
            }

//            $this->removeField('520');
            $this->removeField('856');

            $this->removeField('d08');
            $this->removeField('f21');
            $marcBasis->insert_subfield('u', '008', 'u');
        } else {
            $this->copySubfield('008', 'u');
            $this->removeSubField('009', 'g');
            $marcBasis->insert_subfield('xe', '009', 'g');
            $this->removeField('017');
            //  $this->removeField('512');
        }

        $this->removeSubField('512', 'a', array('Beskrivelsen baseret på det fysiske forlæg', 'Downloades i'));
        $this->copyField('512');

        $this->substitutField('001');
        $this->removeSubField('008', 'v');
        $this->copySubfield('008', 'v');
        $this->removeSubField('245', '&');
        $this->removeSubField('440', '&');
        $this->removeSubField('440', 'z');


        $this->removeSubField('840', '&');

        if ($status == 'Template') {
            $this->removeField('f06');
        }
        $this->substitutField('f06');

        $this->copySubfield('008', 'a');
        $this->copySubfield('008', 'n');
        $this->copySubfield('021', 'e');

        $this->copyField('032');
//        $this->copySubfield('001', 'd', 'n51', 'a');

        $this->copyField('f07');
        $this->copyField('d08');
        $this->copyField('d70');
        $this->copyField('f21');
        $this->copyField('z99');

        $marcBasis->thisField('008');
        $f008 = $marcBasis->field();
        $subs = $f008['subfield'];
        sort($subs);
        $newsub = array();
        foreach ($subs as $key => $sub) {
            if (substr($sub, 0, 1) == 't') {
                $newsub[] = $sub;
                $subs[$key] = "";
            }
        }
        foreach ($subs as $key => $sub) {
            if (substr($sub, 0, 1) == 'u') {
                $newsub[] = $sub;
                $subs[$key] = "";
            }
        }
        foreach ($subs as $key => $sub) {
            if ($sub) {
                $newsub[] = $sub;
            }
        }
        $f008['subfield'] = $newsub;
        $this->removeField('008');
        $marcBasis->insert($f008);
        return $marcBasis;
    }

    /**
     *
     * @param type $marcBasis
     * @param type $marcFromXml
     * @param type $orgformat
     * @param type $weekLetterCode
     * @param type $urlPart
     * @param type $wcode
     * @return type
     */
    function merge(marc $marcBasis, marc $marcFromXml, $orgformat, $weekLetterCode, $urlPart, $wcode, $dcode, $costfree, $drop) {

        $updated = false;

        $f856s = $marcFromXml->findFields('856');
        if ($f856s) {
            $f856Inserted = $marcBasis->insertWithoutDublets($f856s[0]);
            if ($f856Inserted) {
                $updated = true;
            }
        }
//        $strng = $marcBasis->toLineFormat();

        /* remove dublicated f21 fields */
        $f21s = $marcBasis->findFields('f21');
        for ($i = count($f21s) - 1; $i >= 0; $i--) {
            $marcBasis->remField('f21', $i);
        }
//        $strng = $marcBasis->toLineFormat();
        foreach ($f21s as $f21) {
            /* have to look af dublets with different hex value (upper and lower case) */
            $subfs = $f21['subfield'];
            foreach ($subfs as $index => $subf) {
                if ($subf[0] == 'f') {
                    $hex = basename($subf);
                    $lhex = strtolower($hex);
                    if ($hex != $lhex) {
                        $subf = str_replace($hex, $lhex, $subf);
                        $f21['subfield'][$index] = $subf;
                        $updated = true;
                    }
                }
            }
            $finserted = $marcBasis->insertWithoutDublets($f21);
        }


        $f21ls = $marcFromXml->findSubFields('f21', 'l');
        $f21l = 'l' . $f21ls[0];
// remove field with same *l as xml
        $f21s = $marcBasis->findFields('f21');
        for ($i = count($f21s) - 1; $i >= 0; $i--) {
            $subs = $f21s[$i]['subfield'];
            foreach ($subs as $sub) {
                if ($sub == $f21l) {
                    $marcBasis->remField('f21', $i);
                    $strng = $marcBasis->toLineFormat();
                }
            }
        }
//        $strng = $marcBasis->toLineFormat();
        $f21s = $marcFromXml->findFields('f21');
        foreach ($f21s as $f21) {
            $f21Inserted = $marcBasis->insertWithoutDublets($f21);
            if ($f21Inserted) {
                $updated = true;
            }
        }
        $strng = $marcBasis->toLineFormat();
// remove if more than 2 from same format
        $cntf21 = 0;
        $remf21 = "";
        while ($marcBasis->thisField('f21')) {
            while ($marcBasis->thisSubField('l')) {
                $sub = $marcBasis->subfield();
                $pos = strstr($sub, $orgformat);
                if ($pos) {
                    $cntf21++;
                }
            }
        }

        if ($cntf21 > 1) {
            $indx = 0;
            $res = $marcBasis->findSubFields('f21', 'al');
            for ($i = 0; $i < count($res); $i = $i + 2) {
                if ($res[$i + 1] == $orgformat) {
                    $pos = strstr($res[$i], '.pdf');
                    if ($pos) {
                        $marcBasis->remField('f21', $indx);
                        $updated = true;
                        $cntf21--;
                        break;
                    }
                }
                $indx++;
            }
        }
        if ($cntf21 > 1) {
            $indx = 0;
            $res = $marcBasis->findSubFields('f21', 'lf');
            for ($i = 0; $i < count($res); $i = $i + 2) {
                if ($res[$i] == $orgformat) {
                    $pos = strstr($res[$i + 1], '/Media/covers/originals');
                    if ($pos) {
                        $marcBasis->remField('f21', $indx);
                        $updated = true;
                        $cntf21--;
                        break;
                    }
                }
                $indx++;
            }
        }
        if ($cntf21 > 1) {
            $strng = $marcBasis->toLineFormat();
            verbose::log(ERROR, "more than one f21 fields. cntf21:$cntf21 \n$strng");
        }
        $strng = $marcBasis->toLineFormat();

        $fz98cnt = 0;
        while ($fz98s = $marcBasis->findSubFields('z98', 'a')) {
            $marcBasis->remField('z98');
            $fz98cnt++;
        }
        if ($fz98cnt != 1) {
            $updated = true;
        }

        $thisWC = date('YW');
        $z98 = true;
        $DBR = faLse;
        while ($marcBasis->thisField('032')) {
            while ($marcBasis->thisSubfield('a')) {
                $code = substr($marcBasis->subfield(), 0, 3);
                if ($code == 'DBR') {
                    $DBR = substr($marcBasis->subfield(), 3);
                }
                if ($code == 'DBF' or $code == 'DLF') {
                    if (substr($marcBasis->subfield(), 3, 2) != '99') {
                        if (substr($marcBasis->subfield(), 3) >= $thisWC) {
                            $z98 = false;
                        }
                    }
                }
            }
        }
        if ($z98) {
            if ($weekLetterCode != 'ERE' and $weekLetterCode != 'ERL') {
                $z98Inserted = $marcBasis->insertWithoutDublets($marcBasis->stringToField('z98 00*aMinus korrekturprint'));
            }
        }


        $fz99s = $marcFromXml->findFields('z99');
        $fz99Inserted = $marcBasis->insertWithoutDublets($fz99s[0]);
        if ($fz99Inserted) {
            $updated = true;
        }


        // if there is no ERA code and costfree is true, insert a ERA code
        $weekCodes = $marcBasis->findSubFields('032', 'x');
        $ERAexsist = 'false';
        foreach ($weekCodes as $weekCode) {
            $code = substr($weekCode, 0, 3);
            if ($code == 'ERA') {
                $ERAexsist = 'true';
            }
        }
        if ($DBR) {
            if ($DBR = '999999') {
                $erawcode = $DBR;
            } else {
                $erawcode = $dcode;
            }
        } else {
            $erawcode = $wcode;
        }
//        $weekCodes = $marcBasis->findSubFields('032', 'a');
//        foreach ($weekCodes as $weekCode) {
//            $code = substr($weekCode, 0, 3);
//            if ($code == 'DBF' or $code == 'DLF') {
//                if (substr($weekCode, 3) > $erawcode) {
//                    $erawcode = substr($weekCode, 3);
//                }
//            }
//        }
//        $strng = $marcBasis->toLineFormat();
        if ($ERAexsist == 'false' and $costfree == 'true') {
            $marcBasis->insert_subfield("ERA$erawcode", '032', 'x');
            $updated = true;
        }
        if ($ERAexsist == 'true' and $costfree == 'false') {
            $marcBasis->remSubfieldText('032', 'x', 'ERA');
            $updated = true;
        }


        if ($drop) {
            $marcBasis->remSubfieldText('032', 'x', $weekLetterCode);
            $weekC = $weekLetterCode . $wcode;
            $marcBasis->insert_subfield($weekC, '032', 'x');
            $strng = $marcBasis->toLineFormat(78);
        }
// if the basis record already have the correct production code - return to the calling program

        $weekCodes = $marcBasis->findSubFields('032', 'x');
        foreach ($weekCodes as $weekCode) {
            $code = substr($weekCode, 0, 3);
            if ($code == $weekLetterCode) {
                return array('updated' => $updated, 'marc' => $marcBasis);
            }
        }

        $updated = true;

        if ($weekLetterCode != 'non') {
            $c = $dcode;
            if ($DBR == '999999') {
                $c = $DBR;
            }
            if ($DBR) {
                $weekC = $weekLetterCode . $c;
            } else {
                $weekC = $weekLetterCode . $wcode;
            }
            $marcBasis->insert_subfield($weekC, '032', 'x');
        }
//        $weekC = 'DAT' . $dcode;
//        $marcBasis->insert_subfield($weekC, '032', 'x');
// look if 856  is present, otherwise insert it
        $f856s = $marcFromXml->findFields('856');
        if ($f856s) {
            $marcBasis->insertWithoutDublets($f856s[0]);
        }

// Comment wrong? --> is there a d08 *a Starting with the text: "Fjernet fra "; remove it

        $removed = "Fjernet fra $orgformat";
        $removed = $this->getRemoveText($orgformat, '');

        $rem = false;
        $d08s = $marcBasis->findSubFields('d08', 'a');
        foreach ($d08s as $d08) {
//      echo "removed:$removed " . substr($d08, 0, strlen($removed)) . "\n";
            if (substr($d08, 0, strlen($removed)) == $removed) {
                $date = date('d/m/Y');
                $remtxt = $this->getRemoveText($orgformat, $date);
                $instxt = $this->getInsertText($orgformat, $date);
                $rem = true;
                while ($marcBasis->thisField('d08')) {
                    while ($marcBasis->thisSubField('a')) {
                        if ($marcBasis->subfield() == $remtxt) {
                            $marcBasis->RemSubfield();
                            $rem = false;
                            $updated = true;
                        }
                    }
                }
            }
        }
        if ($rem) {
            $marcBasis->insert_subfield($instxt, 'd08', 'a');
        }

        return array('updated' => $updated, 'marc' => $marcBasis);
    }

    function convert2printedFormat(marc $marcBasis, $expisbn, $f512) {
        $this->substitutSubField('008', 'v', '7');
        $this->substitutSubField('009', 'g', 'xx');
        $this->substitutSubField('021', 'e', $expisbn);
        $this->removeSubField('008', 'n');
        $this->removeField('250');
        if (!$f512) {
            $this->removeField('512');
        }
        $this->removeField('f21');

        $marcBasis->insert_field('Minus korrekturprint', 'z98', 'a');

        $fields = $marcBasis->findSubFields('260', 'b');
        $txt = $fields[0];
        $marcBasis->remField('260');
        $marcBasis->insert_field($txt, '260', 'b');

        return ($marcBasis->toIso());

    }

    /**
     *
     * @param type $marcBasis
     * @param type $marcFromXml
     * @param type $weekLetterCode
     * @param type $urlPart
     * @param type $orgformat
     * @param type $wcode
     * @return type
     */
    function removeData($marcBasis, $marcFromXml, $weekLetterCode, $urlPart, $orgformat, $wcode, $dcode, $costfree) {
        $updated = false;

//        $astrng = $marcBasis->toLineFormat();

        $f032 = false;
        while ($marcBasis->thisField('032')) {
            while ($marcBasis->thisSubfield('x')) {
                if (substr($marcBasis->subfield(), 0, 3) == $weekLetterCode) {
                    $marcBasis->remSubfield();
                    $f032 = true;
                }
            }
        }
        if ($f032) {
            $updated = true;
        }

        while ($marcBasis->remSubfieldText('032', 'x', 'ERA')) {
            $updated = true;
        }

        if ($f032) {
            // insert a field d08 saying that the week-code have been removed at date $dato
            // and insert a DAT week-code with the same date
            $dato = date('d/m/Y');
            $datdate = 'DAT' . $dcode;
            $insertDAT = true;
            while ($marcBasis->thisField('032')) {
                while ($marcBasis->thisSubField('x')) {
//          echo "datdate:$datdate, " . $marcBasis->subfield() . "\n";
                    if ($marcBasis->subfield() == $datdate) {
                        $insertDAT = false;
                    }
                }
            }
            if ($insertDAT) {
                $marcBasis->insert_subfield($datdate, '032', 'x');
            }

            $remtxt = $this->getRemoveText($orgformat, $dato);
            $instxt = $this->getInsertText($orgformat, $dato);
            $insert = true;
            while ($marcBasis->thisField('d08')) {
                while ($marcBasis->thisSubField('a')) {
                    if ($marcBasis->subfield() == $instxt) {
                        $marcBasis->RemSubfield();
                        $insert = false;
                        $updated = true;
                    }
                }
            }

            if ($insert) {
                $marcBasis->insert_subfield($remtxt, 'd08', 'a');
                $updated = true;
            }
        }
        while ($fz98s = $marcBasis->findSubFields('z98', 'a')) {
            $marcBasis->remField('z98');
        }


        $z98Inserted = $marcBasis->insertWithoutDublets($marcBasis->stringToField('z98 00*aMinus korrekturprint'));

        while ($marcBasis->thisField('z99')) {
            while ($marcBasis->thisSubField('a')) {
                if ($marcBasis->subfield() == $orgformat) {
                    $marcBasis->remSubField();
                }
            }
        }


        return array('updated' => $updated, 'marc' => $marcBasis);
    }

    /**
     *
     * @param type $marcBasis
     * @param type $orgMarc
     */
    function afterPhus($isomarc) {
        $marcBasis = new marc();

        $marcBasis->fromIso($isomarc);
        //if non WeekCodes make the record a delete record
        $f032 = false;
        while ($marcBasis->thisField('032')) {
            while ($marcBasis->thisSubfield('x')) {
                $code = substr($marcBasis->subfield(), 0, 3);
                if ($code == 'ERE' || $code == 'NLY' || $code == 'ERL') {
                    $f032 = true;
                }
            }
        }
        if (!$f032) {
            while ($marcBasis->thisField('004')) {
                while ($marcBasis->thisSubfield('r')) {
                    $marcBasis->updateSubfield('d');
                }
            }
        }

        // remove 032*x DAT
        while ($marcBasis->remSubfieldText('032', 'x', 'DAT')) {
            ;
        }
        return $marcBasis->toIso();
    }

    function AfterAll($isomarc, $Drop, $wcode) {
        $marcBasis = new marc();
        $marcBasis->fromIso($isomarc);

        $tereol = utf8_decode('Adgang til lån hos eReolen.dk');
        $cebib = $cereol = 0;
        $subs = $marcBasis->findSubFields('856', 'z');
        foreach ($subs as $key => $sub) {
            if ($sub == $tereol) {
                $cereol++;
            }
        }
        if ($cereol == 1) {
            while ($marcBasis->thisField('856')) {
                $found = false;
                while ($marcBasis->thisSubfield('z')) {
                    if ($marcBasis->subfield() == $tereol) {
                        $found = true;
                        while ($marcBasis->thisSubfield('y')) {
                            $marcBasis->remSubfield();
                        }
                    }
                    if ($found) {
                        break;
                    }
                }
            }
        }

        //       •	Slet alle DAT mindre end den yngste DAT
        //             o	 *xDAT201340*xDAT201515  slet DAT201340
        // NEJ gælder ikke mere      •	Slet DAT hvis:
        //             o	DAT == DLF el. DBF
        //


        // hvis DAT starter med '99' skal den ikke indgå i fjernelse af gamle DAT'er
        $dat = '';
        $dat99s = array();
        $f032xs = $marcBasis->findSubFields('032', 'x');
        foreach ($f032xs as $f032x) {
            $code = substr($f032x, 0, 3);
            if ($code == 'DAT') {
                if (substr($f032x, 3, 2) != '99') {
                    if ($dat < $f032x) {
                        $dat = $f032x;
                    }
                } else {
                    $dat99s[] = $f032x;
                }
            }
        }

//        $f032as = $marcBasis->findSubFields('032', 'a');
//        $alike = false;
//        foreach ($f032as as $f032a) {
//            if (substr($f032a, 3) == substr($dat, 3)) {
//                $alike = true;
//            }
//        }
        // delete only DAT not starting with DAT99
//        $strng = $marcBasis->toLineFormat();
        while ($marcBasis->remSubfieldText('032', 'x', 'DAT')) {
            ;
        }
//        if (strlen($dat) and ! $alike) {
//        $strng = $marcBasis->toLineFormat();
        if (strlen($dat)) {
            $marcBasis->insert_subfield($dat, '032', 'x');
        }
//        $strng = $marcBasis->toLineFormat();
        foreach ($dat99s as $dat99) {
            $marcBasis->insert_subfield($dat99, '032', 'x');
        }

//        $fs = $marcBasis->findSubFields('032', 'x');
//        if ($fs) {
//            foreach ($fs as $f) {
//                $code = substr($f, 0, 3);
//                if ($code == 'ERE' or code == 'ERL' or code == 'NLY' or code == 'NLL') {
//                    if (substr($f, 3) == '999999') {
//                        $marcBasis->substitute('032', 'x', "$code" . $wcode);
//                    }
//                }
//            }
//        }
//        $strng = $marcBasis->toLineFormat();


        if ($Drop) {
            $marcBasis->substitute('008', 'v', '5');
//            $marcBasis->substitute('008', 'l', 'und');
            $marcBasis->remSubfieldText('032', 'x', 'ACC');
            $marcBasis->insert_field(utf8_decode('Maskingenereret post baseret på data fra eReolen'), '512', 'a');
            $marcBasis->insert_field(utf8_decode('Uden klassemærke'), '652', 'm');
            $marcBasis->remSubfieldText('720', '4', '');
            $publisher = $marcBasis->findSubFields('260', 'b', 1);
            if (strtolower($publisher) == 'edition wilhelm hansen') {
                $marcBasis->substitute('009', 'a', 'c');
            } else {
                $marcBasis->insert_field('Registreres ikke i: Dansk bogfortegnelse', '512', 'a');
            }
//            $hit = $marcBasis->findSubFields('d70', 'b');
            $marcBasis->remField('d70');
//            $marcBasis->insert_subfield('Publizon', 'd70', 'b');
            $marcBasis->insert_field('Publizon', 'd70', 'b');
            $strng = $marcBasis->toLineFormat();
        }
        return $marcBasis->toIso();
    }

    /**
     *
     * @global type $nothing
     * @param type $isomarc
     * @param type $weekcode
     * @return type
     */
    function insertDAT($isomarc, $weekcode) {
        global $nothing;

        $insert = true;
        $marc = new marc();
        $marc->fromIso($isomarc);
        $dats = $marc->findSubFields('032', 'x');
        if ($dats) {
            foreach ($dats as $dat) {
                if (substr($dat, 0, 3) == 'DAT') {
                    $w = substr($dat, 3, 6);
                    if ($w == $weekcode) {
                        $insert = false;
                    }
                }
            }
        }
        if ($insert) {
            $marc->insert_subfield('DAT' . $weekcode, '032', 'x');
            return $marc->toIso();
        }
        return $isomarc;
    }

    /**
     *
     * @global type $nothing
     * @param type $isomarc
     * @param type $orgmarc
     * @return boolean
     */
    function alike($isomarc, $orgmarc) {
        global $nothing;

        $marc = new marc();
        $marc->fromIso($isomarc);
        $ISOstrng = str_replace('/Media/', '/media/', $marc->toLineFormat());
        $ISOarr = explode("\n", $ISOstrng);
        if ($nothing) {
            echo "ISO:\n$ISOstrng\n";
        }
        $marc->fromIso($orgmarc);
        $ORGstrng = str_replace('/Media/', '/media/', $marc->toLineFormat());
        if ($nothing) {
            echo "ORG:\n$ORGstrng\n";
        }
        $ORGarr = explode("\n", $ORGstrng);
        $diff = array_diff($ORGarr, $ISOarr);
        if ($nothing) {
            echo "Diff result:\n";
            print_r($diff);
            $diff2 = array_diff($ISOarr, $ORGarr);
            print_r($diff2);
            if (!$diff) {
                echo "NOT DIFF\n";
                if ($diff2) {
                    echo "KUN DIFF2\n";
                }
            }
        }

        if ($ORGstrng == $ISOstrng) {
            if ($nothing) {
                echo "De er ens\n";
            }
            return true;
        } else {
            $ret = false;
// $kun856 = true;
            $kun856 = false;
            if ($kun856) {
                $ret = true;
                $diff3 = array();
                foreach ($diff2 as $d) {
                    if (substr($d, 0, 1) != 'z') {
                        $diff3[] = $d;
                    }
                }
                $diff2 = $diff3;
                foreach ($diff as $ln) {
                    if (substr($ln, 0, 3) == '856') {
                        $pos1 = strpos($ln, 'Netlydbog.dk');
                        $pos2 = strpos($ln, 'eReolen.dk');
                    }
                    if ($pos1 + $pos2) {
                        $ret = false;
                    }
                }
                foreach ($diff2 as $ln) {
                    if (substr($ln, 0, 3) == '856') {
                        $pos1 = strpos($ln, 'Netlydbog.dk');
                        $pos2 = strpos($ln, 'eReolen.dk');
                    }
                    if ($pos1 + $pos2) {
                        $ret = false;
                    }
                }

                $xx = 0;
                if (count($diff) == 1) {
                    if (count($diff2) == 1) {
                        foreach ($diff as $d) {
                            if (substr($d, 0, 3) == '856') {
                                $xx++;
                            }
                        }
                        foreach ($diff2 as $d) {
                            if (substr($d, 0, 3) == '856') {
                                $xx++;
                            }
                        }
                    }
                }
                if ($xx == 2) {
                    $ret = true;
                }
            }
            if ($nothing) {
                echo "De er forskellige (ret:$ret)\n";
            }
            return $ret;
        }
//    echo "ISO;\n$isomarc\nORG:\n$orgmarc\n";
//    if ($isomarc == $orgmarc) {
//
//      return true;
//    }
//    else {
//      return false;
//    }
    }


    function mergeRBD(marc $marcB, marc $marcX) {
        global $marcBasis, $marcFromXml;

        $marcBasis = $marcB;
        $marcFromXml = $marcX;


        $bstrng = $marcFromXml->toLineFormat(78);
        $cstrng = $marcBasis->toLineFormat(78);
        if ($cstrng == "$\n") {
            return $marcFromXml;
        }
        $txt520 = "Digitalisering af bogen:";
        $marcBasis->remSubFieldText('250', 'b', chr(247));

        $f250s = $marcBasis->findFields('250');
        $marcBasis->remField('250');
        foreach ($f250s as $f250) {
            $subs = $f250['subfield'];
            foreach ($subs as $key => $sub) {
                $f250['subfield'][$key] = str_replace(array(']', '['), '', $sub);
                $marcBasis->insert($f250);
            }
        }

        $fs = $marcBasis->findSubFields('250', 'b', 1);
        if ($fs) {
            if (substr($fs, 0, 1) == chr(247)) { // 247 == ÷
                $fs = null;
            }
        }
        if (!$fs) {
            $fs = $marcBasis->findSubFields('250', 'a', 1);
        }
        if ($fs) {
            $txt520 .= " $fs.";
        }
        $fs260a = $marcBasis->findSubFields('260', 'a', 1);
        if ($fs260a) {
            $txt520 .= " $fs260a";
        }
        $fs260b = $marcBasis->findSubFields('260', 'b', 1);
        if ($fs260b) {
            if ($fs260a) {
                $txt520 .= " :";
            }
            $txt520 .= " $fs260b";
        }
        $fs = $marcBasis->findSubFields('008', 'a', 1);
        if ($fs) {
            if ($fs260a or $fs260b) {
                $txt520 .= ",";
            }
            $txt520 .= " $fs";
        }
        $txt520 = str_replace(array(']', '['), '', $txt520);
        $txtfaust = $marcBasis->findSubFields('001', 'a', 1);
        $txtisbn = $marcBasis->findSubFields('021', 'e', 1);
        if (!$txtisbn) {
            $txtisbns = $marcBasis->findSubFields('021', 'a');
            if ($txtisbns) {
                $txtisbn = $txtisbns[0];
            }
        }
        if (!$txtisbn) {
            $txtisbn = $txtfaust;
        }

        $this->substitutField('001');
        $this->removeField('002');

        $this->removeSubField('008', '&');
        $this->removeSubField('008', 'r');
        $this->removeSubField('008', 'z');
        $this->removeSubField('008', 'd', array('å'));
        $this->removeSubField('008', 'u');
        $marcBasis->insert_subfield('f', '008', 'u');
        $this->removeSubField('008', 'v');
        $this->copySubfield('008', 'v');
        $this->removeSubField('008', 'a');
        $this->copySubfield('008', 'a');
        $this->removeSubField('008', 'n');
        $marcBasis->insert_subfield('b', '008', 'n');
        $marcBasis->changeOrderSubfields('008', 'tuabdjlnxvw');
        $this->removeSubField('009', 'g');
        $this->copySubfield('009', 'g');

        $this->removeField('014');
        $this->removeField('017');
        $this->removeField('021');
        $this->copySubfield('021', 'a');
        $this->copySubfield('021', 'e');

        $this->substitutField('032');
//        while ($marcBasis->thisField('032')) {
//            while ($marcBasis->thisSubfield('a')) {
//                if (substr($marcBasis->subfield(), 0, 3) == 'DBR') {
//                    $marcBasis->updateSubfield('DBR999999');
//                    $st1 = $marcBasis->toLineFormat(72);
//                }
//            }
//        }

        $this->removeSubField('245', '&');
        $this->substitutField('260');
        $this->removeSubField('300', 'ncdel');
        $this->removeSubField('440', '&z');
        $this->copyField('512');
        $marcBasis->remSubfieldText('512', 'a', "Maskinelt dannet beskrivelse");
        $f = utf8_decode("Maskinelt dannet beskrivelse baseret på distributørdata og beskrivelsen af det fysiske forlæg");
        $marcBasis->insert_field($f, '512', 'a');

        $f5xxs = $marcBasis->findFields('5xx');
        if ($f5xxs) {
            foreach ($f5xxs as $f5xx) {
                $this->removeField($f5xx['field']);
            }
        }
        $f6xxs = $marcBasis->findFields('6xx');
        if ($f6xxs) {
            foreach ($f6xxs as $f6xx) {
                if ($f6xx['field'] != '652' and $f6xx['field'] != '654') {
                    $this->removeField($f6xx['field']);
                }
            }
        }
//        $str = $marcBasis->toLineFormat(78);
//        $f520s = $marcBasis->findFields('520');
//        $this->removeField('520');
        $marcBasis->insert_subfield($txt520, '520', 'a');
        $marcBasis->insert_subfield($txtfaust, '520', 'n');
        $marcBasis->insert_subfield($txtisbn, '520', 'r');

        if ($f5xxs) {
            foreach ($f5xxs as $f5xx) {
                $ins = true;
                $subs = $f5xx['subfield'];
                foreach ($subs as $key => $sub) {
                    if (substr($sub, 0, 2) == '&1') {
                        $ins = false;
                        break;
                    }
                    $f5xx['subfield'][$key] = str_replace(array(']', '['), '', $sub);
                }
                // alle 5xx felter skal fjernes hvis det indeholder delfelt & med "1", undtagen 520, der er det omvendt.
                if ($f5xx['field'] == '520') {
                    if ($ins) {
                        $ins = false;
                    } else {
                        $ins = true;
                    }
                }
                if ($ins or $f5xx['field'] == '532') {
                    $marcBasis->insert($f5xx);
                }
            }
        }


        $this->removeField('521');
        $this->removeField('720');
        $this->removeField('990');
        $this->removeField('991');
        $this->removeField('d08');
        $this->removeField('d09');
        $this->removeField('d30');
        $this->removeField('d31');
        $this->removeField('d32');
        $this->removeField('d33');
        $this->removeField('d34');
        $this->removeField('d35');
        $this->removeField('d90');
        $this->removeField('n51');
        $this->removeField('s12');
        $this->removeField('z98');
        $this->removeField('z99');
        $marcBasis->insert_subfield('Publizon', 'z99', 'a');

        $this->removeField('f06');

        $this->copyField('d08');
        $this->copyField('d70');
        $this->copyField('f21');

        return $marcBasis;
//        $astrng = $marcBasis->toLineFormat(78);
//        $iso = $marcBasis->toIso();
//        return $iso;
    }
}

?>
