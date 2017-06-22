<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

$startdir = dirname(__FILE__);
$inclnk = $startdir . "/../inc";

/**
 * @file XmlDiff_class.php
 * @brief A function to find differences betwen to elements
 *
 * @author Hans-Henrik Lund
 *
 * @date 15-06-2012
 *
 */
class XmlDiff {

//  private $new;
//  private $old;
  private $xpathnew;
  private $xpathold;

  /**
   *
   * @param type $xmlold
   * @param type $xmlnew
   */
  function __construct() {


//    $this->new = new DOMDocument();
//    $this->new->loadXML($xmlnew);
//
//    $this->old = new DOMDocument();
//    $this->old->loadXML($xmlold);
//    $this->xpathold = new DOMXPath($this->old);
  }

  /**
   *
   * @param type $path The path to be compared. ex  'formats',  'product/price'
   * use xpath syntax
   * @return boolean or array.  False if no differnce.
   * If diff: an array
   *   arr (
   *    'old'
   *      '0' .....
   *    'new'
   *      '1' ......
   *  )
   */
  function diff($path, $old, $new, $short = false) {

    $this->xpathold = new DOMXPath($old);
    $this->xpathnew = new DOMXpath($new);

    $elements = $this->xpathold->query($path);
    $oldarr = array();
    foreach ($elements as $element) {
      $oldarr[] = str_replace(array("\t", " "), "", trim($element->nodeValue));
    }


    $elements = $this->xpathnew->query($path);
    $newarr = array();
    foreach ($elements as $element) {
      $newarr[] = str_replace(array("\t", " "), "", trim($element->nodeValue));
    }

    if (count($oldarr) == 1 && count($newarr) == 1 && $short) {
      if (count($oldarr[0]) < count($newarr[0])) {
        $len = count($oldarr[0]);
      }
      else {
        $len = count($newarr[0]);
      }
      $res = strncmp($oldarr[0], $newarr[0], $len);
      if ($res != 0)
        return $oldarr[0];
      else
        return false;
    }

    /*
      echo "oldarr:\n";
      print_r($oldarr);
      echo "\nnewarr:\n";
      print_r($newarr);
     */
    $retarr = array();
    $res = array_diff($newarr, $oldarr);
    if ($res) {
      $retarr['old'] = $oldarr;
      $retarr['new'] = $newarr;
      return $retarr;
    }
    else {
      $res = array_diff($oldarr, $newarr);
      if ($res) {
        $retarr['old'] = $oldarr;
        $retarr['new'] = $newarr;
        return $retarr;
      }
      else {
        return false;
      }
    }
  }

}

?>
