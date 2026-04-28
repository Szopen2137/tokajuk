<?php

$host = '100.108.229.101';
$port = '13306';
$dbname = 'Tokajuk|_baza';
$user = 'php_szopen';
$pass = 'Nigger123';

try {
    $pdo = new PDO( "mysql:host=$host;port=$port;dbname=$dbname", $user, $pass );
    $pdo->query('SET NAMES utf8');
} catch (PDOException $e) {
    echo 'Połączenie nie mogło zostać utworzone: ' . $e->getMessage();
    exit();
}

?>