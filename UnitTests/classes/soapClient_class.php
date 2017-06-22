<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Class soapClientException
 */
class soapClientException extends Exception {

    public function __toString() {
        return 'soapClientException -->' . $this->getMessage() . ' --- ' . $this->getFile() . ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 26-11-2015
 * Time: 09:31
 */

/**
 * Class soapClient_class
 */
class soapClient_class {

    private $readFromFile;
    private $writeToFile;
    private $curl_proxy;
    private $error;
    private $postxml;
    private $url;
    private $postreq;
    private $retailerkeycode;
    private $currentpostxml;

    /**
     * soapClient_class constructor.
     * @param $config
     * @param $service
     * @param string $clientName
     */
    function __construct($config, $service, $clientName = '', $nocreditials = false) {
        $this->readFromFile = false;
        $this->writeToFile = true;
        $this->error = false;
        $this->postreq = true;
        $retailerkeycode = $agreementId = $retailerId = '';

        $this->curl_proxy = $config->get_value('curl_proxy', 'setup');
        if (($soap = $config->get_value("soap", $service)) == false) {
            $this->postreq = false;
        }
        if (($url = $config->get_value("url", $service)) == false) {
            $this->error = "no url stated in configuration file [$service]";
        }
        if ( !$nocreditials) {
            if (($retailerkeycode = $config->get_value("retailerkeycode", $service)) == false) {
                $this->error = "no retailerkeycode stated in configuration file";
            }
            $this->retailerkeycode = $retailerkeycode;

            $retailerId = $config->get_value("retailerid", $service);
            $agreementId = "";
            if ($clientName) {
                if (($agreementId = $config->get_value("agreementid", $clientName)) == false) {
                    $this->error = "no agreementid  stated in configuration file under [$clientName]";
                }
            }
        }
        if ($this->error) {
            return;
        }
        $postxml = "";
        if ($soap) {
            foreach ($soap as $indx => $values) {
                foreach ($values as $value) {
                    $data = str_replace("@", '"', "$value");
                    $data = str_replace("+retailerid+", $retailerId, $data);
                    $data = str_replace('+agreementid+', $agreementId, $data);
                    $postxml .= str_replace("+retailerkeycode+", $retailerkeycode, $data) . "\n";
                }
            }
        }
        $this->postxml = $postxml;
        $this->url = $url;

        return;
    }

    /**
     * @return mixed
     */
    function getError() {
        return $this->error;
    }

    /**
     * @param bool|true $tf
     */
    function setReadFromFile($tf = true) {
        $this->readFromFile = $tf;
    }

    /**
     * @param bool|false $tf
     */
    function setWriteToFile($tf = false) {
        $this->writeToFile = $tf;
    }

    /**
     * @param $datafile
     * @return bool|mixed|string
     * @throws soapClientException
     */
    function soapClient($datafile) {
        if ($this->readFromFile) {
            if (file_exists($datafile)) {
                $xml = file_get_contents($datafile);
            } else {
                $this->error = "datafile $datafile does not exists";
                throw new soapClientException($this->error);
            }
        } else {
            // Send request to webservice
            $headers[] = "Content-Type: text/xml; charset=UTF-8";
            if ($this->retailerkeycode) {
                $headers[] = "x-service-key:" . $this->retailerkeycode;
            }
            $ch = curl_init();
            if ($this->curl_proxy) {
                curl_setopt($ch, CURLOPT_PROXY, $this->curl_proxy);
            }
            curl_setopt($ch, CURLOPT_TIMEOUT, 120);
            if ($this->postreq) {
                curl_setopt($ch, CURLOPT_POST, 1);
                if ($this->currentpostxml) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->currentpostxml);
                } else {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $this->postxml);
                }
            }
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_URL, $this->url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

            $xml = curl_exec($ch);
            if (curl_errno($ch)) {
                throw new soapClientException(curl_error($ch));
            }
            curl_close($ch);
            if ($datafile) {
                if ($this->writeToFile) {
                    $fp = fopen($datafile, 'w');
                    fwrite($fp, $xml);
                    fclose($fp);
                }
            }
        }
        return $xml;
    }

    function query($query) {
        $this->currentpostxml = str_replace('%query%', $query, $this->postxml);
        $xml = $this->soapClient('');
        return $xml;
    }

    function callMoreInfo($image, $id) {
        $this->currentpostxml = str_replace('%id%', $id, $this->postxml);
        $im = base64_encode($image);
        if (strlen($im) > 50 * 1024 * 1024) {
            $txt = "moreinfoupdate kan kun klare filer indtil 50MB";
            verbose::log(ERROR, $txt);
            throw new soapClientException($txt);
        }

        $this->currentpostxml = str_replace('%coverImage%', $im, $this->currentpostxml);
        $xml = $this->soapClient('');
        return $xml;
    }
}