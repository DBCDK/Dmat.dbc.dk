<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * an epub file is a file containing many files packed in a "zip" container.  One file, which is mandatory, is
 * the *.opf file.  The files contains information in a xml structure with Dublin core alike
 * informations.
 */

/**
 * Class epubException
 */
class epubException extends Exception {

    public function __toString() {
        verbose::log(ERROR, $this->getMessage());
        return 'epubException -->' . $this->getMessage() . ' --- ' . $this->getFile() . ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}

/**
 * Class epub_class
 */
class epub_class {

    private $doc;

    /**
     * epub_class constructor.
     */
    public function __construct() {
        $this->doc = new DOMDocument();
    }

    /**
     * @param $txt string the file list ex. "   343432 2016-06-01 12:24 OEP/hCB koder.opf"
     * @return string "OEP/hCB\ koder.opf"
     */
    private function findFilename($txt) {
        $blankbloks = 4;
        $notblank = true;
        for ($i = 0; $i < strlen($txt); $i++) {
            $char = $txt[$i];
            if ($char == ' ' & $notblank) {
                $blankbloks--;
                $notblank = false;
            }
            if ($char != ' ' & !$notblank) {
                $notblank = true;
            }
            if ($blankbloks == 0 & $notblank) {
                $ret = substr($txt, $i);
                $ret = str_replace(' ', '\ ', $ret);
                return $ret;
            }
        }
    }

    /**
     * @param $filename string where the epub file is located
     * @return string the xml from the opf file in the epub
     * @throws epubException (ERROR will be dumped in the log file)
     */
    private function getxml($filename) {
        $cmd = "unzip -l $filename | grep .opf$";
        exec($cmd, $filelist, $ret);
        if ($ret) {
            verbose::log(ERROR, "It is not a e-pub file $filename");
            return false;
//            throw new epubException("cmd:$cmd in error, ret=$ret \noutput:\n[$filelist]\n");
        }
        $file = $this->findFilename($filelist[0]);
//        $arr = explode(' ', $filelist[0]);
//        $file = $arr[count($arr) - 1];
        $cmd = "unzip -c $filename $file";
        exec($cmd, $output, $ret);
        if ($ret) {
            if ($ret != 1) {
                $out = implode("\n", $output);
                throw new epubException("cmd:$cmd in error, ret=$ret \noutput:\n[$out]\n");
            }
        }
        array_shift($output);
        array_shift($output);
        $output[0] = str_replace('version="1.1"', 'version="1.0"', $output[0]);
        $xml = implode("\n", $output);
        return $xml;
    }

    /**
     * @param $filename string where the epub file is located
     * @return bool (only true, on failure: throw exception)
     * @throws epubException (ERROR will be dumped in the log fil
     */
    public function initEpub($filename) {
        if ( !file_exists($filename)) {
            throw new epubException("filename: $filename does not exists!");
        }
        if ( !$xml = $this->getxml($filename)) {
            return false;
        }
        $this->doc->formatOutput = true;
        $xml = str_replace('<dc:', '<', $xml);
        $xml = str_replace('</dc:', '</', $xml);
        if ( !$this->doc->loadXML($xml)) {
            $txt = "Error when loading XML:\n$xml\n";
            throw new epubException($txt);
        };
        return true;

    }

    /**
     * @return the layout (fixedformat or ..)  or false
     */
    public function getLayout() {
        foreach ($this->doc->getElementsByTagName('meta') as $entries) {
            $att = $entries->getAttribute('property');
            if ($att == 'rendition:layout') {
                return $entries->nodeValue;
            }
        }
        return false;
    }

    /**
     * @return the title or false
     */
    public function getTitle() {
        foreach ($this->doc->getElementsByTagName('title') as $element) {
            return $element->nodeValue;
        }
        return false;
    }

    /**
     * @param $elementname (could by source, title, ...)
     * @return bool or the string of the data
     */
    public function getElement($elementname) {
        foreach ($this->doc->getElementsByTagName($elementname) as $element) {
            return $element->nodeValue;
        }
        return false;
    }

    /**
     * @return string  the xml from the opf file
     */
    public function getOpfXml() {
        $xml = $this->doc->saveXML();
        return $xml;
    }
}