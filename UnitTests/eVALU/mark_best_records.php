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

function mark_best_records($libv3, $Identifier, $candidates) {

    $startscore = 100;
    if (!$candidates) {
        return $candidates;
    }
//select base, lokalid, bibliotek, type, matchtype
    $return = array();
    for ($i = 0; $i < count($candidates); $i++) {
        $score = $candidates[$i]['matchtype'] * $startscore;
        $base = $candidates[$i]['base'];
        $lokalid = $candidates[$i]['lokalid'];
        $bibliotek = $candidates[$i]['bibliotek'];
        $libv3->set('withAuthor');
        $libv3->set('hbs');
        $libv3->set($base);
        $records = $libv3->getMarcByLokalidBibliotek($lokalid, $bibliotek);

        $fname = "data/" . str_replace(' ', '', $lokalid) . ".$bibliotek";
        if ($records) {
            $strng = "";
            $marc = new marc();
            $cnt = 0;
            $j = 0;
            foreach ($records as $record) {
                $rec = $record['DATA'];

                $marc->fromIso($rec);

                $strng .= utf8_encode($marc->toLineFormat());
                if ($j != $cnt) {
                    $strng .= "------------------\n";
                }
                $j++;
            }
            $fp = fopen($fname, "w");
            fwrite($fp, $strng);
            fclose($fp);

            foreach ($records as $record) {

                $marc->fromIso($record['DATA']);

                $isbns = getIsbnFromF21($marc);


                $f009gs = $marc->findSubFields('009', 'g');
                if ($f009gs[0] == 'xe') {
                    $score += 16;
                }
                $f032xs = $marc->findSubFields('032', 'x');
                $ere = $ebi = $ebo = false;
                foreach ($f032xs as $f032x) {
                    $code = substr($f032x, 0, 3);
                    if ($code == 'ERE') {
                        $ere = true;
                    }
                    if ($code == 'EBI') {
                        $ebi = true;
                    }
                    if ($code == 'EBO') {
                        $ebo = true;
                    }
                }
                if ($ere) {
                    $score += 8;
                } else {
                    if ($ebi) {
                        $score += 4;
                    } else {
                        if ($ebo) {
                            $score += 2;
                        }
                    }
                }
                foreach ($isbns as $isbn) {
                    if ($Identifier == $isbn) {
                        $score += 1;
                        break;
                    }
                }
            }
            $candidates[$i]['score'] = $score;
//            $return[] = $candidates[$i];
        }
    }
    $more = true;
    while ($more) {
        $more = false;
        $highscore = -1;
        $best = -1;
        for ($i = 0; $i < count($candidates); $i++) {
            if ($candidates[$i]['score'] > 0) {
                $more = true;
                if ($candidates[$i]['score'] > $highscore) {
                    $best = $i;
                    $highscore = $candidates[$i]['score'];
                }
            }
        }
        if ($more) {
            $return[] = $candidates[$best];
            $candidates[$best]['score'] = -1;
        }
    }
    return $return;
}
