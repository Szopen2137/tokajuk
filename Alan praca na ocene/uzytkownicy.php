<?php
// ajax/uzytkownicy.php  –  CRUD użytkowników (tylko admin)
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
wymagajAdmina();

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ── Lista użytkowników ──
    case 'lista':
        $rows = db()->query(
            'SELECT id, login, imie, nazwisko, email, rola, aktywny,
                    DATE_FORMAT(data_utworzenia,\'%d.%m.%Y\') AS data_ut
             FROM uzytkownicy ORDER BY id'
        )->fetchAll();
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;

    // ── Dodaj użytkownika ──
    case 'dodaj':
        $imie     = trim($_POST['imie']     ?? '');
        $nazwisko = trim($_POST['nazwisko'] ?? '');
        $login    = trim($_POST['login']    ?? '');
        $email    = trim($_POST['email']    ?? '');
        $haslo    = $_POST['haslo']          ?? '';
        $rola     = $_POST['rola']           ?? 'uzytkownik';

        if (!$imie || !$nazwisko || !$login || !$email || !$haslo) {
            echo json_encode(['ok' => false, 'msg' => 'Wypełnij wszystkie pola.']);
            break;
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['ok' => false, 'msg' => 'Nieprawidłowy adres e-mail.']);
            break;
        }
        if (strlen($haslo) < 6) {
            echo json_encode(['ok' => false, 'msg' => 'Hasło musi mieć min. 6 znaków.']);
            break;
        }
        if (!in_array($rola, ['admin', 'uzytkownik'])) {
            $rola = 'uzytkownik';
        }

        try {
            $stmt = db()->prepare(
                'INSERT INTO uzytkownicy (imie, nazwisko, login, email, haslo, rola)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([$imie, $nazwisko, $login, $email, hashHaslo($haslo), $rola]);
            echo json_encode(['ok' => true, 'msg' => 'Użytkownik został dodany.']);
        } catch (PDOException $e) {
            $msg = str_contains($e->getMessage(), 'Duplicate')
                ? 'Login lub e-mail już istnieje.'
                : 'Błąd bazy danych.';
            echo json_encode(['ok' => false, 'msg' => $msg]);
        }
        break;

    // ── Zmiana statusu aktywności ──
    case 'toggle':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Brak ID']); break; }

        // Nie można dezaktywować własnego konta
        if ($id === (int)aktualnyUzytkownika()['id']) {
            echo json_encode(['ok' => false, 'msg' => 'Nie możesz dezaktywować własnego konta.']);
            break;
        }
        db()->prepare('UPDATE uzytkownicy SET aktywny = 1 - aktywny WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    // ── Usuń użytkownika ──
    case 'usun':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Brak ID']); break; }
        if ($id === (int)aktualnyUzytkownika()['id']) {
            echo json_encode(['ok' => false, 'msg' => 'Nie możesz usunąć własnego konta.']);
            break;
        }
        db()->prepare('DELETE FROM uzytkownicy WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Nieznana akcja.']);
}
