<?php
/**
 * Copyright Dansk Bibliotekscenter a/s. Licensed under GNU GPL v3
 * See license text at https://opensource.dbc.dk/licenses/gpl-3.0
 */

// TODO det skal skrives ud af alle programmer og overføre til sessin_class.php

session_start();
if (array_key_exists('dmat', $_COOKIE)) {
    $c = $_COOKIE['dmat'];
    $arr = explode('/', $c);
    $_SESSION['username'] = $arr[0];
    $_SESSION['role'] = $arr[1];
}

function logud($req) {
    $initials = $req['ini'];
    setcookie('MatLu', $initials, 1);
}

function login($req) {
    $threeweeks = 60 * 60 * 24 * 21;

    if (array_key_exists('ini', $req)) {
        $initials = $req['ini'];
        if ($initials != '') {
            setcookie('MatLu', $initials, time() + $threeweeks);
            $_COOKIE['MatLu'] = $initials;
            return $initials;
        }
    }
    if (array_key_exists('MatLu', $_COOKIE)) {
        $initials = $_COOKIE['MatLu'];
        setcookie('MatLu', $initials, time() + $threeweeks);
        return $initials;
    }
}

function getInitials() {
    if ($_SESSION['username']) {
        return $_SESSION['username'];
    }
    return ($_COOKIE['MatLu']);
}

function setlist($req) {
    $threeweeks = 60 * 60 * 24 * 21;

    if (array_key_exists('type', $req)) {
        $list = $req['type'];
        if ($list != '') {
            setcookie('evalulist', $list, time() + $threeweeks);
            $_COOKIE['evalulist'] = $list;
            return $list;
        }
    }
    if (array_key_exists('evalulist', $_COOKIE)) {
        $initials = $_COOKIE['evalulist'];
        setcookie('evalulist', $list, time() + $threeweeks);
        return $list;
    }
//    else {
//        header('Location: index.php?cmd=loin');
//    }
}

function getlist() {
    $list = $_COOKIE['evalulist'];
    return $list;
}

//function setFoundation() {
//    setcookie('fndt', 'true');
//}

//function getFoundation() {
//    return($_COOKIE['fndt']);
//}
//
//function removeFoundation() {
//    setcookie('fndt', 'false', 1);
//}
