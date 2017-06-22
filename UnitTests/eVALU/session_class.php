<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

// TODO skal laves om til mekdiadb_class kald istedet for kald til sql
class session {

    private $db;
    private $tablename;
    private $role;

    function __construct($db) {
        session_start();
        $this->getC();

        $this->db = $db;
        $this->tablename = 'mediaserviceuserinfo';
        $this->role = false;

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
                . "('admin',current_timestamp,current_timestamp, 'admin', 'dglat')";
            $db->exe($insert);
            $insert = "insert into " . $this->tablename . " "
                . "( username, createdate, update, role, passwd) "
                . "values "
                . "('eVa',current_timestamp,current_timestamp, 'eVa', 'eVa')";
            $db->exe($insert);
            $insert = "insert into " . $this->tablename . " "
                . "( username, createdate, update, role, passwd) "
                . "values "
                . "('eLu',current_timestamp,current_timestamp, 'eLu', 'eLu')";
            $db->exe($insert);
        }
    }

    function getrole() {
        return $this->role;
    }

    function setC() {
        $threeweeks = 60 * 60 * 24 * 21;
        $c = $_SESSION['username'] . "/" . $_SESSION['role'];
        setcookie('dmat', $c, time() + $threeweeks);
    }

    function getC() {
        if (array_key_exists('dmat', $_COOKIE)) {
            $c = $_COOKIE['dmat'];
            $arr = explode('/', $c);
            $_SESSION['username'] = $arr[0];
            $_SESSION['role'] = $arr[1];
        }
    }

    function clearC() {
        setcookie('dmat', 'false', 1);
    }

    function login($req) {

        if ($req['loginpar']) {
            if ($req['logud']) {
                $_SESSION['role'] = false;
                $_SESSION['username'] = false;
                $this->clearC();
            } else {
                if (array_key_exists('newpw', $req)) {
                    $pw = $req['passwd'];
                    $update = "update " . $this->tablename . " "
                        . "set passwd = '" . $req['passwd'] . "' "
                        . "where username = '" . $_SESSION['username'] . "' ";
                    $this->db->exe($update);
                } else {
                    $loginOK = false;
                    $username = $req[initials];
                    $passwd = $req[passwd];
                    $sql = "select role from " . $this->tablename . " "
                        . "where username = $1 "
                        . "and passwd = $2 ";
                    $roles = $this->db->fetch($sql, array($username, $passwd));
                    if ($roles) {
                        $this->role = $roles[0]['role'];
                        $loginOK = true;
                    }

                    if ($loginOK) {
                        $_SESSION['role'] = $this->role;
                        $_SESSION['username'] = $username;
                        $this->setC();
                    } else {
                        $_SESSION['role'] = false;
                        $_SESSION['username'] = false;
                        $_SESSION['loginfailure'] = true;
                    }
                }
            }
            header("Location:index.php");
        }
    }

}
