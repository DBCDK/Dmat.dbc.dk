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

//print_r($_REQUEST);
$Faust = $_REQUEST['Faust'];
$Isbn = $_REQUEST['Isbn'];
$Title = $_REQUEST['Title'];
if (array_key_exists('Clear', $_REQUEST)) {
    $Faust = $Isbn = $Title = "";
}

$info = "";
if (array_key_exists('janej', $_REQUEST)) {
    $janej = $_REQUEST['janej'];
    $seqno = $_REQUEST['Seqno'];
    $nytfaust = $_REQUEST['nytfaust'];
    if ($janej == 'Så pyt da') {
        $sql = "
        update $tablename
          set faust = '$nytfaust'
          where seqno = $seqno
    ";
        $db = new pg_database($connect_string);
        $db->open();
        $db->exe($sql);
        $info = "Faust er blevet ændret [$nytfaust]";
    } else {
        $info = "Ingen ændringer foretaget";
    }
    unset($_REQUEST['Seqno']);
}
if (array_key_exists('format', $_REQUEST))
    $format = $_REQUEST['format'];


if (array_key_exists('Seqno', $_REQUEST)) {
    $seqno = $_REQUEST['Seqno'];
    $sql = "
    select
      seqno, status, faust, isbn13, title, format, createdate, sent_to_basis, sent_to_well, deletedate
      from $tablename where  seqno = $seqno
  ";
    $db = new pg_database($connect_string);
    $db->open();
    $seqnoRes = $db->fetch($sql);
    if ($seqnoRes[0] == false) {
        $seqnoRes = array();
        $info = "Ingen poster fundet med det pågælende seqno";
    }
    if ($seqnoRes) {
        $ths = "<th>#</th>";
        $heads = $seqnoRes[0];
        foreach ($heads as $key => $value) {
            $ths .= "<th>$key</th>\n";
        }
    }
}

$res = array();
if (array_key_exists('Search', $_REQUEST)) {

    if ($Faust || $Isbn || $Title) {
        $sql = "
    select
      seqno, status, faust, isbn13, title, format, createdate, sent_to_basis, sent_to_well, deletedate
      from $tablename where provider = 'Pubhub' ";
        if ($Faust) {
            $Faust = str_replace('"', '', $Faust);
            $Faust = materialId::normalizeFAUST($Faust, true);
            $where .= "or faust = '$Faust'";
        }
        if ($Isbn) {
            $Isbn = str_replace('"', '', $Isbn);
            $where .= "or isbn13 = '$Isbn'";
        }
        if ($Title) {
            $Title = strtolower($Title);
            $where .= "or lower(title) like '$Title'";
        }
        $sql .= ' and (' . substr($where, 2) . ")";
        $sql .= " order by title, format";

        $db = new pg_database($connect_string);
        $db->open();
        $fromRes = $db->fetch($sql);
        if ($fromRes[0] == false) {
            $fromRes = array();
            $info = "Ingen poster fundet med den pågælende søgning";
        }
        if ($fromRes) {
            $ths = "<th>#</th>";
            $heads = $fromRes[0];
            foreach ($heads as $key => $value) {
                $ths .= "<th>$key</th>\n";
            }
        }
    }
}
?>



<?php header('Content-Type: text/html; charset=utf-8'); ?>

<h1>Se hvad der ligger i digitalresources tabellen!</h1>

<h2><?php echo $err; ?></h2>

<br />
<form action="" width="500">
    <fieldset>
        <legend>Søg:</legend>

        </select>
        Faust: <input type="text" name="Faust" value="<?php echo $Faust; ?>" />
        Isbn: <input type="text" name="Isbn" value="<?php echo $Isbn; ?>" />
        Titel: <input type="text" name="Title" value="<?php echo $Title; ?>" />
        <input type="submit" value="OK" name="Search"/>
        <input type="submit" value="Clear" name="Clear"/>
    </fieldset>
