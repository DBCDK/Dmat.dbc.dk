<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */


/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 25-02-2016
 * Time: 10:45
 */
class ftp_transfer_class {

    private $nothing;
    private $datafile;
    private $status;
    private $fp;
    private $vars;
    private $error;
    private $cnt;

    public function __construct(inifile $config, $status, $section, $nothing) {

        $this->nothing = $nothing;
        $this->status = $status;
        $this->error = "";
        $this->cnt = 0;


//        var_dump($config);
        $workdir = $config->get_value('workdir', 'setup');
        $datafile = array();
        if (!$dfile = $config->get_value('datafile', $section)) {
            $this->error = "no datafile stated in the configuration file ($section)";
            return;
        }
        $datafile[$section] = $workdir . "/" . $dfile;


        $ts = date('YmdHi');
        $datafile[$section] = str_replace('$ts', $ts, $datafile[$section]);
        $datafile[$section] = str_replace('$name', 'M' . $status, $datafile[$section]);
        if ($nothing) {
            echo "DATAFILE:" . $datafile[$section] . "\n";
        }
        if (!$this->fp = @ fopen($datafile[$section], "w")) {
            $this->error = "couldn't open the file:" . $datafile[$section] . " for writing\n";
            return;
        }

        if (($transline = $config->get_value('transline', $section)) == false) {
            $this->error = "no transline stated in the configuration file ($section)";
            return;
        }
        if (($transfile = $workdir . "/" . $config->get_value('transfile', $section)) == false) {
            $this->error = "no transfile stated in the configuration file ($section)";
            return;
        }

        $transfile = str_replace('$ts', $ts, $transfile);
        $transfile = str_replace('$name', 'M' . $status, $transfile);
        $transline = str_replace('$datafile', basename($datafile[$section]), $transline);

        if (!$fptrans = fopen($transfile, "w")) {
            $this->error = "couldn't open the file:" . $transfile . " for writing\n";
            return;
        }

        fwrite($fptrans, $transline);
        fwrite($fptrans, "\nslut\n");
        fclose($fptrans);

        $ftp_server = $config->get_value('ftp_server', 'setup');
        $ftp_user_name = $config->get_value('ftp_user_name', 'setup');
        $ftp_user_pass = $config->get_value('ftp_user_pass', 'setup');

        if (!$conn_id = ftp_connect($ftp_server)) {
            $this->error = "FTP connection has failed ($ftp_server)!\n";
            return;
        }
        // login with username and password
        $login_result = ftp_login($conn_id, $ftp_user_name, $ftp_user_pass);

        // check connection
        if ((!$conn_id) || (!$login_result)) {
            $this->error = "FTP connection has failed ($ftp_server,$ftp_user_name,$ftp_user_pass)!";
            return;
        }

        // try to change the directory to datain
        if (!ftp_chdir($conn_id, "datain")) {
            $this->error = "Couldn't change directory to datain\n";
            return;
        }

        $this->vars['transfile'] = $transfile;
        $this->vars['transline'] = $transline;
        $this->datafile = $datafile;
        $this->vars['ts'] = $ts;
        $this->conn_id = $conn_id;
        $this->vars['Basis'] = $section;
//        $this->vars['config'] = $config;
    }

    function error() {
        return $this->error;
    }

    function write($isomarc) {
        $this->cnt++;
        fwrite($this->fp, $isomarc);
    }

    function put() {
        fclose($this->fp);
        $conn_id = $this->conn_id;
        $datafile = $this->datafile;
        $Basis = $this->vars['Basis'];
        $transfile = $this->vars['transfile'];

        if ($this->nothing) {
            return "nothing: No ftp transfer";
        } else {
            if ($this->cnt == 0) {
                return true;
            }
            if (!ftp_put($conn_id, basename($datafile[$Basis]), $datafile[$Basis], FTP_BINARY)) {
                return "There was a problem while uploading " . $datafile[$Basis] . "\n";
            }
            if (!ftp_put($conn_id, basename($transfile), $transfile, FTP_BINARY)) {
                return "There was a problem while uploading $transfile\n";
            }

            // close the connection
            ftp_close($conn_id);
//
//            unlink($transfile);
//            unlink($datafile[$Basis]);
            $this->removeFiles();
        }
    }

    function removeFiles() {
        $datafile = $this->datafile;
        $Basis = $this->vars['Basis'];
        $transfile = $this->vars['transfile'];

        unlink($transfile);
        unlink($datafile[$Basis]);
    }

    /**
     * @param $var
     * @return mixed
     *
     * Mostly for PHPUNIT testing
     */
    function get($var) {
        return $this->vars[$var];
    }

    /**
     * @return array
     *
     * Mostly for PHPUNIT testing
     */
    function getDatafile() {
        return $this->datafile;
    }


}