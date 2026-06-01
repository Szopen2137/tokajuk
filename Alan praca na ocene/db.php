<?php
// includes/db.php
// ---------------------------------------------------------------
//  Singleton – połączenie PDO z bazą danych
// ---------------------------------------------------------------

require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST, DB_NAME, DB_CHARSET);
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            if (DEBUG) {
                die(json_encode(['ok' => false, 'msg' => $e->getMessage()]));
            }
            die(json_encode(['ok' => false, 'msg' => 'Błąd połączenia z bazą danych.']));
        }
    }
    return $pdo;
}
