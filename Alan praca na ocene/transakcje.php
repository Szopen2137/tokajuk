<?php
// ajax/transakcje.php  –  CRUD transakcji
require_once __DIR__ . '/../includes/auth.php';
header('Content-Type: application/json; charset=utf-8');
wymagajZalogowania();

$u      = aktualnyUzytkownika();
$uid    = (int)$u['id'];
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Pomocnicze: sprawdź czy użytkownik może czytać transakcje danego właściciela
function canRead(int $ownerUid): bool {
    global $uid, $u;
    return $ownerUid === $uid || $u['rola'] === 'admin';
}

switch ($action) {
    // ── Lista transakcji ──
    case 'lista':
        $filterUid    = (int)($_GET['uzytkownik_id'] ?? 0);
        $filterTyp    = $_GET['typ']     ?? '';
        $filterRodzaj = $_GET['rodzaj']  ?? '';
        $filterMies   = $_GET['miesiac'] ?? ''; // format YYYY-MM

        // Kto widzi co
        if ($u['rola'] === 'admin') {
            $baseUid = $filterUid > 0 ? $filterUid : null;
        } else {
            $baseUid = $uid;
        }

        $params = [];
        $sql = 'SELECT t.id, t.typ, t.rodzaj, t.nazwa, t.kwota,
                       DATE_FORMAT(t.data_transakcji,\'%d.%m.%Y\') AS data_f,
                       t.data_transakcji,
                       t.opis, t.kategoria_id,
                       k.nazwa AS kategoria_nazwa, k.kolor AS kategoria_kolor,
                       CONCAT(uz.imie,\' \',uz.nazwisko) AS uzytkownik
                FROM transakcje t
                LEFT JOIN kategorie k ON k.id = t.kategoria_id
                JOIN uzytkownicy uz ON uz.id = t.uzytkownik_id
                WHERE 1=1 ';

        if ($baseUid !== null) {
            $sql .= ' AND t.uzytkownik_id = ?';
            $params[] = $baseUid;
        }
        if ($filterTyp) {
            $sql .= ' AND t.typ = ?';
            $params[] = $filterTyp;
        }
        if ($filterRodzaj) {
            $sql .= ' AND t.rodzaj = ?';
            $params[] = $filterRodzaj;
        }
        if ($filterMies && preg_match('/^\d{4}-\d{2}$/', $filterMies)) {
            $sql .= ' AND DATE_FORMAT(t.data_transakcji,\'%Y-%m\') = ?';
            $params[] = $filterMies;
        }
        $sql .= ' ORDER BY t.data_transakcji DESC, t.id DESC';

        $stmt = db()->prepare($sql);
        $stmt->execute($params);
        echo json_encode(['ok' => true, 'data' => $stmt->fetchAll()]);
        break;

    // ── Dodaj transakcję ──
    case 'dodaj':
        $typ      = in_array($_POST['typ'] ?? '', ['wydatek','przychod']) ? $_POST['typ'] : null;
        $rodzaj   = in_array($_POST['rodzaj'] ?? '', ['staly','zmienny']) ? $_POST['rodzaj'] : 'zmienny';
        $nazwa    = trim($_POST['nazwa']  ?? '');
        $kwota    = round((float)($_POST['kwota'] ?? 0), 2);
        $katId    = (int)($_POST['kategoria_id'] ?? 0) ?: null;
        $data     = $_POST['data'] ?? date('Y-m-d');
        $opis     = trim($_POST['opis'] ?? '');

        if (!$typ || !$nazwa || $kwota <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Uzupełnij wymagane pola.']);
            break;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            $data = date('Y-m-d');
        }

        // Sprawdź czy kategoria należy do użytkownika (jeśli podana)
        if ($katId) {
            $chk = db()->prepare('SELECT uzytkownik_id FROM kategorie WHERE id = ?');
            $chk->execute([$katId]);
            $kRow = $chk->fetch();
            if (!$kRow || $kRow['uzytkownik_id'] != $uid) {
                $katId = null;
            }
        }

        $stmt = db()->prepare(
            'INSERT INTO transakcje (uzytkownik_id,kategoria_id,typ,rodzaj,nazwa,kwota,opis,data_transakcji)
             VALUES (?,?,?,?,?,?,?,?)'
        );
        $stmt->execute([$uid, $katId, $typ, $rodzaj, $nazwa, $kwota, $opis, $data]);
        echo json_encode(['ok' => true, 'msg' => 'Transakcja dodana.']);
        break;

    // ── Edytuj transakcję ──
    case 'edytuj':
        $id       = (int)($_POST['id'] ?? 0);
        $typ      = in_array($_POST['typ'] ?? '', ['wydatek','przychod']) ? $_POST['typ'] : null;
        $rodzaj   = in_array($_POST['rodzaj'] ?? '', ['staly','zmienny']) ? $_POST['rodzaj'] : 'zmienny';
        $nazwa    = trim($_POST['nazwa']  ?? '');
        $kwota    = round((float)($_POST['kwota'] ?? 0), 2);
        $katId    = (int)($_POST['kategoria_id'] ?? 0) ?: null;
        $data     = $_POST['data'] ?? date('Y-m-d');
        $opis     = trim($_POST['opis'] ?? '');

        if (!$id || !$typ || !$nazwa || $kwota <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Uzupełnij wymagane pola.']);
            break;
        }

        $chk = db()->prepare('SELECT uzytkownik_id FROM transakcje WHERE id = ?');
        $chk->execute([$id]);
        $row = $chk->fetch();
        if (!$row || !canRead((int)$row['uzytkownik_id'])) {
            echo json_encode(['ok' => false, 'msg' => 'Brak uprawnień.']);
            break;
        }

        db()->prepare(
            'UPDATE transakcje SET typ=?,rodzaj=?,nazwa=?,kwota=?,opis=?,data_transakcji=?,kategoria_id=?
             WHERE id=?'
        )->execute([$typ, $rodzaj, $nazwa, $kwota, $opis, $data, $katId, $id]);
        echo json_encode(['ok' => true, 'msg' => 'Transakcja zaktualizowana.']);
        break;

    // ── Usuń transakcję ──
    case 'usun':
        $id = (int)($_POST['id'] ?? 0);
        if (!$id) { echo json_encode(['ok' => false, 'msg' => 'Brak ID']); break; }

        $chk = db()->prepare('SELECT uzytkownik_id FROM transakcje WHERE id = ?');
        $chk->execute([$id]);
        $row = $chk->fetch();
        if (!$row || !canRead((int)$row['uzytkownik_id'])) {
            echo json_encode(['ok' => false, 'msg' => 'Brak uprawnień.']);
            break;
        }
        db()->prepare('DELETE FROM transakcje WHERE id = ?')->execute([$id]);
        echo json_encode(['ok' => true]);
        break;

    default:
        echo json_encode(['ok' => false, 'msg' => 'Nieznana akcja.']);
}
