<?php
ini_set('display_errors', 0);
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php_errors.log');
@session_start();


define('DB_DSN', 'mysql:host=100.108.229.101;dbname=budzet_domowy;charset=utf8mb4');
define('DB_USER', 'root');
define('DB_PASS', 'root');

function getDB() {
    static $conn = null;
    if ($conn === null) {
        try {
            $conn = new PDO(DB_DSN, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
            ]);
        } catch (PDOException $e) {
            die(json_encode(['error' => 'Błąd połączenia z bazą danych: ' . $e->getMessage()]));
        }
    }
    return $conn;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function requireLogin() {
    if (!isLoggedIn()) {
        jsonResponse(['error' => 'Wymagane zalogowanie'], 401);


    }
    $db = getDB();
    $stmt = $db->prepare("SELECT id FROM uzytkownicy WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    if (!$stmt->fetch()) {
        session_destroy();
        jsonResponse(['error' => 'Sesja wygasła. Zaloguj się ponownie.'], 401);
    }
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
