<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */
class session {

    private $db;
    private $username;
    private $site;
    private $tablename;
    private $role;

    function __construct($db, $site) {
        session_start();


        $this->db = $db;
        $this->site = $site;
        $this->tablename = $site . 'userinfo';
        $this->username = $site . "name";
        $this->role = false;
        $this->getC();
// look for the userinfo table
        $sql = "select tablename from pg_tables where tablename = $1";
        $arr = $db->fetch($sql, array($this->tablename));
        if (!$arr) {
            $sql = "create table " . $this->tablename . " ( "
                . "username varchar(50),"
                . "createdate timestamp with time zone, "
                . "update timestamp with time zone, "
                . "role varchar(20),"
                . "passwd varchar(20) "
                . ")";
            $db->exe($sql);
//            verbose::log(TRACE, "table created:$sql");
            $insert = "insert into " . $this->tablename . " "
                . "( username, createdate, update, role, passwd) "
                . "values "
                . "('admin',current_timestamp,current_timestamp, 'admin', 'admin')";
            $db->exe($insert);
        }
    }

    function getUserName() {
        return $this->username;
    }

    function getrole() {
        return $this->role;
    }

    function setC() {
        $threeweeks = 60 * 60 * 24 * 21;
        $c = $_SESSION[$this->username] . "/" . $_SESSION['role'];
        // skal måske bruges igen
        //        setcookie($this->site, $c, time() + $threeweeks);
    }

    function getC() {
        if (array_key_exists($this->site, $_COOKIE)) {
            $c = $_COOKIE[$this->site];
            $arr = explode('/', $c);
            $_SESSION[$this->username] = $arr[0];
            $_SESSION['role'] = $arr[1];
        }
    }

    function clearC() {
        setcookie($this->site, 'false', 1);
    }

    function delete($user) {
        $del = "delete from " . $this->tablename . " "
            . "where username = '$user'";
        $this->db->exe($del);
    }

    function update($user, $passwd, $role) {
        $reset = "update " . $this->tablename . " "
            . "set passwd = '$passwd', "
            . "role = '$role' "
            . "where username = '$user'";
        $this->db->exe($reset);
    }

    function insert($user, $passwd, $role) {
        $select = "select username from " . $this->tablename . " "
            . "where username = '$user'";
        $users = $this->db->fetch($select);
        if ($users) {
            return 'knownuser';
        }
        $insert = "insert into " . $this->tablename . " "
            . "(username, createdate, update, role, passwd) "
            . "values "
            . "('$user',current_timestamp,current_timestamp, '$passwd', '$role')";
        $this->db->exe($insert);
    }

    function listusers() {
        $select = "SELECT "
            . "username, "
            . "to_char(createdate,'DD-MM-YYYY') as createdate,"
            . "to_char(update,'DD-MM-YYYY') as update,"
            . "role "
            . "FROM " . $this->tablename;
        $rows = $this->db->fetch($select);
        return $rows;
    }

    function getInfo($user) {
        $select = "SELECT "
            . "username, "
            . "to_char(createdate,'DD-MM-YYYY') as createdate,"
            . "to_char(update,'DD-MM-YYYY') as update,"
            . "role,"
            . "passwd "
            . "FROM " . $this->tablename . " "
            . "where username = '$user'";
        $rows = $this->db->fetch($select);
        return $rows[0];
    }

    function login($req) {
        if ($req['loginpar']) {
            if ($req['logud']) {
                $_SESSION['role'] = false;
                $_SESSION[$this->username] = false;
                $this->clearC();
            } else {
                if (array_key_exists('newpw', $req)) {
                    $pw = $req['passwd'];
                    $update = "update " . $this->tablename . " "
                        . "set passwd = '" . $req['passwd'] . "' "
                        . "where username = '" . $_SESSION[$this->username] . "' ";
                    $this->db->exe($update);
                } else {
                    $loginOK = false;
                    $username = $req[initials];
                    $passwd = $req[passwd];
                    $sql = "select role, passwd from " . $this->tablename . " "
                        . "where username = $1 "
                        . "and passwd = $2 ";
                    $roles = $this->db->fetch($sql, array($username, $passwd));
                    if ($roles) {
                        $this->role = $roles[0]['role'];
                        $loginOK = true;
                    }
                    $changeToRole = false; // kode som ikke virker da Kiska er for gammel.
                    if ($changeToRole) {
                        $this->role = $roles[0]['role'];
                        if ($this->role == 'Bruger') {
                            $descriptorspec = array(
                                0 => array("pipe", "r"),  // stdin is a pipe that the child will read from
                                1 => array("pipe", "w"),  // stdout is a pipe that the child will write to
                                2 => array("pipe", "w") // stderr is a file to write to
                            );
                            $process = proc_open("kinit $username", $descriptorspec, $pipes);
                            if (is_resource($process)) {
                                // $pipes now looks like this:
                                // 0 => writeable handle connected to child stdin
                                // 1 => readable handle connected to child stdout
                                // Any error output will be appended to /tmp/error-output.txt

                                fwrite($pipes[0], $passwd);
                                fclose($pipes[0]);

                                //    echo stream_get_contents($pipes[1]);
                                fclose($pipes[1]);

                                // It is important that you close any pipes before calling
                                // proc_close in order to avoid a deadlock
                                $res = proc_close($process);
                                if ($res == 0) {
                                    $loginOK = true;
                                }
                            }
                        } else {
                            if ($roles[0]['passwd'] == $passwd) {
                                $loginOK = true;
                            }
                        }

                    }

                    if ($loginOK) {
                        $_SESSION['role'] = $this->role;
                        $_SESSION[$this->username] = $username;
                        $this->setC();
                    } else {
                        $_SESSION['role'] = false;
                        $_SESSION[$this->username] = false;
                        $_SESSION['loginfailure'] = true;
                    }
                }
            }
            header("Location:ProductionPlan.php");
        }
    }

}
