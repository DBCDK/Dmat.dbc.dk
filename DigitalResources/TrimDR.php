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
require_once "$inclnk/OLS_class_lib/pg_database_class.php";

function showresult($showres) {
    echo "<br />";
    echo "<table border='1' width='80%'>";

    foreach ($showres[0] as $key => $value) {
        echo "<th>$key</th>";
    }
    foreach ($showres as $res) {
        echo "<tr>";
        foreach ($res as $key => $value) {
            echo "<td>$value</td>";
        }
        echo "</tr>";
    }
    echo "</table>";
    echo "<br />";
}

$inifile = $startdir . "/../DigitalResources.ini";
//echo "inifile:$inifile\n";
// Fetch ini file and Check for needed settings
$config = new inifile($inifile);
if ($config->error)
    die($config->error);

if (($logfile = $config->get_value('logfile', 'setup')) == false)
    die("no logfile stated in the configuration file");
if (($verboselevel = $config->get_value('verboselevel', 'setup')) == false)
    die("no verboselevel stated in the configuration file");
$tablename = "digitalresources";
$seqname = $tablename . 'seq';

$connect_string = $config->get_value("connect", "setup");

verbose::open($logfile, $verboselevel);

$seqnos = $_REQUEST['seqnos'];
if (array_key_exists('newfaust', $_REQUEST)) {
    $newfaust = $_REQUEST['newfaust'];
    if (strlen($newfaust) > 7) {
        $newfaust = materialId::normalizeFAUST($newfaust, true);
    } else {
        $newfaust = "";
    }
}
$Faust = $_REQUEST['Faust'];
$Isbn = $_REQUEST['Isbn'];
$Title = $_REQUEST['Title'];
if (array_key_exists('format', $_REQUEST))
    $format = $_REQUEST['format'];
else
    $format = 'Alle';
if (array_key_exists('Clear', $_REQUEST)) {
    $Faust = $Isbn = $Title = "";
    $format = 'Alle';
}

$formats = array('Alle' => false, 'ebib' => false, 'eReolen' => false, 'Netlydbog' => false);
$formats[$format] = 'selected';


$res = array();
if (array_key_exists('Search', $_REQUEST)) {

    if ($Faust || $Isbn || $Title) {
        $sql = "
    select
    seqno, provider, status, faust, isbn13, title, format, createdate, sent_to_basis, sent_to_well, deletedate
    from $tablename where provider like 'Pubhub%' ";
        if ($format != 'Alle') {
            $sql .= " and format = '$format' ";
        }
        if ($Faust) {
            $Faust = str_replace('"', '', $Faust);
            $Faust = materialId::normalizeFAUST($Faust, true);
            $where .= "or faust = '$Faust'";
        }
        if ($Isbn) {
            $Isbn = str_replace('"', '', $Isbn);
            $where .= " or isbn13 = '$Isbn'";
        }
        if ($Title) {
            $Title = strtolower($Title);
            $where .= " or lower(title) like '$Title'";
        }

        $sql .= ' and (' . substr($where, 3) . ")";
        $sql .= " order by title, format";

//        echo $sql;
        $db = new pg_database($connect_string);
        $db->open();
        $fromRes = $db->fetch($sql);
        if ($fromRes[0] == false) {
            $fromRes = array();
            $info = "Ingen poster fundet med den pågældende søgning";
        }
        if ($fromRes) {
            $ths = " < th>#</th>";
            $heads = $fromRes[0];
            foreach ($heads as $key => $value) {
                $ths .= "<th>$key</th>\n";
            }
        }
    }
}
?>


<?php header('Content-Type: text/html; charset=utf-8'); ?>
<link rel='stylesheet' type='text/css' href='styles.css'/>
<h1>Administration af Digitalresources</h1>

<h2><?php echo $err; ?></h2>

<br/>
<form action="" width="500">
    <fieldset>
        <legend>Søg:</legend>
        Format: <select name="format">
            <?php
            foreach ($formats as $format => $value) {
                echo "<option value='$format' $value>$format</option>";
            }
            ?>
        </select>
        Faust: <input type="text" name="Faust" value="<?php echo $Faust; ?>"/>
        Isbn: <input type="text" name="Isbn" value="<?php echo $Isbn; ?>"/>
        Titel: <input type="text" name="Title" value="<?php echo $Title; ?>"/>
        <input type="submit" value="OK" name="Search"/>
        <input type="submit" value="Clear" name="Clear"/>
    </fieldset>
</form>
<br/>
Hint: hvis man skriver %hunde% i titelfeltet søger man på titler der indeholder strengen "hunde"
<br/>


