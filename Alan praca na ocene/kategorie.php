<?php
// ajax/kategorie.php  –  CRUD kategorii (każdy użytkownik)
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
wymagajZalogowania();

$u      = aktualnyUzytkownika();
$uid    = (int)$u['id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    // ── Lista kategorii (własnych lub wszystkich dla admina) ──
    case 'lista':
        $targetUid = (int)($_GET['uzytkownik_id'] ?? 0);

        if ($u['rola'] === 'admin' && $targetUid === 0) {
            // Admin bez filtra → wszystkie kategorie + właściciel
            $rows = db()->query(
                'SELECT k.*, CONCAT(uz.imie,\' \',uz.nazwisko) AS wlasciciel
                 FROM kategorie k
                 JOIN uzytkownicy uz ON uz.id = k.uzytkownik_id
                 ORDER BY uz.nazwisko, k.nazwa'
            )->fetchAll();
        } elseif ($u['rola'] === 'admin' && $targetUid > 0) {
            $stmt = db()->prepare(
                'SELECT k.*, CONCAT(uz.imie,\' \',uz.nazwisko) AS wlasciciel
                 FROM kategorie k
                 JOIN uzytkownicy uz ON uz.id = k.uzytkownik_id
                 WHERE k.uzytkownik_id = ?
                 ORDER BY k.nazwa'
            );
            $stmt->execute([$targetUid]);
            $rows = $stmt->fetchAll();
        } else {
            $stmt = db()->prepare(
                'SELECT * FROM kategorie WHERE uzytkownik_id = ? ORDER BY nazwa'
            );
            $stmt->execute([$uid]);
            $rows = $stmt->fetchAll();
        }
        echo json_encode(['ok' => true, 'data' => $rows]);
        break;

    // ── Dodaj kategorię ──
    case 'dodaj':
        $nazwa = trim($_POST['nazwa'] ?? '');
        $opis  = trim($_POST['opis']  ?? '');
        $kolor = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['kolor'] ?? '')
                 ? $_POST['kolor'] : '#3b82f6';

        if (!$nazwa) {
            echo json_encode(['ok' => false, 'msg' => 'Podaj nazwę kategorii.']);
            break;
        }
        $stmt = db()->prepare(
            'INSERT INTO kategorie (uzytkownik_id, nazwa, opis, kolor) VALUES (?,?,?,?)'
        );
        $stmt->execute([$uid, $nazwa, $opis, $kolor]);
        echo json_encode(['ok' => true, 'msg' => 'Kategoria dodana.', 'id' => db()->lastInsertId()]);
        break;

    // ── Edytuj kategorię ──
    case 'edytuj':
        $id    = (int)($_POST['id']    ?? 0);
        $nazwa = trim($_POST['nazwa']  ?? '');
        $opis  = trim($_POST['opis']   ?? '');
        $kolor = preg_match('/^#[0-9a-fA-F]{6}$/', $_POST['kolor'] ?? '')
                 ? $_POST['kolor'] : '#3b82f6';

        if (!$id || !$nazwa) {
            echo json_encode(['ok' => false, 'msg' => 'Brak danych.']);
            break;
        }
        // Sprawdź właściciela
        $owner = db()->prepare('SELECT uzytkownik_id FROM kategorie WHERE id = ?');
        $owner->execute([$id]);
        $row = $owner->fetch();
        if (!$row || ($row['uzytkownik_id'] != $uid && $u['rola'] !== 'admin')) {
            echo json_encode(['ok' => false, 'msg' => 'Brak uprawnień.']);
            break;
        }
        db()->prepare(
            'UPDATE kategorie SET nazwa=?, opis=?, kolor=? WHERE id=?'
        )->execute([$nazwa, $opis, $kolor, $id]);
        echo json_encode(['ok' => true, 'msg' => 'Kategoria zaktualizowana.']);
        break;

    // ── Usuń kategorię ──
    case 'usun':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Brak ID']); break; }

        $owner = db()->prepare('SELECT uzytkownik_id FROM kategorie WHERE id = ?');
        $owner->execute([$id]);
        $row = $owner->fetch();
        if (!$row || ($row['uzytkownik_id'] != $uid && $u['rola'] !== 'admin')) {
            echo json_encode(['ok' => false, 'msg' => 'Brak uprawnień.']);
            break;
        }
        db()->prepare('DELETE FROM kategorie WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Nieznana akcja.']);
}
