<?php
// ajax/login.php
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'msg' => 'Metoda niedozwolona']);
    exit;
}

$login = trim($_POST['login'] ?? '');
$haslo = $_POST['haslo'] ?? '';

if (!$login || !$haslo) {
    echo json_encode(['ok' => false, 'msg' => 'Podaj login i hasło.']);
    exit;
}

echo json_encode(zalogujUzytkownika($login, $haslo));
