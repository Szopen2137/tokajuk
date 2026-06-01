<?php
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$action = $input['action'] ?? $_POST['action'] ?? '';
try {
switch ($action) {

    case 'login':
        $login = $input['login'] ?? '';
        $haslo = $input['haslo'] ?? '';
        if (empty($login) || empty($haslo)) {
            jsonResponse(['error' => 'Podaj login i hasło'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM uzytkownicy WHERE login = ?");
        $stmt->execute([$login]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($haslo, $user['haslo'])) {
            jsonResponse(['error' => 'Nieprawidłowy login lub hasło'], 401);
        }
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['login'] = $user['login'];
        $_SESSION['imie'] = $user['imie'];
        $_SESSION['nazwisko'] = $user['nazwisko'];
        $_SESSION['role'] = $user['rola'];
        jsonResponse([
            'id' => $user['id'], 'imie' => $user['imie'], 'nazwisko' => $user['nazwisko'],
            'login' => $user['login'], 'rola' => $user['rola']
        ]);
        break;

    case 'register':
        $imie = trim($input['imie'] ?? '');
        $nazwisko = trim($input['nazwisko'] ?? '');
        $login = trim($input['login'] ?? '');
        $haslo = $input['haslo'] ?? '';
        if (empty($imie) || empty($nazwisko) || empty($login) || empty($haslo)) {
            jsonResponse(['error' => 'Wypełnij wszystkie pola'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM uzytkownicy WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Podany login już istnieje'], 400);
        }
        $hashedPass = password_hash($haslo, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO uzytkownicy (login, haslo, imie, nazwisko, rola) VALUES (?, ?, ?, ?, 'uzytkownik')");
        $stmt->execute([$login, $hashedPass, $imie, $nazwisko]);
        jsonResponse(['success' => true, 'message' => 'Konto utworzone pomyślnie']);
        break;

    case 'logout':
        session_destroy();
        jsonResponse(['success' => true]);
        break;

    case 'check_session':
        if (isLoggedIn()) {
            jsonResponse([
                'logged_in' => true, 'id' => $_SESSION['user_id'], 'imie' => $_SESSION['imie'],
                'nazwisko' => $_SESSION['nazwisko'], 'login' => $_SESSION['login'], 'rola' => $_SESSION['role']
            ]);
        } else {
            jsonResponse(['logged_in' => false]);
        }
        break;

    case 'dashboard':
        requireLogin();
        $month = intval($input['month'] ?? date('n'));
        $year = intval($input['year'] ?? date('Y'));
        $userId = $_SESSION['user_id'];
        $db = getDB();

        $stmt = $db->prepare("SELECT COALESCE(SUM(kwota), 0) as total FROM przychody WHERE uzytkownik_id = ? AND MONTH(data_przychodu) = ? AND YEAR(data_przychodu) = ?");
        $stmt->execute([$userId, $month, $year]);
        $income = $stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT w.*, kw.nazwa as kat_nazwa, kw.typ FROM wydatki w JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE w.uzytkownik_id = ? AND MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ? ORDER BY w.data_wydatku DESC");
        $stmt->execute([$userId, $month, $year]);
        $expenses = $stmt->fetchAll();

        $totalExpense = 0;
        foreach ($expenses as $e) {
            $totalExpense += $e['kwota'];
        }

        jsonResponse(['income' => $income, 'expense' => $totalExpense, 'expenses' => $expenses]);
        break;

    case 'get_categories':
        requireLogin();
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM kategorie_wydatkow WHERE uzytkownik_id = ? ORDER BY nazwa");
        $stmt->execute([$_SESSION['user_id']]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'add_category':
        requireLogin();
        $nazwa = trim($input['nazwa'] ?? '');
        $typ = $input['typ'] ?? 'zmienny';
        $kwota = floatval($input['kwota_domyslna'] ?? 0);
        if (empty($nazwa)) {
            jsonResponse(['error' => 'Podaj nazwę kategorii'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO kategorie_wydatkow (uzytkownik_id, nazwa, typ, kwota_domyslna) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $nazwa, $typ, $kwota]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update_category':
        requireLogin();
        $id = intval($input['id'] ?? 0);
        $nazwa = trim($input['nazwa'] ?? '');
        $typ = $input['typ'] ?? 'zmienny';
        $kwota = floatval($input['kwota_domyslna'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("UPDATE kategorie_wydatkow SET nazwa = ?, typ = ?, kwota_domyslna = ? WHERE id = ? AND uzytkownik_id = ?");
        $stmt->execute([$nazwa, $typ, $kwota, $id, $_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    case 'delete_category':
        requireLogin();
        $id = intval($input['id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM wydatki WHERE kategoria_id = ? AND uzytkownik_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        $cnt = $stmt->fetch()['cnt'];
        if ($cnt > 0) {
            jsonResponse(['error' => 'Nie można usunąć kategorii - posiada przypisane wydatki. Najpierw usuń wydatki.'], 400);
        }
        $stmt = $db->prepare("DELETE FROM kategorie_wydatkow WHERE id = ? AND uzytkownik_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    case 'get_expenses':
        requireLogin();
        $month = intval($input['month'] ?? date('n'));
        $year = intval($input['year'] ?? date('Y'));
        $db = getDB();
        $stmt = $db->prepare("SELECT w.*, kw.nazwa as kat_nazwa, kw.typ FROM wydatki w JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE w.uzytkownik_id = ? AND MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ? ORDER BY w.data_wydatku DESC");
        $stmt->execute([$_SESSION['user_id'], $month, $year]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'add_expense':
        requireLogin();
        $kategoriaId = intval($input['kategoria_id'] ?? 0);
        $kwota = floatval($input['kwota'] ?? 0);
        $data = $input['data_wydatku'] ?? date('Y-m-d');
        $opis = trim($input['opis'] ?? '');
        if ($kategoriaId <= 0 || $kwota <= 0) {
            jsonResponse(['error' => 'Nieprawidłowe dane wydatku'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM kategorie_wydatkow WHERE id = ? AND uzytkownik_id = ?");
        $stmt->execute([$kategoriaId, $_SESSION['user_id']]);
        if (!$stmt->fetch()) {
            jsonResponse(['error' => 'Kategoria nie należy do Ciebie'], 403);
        }
        $stmt = $db->prepare("INSERT INTO wydatki (uzytkownik_id, kategoria_id, kwota, data_wydatku, opis) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $kategoriaId, $kwota, $data, $opis]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update_expense':
        requireLogin();
        $id = intval($input['id'] ?? 0);
        $kategoriaId = intval($input['kategoria_id'] ?? 0);
        $kwota = floatval($input['kwota'] ?? 0);
        $data = $input['data_wydatku'] ?? date('Y-m-d');
        $opis = trim($input['opis'] ?? '');
        $db = getDB();
        $stmt = $db->prepare("UPDATE wydatki SET kategoria_id = ?, kwota = ?, data_wydatku = ?, opis = ? WHERE id = ? AND uzytkownik_id = ?");
        $stmt->execute([$kategoriaId, $kwota, $data, $opis, $id, $_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    case 'delete_expense':
        requireLogin();
        $id = intval($input['id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM wydatki WHERE id = ? AND uzytkownik_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    case 'get_income':
        requireLogin();
        $month = intval($input['month'] ?? date('n'));
        $year = intval($input['year'] ?? date('Y'));
        $db = getDB();
        $stmt = $db->prepare("SELECT * FROM przychody WHERE uzytkownik_id = ? AND MONTH(data_przychodu) = ? AND YEAR(data_przychodu) = ? ORDER BY data_przychodu DESC");
        $stmt->execute([$_SESSION['user_id'], $month, $year]);
        jsonResponse($stmt->fetchAll());
        break;

    case 'add_income':
        requireLogin();
        $kwota = floatval($input['kwota'] ?? 0);
        $data = $input['data_przychodu'] ?? date('Y-m-d');
        $opis = trim($input['opis'] ?? '');
        if ($kwota <= 0) {
            jsonResponse(['error' => 'Podaj kwotę'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO przychody (uzytkownik_id, kwota, data_przychodu, opis) VALUES (?, ?, ?, ?)");
        $stmt->execute([$_SESSION['user_id'], $kwota, $data, $opis]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'update_income':
        requireLogin();
        $id = intval($input['id'] ?? 0);
        $kwota = floatval($input['kwota'] ?? 0);
        $data = $input['data_przychodu'] ?? date('Y-m-d');
        $opis = trim($input['opis'] ?? '');
        $db = getDB();
        $stmt = $db->prepare("UPDATE przychody SET kwota = ?, data_przychodu = ?, opis = ? WHERE id = ? AND uzytkownik_id = ?");
        $stmt->execute([$kwota, $data, $opis, $id, $_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    case 'delete_income':
        requireLogin();
        $id = intval($input['id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("DELETE FROM przychody WHERE id = ? AND uzytkownik_id = ?");
        $stmt->execute([$id, $_SESSION['user_id']]);
        jsonResponse(['success' => true]);
        break;

    case 'get_stats':
        requireLogin();
        $month = intval($input['month'] ?? date('n'));
        $year = intval($input['year'] ?? date('Y'));
        $userId = $_SESSION['user_id'];
        $db = getDB();

        $stmt = $db->prepare("SELECT COALESCE(SUM(kwota), 0) as total FROM przychody WHERE uzytkownik_id = ? AND MONTH(data_przychodu) = ? AND YEAR(data_przychodu) = ?");
        $stmt->execute([$userId, $month, $year]);
        $income = $stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT COALESCE(SUM(w.kwota), 0) as total FROM wydatki w JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE w.uzytkownik_id = ? AND kw.typ = 'staly' AND MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ?");
        $stmt->execute([$userId, $month, $year]);
        $fixed = $stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT COALESCE(SUM(w.kwota), 0) as total FROM wydatki w JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE w.uzytkownik_id = ? AND kw.typ = 'zmienny' AND MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ?");
        $stmt->execute([$userId, $month, $year]);
        $variable = $stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT kw.nazwa, SUM(w.kwota) as total FROM wydatki w JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE w.uzytkownik_id = ? AND MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ? GROUP BY kw.nazwa ORDER BY total DESC");
        $stmt->execute([$userId, $month, $year]);
        $byCategory = $stmt->fetchAll();

        jsonResponse([
            'income' => $income, 'fixed' => $fixed, 'variable' => $variable, 'by_category' => $byCategory
        ]);
        break;

    case 'admin_get_users':
        requireLogin();
        if (!isAdmin()) jsonResponse(['error' => 'Brak uprawnień'], 403);
        $db = getDB();
        $stmt = $db->query("SELECT id, login, imie, nazwisko, rola, data_dodania FROM uzytkownicy ORDER BY nazwisko");
        jsonResponse($stmt->fetchAll());
        break;

    case 'admin_add_user':
        requireLogin();
        if (!isAdmin()) jsonResponse(['error' => 'Brak uprawnień'], 403);
        $imie = trim($input['imie'] ?? '');
        $nazwisko = trim($input['nazwisko'] ?? '');
        $login = trim($input['login'] ?? '');
        $haslo = $input['haslo'] ?? '';
        $rola = $input['rola'] ?? 'uzytkownik';
        if (empty($imie) || empty($nazwisko) || empty($login) || empty($haslo)) {
            jsonResponse(['error' => 'Wypełnij wszystkie pola'], 400);
        }
        if (!in_array($rola, ['admin', 'uzytkownik'])) {
            jsonResponse(['error' => 'Nieprawidłowa rola'], 400);
        }
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM uzytkownicy WHERE login = ?");
        $stmt->execute([$login]);
        if ($stmt->fetch()) {
            jsonResponse(['error' => 'Login już istnieje'], 400);
        }
        $hashedPass = password_hash($haslo, PASSWORD_DEFAULT);
        $stmt = $db->prepare("INSERT INTO uzytkownicy (login, haslo, imie, nazwisko, rola) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$login, $hashedPass, $imie, $nazwisko, $rola]);
        jsonResponse(['success' => true, 'id' => $db->lastInsertId()]);
        break;

    case 'admin_delete_user':
        requireLogin();
        if (!isAdmin()) jsonResponse(['error' => 'Brak uprawnień'], 403);
        $id = intval($input['id'] ?? 0);
        $db = getDB();
        $stmt = $db->prepare("SELECT login FROM uzytkownicy WHERE id = ?");
        $stmt->execute([$id]);
        $user = $stmt->fetch();
        if ($user && $user['login'] === 'admin') {
            jsonResponse(['error' => 'Nie można usunąć głównego administratora'], 400);
        }
        $stmt = $db->prepare("DELETE FROM uzytkownicy WHERE id = ?");
        $stmt->execute([$id]);
        jsonResponse(['success' => true]);
        break;

    case 'admin_get_expenses':
        requireLogin();
        if (!isAdmin()) jsonResponse(['error' => 'Brak uprawnień'], 403);
        $month = intval($input['month'] ?? date('n'));
        $year = intval($input['year'] ?? date('Y'));
        $userId = intval($input['user_id'] ?? 0);
        $db = getDB();

        if ($userId > 0) {
            $stmt = $db->prepare("SELECT w.*, u.imie, u.nazwisko, kw.nazwa as kat_nazwa, kw.typ FROM wydatki w JOIN uzytkownicy u ON w.uzytkownik_id = u.id JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE w.uzytkownik_id = ? AND MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ? ORDER BY w.data_wydatku DESC");
            $stmt->execute([$userId, $month, $year]);
        } else {
            $stmt = $db->prepare("SELECT w.*, u.imie, u.nazwisko, kw.nazwa as kat_nazwa, kw.typ FROM wydatki w JOIN uzytkownicy u ON w.uzytkownik_id = u.id JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ? ORDER BY w.data_wydatku DESC");
            $stmt->execute([$month, $year]);
        }
        jsonResponse($stmt->fetchAll());
        break;

    case 'admin_get_stats':
        requireLogin();
        if (!isAdmin()) jsonResponse(['error' => 'Brak uprawnień'], 403);
        $month = intval($input['month'] ?? date('n'));
        $year = intval($input['year'] ?? date('Y'));
        $db = getDB();

        $stmt = $db->prepare("SELECT COALESCE(SUM(kwota), 0) as total FROM przychody WHERE MONTH(data_przychodu) = ? AND YEAR(data_przychodu) = ?");
        $stmt->execute([$month, $year]);
        $income = $stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT COALESCE(SUM(kwota), 0) as total FROM wydatki WHERE MONTH(data_wydatku) = ? AND YEAR(data_wydatku) = ?");
        $stmt->execute([$month, $year]);
        $expense = $stmt->fetch()['total'];

        $stmt = $db->prepare("SELECT u.imie, u.nazwisko, SUM(w.kwota) as total FROM wydatki w JOIN uzytkownicy u ON w.uzytkownik_id = u.id WHERE MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ? GROUP BY w.uzytkownik_id ORDER BY total DESC");
        $stmt->execute([$month, $year]);
        $byUser = $stmt->fetchAll();

        $stmt = $db->prepare("SELECT kw.nazwa, SUM(w.kwota) as total FROM wydatki w JOIN kategorie_wydatkow kw ON w.kategoria_id = kw.id WHERE MONTH(w.data_wydatku) = ? AND YEAR(w.data_wydatku) = ? GROUP BY kw.nazwa ORDER BY total DESC");
        $stmt->execute([$month, $year]);
        $byCategory = $stmt->fetchAll();

        jsonResponse([
            'income' => $income, 'expense' => $expense, 'by_user' => $byUser, 'by_category' => $byCategory
        ]);
        break;

    default:
        jsonResponse(['error' => 'Nieznana akcja: ' . $action], 400);
        break;
}
} catch (\Throwable $e) {
    jsonResponse(['error' => 'Błąd serwera: ' . $e->getMessage()], 500);
}