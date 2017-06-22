<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

class pubhubFtpException extends Exception {

    public function __toString() {
        verbose::log(ERROR, $this->getMessage());
        return 'pubhubFtpException -->' . $this->getMessage() . ' --- ' .
        $this->getFile() .
        ':' . $this->getLine() . "\nStack trace:\n" . $this->getTraceAsString();
    }

}


class pubhubFtp {

    private $ftp_server;
    private $ftp_user;
    private $ftp_passwd;
    private $conn_id;
    private $maxfetch;
    private $fetchcnt;
    private $year;
    private $dir;

    public function __construct($ftp_server, $ftp_user, $ftp_passwd, $dir = 'work') {
        $this->ftp_server = $ftp_server;
        $this->ftp_user = $ftp_user;
        $this->ftp_passwd = $ftp_passwd;
        $this->ftpconnect();
        $this->maxfetch = 25;
        $this->fetchcnt = 0;
        $this->dir = $dir;
        if (!is_dir($dir)) {
            throw new pubhubFtpException("dir:\"$dir\" is not a directory!");
        }
    }

    private function ftpconnect() {

        $this->year = date('Y');
        if (!$this->conn_id = ftp_connect($this->ftp_server))
            throw new pubhubFtpException("Couldn't connect to " . $this->ftp_server);

        // try to login
        if (ftp_login($this->conn_id, $this->ftp_user, $this->ftp_passwd)) {
//    echo "Connected as $ftp_user@$ftp_server\n";
        } else {
            throw new pubhubFtpException("Couldn't connect as " . $this->ftp_user);
        }
    }


    function getInfo() {
        $now = time();
        $files = ftp_rawlist($this->conn_id, '.');
        $pubhubinfo = array();
        foreach ($files as $file) {
            $info = array();
            $info['status'] = 'new';
            $arr = explode(' ', $file);
            $info['server_file'] = strtolower($arr[count($arr) - 1]);
            $i = 0;
            $fdate = "";
            foreach ($arr as $value) {
                if ($value) {
                    switch ($i) {
                        case 4:
                            $info['filesize'] = $value;
                            break;
                        case 5:
                        case 6:
                        case 7:
//                            $pos = strpos($value, ':');
                            if (strpos($value, ':')) {
                                $tm = strtotime($fdate);
                                if ($tm > $now) {
                                    $value = $this->year - 1;
                                } else {
                                    $value = $this->year;
                                }
                            }
                            $fdate .= $value . " ";
                    }
                    $i++;
                }
            }
            $info['fdate'] = trim($fdate);
            $path_parts = pathinfo($info['server_file']);
            $info['ext'] = $path_parts['extension'];
            $filename = $path_parts['filename'];
            $info['bookid'] = $filename;
            switch ($info['server_file']) {
                case 'Copy.epub':
                case '.epub':
                case '997fec70-399f-425e-a5d3-ab8ea5262cf8.epub':
                    $info['status'] = 'broken';
                    echo "sat til broken\n";
                    print_r($info);
                    break;
            }
//            if ($info['server_file'] == 'Copy.epub' || $info['server_file'] == '.epub') {
//                $info['status'] = 'broken';
//            }
            $pubhubinfo[$info['server_file']] = $info;
        }
        return $pubhubinfo;
    }

    function getEbookFromPubhub($info) {
        if ($info['ext'] != 'epub') {
            return false;
        }
        if ($this->fetchcnt >= $this->maxfetch) {
            ftp_close($this->conn_id);
            $this->ftpconnect();
            $this->fetchcnt = 0;
        }
        $this->fetchcnt++;
        $continue = 3;
        while ($continue) {
            $server_file = $info['server_file'];
            $local_file = $this->dir . "/" . $server_file;
            if (@ftp_get($this->conn_id, $local_file, $server_file, FTP_BINARY)) {
                return $local_file;
            } else {
                echo "try once more: $server_file\n";
                $continue--;
                ftp_close($this->conn_id);
                $this->ftpconnect();
            }
        }
        throw new pubhubFtpException ("There was a problem when ftp_get ($server_file)");
    }

    function removeFile($filename) {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
}