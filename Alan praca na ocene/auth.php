<?php
// includes/auth.php
// ---------------------------------------------------------------
//  Funkcje pomocnicze – sesja i autoryzacja
// ---------------------------------------------------------------

require_once __DIR__ . '/db.php';

function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

function zalogowany(): bool {
    startSession();
    return isset($_SESSION['uzytkownik_id']);
}

function wymagajZalogowania(): void {
    if (!zalogowany()) {
        if (isAjax()) {
            echo json_encode(['ok' => false, 'msg' => 'Niezalogowany']);
            exit;
        }
        header('Location: ' . APP_URL . '/index.php');
        exit;
    }
}

function wymagajAdmina(): void {
    wymagajZalogowania();
    if ($_SESSION['rola'] !== 'admin') {
        if (isAjax()) {
            echo json_encode(['ok' => false, 'msg' => 'Brak uprawnień']);
            exit;
        }
        header('Location: ' . APP_URL . '/user/dashboard.php');
        exit;
    }
}

function isAjax(): bool {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH'])
        && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

function zalogujUzytkownika(string $login, string $haslo): array {
    $stmt = db()->prepare(
        'SELECT id, login, haslo, imie, nazwisko, rola, aktywny
         FROM uzytkownicy WHERE login = ? LIMIT 1'
    );
    $stmt->execute([$login]);
    $u = $stmt->fetch();

    if (!$u || !password_verify($haslo, $u['haslo'])) {
        return ['ok' => false, 'msg' => 'Nieprawidłowy login lub hasło.'];
    }
    if (!$u['aktywny']) {
        return ['ok' => false, 'msg' => 'Konto jest nieaktywne.'];
    }

    startSession();
    session_regenerate_id(true);
    $_SESSION['uzytkownik_id'] = $u['id'];
    $_SESSION['login']         = $u['login'];
    $_SESSION['imie']          = $u['imie'];
    $_SESSION['nazwisko']      = $u['nazwisko'];
    $_SESSION['rola']          = $u['rola'];

    $redirect = $u['rola'] === 'admin'
        ? APP_URL . '/admin/dashboard.php'
        : APP_URL . '/user/dashboard.php';

    return ['ok' => true, 'redirect' => $redirect];
}

function wyloguj(): void {
    startSession();
    session_destroy();
}

function aktualnyUzytkownik(): array {
    startSession();
    return [
        'id'       => $_SESSION['uzytkownik_id'] ?? null,
        'login'    => $_SESSION['login']          ?? '',
        'imie'     => $_SESSION['imie']           ?? '',
        'nazwisko' => $_SESSION['nazwisko']        ?? '',
        'rola'     => $_SESSION['rola']            ?? '',
    ];
}

function hashHaslo(string $haslo): string {
    return password_hash($haslo, PASSWORD_BCRYPT, ['cost' => 12]);
}

function csrf(): string {
    startSession();
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verifyCsrf(string $token): bool {
    startSession();
    return isset($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
}
