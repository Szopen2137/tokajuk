<?php
// index.php  –  Strona logowania
require_once __DIR__ . '/includes/auth.php';

startSession();
if (zalogowany()) {
    $r = $_SESSION['rola'] === 'admin'
        ? APP_URL . '/admin/dashboard.php'
        : APP_URL . '/user/dashboard.php';
    header('Location: ' . $r);
    exit;
}
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Logowanie – BudżetDomowy</title>
<link rel="stylesheet" href="assets/css/main.css">
</head>
<body class="login-body">

<div class="login-wrapper">
  <div class="login-card">
    <div class="login-logo">
      <span class="logo-icon">💰</span>
      <h1>BudżetDomowy</h1>
      <p>Zarządzaj finansami rodziny</p>
    </div>

    <div id="alert" class="alert hidden"></div>

    <div class="form-group">
      <label for="login">Login</label>
      <input type="text" id="login" placeholder="Twój login" autocomplete="username">
    </div>
    <div class="form-group">
      <label for="haslo">Hasło</label>
      <input type="password" id="haslo" placeholder="••••••••" autocomplete="current-password">
    </div>
    <button id="btn-login" class="btn btn-primary btn-full">
      <span id="btn-text">Zaloguj się</span>
      <span id="btn-loader" class="loader hidden"></span>
    </button>

    <p class="login-hint">Admin: <strong>admin</strong> / <strong>admin123</strong><br>
       Użytkownik: <strong>jan.kowalski</strong> / <strong>user123</strong></p>
  </div>
</div>

<script src="assets/js/login.js"></script>
</body>
</html>
