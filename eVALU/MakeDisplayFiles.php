<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
function getIsbnFromF21($marc) {
    $isbns = array();
    $f21s = $marc->findSubFields('f21', 'a');
    foreach ($f21s as $f21) {
        $path_parts = pathinfo($f21);
        $isbns[] = trim(basename($f21, $path_parts['extension']), '.');
    }
    return $isbns;
}

function checkDB($libv3, $base, $lokalid, $bibliotek) {
    $libv3->set($base);
    $records = $libv3->getMarcByLokalidBibliotek($lokalid, $bibliotek);
    if ($records) {
        $marc = new marc();
        $marc->fromIso($records[0]['DATA']);
//    $lektoer = false;
//    $f06s = $marc->findSubFields('f06', 'b');
//    foreach ($f06s as $f06) {
//      if (strtolower($f06) == 'l') {
//        $lektoer = true;
//      }
//    }
//    $f990s = $marc->findSubFields('990', 'b');
//    foreach ($f990s as $f990) {
//      if (strtolower($f990) == 'l') {
//        $lektoer = true;
//      }
//    }
        $lektoer = $marc->HasLektoer();
        $f009gs = $marc->findSubFields('009', 'g');
        if ($f009gs[0] == 'xe') {
            return array(351, $lektoer);
        } else {
            return array(350, $lektoer);
        }
    }
    return false;
}

function MakeDisplayFiles($libv3, $candidates, $datadir) {

    return;
    if (!$candidates) {
        return $candidates;
    }
//select base, lokalid, bibliotek, type, matchtype
    $return = array();
    for ($i = 0; $i < count($candidates); $i++) {
        $base = $candidates[$i]['base'];
        $lokalid = $candidates[$i]['lokalid'];
        $bibliotek = $candidates[$i]['bibliotek'];
        $libv3->set('withAuthor');
        $libv3->set('hsb');
        $libv3->set($base);
        $records = $libv3->getMarcByLokalidBibliotek($lokalid, $bibliotek);

        $fname = "$datadir/" . str_replace(' ', '', $lokalid) . ".$bibliotek";
        if ($records) {
            $strng = "";
            $marc = new marc();
            $j = 0;
            foreach ($records as $record) {
                $rec = $record['DATA'];

                $marc->fromIso($rec);
                if ($j > 0) {
                    $strng .= "---------------------\n";
                }
                $strng .= utf8_encode($marc->toLineFormat(78));

                $j++;
            }
            $fp = fopen($fname, "w");
            fwrite($fp, $strng);
            fclose($fp);

//            $return[] = $candidates[$i];
        }
    }
}
