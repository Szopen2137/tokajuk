<?php
session_start();

// ====== INICJALIZACJA BAZY DANYCH ======
$dbDir = __DIR__;
if (!is_writable($dbDir)) {
    $dbDir = sys_get_temp_dir();
}

$dbFile = $dbDir . '/budzet.db';
try {
    $db = new PDO('sqlite:' . $dbFile);
} catch (PDOException $e) {
    $dbFile = sys_get_temp_dir() . '/budzet.db';
    $db = new PDO('sqlite:' . $dbFile);
}

$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$db->exec('PRAGMA foreign_keys=ON');

// Tworzenie tabel
$db->exec("CREATE TABLE IF NOT EXISTS users (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    login TEXT UNIQUE NOT NULL,
    haslo TEXT NOT NULL,
    imie TEXT NOT NULL,
    nazwisko TEXT NOT NULL,
    rola TEXT DEFAULT 'uzytkownik' CHECK(rola IN ('admin','uzytkownik')),
    data_dodania DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$db->exec("CREATE TABLE IF NOT EXISTS kategorie (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uzytkownik_id INTEGER NOT NULL,
    nazwa TEXT NOT NULL,
    typ TEXT DEFAULT 'zmienny' CHECK(typ IN ('staly','zmienny')),
    FOREIGN KEY(uzytkownik_id) REFERENCES users(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS wydatki (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uzytkownik_id INTEGER NOT NULL,
    kategoria_id INTEGER NOT NULL,
    kwota REAL NOT NULL,
    data_wydatku DATE NOT NULL,
    opis TEXT DEFAULT '',
    FOREIGN KEY(uzytkownik_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(kategoria_id) REFERENCES kategorie(id) ON DELETE CASCADE
)");

$db->exec("CREATE TABLE IF NOT EXISTS przychody (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    uzytkownik_id INTEGER NOT NULL,
    kwota REAL NOT NULL,
    data_przychodu DATE NOT NULL,
    opis TEXT DEFAULT '',
    FOREIGN KEY(uzytkownik_id) REFERENCES users(id) ON DELETE CASCADE
)");

// Tworzenie użytkownika domyślnego
if (!$db->query("SELECT COUNT(*) FROM users WHERE login='admin'")->fetchColumn()) {
    $db->prepare("INSERT INTO users (login, haslo, imie, nazwisko, rola) VALUES (?, ?, ?, ?, ?)")
        ->execute(['admin', password_hash('admin123', PASSWORD_DEFAULT), 'Administrator', 'System', 'admin']);
}

// ====== OBSŁUGA API ======
if (isset($_POST['action']) || isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    header('Content-Type: application/json; charset=utf-8');
    
    $action = $_POST['action'] ?? '';
    $input = $_POST;
    
    // Funkcje pomocnicze
    function json($d, $c = 200) {
        http_response_code($c);
        echo json_encode($d, JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    function getDb() {
        global $db;
        return $db;
    }
    
    function requireLogin() {
        if (!isset($_SESSION['user_id'])) {
            json(['error' => 'Wymagane logowanie'], 401);
        }
    }
    
    function requireAdmin() {
        requireLogin();
        if ($_SESSION['role'] !== 'admin') {
            json(['error' => 'Brak uprawnień'], 403);
        }
    }
    
    function uid() {
        return $_SESSION['user_id'];
    }
    
    // Obsługuje akcje
    try {
        switch ($action) {
            // ====== LOGOWANIE ======
            case 'login':
                $login = trim($input['login'] ?? '');
                $haslo = $input['haslo'] ?? '';
                
                if (!$login || !$haslo) {
                    json(['error' => 'Podaj login i hasło'], 400);
                }
                
                $s = getDb()->prepare("SELECT * FROM users WHERE login = ?");
                $s->execute([$login]);
                $u = $s->fetch(PDO::FETCH_ASSOC);
                
                if (!$u || !password_verify($haslo, $u['haslo'])) {
                    json(['error' => 'Nieprawidłowy login lub hasło'], 401);
                }
                
                $_SESSION = [
                    'user_id' => $u['id'],
                    'login' => $u['login'],
                    'imie' => $u['imie'],
                    'nazwisko' => $u['nazwisko'],
                    'role' => $u['rola']
                ];
                
                json([
                    'ok' => true,
                    'imie' => $u['imie'],
                    'nazwisko' => $u['nazwisko'],
                    'login' => $u['login'],
                    'rola' => $u['rola']
                ]);
                
            // ====== WYLOGOWANIE ======
            case 'logout':
                session_destroy();
                json(['ok' => true]);
                
            // ====== SPRAWDZENIE LOGOWANIA ======
            case 'check':
                if (!empty($_SESSION['user_id'])) {
                    json([
                        'logged' => true,
                        'imie' => $_SESSION['imie'],
                        'nazwisko' => $_SESSION['nazwisko'],
                        'login' => $_SESSION['login'],
                        'rola' => $_SESSION['role']
                    ]);
                }
                json(['logged' => false]);
                
            // ====== REJESTRACJA ======
            case 'register':
                $imie = trim($input['imie'] ?? '');
                $nazw = trim($input['nazwisko'] ?? '');
                $login = trim($input['login'] ?? '');
                $haslo = $input['haslo'] ?? '';
                
                if (!$imie || !$nazw || !$login || !$haslo) {
                    json(['error' => 'Wypełnij wszystkie pola'], 400);
                }
                
                $db = getDb();
                $s = $db->prepare("SELECT id FROM users WHERE login = ?");
                $s->execute([$login]);
                
                if ($s->fetch()) {
                    json(['error' => 'Login już istnieje'], 400);
                }
                
                $db->prepare("INSERT INTO users (login, haslo, imie, nazwisko, rola) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$login, password_hash($haslo, PASSWORD_DEFAULT), $imie, $nazw, 'uzytkownik']);
                
                json(['ok' => true]);
                
            // ====== DASHBOARD ======
            case 'dashboard':
                requireLogin();
                $m = intval($input['month'] ?? date('n'));
                $y = intval($input['year'] ?? date('Y'));
                $db = getDb();
                
                $s = $db->prepare("SELECT COALESCE(SUM(kwota), 0) FROM przychody 
                    WHERE uzytkownik_id = ? 
                    AND strftime('%m', data_przychodu) = ? 
                    AND strftime('%Y', data_przychodu) = ?");
                $s->execute([uid(), sprintf('%02d', $m), $y]);
                $income = $s->fetchColumn();
                
                $s = $db->prepare("SELECT w.*, k.nazwa as kat, k.typ FROM wydatki w 
                    JOIN kategorie k ON w.kategoria_id = k.id 
                    WHERE w.uzytkownik_id = ? 
                    AND strftime('%m', w.data_wydatku) = ? 
                    AND strftime('%Y', w.data_wydatku) = ? 
                    ORDER BY w.data_wydatku DESC");
                $s->execute([uid(), sprintf('%02d', $m), $y]);
                $exps = $s->fetchAll(PDO::FETCH_ASSOC);
                
                $total = array_sum(array_column($exps, 'kwota'));
                
                json(['income' => $income, 'expense' => $total, 'expenses' => $exps]);
                
            // ====== KATEGORIE ======
            case 'get_cats':
                requireLogin();
                $s = getDb()->prepare("SELECT * FROM kategorie WHERE uzytkownik_id = ? ORDER BY nazwa");
                $s->execute([uid()]);
                json($s->fetchAll(PDO::FETCH_ASSOC));
                
            case 'add_cat':
                requireLogin();
                $nazwa = trim($input['nazwa'] ?? '');
                $typ = $input['typ'] ?? 'zmienny';
                
                if (!$nazwa) {
                    json(['error' => 'Podaj nazwę'], 400);
                }
                
                getDb()->prepare("INSERT INTO kategorie (uzytkownik_id, nazwa, typ) VALUES (?, ?, ?)")
                    ->execute([uid(), $nazwa, $typ]);
                
                json(['ok' => true, 'id' => getDb()->lastInsertId()]);
                
            case 'del_cat':
                requireLogin();
                getDb()->prepare("DELETE FROM kategorie WHERE id = ? AND uzytkownik_id = ?")
                    ->execute([intval($input['id']), uid()]);
                json(['ok' => true]);
                
            // ====== WYDATKI ======
            case 'get_expenses':
                requireLogin();
                $m = intval($input['month'] ?? date('n'));
                $y = intval($input['year'] ?? date('Y'));
                $db = getDb();
                
                $s = $db->prepare("SELECT w.*, k.nazwa as kat, k.typ FROM wydatki w 
                    JOIN kategorie k ON w.kategoria_id = k.id 
                    WHERE w.uzytkownik_id = ? 
                    AND strftime('%m', w.data_wydatku) = ? 
                    AND strftime('%Y', w.data_wydatku) = ? 
                    ORDER BY w.data_wydatku DESC");
                $s->execute([uid(), sprintf('%02d', $m), $y]);
                
                json($s->fetchAll(PDO::FETCH_ASSOC));
                
            case 'add_expense':
                requireLogin();
                $kid = intval($input['kategoria_id'] ?? 0);
                $kw = floatval($input['kwota'] ?? 0);
                $dt = $input['data_wydatku'] ?? date('Y-m-d');
                $op = trim($input['opis'] ?? '');
                
                if ($kid <= 0 || $kw <= 0) {
                    json(['error' => 'Nieprawidłowe dane'], 400);
                }
                
                getDb()->prepare("INSERT INTO wydatki (uzytkownik_id, kategoria_id, kwota, data_wydatku, opis) 
                    VALUES (?, ?, ?, ?, ?)")
                    ->execute([uid(), $kid, $kw, $dt, $op]);
                
                json(['ok' => true]);
                
            case 'del_expense':
                requireLogin();
                getDb()->prepare("DELETE FROM wydatki WHERE id = ? AND uzytkownik_id = ?")
                    ->execute([intval($input['id']), uid()]);
                json(['ok' => true]);
                
            // ====== PRZYCHODY ======
            case 'get_income':
                requireLogin();
                $m = intval($input['month'] ?? date('n'));
                $y = intval($input['year'] ?? date('Y'));
                
                $s = getDb()->prepare("SELECT * FROM przychody 
                    WHERE uzytkownik_id = ? 
                    AND strftime('%m', data_przychodu) = ? 
                    AND strftime('%Y', data_przychodu) = ? 
                    ORDER BY data_przychodu DESC");
                $s->execute([uid(), sprintf('%02d', $m), $y]);
                
                json($s->fetchAll(PDO::FETCH_ASSOC));
                
            case 'add_income':
                requireLogin();
                $kw = floatval($input['kwota'] ?? 0);
                $dt = $input['data_przychodu'] ?? date('Y-m-d');
                $op = trim($input['opis'] ?? '');
                
                if ($kw <= 0) {
                    json(['error' => 'Podaj kwotę'], 400);
                }
                
                getDb()->prepare("INSERT INTO przychody (uzytkownik_id, kwota, data_przychodu, opis) VALUES (?, ?, ?, ?)")
                    ->execute([uid(), $kw, $dt, $op]);
                
                json(['ok' => true]);
                
            case 'del_income':
                requireLogin();
                getDb()->prepare("DELETE FROM przychody WHERE id = ? AND uzytkownik_id = ?")
                    ->execute([intval($input['id']), uid()]);
                json(['ok' => true]);
                
            // ====== STATYSTYKI ======
            case 'get_stats':
                requireLogin();
                $m = intval($input['month'] ?? date('n'));
                $y = intval($input['year'] ?? date('Y'));
                $db = getDb();
                $sm = sprintf('%02d', $m);
                
                // Funkcja pomocnicza do pobierania sum
                $q = function ($sql, $p) use ($db) {
                    $s = $db->prepare($sql);
                    $s->execute($p);
                    return floatval($s->fetchColumn());
                };
                
                $inc = $q("SELECT COALESCE(SUM(kwota), 0) FROM przychody 
                    WHERE uzytkownik_id = ? 
                    AND strftime('%m', data_przychodu) = ? 
                    AND strftime('%Y', data_przychodu) = ?", [uid(), $sm, $y]);
                
                $fixed = $q("SELECT COALESCE(SUM(w.kwota), 0) FROM wydatki w 
                    JOIN kategorie k ON w.kategoria_id = k.id 
                    WHERE w.uzytkownik_id = ? 
                    AND k.typ = 'staly' 
                    AND strftime('%m', w.data_wydatku) = ? 
                    AND strftime('%Y', w.data_wydatku) = ?", [uid(), $sm, $y]);
                
                $var = $q("SELECT COALESCE(SUM(w.kwota), 0) FROM wydatki w 
                    JOIN kategorie k ON w.kategoria_id = k.id 
                    WHERE w.uzytkownik_id = ? 
                    AND k.typ = 'zmienny' 
                    AND strftime('%m', w.data_wydatku) = ? 
                    AND strftime('%Y', w.data_wydatku) = ?", [uid(), $sm, $y]);
                
                $s = $db->prepare("SELECT k.nazwa, SUM(w.kwota) as total FROM wydatki w 
                    JOIN kategorie k ON w.kategoria_id = k.id 
                    WHERE w.uzytkownik_id = ? 
                    AND strftime('%m', w.data_wydatku) = ? 
                    AND strftime('%Y', w.data_wydatku) = ? 
                    GROUP BY k.nazwa 
                    ORDER BY total DESC");
                $s->execute([uid(), $sm, $y]);
                
                json(['income' => $inc, 'fixed' => $fixed, 'variable' => $var, 'by_category' => $s->fetchAll(PDO::FETCH_ASSOC)]);
                
            // ====== ADMIN - UŻYTKOWNICY ======
            case 'admin_users':
                requireAdmin();
                $s = getDb()->query("SELECT id, login, imie, nazwisko, rola, data_dodania FROM users ORDER BY nazwisko");
                json($s->fetchAll(PDO::FETCH_ASSOC));
                
            case 'admin_add_user':
                requireAdmin();
                $imie = trim($input['imie'] ?? '');
                $nazw = trim($input['nazwisko'] ?? '');
                $login = trim($input['login'] ?? '');
                $haslo = $input['haslo'] ?? '';
                $rola = $input['rola'] ?? 'uzytkownik';
                
                if (!$imie || !$nazw || !$login || !$haslo) {
                    json(['error' => 'Wypełnij pola'], 400);
                }
                
                if (!in_array($rola, ['admin', 'uzytkownik'])) {
                    $rola = 'uzytkownik';
                }
                
                $db = getDb();
                $s = $db->prepare("SELECT id FROM users WHERE login = ?");
                $s->execute([$login]);
                
                if ($s->fetch()) {
                    json(['error' => 'Login istnieje'], 400);
                }
                
                $db->prepare("INSERT INTO users (login, haslo, imie, nazwisko, rola) VALUES (?, ?, ?, ?, ?)")
                    ->execute([$login, password_hash($haslo, PASSWORD_DEFAULT), $imie, $nazw, $rola]);
                
                json(['ok' => true]);
                
            case 'admin_del_user':
                requireAdmin();
                $id = intval($input['id'] ?? 0);
                $db = getDb();
                
                $s = $db->prepare("SELECT login FROM users WHERE id = ?");
                $s->execute([$id]);
                $u = $s->fetch();
                
                if ($u && $u['login'] === 'admin') {
                    json(['error' => 'Nie można usunąć admina'], 400);
                }
                
                $db->prepare("DELETE FROM users WHERE id = ?")->execute([$id]);
                json(['ok' => true]);
                
            // ====== ADMIN - WYDATKI ======
            case 'admin_expenses':
                requireAdmin();
                $m = intval($input['month'] ?? date('n'));
                $y = intval($input['year'] ?? date('Y'));
                $uid = intval($input['user_id'] ?? 0);
                $db = getDb();
                $sm = sprintf('%02d', $m);
                
                if ($uid > 0) {
                    $s = $db->prepare("SELECT w.*, u.imie || ' ' || u.nazwisko as user, k.nazwa as kat, k.typ 
                        FROM wydatki w 
                        JOIN users u ON w.uzytkownik_id = u.id 
                        JOIN kategorie k ON w.kategoria_id = k.id 
                        WHERE w.uzytkownik_id = ? 
                        AND strftime('%m', w.data_wydatku) = ? 
                        AND strftime('%Y', w.data_wydatku) = ? 
                        ORDER BY w.data_wydatku DESC");
                    $s->execute([$uid, $sm, $y]);
                } else {
                    $s = $db->prepare("SELECT w.*, u.imie || ' ' || u.nazwisko as user, k.nazwa as kat, k.typ 
                        FROM wydatki w 
                        JOIN users u ON w.uzytkownik_id = u.id 
                        JOIN kategorie k ON w.kategoria_id = k.id 
                        WHERE strftime('%m', w.data_wydatku) = ? 
                        AND strftime('%Y', w.data_wydatku) = ? 
                        ORDER BY w.data_wydatku DESC");
                    $s->execute([$sm, $y]);
                }
                
                json($s->fetchAll(PDO::FETCH_ASSOC));
                
            // ====== ADMIN - STATYSTYKI ======
            case 'admin_stats':
                requireAdmin();
                $m = intval($input['month'] ?? date('n'));
                $y = intval($input['year'] ?? date('Y'));
                $db = getDb();
                $sm = sprintf('%02d', $m);
                
                $q = function ($sql, $p) use ($db) {
                    $s = $db->prepare($sql);
                    $s->execute($p);
                    return floatval($s->fetchColumn());
                };
                
                $inc = $q("SELECT COALESCE(SUM(kwota), 0) FROM przychody 
                    WHERE strftime('%m', data_przychodu) = ? 
                    AND strftime('%Y', data_przychodu) = ?", [$sm, $y]);
                
                $exp = $q("SELECT COALESCE(SUM(kwota), 0) FROM wydatki 
                    WHERE strftime('%m', data_wydatku) = ? 
                    AND strftime('%Y', data_wydatku) = ?", [$sm, $y]);
                
                $s = $db->prepare("SELECT u.imie || ' ' || u.nazwisko as user, SUM(w.kwota) as total 
                    FROM wydatki w 
                    JOIN users u ON w.uzytkownik_id = u.id 
                    WHERE strftime('%m', w.data_wydatku) = ? 
                    AND strftime('%Y', w.data_wydatku) = ? 
                    GROUP BY w.uzytkownik_id 
                    ORDER BY total DESC");
                $s->execute([$sm, $y]);
                $byUser = $s->fetchAll(PDO::FETCH_ASSOC);
                
                $s = $db->prepare("SELECT k.nazwa, SUM(w.kwota) as total 
                    FROM wydatki w 
                    JOIN kategorie k ON w.kategoria_id = k.id 
                    WHERE strftime('%m', w.data_wydatku) = ? 
                    AND strftime('%Y', w.data_wydatku) = ? 
                    GROUP BY k.nazwa 
                    ORDER BY total DESC");
                $s->execute([$sm, $y]);
                $byCat = $s->fetchAll(PDO::FETCH_ASSOC);
                
                json(['income' => $inc, 'expense' => $exp, 'by_user' => $byUser, 'by_category' => $byCat]);
                
            default:
                json(['error' => 'Nieznana akcja'], 400);
        }
    } catch (Throwable $e) {
        json(['error' => 'Błąd: ' . $e->getMessage()], 500);
    }
    
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Budżet Domowy</title>
    <style>
        /* Normalizacja */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f0f2f5;
            color: #333;
        }

        /* Nawigacja */
        .nb {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            padding: 14px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #fff;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
        }

        .nb h1 {
            font-size: 19px;
        }

        .nb .ui {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
        }

        .nb a {
            color: #fff;
            text-decoration: none;
            padding: 7px 14px;
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.15);
            font-size: 12px;
            transition: 0.2s;
        }

        .nb a:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .nb a.ab {
            background: #e74c3c !important;
            font-weight: 700;
        }

        /* Kontener */
        .ct {
            max-width: 1100px;
            margin: 18px auto;
            padding: 0 18px;
        }

        /* Login Box */
        .lb {
            max-width: 380px;
            margin: 70px auto;
            background: #fff;
            border-radius: 12px;
            padding: 36px;
            box-shadow: 0 2px 15px rgba(0, 0, 0, 0.1);
        }

        .lb h2 {
            text-align: center;
            margin-bottom: 20px;
            color: #2c3e50;
        }

        /* Tabela przełączników (Login/Register) */
        .lb .tb {
            display: flex;
            margin-bottom: 18px;
            border-bottom: 2px solid #eee;
        }

        .lb .tb button {
            flex: 1;
            padding: 9px;
            border: none;
            background: none;
            cursor: pointer;
            font-size: 13px;
            color: #888;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: 0.2s;
        }

        .lb .tb button.on {
            color: #3498db;
            border-bottom-color: #3498db;
            font-weight: 700;
        }

        /* Pola formularza */
        .fg {
            margin-bottom: 13px;
        }

        .fg label {
            display: block;
            margin-bottom: 4px;
            font-weight: 600;
            font-size: 12px;
            color: #555;
        }

        .fg input,
        .fg select {
            width: 100%;
            padding: 9px 11px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 13px;
            transition: 0.2s;
        }

        .fg input:focus,
        .fg select:focus {
            outline: none;
            border-color: #3498db;
        }

        /* Przyciski */
        .btn {
            padding: 9px 18px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .btn.bp {
            background: #3498db;
            color: #fff;
            width: 100%;
        }

        .btn.bp:hover {
            background: #2980b9;
        }

        .btn.bg {
            background: #27ae60;
            color: #fff;
        }

        .btn.bg:hover {
            background: #219a52;
        }

        .btn.bd {
            background: #e74c3c;
            color: #fff;
        }

        .btn.bd:hover {
            background: #c0392b;
        }

        .btn.bw {
            background: #f39c12;
            color: #fff;
        }

        .btn.bw:hover {
            background: #d68910;
        }

        .btn.bs {
            padding: 5px 10px;
            font-size: 11px;
        }

        /* Dashboard - Karty */
        .dash {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }

        .sc {
            background: #fff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        }

        .sc h3 {
            font-size: 12px;
            color: #888;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            margin-bottom: 8px;
        }

        .sc .v {
            font-size: 26px;
            font-weight: 700;
        }

        .sc.inc .v {
            color: #27ae60;
        }

        .sc.exp .v {
            color: #e74c3c;
        }

        .sc.sav .v {
            color: #3498db;
        }

        /* Panel */
        .pn {
            background: #fff;
            border-radius: 12px;
            padding: 22px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            margin-bottom: 18px;
        }

        .pn h2 {
            font-size: 17px;
            color: #2c3e50;
            margin-bottom: 16px;
            padding-bottom: 8px;
            border-bottom: 2px solid #f0f2f5;
        }

        .ph {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        /* Prze przełącznik miesięcy */
        .tn {
            display: flex;
            gap: 4px;
            margin-bottom: 18px;
            background: #f0f2f5;
            padding: 4px;
            border-radius: 10px;
            flex-wrap: wrap;
        }

        .tn button {
            flex: 1;
            padding: 9px 13px;
            border: none;
            background: none;
            cursor: pointer;
            border-radius: 8px;
            font-size: 12px;
            font-weight: 600;
            color: #666;
            transition: 0.2s;
            min-width: 100px;
        }

        .tn button.on {
            background: #fff;
            color: #3498db;
            box-shadow: 0 1px 5px rgba(0, 0, 0, 0.1);
        }

        /* Tabela */
        table {
            width: 100%;
            border-collapse: collapse;
        }

        table th {
            text-align: left;
            padding: 10px;
            background: #f8f9fa;
            font-size: 11px;
            text-transform: uppercase;
            color: #666;
            border-bottom: 2px solid #eee;
        }

        table td {
            padding: 10px;
            border-bottom: 1px solid #f0f2f5;
            font-size: 13px;
        }

        table tr:hover {
            background: #f8f9fa;
        }

        /* Tagi */
        .tg {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 10px;
            font-weight: 600;
        }

        .tg-s {
            background: #ffeaa7;
            color: #d68910;
        }

        .tg-z {
            background: #dfe6e9;
            color: #636e72;
        }

        /* Modal */
        .mo {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }

        .mo.on {
            display: flex;
        }

        .md {
            background: #fff;
            border-radius: 12px;
            padding: 28px;
            width: 90%;
            max-width: 440px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .md h3 {
            margin-bottom: 16px;
            color: #2c3e50;
        }

        .ma {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            margin-top: 16px;
        }

        /* Przełącznik miesięcy */
        .ms {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 18px;
        }

        .ms button {
            padding: 7px 13px;
            border: 2px solid #e0e0e0;
            background: #fff;
            border-radius: 8px;
            cursor: pointer;
            font-size: 15px;
        }

        .ms button:hover {
            border-color: #3498db;
        }

        .ms span {
            font-size: 17px;
            font-weight: 600;
            min-width: 180px;
            text-align: center;
        }

        /* Pasek postępu */
        .cb {
            display: flex;
            align-items: center;
            margin-bottom: 7px;
        }

        .cb .l {
            width: 110px;
            font-size: 12px;
            text-align: right;
            padding-right: 8px;
        }

        .cb .b {
            flex: 1;
            height: 22px;
            border-radius: 4px;
            overflow: hidden;
            background: #f0f2f5;
        }

        .cb .bf {
            height: 100%;
            border-radius: 4px;
            transition: width 0.5s;
            display: flex;
            align-items: center;
            padding-left: 7px;
            font-size: 10px;
            color: #fff;
            font-weight: 600;
            background: linear-gradient(90deg, #e74c3c, #e67e22);
        }

        /* Komunikat */
        #msg {
            position: fixed;
            top: 18px;
            right: 18px;
            padding: 13px 22px;
            border-radius: 10px;
            color: #fff;
            font-weight: 600;
            z-index: 2000;
            display: none;
            animation: si 0.3s;
        }

        #msg.s {
            background: #27ae60;
        }

        #msg.e {
            background: #e74c3c;
        }

        @keyframes si {
            from {
                transform: translateX(80px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        /* UtilityClasses */
        .hid {
            display: none !important;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nb {
                flex-direction: column;
                gap: 8px;
            }

            .tn {
                flex-direction: column;
            }

            .tn button {
                min-width: auto;
            }
        }
    </style>
</head>
<body>
    <div id="msg"></div>

    <!-- Navbar -->
    <div class="nb hid" id="nb">
        <h1>Budżet Domowy</h1>
        <div class="ui">
            <span id="nb-u"></span>
            <a href="#" id="nb-a" class="ab hid" onclick="showAdmin(); return false;">Admin</a>
            <a href="#" onclick="logout(); return false;">Wyloguj</a>
        </div>
    </div>

    <!-- Logowanie / Rejestracja -->
    <div class="ct" id="auth">
        <div class="lb">
            <h2>Budżet Domowy</h2>
            <div class="tb">
                <button class="on" onclick="at('login')">Logowanie</button>
                <button onclick="at('register')">Rejestracja</button>
            </div>

            <!-- Logowanie -->
            <form id="lf" onsubmit="return doLogin(event)">
                <div class="fg">
                    <label>Login</label>
                    <input type="text" id="l-l" required>
                </div>
                <div class="fg">
                    <label>Hasło</label>
                    <input type="password" id="l-p" required>
                </div>
                <button type="submit" class="btn bp">Zaloguj się</button>
                <p style="text-align: center; margin-top: 14px; font-size: 11px; color: #999;">
                    Domyślne konto: admin / admin123
                </p>
            </form>

            <!-- Rejestracja -->
            <form id="rf" class="hid" onsubmit="return doRegister(event)">
                <div class="fg">
                    <label>Imię</label>
                    <input type="text" id="r-i" required>
                </div>
                <div class="fg">
                    <label>Nazwisko</label>
                    <input type="text" id="r-n" required>
                </div>
                <div class="fg">
                    <label>Login</label>
                    <input type="text" id="r-l" required>
                </div>
                <div class="fg">
                    <label>Hasło</label>
                    <input type="password" id="r-p" required>
                </div>
                <button type="submit" class="btn bp">Zarejestruj się</button>
            </form>
        </div>
    </div>

    <!-- Główna aplikacja -->
    <div class="ct hid" id="app">
        <!-- Menu -->
        <div class="tn" id="mt">
            <button class="on" onclick="sw('dash')">Podsumowanie</button>
            <button onclick="sw('inc')">Przychody</button>
            <button onclick="sw('cat')">Kategorie</button>
            <button onclick="sw('exp')">Wydatki</button>
            <button onclick="sw('stt')">Statystyki</button>
        </div>

        <!-- Podsumowanie -->
        <div id="t-dash">
            <div class="ms">
                <button onclick="cm(-1)">&#9664;</button>
                <span id="ml"></span>
                <button onclick="cm(1)">&#9654;</button>
            </div>
            <div class="dash">
                <div class="sc inc">
                    <h3>Przychody</h3>
                    <div class="v" id="si">0,00 zł</div>
                </div>
                <div class="sc exp">
                    <h3>Wydatki</h3>
                    <div class="v" id="se">0,00 zł</div>
                </div>
                <div class="sc sav">
                    <h3>Oszczędności</h3>
                    <div class="v" id="ss">0,00 zł</div>
                </div>
            </div>
            <div class="pn">
                <h2>Wydatki w tym miesiącu</h2>
                <div id="de"></div>
            </div>
        </div>

        <!-- Kategorie -->
        <div id="t-cat" class="hid">
            <div class="pn">
                <div class="ph">
                    <h2>Kategorie wydatków</h2>
                    <button class="btn bg" onclick="ocm()">+ Nowa</button>
                </div>
                <div id="cl"></div>
            </div>
        </div>

        <!-- Przychody -->
        <div id="t-inc" class="hid">
            <div class="ms">
                <button onclick="cm(-1)">&#9664;</button>
                <span id="ml2"></span>
                <button onclick="cm(1)">&#9654;</button>
            </div>
            <div class="pn">
                <div class="ph">
                    <h2>Przychody</h2>
                    <button class="btn bg" onclick="oim()">+ Nowy</button>
                </div>
                <div id="il"></div>
            </div>
        </div>

        <!-- Wydatki -->
        <div id="t-exp" class="hid">
            <div class="ms">
                <button onclick="cm(-1)">&#9664;</button>
                <span id="ml3"></span>
                <button onclick="cm(1)">&#9654;</button>
            </div>
            <div class="pn">
                <div class="ph">
                    <h2>Wydatki</h2>
                    <button class="btn bg" onclick="oem()">+ Nowy</button>
                </div>
                <div id="el"></div>
            </div>
        </div>

        <!-- Statystyki -->
        <div id="t-stt" class="hid">
            <div class="ms">
                <button onclick="cm(-1)">&#9664;</button>
                <span id="ml4"></span>
                <button onclick="cm(1)">&#9654;</button>
            </div>
            <div class="pn">
                <h2>Porównanie</h2>
                <div id="st1"></div>
            </div>
            <div class="pn">
                <h2>Wg kategorii</h2>
                <div id="st2"></div>
            </div>
        </div>
    </div>

    <!-- Panel administratora -->
    <div class="ct hid" id="adm">
        <div class="tn">
            <button class="on" onclick="aw('au')">Użytkownicy</button>
            <button onclick="aw('ae')">Wydatki wszystkich</button>
            <button onclick="aw('as')">Statystyki</button>
            <button onclick="aw('au2')">Dodaj użytkownika</button>
        </div>

        <!-- Użytkownicy -->
        <div id="at-au" class="pn">
            <h2>Użytkownicy</h2>
            <div id="aul"></div>
        </div>

        <!-- Wydatki wszystkich -->
        <div id="at-ae" class="pn hid">
            <div class="ph">
                <h2>Wydatki wszystkich</h2>
                <select id="auf" onchange="lae()" style="padding: 5px 8px; border: 2px solid #e0e0e0; border-radius: 6px; font-size: 12px;">
                    <option value="0">Wszyscy</option>
                </select>
            </div>
            <div class="ms">
                <button onclick="cm(-1)">&#9664;</button>
                <span id="ml5"></span>
                <button onclick="cm(1)">&#9654;</button>
            </div>
            <div id="ael"></div>
        </div>

        <!-- Statystyki admin -->
        <div id="at-as" class="pn hid">
            <div class="ms">
                <button onclick="cm(-1)">&#9664;</button>
                <span id="ml6"></span>
                <button onclick="cm(1)">&#9654;</button>
            </div>
            <h2>Statystyki ogólne</h2>
            <div id="asl"></div>
        </div>

        <!-- Dodaj użytkownika -->
        <div id="at-au2" class="pn hid">
            <h2>Dodaj użytkownika</h2>
            <form onsubmit="return aau(event)" style="max-width: 380px;">
                <div class="fg">
                    <label>Imię</label>
                    <input type="text" id="a-i" required>
                </div>
                <div class="fg">
                    <label>Nazwisko</label>
                    <input type="text" id="a-n" required>
                </div>
                <div class="fg">
                    <label>Login</label>
                    <input type="text" id="a-l" required>
                </div>
                <div class="fg">
                    <label>Hasło</label>
                    <input type="password" id="a-p" required>
                </div>
                <div class="fg">
                    <label>Rola</label>
                    <select id="a-r">
                        <option value="uzytkownik">Użytkownik</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
                <button type="submit" class="btn bg">Dodaj</button>
            </form>
        </div>

        <div style="margin-top: 12px;">
            <button class="btn bw" onclick="hideAdmin()">Wróć</button>
        </div>
    </div>

    <!-- Modale -->
    <!-- Modal Kategoria -->
    <div class="mo" id="m-cat">
        <div class="md">
            <h3 id="mc-t">Nowa kategoria</h3>
            <div class="fg">
                <label>Nazwa</label>
                <input type="text" id="mc-n">
            </div>
            <div class="fg">
                <label>Typ</label>
                <select id="mc-tp">
                    <option value="zmienny">Zmienny</option>
                    <option value="staly">Stały</option>
                </select>
            </div>
            <div class="ma">
                <button class="btn bw" onclick="cm2('m-cat')">Anuluj</button>
                <button class="btn bg" onclick="scat()">Zapisz</button>
            </div>
        </div>
    </div>

    <!-- Modal Wydatek -->
    <div class="mo" id="m-exp">
        <div class="md">
            <h3>Nowy wydatek</h3>
            <div class="fg">
                <label>Kategoria</label>
                <select id="me-c"></select>
            </div>
            <div class="fg">
                <label>Kwota</label>
                <input type="number" id="me-k" step="0.01" min="0.01">
            </div>
            <div class="fg">
                <label>Data</label>
                <input type="date" id="me-d">
            </div>
            <div class="fg">
                <label>Opis</label>
                <input type="text" id="me-o">
            </div>
            <div class="ma">
                <button class="btn bw" onclick="cm2('m-exp')">Anuluj</button>
                <button class="btn bg" onclick="sexp()">Zapisz</button>
            </div>
        </div>
    </div>

    <!-- Modal Przychód -->
    <div class="mo" id="m-inc">
        <div class="md">
            <h3>Nowy przychód</h3>
            <div class="fg">
                <label>Kwota</label>
                <input type="number" id="mi-k" step="0.01" min="0.01">
            </div>
            <div class="fg">
                <label>Data</label>
                <input type="date" id="mi-d">
            </div>
            <div class="fg">
                <label>Opis</label>
                <input type="text" id="mi-o">
            </div>
            <div class="ma">
                <button class="btn bw" onclick="cm2('m-inc')">Anuluj</button>
                <button class="btn bg" onclick="sinc()">Zapisz</button>
            </div>
        </div>
    </div>

    <script>
        // ====== ZMIENNE GLOBALNE ======
        let CU = null; // Current User
        let cM = new Date().getMonth();
        let cY = new Date().getFullYear();

        // ====== FUNKCJE POMOCNICZE ======
        const $ = (id) => document.getElementById(id);

        const api = (a, d = {}) => {
            const f = new FormData();
            f.append('action', a);
            for (const [k, v] of Object.entries(d)) {
                f.append(k, v);
            }
            return fetch('index.php', { method: 'POST', body: f }).then(r => r.json());
        };

        const fm = (v) => parseFloat(v || 0).toFixed(2).replace('.', ',') + ' zł';

        const ms = ['Styczeń', 'Luty', 'Marzec', 'Kwiecień', 'Maj', 'Czerwiec', 'Lipiec', 'Sierpień', 'Wrzesień', 'Październik', 'Listopad', 'Grudzień'];

        const ml = () => ms[cM] + ' ' + cY;

        function showMsg(t, s = 's') {
            const e = $('msg');
            e.textContent = t;
            e.className = s;
            e.style.display = 'block';
            setTimeout(() => e.style.display = 'none', 3000);
        }

        function uml() {
            ['ml', 'ml2', 'ml3', 'ml4', 'ml5', 'ml6'].forEach(i => {
                const e = $(i);
                if (e) e.textContent = ml();
            });
        }

        // ====== LOGOWANIE ======
        function at(t) {
            $('lf').classList.toggle('hid', t !== 'login');
            $('rf').classList.toggle('hid', t !== 'register');
            document.querySelectorAll('.lb .tb button').forEach((b, i) => {
                b.classList.toggle('on', (t === 'login' && i === 0) || (t === 'register' && i === 1));
            });
        }

        async function doLogin(e) {
            e.preventDefault();
            const r = await api('login', { login: $('l-l').value, haslo: $('l-p').value });
            if (r.error) {
                showMsg(r.error, 'e');
                return false;
            }
            CU = r;
            sa();
            return false;
        }

        async function doRegister(e) {
            e.preventDefault();
            const r = await api('register', {
                imie: $('r-i').value,
                nazwisko: $('r-n').value,
                login: $('r-l').value,
                haslo: $('r-p').value
            });
            if (r.error) {
                showMsg(r.error, 'e');
                return false;
            }
            showMsg('Konto utworzone! Zaloguj się.');
            at('login');
            return false;
        }

        async function logout() {
            await api('logout');
            CU = null;
            $('app').classList.add('hid');
            $('adm').classList.add('hid');
            $('nb').classList.add('hid');
            $('auth').classList.remove('hid');
        }

        function sa() {
            $('auth').classList.add('hid');
            $('app').classList.remove('hid');
            $('adm').classList.add('hid');
            $('nb').classList.remove('hid');
            $('nb-u').textContent = CU.imie + ' ' + CU.nazwisko;
            
            if (CU.rola === 'admin') {
                $('nb-a').classList.remove('hid');
            } else {
                $('nb-a').classList.add('hid');
            }
            
            uml();
            ldash();
            lcat();
            lexp();
            linc();
            lstt();
        }

        // ====== ADMIN ======
        function showAdmin() {
            $('app').classList.add('hid');
            $('adm').classList.remove('hid');
            lau();
            lae();
            las();
        }

        function hideAdmin() {
            $('adm').classList.add('hid');
            $('app').classList.remove('hid');
        }

        function aw(t) {
            ['au', 'ae', 'as', 'au2'].forEach(x => {
                $('at-' + x).classList.toggle('hid', x !== t);
            });
            document.querySelectorAll('#adm .tn button').forEach((b, i) => {
                b.classList.toggle('on', ['au', 'ae', 'as', 'au2'][i] === t);
            });
        }

        // ====== PRZEŁĄCZNIK TABÓW ======
        function cm(d) {
            cM += d;
            if (cM > 11) {
                cM = 0;
                cY++;
            }
            if (cM < 0) {
                cM = 11;
                cY--;
            }
            uml();
            refresh();
        }

        function cm2(id) {
            $(id).classList.remove('on');
        }

        function refresh() {
            const a = document.querySelector('#app .tn button.on');
            if (!a) return;
            const t = ['dash', 'inc', 'cat', 'exp', 'stt'][Array.from(document.querySelectorAll('#app .tn button')).indexOf(a)];
            
            if (t === 'dash') ldash();
            else if (t === 'inc') linc();
            else if (t === 'cat') lcat();
            else if (t === 'exp') lexp();
            else if (t === 'stt') lstt();
        }

        function sw(t) {
            ['dash', 'inc', 'cat', 'exp', 'stt'].forEach(x => {
                $('t-' + x).classList.toggle('hid', x !== t);
            });
            document.querySelectorAll('#app .tn button').forEach((b, i) => {
                b.classList.toggle('on', ['dash', 'inc', 'cat', 'exp', 'stt'][i] === t);
            });
            uml();
            refresh();
        }

        // ====== DASHBOARD ======
        async function ldash() {
            uml();
            const r = await api('dashboard', { month: cM + 1, year: cY });
            if (r.error) return;
            
            $('si').textContent = fm(r.income);
            $('se').textContent = fm(r.expense);
            $('ss').textContent = fm(r.income - r.expense);
            
            let h = '<table><tr><th>Kwota</th><th>Kategoria</th><th>Data</th><th>Opis</th></tr>';
            if (r.expenses && r.expenses.length) {
                r.expenses.forEach(e => {
                    h += '<tr><td style="color: #e74c3c; font-weight: 700;">-' + fm(e.kwota) + '</td>' +
                        '<td>' + e.kat + ' <span class="tg tg-' + e.typ + '">' + e.typ + '</span></td>' +
                        '<td>' + e.data_wydatku + '</td>' +
                        '<td>' + e.opis + '</td></tr>';
                });
            } else {
                h += '<tr><td colspan="4" style="text-align: center; color: #aaa;">Brak wydatków</td></tr>';
            }
            $('de').innerHTML = h + '</table>';
        }

        // ====== KATEGORIE ======
        async function lcat() {
            const r = await api('get_cats');
            let h = '<table><tr><th>Nazwa</th><th>Typ</th><th>Akcje</th></tr>';
            
            if (r.length) {
                r.forEach(c => {
                    h += '<tr><td>' + c.nazwa + '</td>' +
                        '<td><span class="tg tg-' + c.typ + '">' + (c.typ === 'staly' ? 'Stały' : 'Zmienny') + '</span></td>' +
                        '<td><button class="btn bd bs" onclick="dcat(' + c.id + ')">Usuń</button></td></tr>';
                });
            } else {
                h += '<tr><td colspan="3" style="text-align: center; color: #aaa;">Brak kategorii</td></tr>';
            }
            $('cl').innerHTML = h + '</table>';
        }

        function ocm() {
            $('mc-n').value = '';
            $('mc-tp').value = 'zmienny';
            $('mc-t').textContent = 'Nowa kategoria';
            $('m-cat').classList.add('on');
        }

        async function scat() {
            const n = $('mc-n').value.trim();
            const t = $('mc-tp').value;
            
            if (!n) {
                showMsg('Podaj nazwę', 'e');
                return;
            }
            
            await api('add_cat', { nazwa: n, typ: t });
            showMsg('Kategoria dodana');
            cm2('m-cat');
            lcat();
        }

        async function dcat(id) {
            if (!confirm('Usunąć?')) return;
            await api('del_cat', { id });
            lcat();
        }

        // ====== WYDATKI ======
        async function lexp() {
            const r = await api('get_expenses', { month: cM + 1, year: cY });
            let h = '<table><tr><th>Kwota</th><th>Kategoria</th><th>Data</th><th>Opis</th><th></th></tr>';
            
            if (r.length) {
                r.forEach(e => {
                    h += '<tr><td style="color: #e74c3c; font-weight: 700;">' + fm(e.kwota) + '</td>' +
                        '<td>' + e.kat + ' <span class="tg tg-' + e.typ + '">' + e.typ + '</span></td>' +
                        '<td>' + e.data_wydatku + '</td>' +
                        '<td>' + e.opis + '</td>' +
                        '<td><button class="btn bd bs" onclick="dexp(' + e.id + ')">Usuń</button></td></tr>';
                });
            } else {
                h += '<tr><td colspan="5" style="text-align: center; color: #aaa;">Brak wydatków</td></tr>';
            }
            $('el').innerHTML = h + '</table>';
        }

        async function oem() {
            const s = $('me-c');
            await lco(s, s);
            $('me-k').value = '';
            $('me-d').value = new Date().toISOString().split('T')[0];
            $('me-o').value = '';
            $('m-exp').classList.add('on');
        }

        async function sexp() {
            const k = parseInt($('me-c').value);
            const w = parseFloat($('me-k').value);
            const d = $('me-d').value;
            const o = $('me-o').value;
            
            if (!w || w <= 0 || !k) {
                showMsg('Wypełnij pola', 'e');
                return;
            }
            
            await api('add_expense', { kategoria_id: k, kwota: w, data_wydatku: d, opis: o });
            showMsg('Wydatek dodany');
            cm2('m-exp');
            lexp();
            ldash();
        }

        async function dexp(id) {
            if (!confirm('Usunąć?')) return;
            await api('del_expense', { id });
            lexp();
            ldash();
        }

        // ====== PRZYCHODY ======
        async function linc() {
            const r = await api('get_income', { month: cM + 1, year: cY });
            let h = '<table><tr><th>Kwota</th><th>Data</th><th>Opis</th><th></th></tr>';
            
            if (r.length) {
                r.forEach(i => {
                    h += '<tr><td style="color: #27ae60; font-weight: 700;">' + fm(i.kwota) + '</td>' +
                        '<td>' + i.data_przychodu + '</td>' +
                        '<td>' + i.opis + '</td>' +
                        '<td><button class="btn bd bs" onclick="dinc(' + i.id + ')">Usuń</button></td></tr>';
                });
            } else {
                h += '<tr><td colspan="4" style="text-align: center; color: #aaa;">Brak przychodów</td></tr>';
            }
            $('il').innerHTML = h + '</table>';
        }

        function oim() {
            $('mi-k').value = '';
            $('mi-d').value = new Date().toISOString().split('T')[0];
            $('mi-o').value = '';
            $('m-inc').classList.add('on');
        }

        async function sinc() {
            const w = parseFloat($('mi-k').value);
            const d = $('mi-d').value;
            const o = $('mi-o').value;
            
            if (!w || w <= 0) {
                showMsg('Podaj kwotę', 'e');
                return;
            }
            
            await api('add_income', { kwota: w, data_przychodu: d, opis: o });
            showMsg('Przychód dodany');
            cm2('m-inc');
            linc();
            ldash();
        }

        async function dinc(id) {
            if (!confirm('Usunąć?')) return;
            await api('del_income', { id });
            linc();
            ldash();
        }

        // ====== STATYSTYKI ======
        async function lstt() {
            uml();
            const r = await api('get_stats', { month: cM + 1, year: cY });
            if (r.error) return;
            
            let h = '<div class="dash">' +
                '<div class="sc inc"><h3>Przychody</h3><div class="v">' + fm(r.income) + '</div></div>' +
                '<div class="sc exp"><h3>Stałe</h3><div class="v">' + fm(r.fixed) + '</div></div>' +
                '<div class="sc sav"><h3>Zmienne</h3><div class="v">' + fm(r.variable) + '</div></div>' +
                '</div>';
            
            $('st1').innerHTML = h;
            
            const mx = Math.max(...r.by_category.map(c => c.total), 1);
            let h2 = '';
            
            if (r.by_category.length) {
                r.by_category.forEach(c => {
                    h2 += '<div class="cb"><div class="l">' + c.nazwa + '</div><div class="b">' +
                        '<div class="bf" style="width: ' + (c.total / mx * 100) + '%">' + fm(c.total) + '</div>' +
                        '</div></div>';
                });
            } else {
                h2 = '<p style="text-align: center; color: #aaa;">Brak danych</p>';
            }
            
            $('st2').innerHTML = h2;
        }

        // ====== ADMIN - UŻYTKOWNICY ======
        async function lau() {
            const r = await api('admin_users');
            let h = '<table><tr><th>Imię Nazwisko</th><th>Login</th><th>Rola</th><th>Od</th><th></th></tr>';
            const f = $('auf');
            f.innerHTML = '<option value="0">Wszyscy</option>';
            
            r.forEach(u => {
                f.innerHTML += '<option value="' + u.id + '">' + u.imie + ' ' + u.nazwisko + '</option>';
                h += '<tr><td>' + u.imie + ' ' + u.nazwisko + '</td>' +
                    '<td>' + u.login + '</td>' +
                    '<td><span class="tg tg-' + (u.rola === 'admin' ? 's' : 'z') + '">' + u.rola + '</span></td>' +
                    '<td>' + u.data_dodania + '</td>' +
                    '<td><button class="btn bd bs" onclick="adu(' + u.id + ')" ' + (u.login === 'admin' ? 'disabled' : '') + '>Usuń</button></td></tr>';
            });
            
            $('aul').innerHTML = h + '</table>';
        }

        async function aau(e) {
            e.preventDefault();
            const r = await api('admin_add_user', {
                imie: $('a-i').value,
                nazwisko: $('a-n').value,
                login: $('a-l').value,
                haslo: $('a-p').value,
                rola: $('a-r').value
            });
            
            if (r.error) {
                showMsg(r.error, 'e');
                return false;
            }
            
            showMsg('Użytkownik dodany');
            $('a-i').value = '';
            $('a-n').value = '';
            $('a-l').value = '';
            $('a-p').value = '';
            lau();
            return false;
        }

        async function adu(id) {
            if (!confirm('Usunąć?')) return;
            const r = await api('admin_del_user', { id });
            if (r.error) {
                showMsg(r.error, 'e');
                return;
            }
            lau();
        }

        // ====== ADMIN - WYDATKI ======
        async function lae() {
            const uid = parseInt($('auf').value || 0);
            const r = await api('admin_expenses', { month: cM + 1, year: cY, user_id: uid });
            let h = '<table><tr><th>Użytkownik</th><th>Kwota</th><th>Kategoria</th><th>Data</th><th>Opis</th></tr>';
            
            if (r.length) {
                r.forEach(e => {
                    h += '<tr><td>' + e.user + '</td>' +
                        '<td style="color: #e74c3c; font-weight: 700;">' + fm(e.kwota) + '</td>' +
                        '<td>' + e.kat + ' <span class="tg tg-' + e.typ + '">' + e.typ + '</span></td>' +
                        '<td>' + e.data_wydatku + '</td>' +
                        '<td>' + e.opis + '</td></tr>';
                });
            } else {
                h += '<tr><td colspan="5" style="text-align: center; color: #aaa;">Brak wydatków</td></tr>';
            }
            
            $('ael').innerHTML = h + '</table>';
        }

        // ====== ADMIN - STATYSTYKI ======
        async function las() {
            uml();
            const r = await api('admin_stats', { month: cM + 1, year: cY });
            if (r.error) return;
            
            let h = '<div class="dash">' +
                '<div class="sc inc"><h3>Przychody</h3><div class="v">' + fm(r.income) + '</div></div>' +
                '<div class="sc exp"><h3>Wydatki</h3><div class="v">' + fm(r.expense) + '</div></div>' +
                '<div class="sc sav"><h3>Oszczędności</h3><div class="v">' + fm(r.income - r.expense) + '</div></div>' +
                '</div>';
            
            h += '<h3 style="margin: 16px 0 8px;">Wg użytkownika</h3>';
            
            const mu = Math.max(...r.by_user.map(u => u.total), 1);
            if (r.by_user.length) {
                r.by_user.forEach(u => {
                    h += '<div class="cb"><div class="l">' + u.user + '</div><div class="b">' +
                        '<div class="bf" style="width: ' + (u.total / mu * 100) + '%">' + fm(u.total) + '</div>' +
                        '</div></div>';
                });
            }
            
            h += '<h3 style="margin: 16px 0 8px;">Wg kategorii</h3>';
            
            const mc = Math.max(...r.by_category.map(c => c.total), 1);
            if (r.by_category.length) {
                r.by_category.forEach(c => {
                    h += '<div class="cb"><div class="l">' + c.nazwa + '</div><div class="b">' +
                        '<div class="bf" style="width: ' + (c.total / mc * 100) + '%">' + fm(c.total) + '</div>' +
                        '</div></div>';
                });
            }
            
            $('asl').innerHTML = h;
        }

        // ====== FUNKCJA POMOCNICZA ======
        function lco(s, el) {
            api('get_cats').then(d => {
                el.innerHTML = '';
                d.forEach(c => {
                    el.innerHTML += '<option value="' + c.id + '">' + c.nazwa + ' (' + c.typ + ')</option>';
                });
            });
        }

        // ====== INICJALIZACJA ======
        document.querySelectorAll('.mo').forEach(m => {
            m.addEventListener('click', e => {
                if (e.target === m) m.classList.remove('on');
            });
        });

        api('check').then(r => {
            if (r.logged) {
                CU = { imie: r.imie, nazwisko: r.nazwisko, login: r.login, rola: r.rola };
                sa();
            }
        });
    </script>
</body>
</html>