<?php if (count($fromRes)) { ?>
    <br/>
    <hr/>
    <br/>
    <form>
        <table border="1" width="100%">
            <?php
            //            echo $ths;
            $seqnos = "";
            $no = -1;
            foreach ($fromRes as $number => $row) {
                ?>
                <tr>
                    <td>
                        <?php echo $number + 1; ?>

                    </td>
                    <?php
                    foreach ($row as $key => $value) {
                        if ($key == 'seqno') {
                            $seqnos .= ';' . $value;
                            if ($row['provider'] == 'Pubhub') {
                                $no++;
                                $value = " <input type='checkbox' name='seq$no' value='$value' /> " . $value;
                            }
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
        <br/>
        <br/>
        Faust rettes til: <input type="text" name="newfaust" value=""/>
        <br/>
        <br/>
        <input type="submit" value="Ret" name="confirm"/>
        <input type="submit" value="discard" name="confirm"/>
    </form>
    <?php
}
?>

<?php
if ($_REQUEST['confirm'] == 'Ret') {
    $db = new pg_database($connect_string);
    $db->open();
    $seqnos = "";
    foreach ($_REQUEST as $key => $value) {
        if (substr($key, 0, 3) == 'seq') {
            $seqnos .= $value . ",";
        }
    }

    $seqnos = trim($seqnos, ',');
    if (strlen($seqnos) > 2 && strlen($newfaust) == 11) {
//  $seqnos = $_REQUEST['seqnos'];
        $select = "
    select faust from $tablename
      where seqno in ($seqnos)
        and provider = 'Pubhub'
  ";
        $fauster = $db->fetch($select);
        $oldfaust = "";
        foreach ($fauster as $row) {
            $oldfaust .= "'" . $row['faust'] . "',";
        }
        $oldfaust = trim($oldfaust, ',');
// this command does that we can make a rollback - no commit is done until
// the commit command is executed
        $db->exe('START TRANSACTION');
        $insert = "
    insert into $tablename
      select nextval('$seqname'), 'PubhubDel', createdate, format, idnumber, title, originalxml,
       marc, faust, isbn13, sent_to_basis, null, sent_to_covers,
       'd', null, null, cover_status
        FROM $tablename
      where seqno  in ($seqnos)
    ";
        $db->exe($insert);
        $update = "
    update $tablename
      set faust = '$newfaust', sent_to_well = null, sent_to_covers = null, sent_xml_to_well = null
        where seqno in ($seqnos)
    ";
        $db->exe($update);
        $db->exe('commit');
        verbose::log(TRACE, "oldfaust:$oldfaust;newfaust:$newfaust;seqnos:$seqnos");
        $requesturi = $_SERVER['REQUEST_URI'];
        $requesturi = str_replace('confirm=', 'show=', $requesturi) . "&oldfaust=$oldfaust";
        header("Location: $requesturi");
    } else {
        echo "newfaust:[$newfaust], seqnos:[$seqnos]<br />";
    }
}
if ($_REQUEST['show'] == 'Ret') {
    $db = new pg_database($connect_string);
    $db->open();

    $oldfaust = $_REQUEST['oldfaust'];
    echo "<hr>";
    echo "<h2>Poster med faust:$newfaust</h2>";
    $sql = "
    select seqno, provider, createdate, format, title, faust, isbn13
    from $tablename
      where faust = '$newfaust'
      ";
    $showres = $db->fetch($sql);
    showresult($showres);
    echo "<h2>Poster der bliver slettet i Brønden:</h2>";
    $sql = "
    select seqno,status, provider, createdate, format, title, faust, isbn13
    from $tablename
      where faust  in ($oldfaust)
        and provider = 'PubhubDel'
      ";
    $showres = $db->fetch($sql);
    showresult($showres);

//  $selectedSeqno = "";
}
if ($_REQUEST['action'] == 'Ret Faust') {
    $no = 0;
    $seqnos = "";
    while (array_key_exists("s$no", $_REQUEST)) {
        $seqnos .= $_REQUEST["s$no"] . ",";
        $no++;
    }

    $seqnos = trim($seqnos, ',');
    $sql = "
    select seqno, provider, createdate, format, title, faust, isbn13 from $tablename
      where seqno in ($seqnos)
      ";
    $db = new pg_database($connect_string);
    $db->open();
    $showres = $db->fetch($sql);
    showresult($showres);
    ?>
    <br/>
    <form action="" width="50">
        Faust rettes til: <input type="text" name="newfaust" value=""/>
        <br/>
        <br/>
        <input type="submit" value="OK" name="alterFAUST"/>
        <input type="submit" value="discard" name="alterFAUST"/>
        <input type='hidden' value="<?php echo $seqnos; ?>" name="seqnos"/>
    </form>

    <?php
}
if ($_REQUEST['alterFAUST'] == 'OK') {
    $seqnos = $_REQUEST['seqnos'];
    $sql = "
    select seqno, provider, createdate, format, title, faust, isbn13 from $tablename
      where faust = '$newfaust'
      ";
    $db = new pg_database($connect_string);
    $db->open();
    $showres = $db->fetch($sql);
    if ($showres) {
        if (count($showres != 0)) {
            echo "<br /><hr><h2>Følgende har allerede det angivne Faust</h2><br />";
            showresult($showres);
            $err = "Faust '$newfaust' findes i forvejen";
        }
    }
    ?>
    <hr>
    <form action="">
        <h2>Skal Faust ændres til <?php echo $newfaust; ?></h2>
        <input type="submit" value="OK" name="confirm"/>
        <input type="submit" value="discard" name="confirm"/>
        <input type="hidden" value="<?php echo $seqnos; ?>" name="seqnos"/>
        <input type="hidden" value="<?php echo $newfaust; ?>" name="newfaust"/>
    </form>
    <?php
}
?>

<br/>
<br/>
<?php echo $info; ?>
<br/>
<br/>
