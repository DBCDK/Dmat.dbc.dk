<?php

/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
class showseqno {


    private $mediadb;
    private $libv3;
    private $data;
    private $doc;
    private $htmlfile;
    private $sitetitle;
    private $site;
    private $matchLibV3;
    private $lcked;
    private $datadir;
    private $scansaxo;
    private $rbd;

//    private $foundation;

    /**
     * showseqno constructor.
     * @param mediadb $mediadb
     * @param $libv3
     * @param $matchLibV3
     * @param $htmlfile
     * @param $site
     */
    function __construct(mediadb $mediadb, $libv3, matchLibV3 $matchLibV3, $htmlfile, $site, $datadir, scanSaxo_class $scansaxo, RBD_class $rbd) {
        $this->mediadb = $mediadb;
        $this->libv3 = $libv3;
        $this->matchLibV3 = $matchLibV3;
        $this->htmlfile = $htmlfile;
        $this->site = $site;
        $this->setTitle($site);
        $this->datadir = $datadir;
        $this->scansaxo = $scansaxo;
        $this->rbd = $rbd;
    }

    /**
     * @param $site
     */
    function setTitle($site) {
        if ($site == 'eVa') {
            $this->sitetitle = "eVa - Valg af elektroniske bøger";
        }
        if ($site == 'eLu') {
            $this->sitetitle = "eLu - Lektørudtalelse til elektroniske bøger";
        }
        $this->sitetitle = "eVa/eLu - Elektroniske bøger";
    }

    private function getChoiceFromMarc($lokalid, $bibliotek, $base) {
//        echo "lokalid:$lokalid";
        $res = $this->libv3->getMarcByLB($lokalid, $bibliotek, '', $base);
        $marc = $res[0]['DATA'];
        $m = new marc();
        $m->fromIso($marc);
        $prechoice = array();

        $prechoice['type'] = 'printed';
        $f009gs = $m->findSubFields('009', 'g');
        foreach ($f009gs as $f009g) {
            if ($f009g == 'xe') {
                $prechoice['type'] = 'ebook';
            }
        }
        $f032s = $m->findSubFields('032', 'a');
        foreach ($f032s as $f032) {
            if (substr($f032, 0, 3) == 'DBF') {
                $prechoice['DBF'] = 'checked';
            }
        }
        $f032s = $m->findSubFields('032', 'x');
        foreach ($f032s as $f032) {
            if (substr($f032, 0, 3) == 'BKM' or substr($f032, 0, 3) == 'SFD') {
                $prechoice['BKM'] = 'checked';
            }
        }
        $f06s = $m->findSubFields('f06', 'b');
        foreach ($f06s as $f06) {
            $prechoice[strtoupper($f06)] = 'checked';
            $prechoice['BKM'] = 'checked';
            $prechoice['DBF'] = 'checked';
        }
//        $prechoice = array('DBF' => 'checked', 'BKM' => 'checked', 'S' => 'checked');
        $f652m = $m->findSubFields('652', 'm');
        $f009g = $m->findSubFields('009', 'g');
        $_SESSION['to_elu'] = false;
        if ($f652m[0] == 'NY TITEL' and $f009g[0] == 'xe') {
            $_SESSION['to_elu'] = true;
        }
        // is it a DBR candidate
        $type = $m->findSubFields('004', 'a', 1);
        if ($type != 'e') {
            // multi volume
            return $prechoice;
        }

        $publishingyear = $m->findSubFields('008', 'a', 1);
        if ( !ctype_digit($publishingyear)) {
            return $prechoice;
        }
        if (strlen($publishingyear) != 4) {
            return $prechoice;
        }
        $breakpoint = date('Y') - 6;
        if ($publishingyear > $breakpoint) {
            return $prechoice;
        }
        $prechoice['DBR'] = 'checked';
        $prechoice['DBF'] = '';

        return $prechoice;
    }

