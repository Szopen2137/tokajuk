<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

try {
    switch ($action) {
        case 'bootstrap':
            require_login();
            json_response([
                'ok' => true,
                'user' => current_user(),
                'categories' => fetch_categories(),
                'entries' => fetch_entries(),
            ]);
            break;

        case 'login':
            if ($method !== 'POST') {
                json_response(['ok' => false, 'error' => 'Nieprawidłowa metoda.'], 405);
            }
            login_action();
            break;

        case 'logout':
            if ($method !== 'POST') {
                json_response(['ok' => false, 'error' => 'Nieprawidłowa metoda.'], 405);
            }
            session_unset();
            session_destroy();
            json_response(['ok' => true]);
            break;

        case 'list':
            require_login();
            json_response([
                'ok' => true,
                'categories' => fetch_categories(),
                'entries' => fetch_entries(),
            ]);
            break;

        case 'save':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'error' => 'Nieprawidłowa metoda.'], 405);
            }
            save_entry();
            break;

        case 'delete':
            require_login();
            if ($method !== 'POST') {
                json_response(['ok' => false, 'error' => 'Nieprawidłowa metoda.'], 405);
            }
            delete_entry();
            break;

        default:
            json_response(['ok' => false, 'error' => 'Nieznana akcja.'], 400);
    }
} catch (Throwable $exception) {
    json_response(['ok' => false, 'error' => $exception->getMessage()], 500);
}

function login_action(): void
{
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        json_response(['ok' => false, 'error' => 'Podaj login i hasło.'], 422);
    }

    $stmt = db()->prepare('SELECT id, password_hash FROM users WHERE username = :username');
    $stmt->execute([':username' => $username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        json_response(['ok' => false, 'error' => 'Nieprawidłowy login lub hasło.'], 401);
    }

    $_SESSION['user_id'] = (int) $user['id'];

    json_response([
        'ok' => true,
        'user' => current_user(),
        'categories' => fetch_categories(),
        'entries' => fetch_entries(),
    ]);
}

function fetch_categories(): array
{
    return db()->query('SELECT id, name FROM categories ORDER BY name ASC')->fetchAll();
}

function fetch_entries(): array
{
    $stmt = db()->query(entry_with_category_sql() . ' ORDER BY e.start_at ASC, e.id DESC');
    $entries = $stmt->fetchAll();

    foreach ($entries as &$entry) {
        $entry['start_at_input'] = format_datetime_input($entry['start_at']);
        $entry['end_at_input'] = format_datetime_input($entry['end_at']);
    }

    return $entries;
}

function save_entry(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $startAt = (string) ($_POST['start_at'] ?? '');
    $endAt = (string) ($_POST['end_at'] ?? '');
    $reminderEmail = trim((string) ($_POST['reminder_email'] ?? ''));
    $reminderMinutes = trim((string) ($_POST['reminder_minutes'] ?? ''));

    if ($title === '' || $categoryId <= 0 || $startAt === '' || $endAt === '') {
        json_response(['ok' => false, 'error' => 'Uzupełnij wymagane pola.'], 422);
    }

    if (!validate_datetime($startAt) || !validate_datetime($endAt)) {
        json_response(['ok' => false, 'error' => 'Nieprawidłowy format daty i czasu.'], 422);
    }

    $startDb = format_datetime_db($startAt);
    $endDb = format_datetime_db($endAt);
    if ($endDb < $startDb) {
        json_response(['ok' => false, 'error' => 'Data końcowa nie może być wcześniejsza niż początkowa.'], 422);
    }

    $stmt = db()->prepare('SELECT COUNT(*) FROM categories WHERE id = :id');
    $stmt->execute([':id' => $categoryId]);
    if ((int) $stmt->fetchColumn() === 0) {
        json_response(['ok' => false, 'error' => 'Wybrana kategoria nie istnieje.'], 422);
    }

    $reminderMinutesValue = null;
    if ($reminderMinutes !== '') {
        if (!ctype_digit($reminderMinutes)) {
            json_response(['ok' => false, 'error' => 'Liczba minut przypomnienia musi być liczbą całkowitą.'], 422);
        }
        $reminderMinutesValue = (int) $reminderMinutes;
    }

    if ($reminderEmail !== '' && !filter_var($reminderEmail, FILTER_VALIDATE_EMAIL)) {
        json_response(['ok' => false, 'error' => 'Nieprawidłowy adres e-mail.'], 422);
    }

    if ($id > 0) {
        $stmt = db()->prepare('UPDATE entries SET title = :title, description = :description, category_id = :category_id, start_at = :start_at, end_at = :end_at, reminder_email = :reminder_email, reminder_minutes = :reminder_minutes, reminder_sent = CASE WHEN :reminder_email = reminder_email AND :reminder_minutes = reminder_minutes THEN reminder_sent ELSE 0 END, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':category_id' => $categoryId,
            ':start_at' => $startDb,
            ':end_at' => $endDb,
            ':reminder_email' => $reminderEmail !== '' ? $reminderEmail : null,
            ':reminder_minutes' => $reminderMinutesValue,
            ':id' => $id,
        ]);
    } else {
        $stmt = db()->prepare('INSERT INTO entries (title, description, category_id, start_at, end_at, reminder_email, reminder_minutes, reminder_sent) VALUES (:title, :description, :category_id, :start_at, :end_at, :reminder_email, :reminder_minutes, 0)');
        $stmt->execute([
            ':title' => $title,
            ':description' => $description,
            ':category_id' => $categoryId,
            ':start_at' => $startDb,
            ':end_at' => $endDb,
            ':reminder_email' => $reminderEmail !== '' ? $reminderEmail : null,
            ':reminder_minutes' => $reminderMinutesValue,
        ]);
    }

    json_response([
        'ok' => true,
        'categories' => fetch_categories(),
        'entries' => fetch_entries(),
    ]);
}

function delete_entry(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if ($id <= 0) {
        json_response(['ok' => false, 'error' => 'Nieprawidłowe ID wpisu.'], 422);
    }

    $stmt = db()->prepare('DELETE FROM entries WHERE id = :id');
    $stmt->execute([':id' => $id]);

    json_response([
        'ok' => true,
        'entries' => fetch_entries(),
    ]);
}
