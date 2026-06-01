<?php

declare(strict_types=1);

function app_config(): array
{
    return [
        'db_path' => sys_get_temp_dir() . '/tokajuk-terminarz/terminarz.sqlite',
        'default_username' => 'admin',
        'default_password' => 'admin',
        'default_categories' => ['Służbowe', 'Prywatne', 'Pilne', 'Inne'],
    ];
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $config = app_config();
    $dataDir = dirname($config['db_path']);
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0777, true);
    }

    if (!file_exists($config['db_path'])) {
        touch($config['db_path']);
    }

    @chmod($dataDir, 0777);
    @chmod($config['db_path'], 0666);

    $pdo = new PDO('sqlite:' . $config['db_path']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    initialize_db($pdo);

    return $pdo;
}

function initialize_db(PDO $pdo): void
{
    $pdo->exec('CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL UNIQUE
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS entries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT \'\',
        category_id INTEGER NOT NULL,
        start_at TEXT NOT NULL,
        end_at TEXT NOT NULL,
        reminder_email TEXT DEFAULT NULL,
        reminder_minutes INTEGER DEFAULT NULL,
        reminder_sent INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE RESTRICT
    )');

    $pdo->exec('CREATE TABLE IF NOT EXISTS settings (
        key TEXT PRIMARY KEY,
        value TEXT NOT NULL
    )');

    $config = app_config();
    $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $stmt->execute([':username' => $config['default_username']]);
    if ((int) $stmt->fetchColumn() === 0) {
        $insert = $pdo->prepare('INSERT INTO users (username, password_hash) VALUES (:username, :password_hash)');
        $insert->execute([
            ':username' => $config['default_username'],
            ':password_hash' => password_hash($config['default_password'], PASSWORD_DEFAULT),
        ]);
    }

    $count = (int) $pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count === 0) {
        $insert = $pdo->prepare('INSERT INTO categories (name) VALUES (:name)');
        foreach ($config['default_categories'] as $category) {
            $insert->execute([':name' => $category]);
        }
    }
}

function json_response(array $payload, int $statusCode = 200): never
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        json_response(['ok' => false, 'error' => 'Brak autoryzacji.'], 401);
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = db()->prepare('SELECT id, username FROM users WHERE id = :id');
    $stmt->execute([':id' => $_SESSION['user_id']]);
    $user = $stmt->fetch();

    return $user ?: null;
}

function entry_with_category_sql(): string
{
    return 'SELECT e.id, e.title, e.description, e.category_id, c.name AS category_name, e.start_at, e.end_at, e.reminder_email, e.reminder_minutes, e.reminder_sent, e.created_at, e.updated_at
            FROM entries e
            INNER JOIN categories c ON c.id = e.category_id';
}

function validate_datetime(string $value): bool
{
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if (!$dt) {
        return false;
    }

    return $dt->format('Y-m-d\TH:i') === $value;
}

function format_datetime_db(string $value): string
{
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $value);
    if (!$dt) {
        throw new RuntimeException('Nieprawidłowa data.');
    }

    return $dt->format('Y-m-d H:i:00');
}

function format_datetime_input(string $value): string
{
    $dt = new DateTime($value);
    return $dt->format('Y-m-d\TH:i');
}