    private function fetchData($name, $title = "", $url = "", $att = "", $content = "") {

        $ret = array();

        if ( !$title) {
            $title = $name;
        }
        $elements = $this->doc->getElementsByTagName($name);
        $k = 0;
        foreach ($elements as $element) {

            $txt = substr($element->nodeValue, 0, 200000);
            if ($url) {
                $txt = "<a target='_blank' href='$txt'>$txt</a>";
            }
            if ($att) {
                $attribute = $elements->item($k++)->getAttribute($att);
                if ($content == $attribute) {
                    $this->data[$title][] = $txt;
                }
            } else {
                $this->data[$title][] = $txt;
                $ret[] = $txt;
            }
        }
        return $ret;
    }

    private function removeOldFiles() {
// rens data dir
        $files = scandir("data");
        $now = time();
// maxdiff = 1 day
        $maxdiff = 1 * 24 * 60 * 60;
        foreach ($files as $file) {
            $filename = $this->datadir . "/$file";
            if ( !is_dir($filename)) {
                $diff = $now - fileatime($filename);
                if ($diff > $maxdiff) {
                    unlink($filename);
                }
            }
        }
    }

    function finido($finido) {
        $this->finido = $finido;
    }

    function locked($locked) {
        $this->lcked = $locked;
    }

    function showtable($seqno, $direction = 0, $req = array(), $locked = false) {

        $initials = getInitials();

        $list = setlist($req);
        $this->site = $list;
        $site = $this->site;

        $typesEVA = array('eVa', 'Alle', 'Template', 'ProgramMatch', 'Drop', 'kunEreol', 'Afventer', 'UpdateBasis', 'UpdatePromat', 'UpdatePublizon', 'OldEva', 'OldTemplate');
        $typesELU = array('eLu', 'Template', 'ProgramMatch', 'UpdateBasis', 'UpdatePromat', 'UpdatePublizon');
        $sites = array();
        if ($site == 'eVa') {
            foreach ($typesEVA as $type) {
                $sites[$type] = '';
            }
        }
        if ($site == 'eLu') {
            foreach ($typesELU as $type) {
                $sites[$type] = '';
            }
        }

        $type = $req['type'];
        if ($type) {
            $sites[$type] = 'selected';
        } else {
            $sites[$site] = 'selected';
        }


        $page = new view('html/showtable_1.phtml');
        $page->set('list', $list);
        $page->set('initials', $initials);
        $page->set('sitetitle', $this->sitetitle);
        $page->set('site', $site);
        $page->set('locked', $this->lcked);
        $page->set('finido', $this->finido);
        $this->lcked = false;
        $page->set('sites', $sites);

        $rows = $this->mediadb->getTableData($seqno, $direction, $req, $site);
        $pages = array_pop($rows);
        if ( !$rows) {
            $rows = array();
        }
        $curr = "?type=$type";
        $nxt = "$curr" . '&cmd=nxtpage';
        $pre = "$curr" . '&cmd=prepage';
        $thispage = "$curr" . "&cmd=thispage";
        if ($_SESSION['page'] == 1) {
            $page->set('preunavailable', "unavailable");
            $pre = $thispage;
        }
        if ($pages <= $_SESSION['page']) {
            $page->set('nxtunavailable', "unavailable");
            $nxt = $thispage;
        }
        $page->set('pre', $pre);
        $page->set('nxt', $nxt);
        $page->set('thispage', $thispage);

        $page->set('pages', $pages);
        $page->set('currentpage', $_SESSION['page']);
        $page->set('type', $req['type']);

        foreach ($rows as $key => $row) {
            if ($row['lockdate'] == '') {
                $rows[$key]['lockdate'] = ' - ';
            }
            if ($row['initials'] == '') {
                $rows[$key]['initials'] = '&nbsp;';
            }
            if ($row['choice'] == '') {
                $rows[$key]['choice'] = '&nbsp;';
            }

            $rows[$key]['title'] = "<a href='?cmd=show&seqno=" . $row['seqno'] . "'>" . $row['title'] . "</a>";
//            }

            $notes = $this->mediadb->getNoteData($row['seqno']);
            $notetextdisp = '';
            if ($notes) {
                foreach ($notes as $note) {
                    if ($note['type'] == 'note') {
                        $n = 'd08';
                    } else {
                        $n = 'Afventer';
                    }
                    $notetextdisp .= "<strong>$n:</strong>" . $note['text'] . "&nbsp;";
                }
                $rows[$key]['Afventer'] = $notetextdisp;
            }
        }
        if (array_key_exists(100, $rows)) {
            array_pop($rows);
            $rows[100]['seqno'] = 0;
            $rows[100]['status'] = '-';
            $rows[100]['title'] = "<strong> --DER ER FLERE TITLER!!!!--</strong>";
        }

        $page->set('rows', $rows);
        $page->set('seqno', $seqno);
        $page->set('oldExists', count($this->mediadb->getNextOlds(1)) > 0);
    }