</form>
<br />
Hint: hvis man skriver %hunde% i titelfeltet søger man på titler der indeholder strengen "hunde"
<br />
<?php if (count($fromRes)) { ?>
    <br />
    <hr />
    <br />
    <table border="1" width="100%">
        <?php echo $ths; ?>
        <?php $seqnos = ""; ?>
        <?php foreach ($fromRes as $number => $row) { ?>
            <tr>
                <td>
                    <?php echo $number + 1; ?>
                </td>
                <?php foreach ($row as $key => $value) { ?>
                    <?php
                    if ($key == 'seqno') {
                        $seqnos .= ';' . $value;
                    }
                    if ($key == 'originalxml') {
                        $value = substr($value, 0, 00);
                    }
                    if ($key == 'createdate' || $key == 'sent_to_basis' || $key == 'sent_to_well' || $key == 'deletedate') {
                        $value = substr($value, 0, 16);
                    }
                    echo "<td>$value</td>\n";
                }
                ?>
            </tr>
        <?php } ?>
    </table>
    <?php
}
?>

<?php if (count($seqnoRes)) { ?>
    <br />
    <hr />
    <br />
    <?php
    $seqarr = explode(';', $_REQUEST['seqnos']);
    $fundet = false;
    foreach ($seqarr as $displayedSeqno) {
        if ($displayedSeqno == $seqnoRes[0]['seqno']) {
            $fundet = true;
        }
    }
    if ($fundet) {
        ?>
        <table border="1" width="100%">
            <?php echo $ths; ?>
            <?php foreach ($seqnoRes as $number => $row) { ?>
                <tr>
                    <td>
                        <?php echo $number + 1; ?>
                    </td>
                    <?php foreach ($row as $key => $value) { ?>
                        <?php
                        if ($key == 'originalxml') {
                            $value = substr($value, 0, 00);
                        }
                        if ($key == 'faust') {
                            $nytfaust = 'd:' . str_replace(' ', '', $value);
                            $value = "<b>$nytfaust</b>";
                        }
                        if ($key == 'createdate' || $key == 'sent_to_basis' || $key == 'sent_to_well' || $key == 'deletedate') {
                            $value = substr($value, 0, 16);
                        }
                        echo "<td>$value</td>\n";
                    }
                    ?>
                </tr>
            <?php } ?>
        </table>
        <br />
        <form action="" >
            Skal ændringen gemmes ?
            <input type="submit" value="Så pyt da" name="janej" />
            <input type="submit" value="Bestemt NEJ" name="janej" />
            <input type="hidden" value="<?php echo $seqno; ?>" name="Seqno" />
            <input type="hidden" name="Faust" value="<?php echo $Faust; ?>" />
            <input type="hidden" name="Isbn" value="<?php echo $Isbn; ?>" />
            <input type="hidden" name="Title" value="<?php echo $Title; ?>" />
            <input type="hidden" name="nytfaust" value="<?php echo $nytfaust; ?>" />
        </form>
        <?php
    } else {
        $info = "Seqno " . $seqnoRes[0]['seqno'] . " var ikke med på listen";
    }
    ?>


    <?php
}
?>

<?php if (count($fromRes)) { ?>
    <br />
    <form action="" width="">
        <fieldset>
            <legend>Ændring til d:xxxxxxxx status:</legend>
            Ønsker du at ændre faustnummeret?
            <br />
            Seqno: <input type="text" value="" name="Seqno" />
            <input type="submit" value="Go" name="dRequest" />
            <input type="hidden" name="Faust" value="<?php echo $Faust; ?>" />
            <input type="hidden" name="Isbn" value="<?php echo $Isbn; ?>" />
            <input type="hidden" name="Title" value="<?php echo $Title; ?>" />
            <input type="hidden" name="seqnos" value="<?php echo trim($seqnos, ';'); ?>" />
        </fieldset>
    </form>
<?php } ?>

<br />
<br />
<?php echo $info; ?>
<br />
<br />

