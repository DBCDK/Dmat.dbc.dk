<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

/**
 * Created by PhpStorm.
 * User: hhl
 * Date: 07-07-2016
 * Time: 12:47
 */
class ssh {

    private $nothing;
    private $datafile;
    private $error;
    private $scpcmdDatafile;
    private $scpcmdTransfile;
    private $fp;
    private $cnt;

    public function __construct(inifile $config, $section, $nothing) {
        $this->nothing = $nothing;
//        $this->status = $status;
        $this->error = "";
        $this->cnt = 0;

        $workdir = $config->get_value('workdir', 'setup');

        if (!$dfile = $config->get_value('datafile', $section)) {
            $this->error = "no datafile stated in the configuration file ($section)";
            return;
        }

        $ts = date('YmdHis');
        $dfile = str_replace('$ts', $ts, $dfile);
        $datafile = $workdir . "/" . $dfile;
        if ($nothing) {
            echo "DATAFILE:" . $datafile . "\n";
        }

//        $x = getcwd();
        if (!$this->fp = @ fopen($datafile, "w")) {
            $this->error = "couldn't open the file:" . $datafile . " for writing\n";
            return;
        }
        $this->datafile = $datafile;


        if (($transline = $config->get_value('transline', $section)) == false) {
            $this->error = "no transline stated in the configuration file ($section)";
            return;
        }
        if (($transfile = $workdir . "/" . $config->get_value('transfile', $section)) == false) {
            $this->error = "no transfile stated in the configuration file ($section)";
            return;
        }

        $transfile = str_replace('$ts', $ts, $transfile);
        $transline = str_replace('$datafile', basename($datafile), $transline);

        if (!$scptrans = fopen($transfile, "w")) {
            $this->error = "couldn't open the file:" . $transfile . " for writing\n";
            return;

        }

        fwrite($scptrans, $transline);
        fwrite($scptrans, "\nslut\n");
        fclose($scptrans);

        if (($scpcmd = $config->get_value('scpcmd', $section)) == false) {
            $this->error = "no scpcmd stated in the configuration file ($section)";
            return;
        }

        $this->scpcmdDatafile = str_replace(' $datafile ', ' ' . $datafile . ' ', $scpcmd);
        $this->scpcmdDatafile = str_replace('$datafile', basename($datafile), $this->scpcmdDatafile);

        $this->scpcmdTransfile = str_replace(' $datafile ', ' ' . $transfile . ' ', $scpcmd);
        $this->scpcmdTransfile = str_replace('$datafile', basename($transfile), $this->scpcmdTransfile);
    }

    function error() {
        return $this->error;
    }

    function write($marcln) {
        $this->cnt++;
        fwrite($this->fp, $marcln);
    }

    function scp() {
        fclose($this->fp);
        if ($this->nothing) {
            echo $this->scpcmdDatafile . "\n";
            echo $this->scpcmdTransfile . "\n";
            return "nothing: No scp transfer";

        } else {
            if ($this->cnt == 0) {
                return true;
            }
            echo "------------------ sender filerne -------------\n";
            exec($this->scpcmdDatafile, $output, $ret);
            if ($output) {
                foreach ($output as $ln) {
                    echo "scpcmdDatafile:$ln\n";
                }
            }
            exec($this->scpcmdTransfile, $output, $ret);
            if ($output) {
                foreach ($output as $ln) {
                    echo "scpcmdTransfile:$ln\n";
                }
            }

        }
    }
}