    function showseqno($seqno, $cmd = "", $req = array()) {
//        verbose::log(TRACE, "ROLE:$role");
//        $this->removeOldFiles();

        $_SESSION['to_elu'] = false;
        $prechoice = array('DBF' => '', 'DBR' => '', 'BKM' => '', 'V' => '', 'B' => '', 'S' => '', 'BKMV' => '');
        $initials = getInitials();
        $list = getlist();
        if ( !$list) {
            setlist($req);
            $list = getlist();
        }

        $page = new view($this->htmlfile);
        if ( !$initials) {
            $page->set('dis', "disabled=true");
        }
        $page->set('list', $list);
        $page->set('disabledeva', "disabled");
        $page->set('disabledelu', "disabled");
        $page->set('disabledBoth', "disabled");
        if ($_SESSION['username']) {
            $role = $_SESSION['role'];
            verbose::log(DEBUG, "role:$role");
//            $page->set('dis', "disabled=true");
            if ($role == 'eVa') {
                $page->set('disabledeva', "");
                $page->set('disabledBoth', "");
            }
            if ($role == 'eLu') {
                $page->set('disabledelu', "");
                $page->set('disabledBoth', "");
//                verbose::log(DEBUG, "disabledelu:maaske");
            }
            $initials = $_SESSION['username'];
        }


        $page->set('initials', $initials);
        $page->set('sitetitle', $this->sitetitle);
        $page->set('site', $this->site);

//        if (array_key_exists('lokalid', $req)) {
        $lokalid = $req['lokalid'];
        $page->set('show', $req['show']);
        $page->set('details', $req['details']);

//        } else {
//            $lokalid = '';
//        }
        $bibliotek = $req['bibliotek'];
        $base = $req['base'];

        if ($req['oldstatus'] == 'Afventer' or $req['oldstatus'] == 'Afventer_eLu') {
            $rows = $this->mediadb->getData($cmd, $seqno, 'Afventer');
        } else {
            $rows = $this->mediadb->getData($cmd, $seqno, $this->site);
        }

        $isChecked = false;
        if ($req['seqno'] == $seqno) {
            foreach ($req as $key => $val) {
                if (substr($key, 0, 4) == 'eVA-') {
                    if ($val == 'on') {
                        $isChecked = true;
                        $prechoice[substr($key, 4)] = 'checked';
                    }
                }
            }
        }
//        find via hjemmeside.

        $expisbn = $published = '';
        if (array_key_exists('expisbn', $req)) {
            $expisbn = $req['expisbn'];
        }
        if (array_key_exists('published', $req)) {
            $published = $req['published'];
        }
        if ( !$expisbn) {
            if (array_key_exists('source', $rows[0])) {
                if ($rows[0]['isbn13'] != $rows[0]['source']) {
                    $expisbn = $rows[0]['source'];
                }
            }
        }

        $xml = $rows[0]['originalxml'];
        $this->doc = new DOMDocument();
        $this->doc->loadXML($xml);
        $this->fetchData('Title');
        $this->fetchData('Authors');

        $author1 = $this->data['Authors'][0];
        $title1 = $this->data['Title'][0];
        if ($expisbn) {
            $saxoinfo = $this->scansaxo->getPrintedDirect($expisbn);
        } else {

            $saxoinfo = $this->scansaxo->getByTitleAuthor($title1, $author1);
        }
        $saxoisbn = $saxodate = '';
        if ($saxoinfo) {
            if (array_key_exists('ISBN-13', $saxoinfo)) {
                if ($saxoinfo['ISBN-13'] != '') {
                    $saxoisbn = $saxoinfo['ISBN-13'];
                }
            }
            if (array_key_exists('Udgivet', $saxoinfo)) {
                if ($saxoinfo['Udgivet'] != '') {
                    $saxodate = $saxoinfo['Udgivet'];
                }
            }

        }
        if ( !$expisbn) {
            if ($saxoisbn) {
                $expisbn = $saxoisbn;
            }
        }
        if ( !$published) {
            if ($saxodate) {
                $published = $saxodate;
            }
        }
//        if ($expisbn) {
        $page->set('isbnsource', $expisbn);
        $isbnsource = $expisbn;
//        }


//check isbn
        if ($cmd == 'expisbn') {
            $page->set('expisbn', $req['expisbn']);
            $invalidISBN = false;
            if ( !materialId::validateEAN($req['expisbn'])) {
                if ( !materialId::validateISBN($req['expisbn'])) {
                    $invalidISBN = 'Forkert ISBN!';
                }
            }
            if ($invalidISBN == false) {
                $records = array();
                $records = $this->matchLibV3->fetchRecords($req['expisbn'], $records);
                if ($records['Basis'] > 0) {

                    $lid = $records['Basis'][0]['LOKALID'];
                    $lbib = $records['Basis'][0]['BIBLIOTEK'];
                    $invalidISBN = "ISBN findes allerede i Basis<br/> $lid - indsat som kandidat";
                    $base = $req['base'];
                    if ($matchtype = checkDB($this->libv3, $base, $lid, $lbib)) {
                        $this->mediadb->insertReftable($seqno, $base, $lid, $lbib, 3, $matchtype[0], $matchtype[1]);
                    }
                }
                if ($records['Phus'] > 0) {
                    $invalidISBN = 'ISBN findes allerede i Phuset';
                }
                if ($seqnum = $this->mediadb->IsbnInExpISBN($req['expisbn'])) {
                    $invalidISBN = "ISBN findes allerede i Dmat (seqno:" . $seqnum[0]['seqno'] . ")";
                }
            }
            if ($invalidISBN) {
                $page->set('invalidISBN', $invalidISBN);
            } else {
                $page->set('eORp', 'printed');
                if ($isChecked) {
                    $page->set('eORp', 'ebook');
                }
            }

        }

//        print_r($rows);
        if ($rows) {
            $faust = $rows[0]['faust'];
//            echo "row:" . $rows[0]['bkxwc'] . "\n";
            $page->set('bkxwc', $rows[0]['bkxwc']);
            if ($rows[0]['bkxwc']) {
                $page->set('pbkxwc', "BKX" . $rows[0]['bkxwc']);
            }

            $seqno = $rows[0]['seqno'];
            switch ($cmd) {
                case 'Note':
                    $ntype = 'note';
                    break;
//                case 'Afvent':
//                    $ntype = 'waiting';
//                    break;
                case 'f991':
                    $ntype = 'f991';
                    $page->set('notetext', 'Trykt version med lektørudtalelse (  )');
                    break;
                default:
                    $ntype = 'waiting';
            }

//            if ($cmd == 'Note') {
//                $ntype = 'note';
//            } else {
//                $ntype = 'waiting';
//            }
            $notes = $this->mediadb->getNoteData($seqno);
            $notetextdisp = "";

            if ($notes) {
                foreach ($notes as $note) {
//                    print_r($notes);
                    switch ($note['type']) {
                        case 'note':
                            $n = 'd08';
                            break;
                        case 'f991':
                            $n = 'Felt 991';
                            break;
                        case 'Afventer':
                            $n = 'Afventer';
                            break;
                        case 'waitingelu':
                            $n = "Afventer eLu";
                    }
//                    if ($note['type'] == 'note') {
//                        $n = 'd08';
//                    } else {
//                        if ($note['type'] == 'f991') {
//                            $n = 'Felt 991';
//                        } else {
//                            $n = 'Afventer';
//                        }
//                    }
                    $notetextdisp .= "<strong>$n:</strong>&nbsp;" . $note['text'] . "&nbsp;";

                    if ($note['type'] == $ntype) {
                        $page->set('notetext', $note['text']);
                    }
                }
                $page->set('notetextdisp', $notetextdisp);
            }
//            $page->set('notetext', 'Trykt version med lektørudtalelse (  )');
//        if ($rows) {
            $seqno = $rows[0]['seqno'];
            $status = $rows[0]['status'];
            $choice = $rows[0]['choice'];

            $is_in_basis = $rows[0]['is_in_basis'];
            if ($is_in_basis or $status == 'RETRO_eLu') {
                $page->set('disabledlekfaust', "disabled");
            }

            $arr = explode(' ', rtrim($choice, ' '));
            foreach ($arr as $val) {
                $prechoice[$val] = 'checked';
            }

//            print_r($prechoice);
            $page->set('chck', $prechoice);
//            $xml = $rows[0]['originalxml'];

            if ($_SESSION['setup'] == 'testcase') {
                $nomatch = true;
            } else {
                $nomatch = false;
                $nomatch = true;
            }
            if ($_REQUEST['show'] == 'true') {
                $nomatch = false;
                $this->matchLibV3->setVerbose();
//                echo "<pre>\n";
            }
            if ( !$nomatch) {
                if ($this->site == 'eVa') {
                    if ($this->matchLibV3) {
                        $mMarc = $this->matchLibV3->match($xml);
//                        print_r($mMarc);
                        $this->mediadb->updateRefData($seqno, $mMarc);
                    }
                }
            }
            if ($_REQUEST['show'] == 'true') {
//                echo "</pre>\n";
                $debugstrng = $this->matchLibV3->getVerbose();
                $page->set('debugstrng', $debugstrng);
            }
            $this->doc = new DOMDocument();
            $this->doc->loadXML($xml);
            $page->set('seqno', $seqno);
            $page->set('is_in_basis', $is_in_basis);

//            $data = array();
            $miniatures = $this->fetchData('Image');
            $miniature = $miniatures[0];
            $this->data = array();

            if ($this->site == 'eLu' or $list == 'eLu' or $list == 'AfventerElu') {
                $page->set('disabled', 'disabled');
            }
            $this->fetchData('Title');
            $page->set('firsttitle', $this->data['Title'][0]);
//            if ($req['cmd'] == 'Lektør') {
//                $this->mediadb->updateLekFaust($seqno, $req);
//                $page->set('lekfaust', $rows[0]['lekfaust']);
//                $page->set('disabledlekfaust', "disabled");
//            }
            if ($req['cmd'] == 'insertLink') {
                $page->set('lekfaust', $req['lokalid']);
                $page->set('disabledlekfaust', "disabled");
            }
            $this->fetchData('SubTitle', 'undertitel');
            $identifiers = $this->fetchData('Identifier');
            $parr = $this->fetchData('PublicationDate');
            $PublicationDate = $parr[0];
            $this->fetchData('Edition');
            $this->fetchData('ImprintName');
            $this->fetchData('PublisherName');
            $this->fetchData('Authors');
            $page->set('authorsearch', $this->data['Authors'][0]);
            $this->fetchData('FileSize');
            $this->fetchData('SampleUrl', '', 'url');
//        $this->fetchData('Description', 'SubjectSimple');

            if ($identifiers[0] == $expisbn) {
                $page->set('warningISBN', 'ISBN til den trykte bog og e-bog er ens!!');
            }

            $this->fetchData('Image', 'Forside', 'url', 'Type', 'Forside');
            $page->set('Miniature', $miniature);
            $contentUrl = "fetchContent.php?guid=" . $rows[0]['bookid'] . "&type=" . $rows[0]['filetype'] . "&title=" . $rows[0]['title'];
            $this->data['contentUrl'][0] = "<a target='_blank' href='$contentUrl'>Se hele bogen (" . $rows[0]['filetype'] . ")</a>";

            if ($rows[0]['renditionlayout'] == 'pre-paginated') {
                $this->data['Layout'][0] = 'fixed layout';
            }
            if ($rows[0]['source']) {
                $isbncandidate = '';
                $txt = $rows[0]['source'];
                for ($i = 0; $i < strlen($txt); $i++) {
                    $int = $txt[$i];
                    if (is_numeric($txt[$i])) {
                        $isbncandidate .= $txt[$i];
                    }
                }

                if ($rows[0]['isbn13'] != $isbncandidate) {
                    if (strlen($isbncandidate) == 10 || strlen($isbncandidate) == 13) {
                        if ($rows[0]['isbn13'] != $isbncandidate) {
                            $this->data['Isbn source'][0] = $isbncandidate;
                            $page->set('isbnsource', $this->data['Isbn source'][0]);
                            $isbnsource = $isbncandidate;
                        }
                    }
                }
            }
            $page->set('contentUrl', $contentUrl);
            $page->set('data', $this->data);
            $this->data = array();
            $this->fetchData('MainDescription');
            $page->set('descp', $this->data);

            $page->set('newstatus', $_SESSION['newstatus']);

            $refrows = false;
            if ($this->site == 'eVa' or $this->site == 'eLu') {
                if ($cmd != 'none' and $cmd != 'econdChoice') {
                    if ($cmd == 'faust') {
                        $base = $req['base'];
                        $lokalid = $req['faust'];
                        $bibliotek = '870970';
//                        $matchtype = '350';
                        $type = 'manuelt';

                        if ($matchtype = checkDB($this->libv3, $base, $lokalid, $bibliotek)) {
                            $this->mediadb->insertReftable($seqno, $base, $lokalid, $bibliotek, $type, $matchtype[0], $matchtype[1]);
                        }
                    }

                    $refrows = $this->mediadb->getRefData($seqno);
                    MakeDisplayFiles($this->libv3, $refrows, $this->datadir);
                }
            }

            $buttons = array();
            $lektoers = array();
            $leks = $this->mediadb->getLektoerCandidates($seqno);
            if ($leks) {
                foreach ($leks as $row) {
                    $txt = "";
                    foreach ($row as $key => $val) {
                        $txt .= "$key=$val&";
                    }
                    $buttons[0][] = $row['lokalid'];
                    $lektoers[] = rtrim($txt, '&');
                }
            }

//http://devel7.dbc.dk/~hhl/posthus/eVALU/fetchFromLibV3.php?seqno=31624&base=Basis&lokalid=5%20224%20890%206&bibliotek=870970&type=isbn13&matchtype=417

            $links = $title = $titleAndAuthor = $titleAndAuthorAndPublisher = $isbnNotTitle = array();
            $endTAP = $endTA = $endT = $endINT = "";
            $cntTAP = $cntTA = $cntT = $cntINT = 1;

            if ($refrows) {
                $candidates = true;
                foreach ($refrows as $row) {

                    $txt = "";
                    foreach ($row as $key => $val) {
//                        $val = str_replace(' ', '+', $val);
                        $txt .= "$key=$val&";
                    }


                    $score = $row['matchtype'];
                    $matchtype = substr($score, 0, 1);
//                $buttons[$matchtype][] = $row['lokalid'] . " : " . $row['bibliotek'] . " (" . $row['base'] . ":" . $score . ")";
                    $buttons[$matchtype][] = $row['lokalid'] . " (" . $row['base'][0] . ":" . $score . ")";

                    switch ($matchtype) {
                        case 1:
                            if (count($title) < 5) {
                                $title[] = rtrim($txt, '&');
                            } else {
                                $endT = "der er flere (" . $cntT++ . ") ...";
                            }
                            break;
                        case 2:
                            if (count($titleAndAuthor) < 5) {
                                $titleAndAuthor[] = rtrim($txt, '&');
                            } else {
                                $endTA = "der er flere (" . $cntTA++ . ") ...";
                            }
                            break;
                        case 3:
                            if (count($titleAndAuthorAndPublisher) < 5) {
                                $titleAndAuthorAndPublisher[] = rtrim($txt, '&');
                            } else {
                                $endTAP = "der er flere (" . $cntTAP++ . ") ...";
                            }
                            break;
                        case 4:
                            if (count($isbnNotTitle) < 5) {
                                $isbnNotTitle[] = rtrim($txt, '&');
                            } else {
                                $endINT = "der er flere (" . $cntINT++ . ") ...";
                            }
                            break;
                    }
//                $links[] = rtrim($txt, '&');
                }
            } else {
//                $candidates = false;
                $page->set('showBKM', true);
            }
        }

        if ( !$published) {
            if ($PublicationDate) {
                $published = $PublicationDate;
            }
        }
        $page->set('published', $published);
        if ($isbnsource) {
//            $page->set('expisbn', $expisbn);
            $invalidISBN = false;
            if ( !materialId::validateEAN($isbnsource)) {
                if ( !materialId::validateISBN($isbnsource)) {
                    $invalidISBN = 'Forkert ISBN!';
                    $page->set('expisbn', $isbnsource);
                }
            }
            if ($invalidISBN == false) {
                $records = array();
                $records = $this->matchLibV3->fetchRecords($isbnsource, $records);
                if ($records['Basis'] > 0) {
                    $lid = $records['Basis'][0]['LOKALID'];
//                    $lbib = $records['Basis'][0]['BIBLIOTEK'];
                    $invalidISBN = "ISBN findes allerede i Basis<br/> $lid - indsat som kandidat";
//                    $base = $req['base'];
//                    if ($matchtype = checkDB($this->libv3, $base, $lid, $lbib)) {
//                        $this->mediadb->insertReftable($seqno, $base, $lid, $lbib, 3, $matchtype[0], $matchtype[1]);
//                    }
                    $page->set('isbnsource', '');
                    $page->set('invalidisbn', $isbnsource);
                    $page->set('linkinvalidisbn', $lid);
                }

//                if ($seqnum = $this->mediadb->IsbnInExpISBN($req['expisbn'])) {
//                    $invalidISBN = "ISBN findes allerede i Dmat (seqno:" . $seqnum[0]['seqno'] . ")";
//                }
            }
//            if ($invalidISBN) {
//                $page->set('invalidISBN', $invalidISBN);
//            }
//            else {
//                $page->set('eORp', 'printed');
//                if ($isChecked) {
//                    $page->set('eORp', 'ebook');
//                }
//            }

        }

        $info = $this->mediadb->getInfoData($seqno);
        $page->set('updatelekbase', $info[0]['updatelekbase']);
        $page->set('lekfaustok', $info[0]['lekfaust']);
        $page->set('cntINT', count($isbnNotTitle));
        $page->set('endTAP', $endTAP);
        $page->set('endTA', $endTA);
        $page->set('endT', $endT);
        $page->set('endINT', $endINT);
        $page->set('candidates', $candidates);
        $page->set('buttons', $buttons);
        $page->set('titleAndAuthorAndPublisher', $titleAndAuthorAndPublisher);
        $page->set('titleAndAuthor', $titleAndAuthor);
        $page->set('title', $title);
        $page->set('lektoers', $lektoers);
        $page->set('templateFaust', $faust);
        $page->set('templatelink', "base=Basis&lokalid=$faust&bibliotek=870970");
        $page->set('isbnNotTitle', $isbnNotTitle);
        $page->set('status', $status);
        $page->set('choice', $choice);
        $page->set('cmd', $cmd);
        $page->set('weekcode', date('oW'));
        if ($cmd == 'secondChoice') {

            $mod = $req['matchtype'] % 100;
//            if ($mod == 50) {
//                $mod = 1;
//            }
            $page->set('mod', $mod);

//            $page->set('candidates', false);
            $page->set('lokalid', $lokalid);
            $page->set('bibliotek', $bibliotek);
            $page->set('base', $base);
            $page->set('matchtype', $req['matchtype']);
            $page->set('secondChoiceTxt', "Du har valgt $lokalid($base:" . $req['matchtype'] . ")");
            $page->set('showBKM', true);

            $prechoice = $this->getChoiceFromMarc($req['lokalid'], $req['bibliotek'], $req['base']);
            if ($prechoice['type'] == 'ebook') {
                $booktype = 'E-bog';
            } else {
                $booktype = "Trykt bog";
            }
            $page->set('chck', $prechoice);
            $page->set('booktype', $booktype);
            $page->set('secondChoice', true);
        }

//        } else {
//            header("Location: " . $this->site . ".php?cmd = Ti oversigten");
//        }
//        return $links;
//        return;
//    print_r($data);
    }

}
