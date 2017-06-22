<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";
require_once "$inclnk/OLS_class_lib/marc_class.php";
//require_once "$inclnk/OLS_class_lib/pg_database_class.php";
require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
require_once "$inclnk/OLS_class_lib/LibV3API_class.php";

class GetFaust {

    public $libv3;

    public function __construct($libv3) {
        $this->libv3 = $libv3;
    }

    function GetHeadRecord(marc $marc) {
//  global $libv3;

        $lokalid = $marc->findSubFields('014', 'a', 1);
        $bibliotek = $marc->findSubFields('001', 'b', 1);
        $res = $this->libv3->getMarcByLokalidBibliotek($lokalid, $bibliotek);

        if (count($res) == 0)
            return;

        $data = $res[0]['DATA'];
        $marc->fromIso($data);
        $bind = $marc->findSubFields('004', 'a', 1);
        if ($bind == 'b' || $bind == 's') {
            $this->GetHeadRecord($marc);
        }
        return;
    }

    function retrive($marc, $isbn13, $basistitle, $status, $nothing = false) {
//    global $nothing;

        $retfaust = array();

        foreach ($marc as $isomarc) {
            $found = false;

            $basis_marc = new marc;
            $basis_marc->fromIso($isomarc['DATA']);

            if ($nothing) {
                echo $basis_marc->toLineFormat() . "+++++++++++++++++++\n";
            }

            $multvol = $basis_marc->findSubFields('004', 'a', 1);
            if ($multvol == 'b') {
                $head_marc = new marc();
                $head_marc->fromIso($isomarc['DATA']);
                $this->GetHeadRecord($head_marc);
            } else {
                $head_marc = $basis_marc;
            }

            if ($nothing) {
                echo $head_marc->toLineFormat() . "*******************\n";
                echo "basis_marc:" . $basis_marc->toLineFormat() . "*******************\n";
            }

            $mat_codes = $head_marc->findSubFields('009', 'g');
            $right_mat_code = true;
            if (!$mat_codes) {
                $right_mat_code = false;
            } else {
                foreach ($mat_codes as $mat_code) {
                    if ($mat_code != 'xe') {
                        $right_mat_code = false;
                    }
                }
            }
            if (!$right_mat_code) {
                continue;
            }

            $isbns = $basis_marc->findSubFields('021', 'a');
            foreach ($isbns as $isbnumber) {
                $isbnumber = materialId::normalizeISBN($isbnumber);
                $isbnumber = materialId::convertISBNToEAN($isbnumber);
                if ($isbnumber == $isbn13)
                    $found = true;
            }
            $isbns = $basis_marc->findSubFields('021', 'e');
            foreach ($isbns as $isbnumber) {
                $isbnumber = materialId::normalizeEAN($isbnumber);
                if ($isbnumber == $isbn13)
                    $found = true;
            }

            if ($nothing) {
                echo "isbn13:$isbn13, found:$found\n";
                echo $basis_marc->toLineFormat() . "***\n";
            }
            if (!$found) {
                continue;
            }

            $titles = $basis_marc->findSubFields('245', 'a');
            $fausts = $basis_marc->findSubFields('001', 'a');
            if (count($titles) == 0)
                $titles[0] = "__Unknown__";
//      if ($status == 'd')
//        $title = utf8_encode($titles[0]);
//      else
            $title = $titles[0];
            verbose::log(TRACE, "faust:" . $fausts[0] . ", title:" . $title);
//      return array($fausts[0], $title);
            $retfaust[] = array($fausts[0], $title, $isbn13, $basistitle);
        }
        // if there is more than one cleared marc-record, remove those who have a different title.
        if (count($retfaust) > 1) {
            $new = array();
            foreach ($retfaust as $key => $value) {
                $val1 = strtolower(str_replace(utf8_decode('¤'), '', ($value[1])));
                $val3 = strtolower(str_replace('¤', '', (utf8_decode($value[3]))));
                if ($val1 == $val3) {
                    $new[] = $value;
                }
            }
            $retfaust = $new;
        }
        return $retfaust;
    }

}

?>
