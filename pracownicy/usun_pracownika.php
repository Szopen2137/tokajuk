<?php
require_once 'database.php';

$idPrac = trim((string)($_POST['id'] ?? ''));

if ($idPrac === '') {
    header('Location: index.php?error=missing');
    exit;
}

try {
    $check = $pdo->prepare('SELECT COUNT(*) FROM pracownicy WHERE ID_SZEFA = :id');
    $check->bindValue(':id', $idPrac, PDO::PARAM_INT);
    $check->execute();

    if ((int)$check->fetchColumn() > 0) {
        header('Location: index.php?error=used');
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM pracownicy WHERE ID_PRAC = :id LIMIT 1');
    $delete->bindValue(':id', $idPrac, PDO::PARAM_INT);
    $delete->execute();

    header('Location: index.php?deleted=1');
    exit;
} catch (PDOException $e) {
    header('Location: index.php?error=failed');
    exit;
}