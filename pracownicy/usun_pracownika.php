<?php
require_once 'database.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
$idPrac = trim((string)($_POST['id'] ?? ''));

if ($idPrac === '') {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Nie wybrano pracownika do usunięcia.']);
    } else {
        header('Location: index.php?error=missing');
    }
    exit;
}

try {
    $check = $pdo->prepare('SELECT COUNT(*) FROM pracownicy WHERE ID_SZEFA = :id');
    $check->bindValue(':id', $idPrac, PDO::PARAM_INT);
    $check->execute();

    if ((int)$check->fetchColumn() > 0) {
        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nie można usunąć pracownika, ponieważ jest szefem innych pracowników.']);
        } else {
            header('Location: index.php?error=used');
        }
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM pracownicy WHERE ID_PRAC = :id LIMIT 1');
    $delete->bindValue(':id', $idPrac, PDO::PARAM_INT);
    $delete->execute();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Pracownik został usunięty.']);
    } else {
        header('Location: index.php?deleted=1');
    }
    exit;
} catch (PDOException $e) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Nie udało się usunąć pracownika.']);
    } else {
        header('Location: index.php?error=failed');
    }
    exit;
}