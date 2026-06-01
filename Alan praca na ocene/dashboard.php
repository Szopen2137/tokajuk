<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
wymagajAdmina();
$u = aktualnyUzytkownika();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel Administratora – BudżetDomowy</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-brand">💰 BudżetDomowy</div>
  <div class="nav-links">
    <a href="#" class="nav-link active" data-section="dashboard-section">Dashboard</a>
    <a href="#" class="nav-link" data-section="uzytkownicy-section">Użytkownicy</a>
    <a href="#" class="nav-link" data-section="wydatki-section">Wydatki</a>
    <a href="#" class="nav-link" data-section="statystyki-section">Statystyki</a>
  </div>
  <div class="nav-user">
    <span>👤 <?= htmlspecialchars($u['imie'] . ' ' . $u['nazwisko']) ?> <span class="badge badge-admin">Admin</span></span>
    <a href="../logout.php" class="btn btn-sm btn-outline">Wyloguj</a>
  </div>
</nav>

<main class="main-content">

  <!-- ===== DASHBOARD ===== -->
  <section id="dashboard-section" class="section active">
    <h2 class="section-title">Dashboard</h2>
    <div id="stats-cards" class="stats-grid">
      <div class="stat-card loading">Ładowanie…</div>
    </div>
    <div class="charts-row">
      <div class="chart-box">
        <h3>Wydatki vs Przychody (bieżący miesiąc – wszyscy)</h3>
        <canvas id="chart-overview"></canvas>
      </div>
      <div class="chart-box">
        <h3>Wydatki wg kategorii (bieżący miesiąc – wszyscy)</h3>
        <canvas id="chart-kat-admin"></canvas>
      </div>
    </div>
  </section>

  <!-- ===== UŻYTKOWNICY ===== -->
  <section id="uzytkownicy-section" class="section">
    <div class="section-header">
      <h2 class="section-title">Użytkownicy</h2>
      <button class="btn btn-primary" id="btn-dodaj-uzytkownika">+ Dodaj użytkownika</button>
    </div>

    <!-- Formularz dodawania -->
    <div id="form-uzytkownik" class="card form-card hidden">
      <h3>Nowy użytkownik</h3>
      <div id="alert-uzytkownik" class="alert hidden"></div>
      <div class="form-row">
        <div class="form-group"><label>Imię</label><input type="text" id="nu-imie"></div>
        <div class="form-group"><label>Nazwisko</label><input type="text" id="nu-nazwisko"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Login</label><input type="text" id="nu-login"></div>
        <div class="form-group"><label>E-mail</label><input type="email" id="nu-email"></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label>Hasło</label><input type="password" id="nu-haslo"></div>
        <div class="form-group">
          <label>Rola</label>
          <select id="nu-rola">
            <option value="uzytkownik">Użytkownik</option>
            <option value="admin">Administrator</option>
          </select>
        </div>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" id="btn-zapisz-uzytkownika">Zapisz</button>
        <button class="btn btn-outline" id="btn-anuluj-uzytkownika">Anuluj</button>
      </div>
    </div>

    <div id="lista-uzytkownikow" class="table-wrapper">
      <p class="loading-text">Ładowanie…</p>
    </div>
  </section>

  <!-- ===== WYDATKI WSZYSTKICH ===== -->
  <section id="wydatki-section" class="section">
    <h2 class="section-title">Wydatki wszystkich użytkowników</h2>
    <div class="filters-bar">
      <select id="filter-uzytkownik"><option value="">Wszyscy użytkownicy</option></select>
      <select id="filter-typ">
        <option value="">Wszystkie typy</option>
        <option value="wydatek">Wydatki</option>
        <option value="przychod">Przychody</option>
      </select>
      <input type="month" id="filter-miesiac" value="<?= date('Y-m') ?>">
      <button class="btn btn-primary" id="btn-filtruj-wydatki">Filtruj</button>
    </div>
    <div id="lista-wydatkow-admin" class="table-wrapper">
      <p class="loading-text">Ładowanie…</p>
    </div>
  </section>

  <!-- ===== STATYSTYKI ===== -->
  <section id="statystyki-section" class="section">
    <h2 class="section-title">Statystyki</h2>
    <div class="filters-bar">
      <select id="stat-uzytkownik"><option value="">Wszyscy</option></select>
      <select id="stat-rok">
        <?php for($y = date('Y'); $y >= date('Y')-3; $y--): ?>
          <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <button class="btn btn-primary" id="btn-wczytaj-stat">Wczytaj</button>
    </div>
    <div id="stat-summary" class="stats-grid" style="margin-top:1rem"></div>
    <div class="charts-row" style="margin-top:1.5rem">
      <div class="chart-box chart-wide">
        <h3>Miesięczne przychody / wydatki / oszczędności</h3>
        <canvas id="chart-roczny"></canvas>
      </div>
    </div>
    <div id="stat-tabela" class="table-wrapper" style="margin-top:1.5rem"></div>
  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>const APP_URL = '<?= APP_URL ?>';</script>
<script src="../assets/js/common.js"></script>
<script src="../assets/js/admin.js"></script>
</body>
</html>
