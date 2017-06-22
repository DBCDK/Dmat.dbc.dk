<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

//phpinfo();
$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

require_once "$inclnk/OLS_class_lib/material_id_class.php";
require_once "$inclnk/OLS_class_lib/inifile_class.php";
require_once "$inclnk/OLS_class_lib/verbose_class.php";
//require_once "$inclnk/OLS_class_lib/LibV3API_class.php";
require_once "$inclnk/OLS_class_lib/pg_database_class.php";

$inifile = $startdir . "/DigitalAdmin.ini";
// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    die($config->error);

if (( $logfile = $config->get_value('logfile', 'setup')) == false)
    die("no logfile stated in the configuration file");
if (( $verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    die("no verboselevel stated in the configuration file");
//if (( $ociuser = $config->get_value('ociuser', 'seBasis')) == false)
//    die("no ociuser stated in the configuration file");
//if (( $ocipasswd = $config->get_value('ocipasswd', 'seBasis')) == false)
//    die("no ocipasswd stated in the configuration file");
//if (( $ocidatabase = $config->get_value('ocidatabase', 'seBasis')) == false)
//    die("no ocidatabase stated in the configuration file");
//$libV3API = new LibV3API($ociuser, $ocipasswd, $ocidatabase);
$tablename = "digitalresources";
$connect_string = $config->get_value("connect", "setup");

verbose::open($logfile, $verboselevel);
//print_r($_REQUEST);

if (array_key_exists('format', $_REQUEST))
    $format = $_REQUEST['format'];

$formats = array('ebib' => false, 'eReolen' => false, 'Netlydbog' => false, 'EreolenLicens' => false);
$formats[$format] = 'selected';
//print_r($formats);

$info = "";
$askPermission = false;
if (array_key_exists('removeRequest', $_REQUEST)) {
    if ($_REQUEST['removeRequest'] == 'OK') {
        $info .= "REMOVE-REQUEST ";
//        $fromFaust = $_REQUEST['fromFaust'];
//        $toFaust = $_REQUEST['toFaust'];
        $removeSeqno = $_REQUEST['removeSeqno'];
        $askPermission = true;
        $sql = "
           select seqno, title, faust, isbn13, format, provider, status
            from $tablename
            where
                seqno =  $removeSeqno
        ";
        $db = new pg_database($connect_string);
        $db->open();
        $arr = $db->fetch($sql);
        $confirm = $arr[0];
    }
}

if (array_key_exists('remove', $_REQUEST)) {
    if ($_REQUEST['remove'] == 'OK') {
        $info .= "REMOVE ";
//        $fromFaust = $_REQUEST['fromFaust'];
//        $toFaust = $_REQUEST['toFaust'];
        $removeSeqno = $_REQUEST['removeSeqno'];
        $sql = "
            delete from $tablename
            where seqno = $removeSeqno
        ";
        $info .= $sql;
        verbose::log(TRACE, $sql);
        $db = new pg_database($connect_string);
        $db->open();
        $db->exe($sql);
    }
}

if (array_key_exists('confirm', $_REQUEST)) {
    if ($_REQUEST['confirm'] == 'OK') {
        $info .= "CONFIRM ";
        $fromFaust = $_REQUEST['fromFaust'];
        $toFaust = $_REQUEST['toFaust'];
        $fromSeqno = $_REQUEST['fromSeqno'];
        $sql = "
            update $tablename
            set faust = '$toFaust',
            sent_to_basis = null,
            sent_to_well = null,
            sent_to_covers = null,
            sent_xml_to_well =null,
            cover_status = null
            where seqno = $fromSeqno
        ";
        $info .= $sql;
        verbose::log(TRACE, $sql);
        $db = new pg_database($connect_string);
        $db->open();
        $db->exe($sql);
    }
}
$res = array();
if (array_key_exists('alterFAUST', $_REQUEST)) {
    $fromFaust = $_REQUEST['fromFaust'];
    $toFaust = $_REQUEST['toFaust'];
    verbose::log(TRACE, "fromFaust:$fromFaust, toFaust:$toFaust");
    $fromFaust = materialId::normalizeFAUST($fromFaust, true);
    $toFaust = materialId::normalizeFAUST($toFaust, true);
    $err = false;
    if (!$fromFaust || !$toFaust) {
        $err = "Forkert Faust FRA (" . $_REQUEST['fromFaust'] . ") eller TIL (" . $_REQUEST['toFaust'] . ")";
        verbose::log(TRACE, $err);
    }
    if (!$err) {
        $db = new pg_database($connect_string);
        $db->open();
//            select seqno, title, faust, isbn13, format, provider, 'From' fromto, status
        $sql = "
            select *, 'From' fromto
            from $tablename
            where
                provider = 'Pubhub'
            and
                faust = '$fromFaust'
            and
                format = '$format'
        ";
        $fromhits = 0;
        $fromRes = $db->fetch($sql);
        if ($fromRes) {
            $fromSeqno = $fromRes[0]['seqno'];
            $fromhits = count($fromRes);
            foreach ($fromRes as $from) {
                $strng = implode("|", $from);
                verbose::log(TRACE, $strng);
                $res[] = $from;
                if ($from['status'] == 'd') {
                    $formhits--;
                }
            }
        }

//            select seqno, title, faust, isbn13, format, provider, 'To' fromto, status
        $sql = "
            select *, 'To' fromto
            from $tablename
            where
                provider = 'Pubhub'
            and
                faust = '$toFaust'
            and
                format = '$format'
        ";
        $tohits = 0;
        $toRes = $db->fetch($sql);
        if ($toRes) {
            $tohits = count($toRes);
            foreach ($toRes as $to) {
                $strng = implode("|", $to);
                verbose::log(TRACE, $strng);
                $res[] = $to;
                if ($to['status'] == 'd') {
                    $tohits--;
                }
            }
        }
        $hits = count($res);
//        print_r($toRes);
//        echo "fromhits:$fromhits, tohits:$tohits, fromSeqno:$fromSeqno";
        if (($fromhits + $tohits) == 0) {
            $err = "Ingen match af faust i tabellen";
        }
        if (($fromhits + $tohits) > 1) {
            $err = "alt for mange hits";
        }
    }
}
?>

<?php header('Content-Type: text/html; charset=utf-8'); ?>

<h1>Administration af Digitalresources</h1>

<h2><?php echo $err; ?></h2>

<br />
<form action="" width="500">
    <fieldset>
        <legend>Ret FAUST:</legend>
        Format: <select name="format">
            <?php
            foreach ($formats as $format => $value) {
                echo "<option value='$format' $value>$format</option>";
            }
            ?>
        </select>
        Fra FAUST: <input type="text" name="fromFaust" value="<?php echo $fromFaust; ?>" />
        Til FAUST: <input type="text" name="toFaust" value="<?php echo $toFaust; ?>" />
        <input type="submit" value="OK" name="alterFAUST"/>
    </fieldset>
</form>

<?php if (count($res)) { ?>
    <br />
    <hr />
    <br />
    <table border="1" width="100%">
        <th>SEQNO</th><th>FROM/TO</th><th>FAUST</th><th>ISBN13</th>
        <th>FORMAT</th><th>PROVIDER</th><th>STATUS</th><th>TITLE</th>
        <?php foreach ($res as $row) { ?>
            <tr>
                <td><?php echo $row['seqno']; ?></td>
                <td><?php echo $row['fromto']; ?></td>
                <td><?php echo $row['faust']; ?></td>
                <td><?php echo $row['isbn13']; ?></td>
                <td><?php echo $row['format']; ?></td>
                <td><?php echo $row['provider']; ?></td>
                <td><?php echo $row['status']; ?></td>
                <td><?php echo $row['title']; ?></td>
            </tr>
        <?php } ?>
    </table>
<?php } ?>

<?php if (($fromhits == 1 && $tohits == 0)) { ?>
    <br />
    <hr />
    <br />
    <form action="">
        <fieldset>
            <legend>Bekræft</legend>
            Ønsker du at rette denne posts FAUSTnummer til <?php echo $toFaust; ?>
            &nbsp; &nbsp; &nbsp; &nbsp;
            <input type="submit" value="OK" name="confirm" />
            &nbsp; &nbsp; &nbsp; &nbsp;
            <input type="submit" value="Discard" name="confirm" />
            <input type="hidden" value="<?php echo $fromFaust; ?>" name='fromFaust' />
            <input type="hidden" value="<?php echo $toFaust; ?>" name='toFaust' />
            <input type="hidden" value="<?php echo $fromSeqno; ?>" name='fromSeqno' />
        </fieldset>
    </form>
<?php } ?>

<?php if (($fromhits + $tohits) > 1) { ?>
    <br />
    <hr />
    <br />
    <form action="">
        <fieldset>
            <legend>Vil du slette en post</legend>
            Ønsker du at slette en af indgangene i tabellen <br />
            Næste gang der bliver kørt en "RunDaily" vil posten blive genoprettet
            og få et nyt faust nummer!
            <br /><br />
            SEQNO:
            &nbsp; &nbsp; &nbsp; &nbsp;
            <input type="text" name="removeSeqno" />
            &nbsp; &nbsp; &nbsp; &nbsp;
            <input type="submit" value="OK" name="removeRequest" />
            &nbsp; &nbsp; &nbsp; &nbsp;
            <input type="submit" value="Discard" name="removeRequest" />
            <input type="hidden" value="<?php echo $fromFaust; ?>" name='fromFaust' />
            <input type="hidden" value="<?php echo $toFaust; ?>" name='toFaust' />
        </fieldset>
    </form>
<?php } ?>

<?php if ($askPermission) { ?>
    <br />
    <hr />
    <br />
    <form action="">
        <fieldset>
            <legend>Sikkerheds tjek</legend>
            Er du helt sikker på du vil slette SEQNO: <?php echo $removeSeqno; ?> <br />
            <br />
            <?php echo $confirm['isbn13']; ?> &nbsp; &nbsp;
            <?php echo $confirm['faust']; ?> &nbsp; &nbsp;
            "<?php echo $confirm['title']; ?>" &nbsp; &nbsp;
            <?php echo $confirm['format']; ?> &nbsp; &nbsp;
            <?php echo $confirm['status']; ?> &nbsp; &nbsp;
            <?php echo $confirm['provider']; ?> &nbsp; &nbsp;
            <br />
            <input type="submit" value="OK" name="remove" />
            &nbsp; &nbsp; &nbsp; &nbsp;
            <input type="submit" value="Discard" name="remove" />
            <input type="hidden" value="<?php echo $removeSeqno; ?>" name='removeSeqno' />
            <input type="hidden" value="<?php echo $fromFaust; ?>" name='fromFaust' />
            <input type="hidden" value="<?php echo $toFaust; ?>" name='toFaust' />
        </fieldset>
    </form>
<?php } ?>

<br />
<br />
<?php echo $info; ?>


