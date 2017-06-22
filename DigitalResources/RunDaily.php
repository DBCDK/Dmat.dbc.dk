#!/usr/bin/php
<?php
/**
 * @file RunDaily.php
 * @brief This script will execute those scripts nessecarry for maintaining
 *   DigitalResources
 */


// fetch records from MediaService
call_php_script("php getXmlFromPubHubMediaService.php");

// update mediaebooks with "source" and "fixed-format"
call_php_script("php getPubHubData.php");

// match records against Basis and eReolen
call_php_script("php MatchMedier.php");

// insert faust
call_php_script("php insertFaustMediaService.php");

// sent records Template to Basis
call_php_script("php ToBasisMediaService.php -s Template");

// sent records ProgramMatch to Basis
call_php_script("php ToBasisMediaService.php -s ProgramMatch");

// sent records UpdateBasis to Basis
call_php_script("php ToBasisMediaService.php -s UpdateBasis");

// sent records to Promat
call_php_script("php ToPromat.php");

// sent update info to Publizon
call_php_script("php ToPublizon.php");

// sent records to Basis with f07 and f06 updates and sent records to lektør basen
call_php_script("php ToLek.php -m 1000");


sleep(3600);

// get the xml data fra PubHub and put them into the postgress database
call_php_script("php XmlsFromPubHub.php -c eReolenLicens");

// get the xml data fra PubHub and put them into the postgress database
call_php_script("php XmlsFromPubHub.php -c eReolen");

// get the xml data fra PubHub and put them into the postgress database
call_php_script("php XmlsFromPubHub.php -c Netlydbog");

call_php_script("php XmlsFromPubHub.php -c NetlydbogLicens");

// get the xml data fra PubHub and put them into the postgress database
call_php_script("php XmlsFromPubHub.php -c Deff");

// look's up the faust number in basis with the isbn from the xml record
call_php_script("php UpdateFaustFromBasis.php");

// pull a new faust from BASIS  to all new records
call_php_script("php insertFaust.php");

// upload danmarc2 records to BASIS
call_php_script("php ToBasis.php -f Netlydbog,NetlydbogLicens,eReolen,eReolenLicens,Deff");

// upload danmarc2 records to Well
//call_php_script("ToWell.php -t eReolenToWell");

// upload danmarc2 records to Well
//call_php_script("ToWell.php -t eReolenLicensToWell");

// upload danmarc2 records to Well
//call_php_script("ToWell.php -t NetlydbogToWell");

// upload danmarc2 records to Well
//call_php_script("ToWell.php -t DeffToWell");

// upload XML  records to Well
call_php_script("php ToWell.php -t PubHubXMLtoWell");


// upload cover image to basis
call_php_script("php UploadFrontPageImages.php -t PubHubImages");


chdir('../cronApps');
// upload ONIX
//call_php_script("php getONIXpubhub.php");

// upload forsider
//call_php_script("php updateMoreinfo.php -m 5000");


/**
 * Call a php script and see if anything has gone wrong!
 *
 * @param string $cmd
 */

function call_php_script($cmd) {
  $date = date('Ymd');
  if (substr($cmd, 0, 4) != 'php ') {
    $cmd = "php " . $cmd;
  }
  //    if ($date >= '20150326' && $date <= '20150401') {
  //if (strpos($cmd, 'ToBasis')) {
  //$cmd .= ' -w 2 -W 2';
  //}
  //}
  //    echo "cmd:$cmd\n";
  $ret = system($cmd, $return_var);
  if ($return_var) {
    echo "RundDaily: " . date('c') . " -- $cmd -- return_var:$return_var, ret:$ret\n";
    exit(10);
  }
} ?>
