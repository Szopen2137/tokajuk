<?php
// user/dashboard.php
require_once __DIR__ . '/../includes/auth.php';
wymagajZalogowania();
$u = aktualnyUzytkownika();
?>
<!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Mój Budżet – BudżetDomowy</title>
<link rel="stylesheet" href="../assets/css/main.css">
</head>
<body>

<nav class="navbar">
  <div class="nav-brand">💰 BudżetDomowy</div>
  <div class="nav-links">
    <a href="#" class="nav-link active" data-section="dashboard-section">Dashboard</a>
    <a href="#" class="nav-link" data-section="kategorie-section">Kategorie</a>
    <a href="#" class="nav-link" data-section="transakcje-section">Transakcje</a>
    <a href="#" class="nav-link" data-section="statystyki-section">Statystyki</a>
  </div>
  <div class="nav-user">
    <span>👤 <?= htmlspecialchars($u['imie'] . ' ' . $u['nazwisko']) ?></span>
    <a href="../logout.php" class="btn btn-sm btn-outline">Wyloguj</a>
  </div>
</nav>

<main class="main-content">

  <!-- ===== DASHBOARD ===== -->
  <section id="dashboard-section" class="section active">
    <h2 class="section-title">Mój Dashboard</h2>
    <div class="filters-bar">
      <input type="month" id="dash-miesiac" value="<?= date('Y-m') ?>">
      <button class="btn btn-primary" id="btn-odswierz-dash">Odśwież</button>
    </div>
    <div id="dash-cards" class="stats-grid" style="margin-top:1rem">
      <div class="stat-card loading">Ładowanie…</div>
    </div>
    <div class="charts-row" style="margin-top:1.5rem">
      <div class="chart-box">
        <h3>Wydatki wg kategorii</h3>
        <canvas id="chart-kategorie"></canvas>
      </div>
      <div class="chart-box">
        <h3>Stałe vs Zmienne</h3>
        <canvas id="chart-rodzaj"></canvas>
      </div>
    </div>
  </section>

  <!-- ===== KATEGORIE ===== -->
  <section id="kategorie-section" class="section">
    <div class="section-header">
      <h2 class="section-title">Moje Kategorie</h2>
      <button class="btn btn-primary" id="btn-dodaj-kat">+ Dodaj kategorię</button>
    </div>

    <div id="form-kat" class="card form-card hidden">
      <h3 id="form-kat-tytul">Nowa kategoria</h3>
      <div id="alert-kat" class="alert hidden"></div>
      <input type="hidden" id="kat-id" value="">
      <div class="form-row">
        <div class="form-group">
          <label>Nazwa kategorii</label>
          <input type="text" id="kat-nazwa" placeholder="np. Żywność">
        </div>
        <div class="form-group">
          <label>Kolor</label>
          <input type="color" id="kat-kolor" value="#3b82f6">
        </div>
      </div>
      <div class="form-group">
        <label>Opis (opcjonalnie)</label>
        <input type="text" id="kat-opis" placeholder="Krótki opis…">
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" id="btn-zapisz-kat">Zapisz</button>
        <button class="btn btn-outline" id="btn-anuluj-kat">Anuluj</button>
      </div>
    </div>

    <div id="lista-kategorii" class="categories-grid">
      <p class="loading-text">Ładowanie…</p>
    </div>
  </section>

  <!-- ===== TRANSAKCJE ===== -->
  <section id="transakcje-section" class="section">
    <div class="section-header">
      <h2 class="section-title">Moje Transakcje</h2>
      <button class="btn btn-primary" id="btn-dodaj-trans">+ Dodaj transakcję</button>
    </div>

    <div id="form-trans" class="card form-card hidden">
      <h3 id="form-trans-tytul">Nowa transakcja</h3>
      <div id="alert-trans" class="alert hidden"></div>
      <input type="hidden" id="trans-id" value="">
      <div class="form-row">
        <div class="form-group">
          <label>Typ</label>
          <select id="trans-typ">
            <option value="wydatek">Wydatek</option>
            <option value="przychod">Przychód</option>
          </select>
        </div>
        <div class="form-group">
          <label>Rodzaj</label>
          <select id="trans-rodzaj">
            <option value="zmienny">Zmienny</option>
            <option value="staly">Stały</option>
          </select>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Nazwa</label>
          <input type="text" id="trans-nazwa" placeholder="np. Zakupy Biedronka">
        </div>
        <div class="form-group">
          <label>Kwota (PLN)</label>
          <input type="number" id="trans-kwota" step="0.01" min="0" placeholder="0.00">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Kategoria</label>
          <select id="trans-kategoria"><option value="">– brak –</option></select>
        </div>
        <div class="form-group">
          <label>Data</label>
          <input type="date" id="trans-data" value="<?= date('Y-m-d') ?>">
        </div>
      </div>
      <div class="form-group">
        <label>Opis (opcjonalnie)</label>
        <textarea id="trans-opis" rows="2" placeholder="Dodatkowe uwagi…"></textarea>
      </div>
      <div class="form-actions">
        <button class="btn btn-primary" id="btn-zapisz-trans">Zapisz</button>
        <button class="btn btn-outline" id="btn-anuluj-trans">Anuluj</button>
      </div>
    </div>

    <div class="filters-bar" style="margin-top:1rem">
      <select id="filter-trans-typ">
        <option value="">Wszystkie typy</option>
        <option value="wydatek">Wydatki</option>
        <option value="przychod">Przychody</option>
      </select>
      <select id="filter-trans-rodzaj">
        <option value="">Stałe i zmienne</option>
        <option value="staly">Stałe</option>
        <option value="zmienny">Zmienne</option>
      </select>
      <input type="month" id="filter-trans-miesiac" value="<?= date('Y-m') ?>">
      <button class="btn btn-primary" id="btn-filtruj-trans">Filtruj</button>
    </div>

    <div id="lista-transakcji" class="table-wrapper" style="margin-top:1rem">
      <p class="loading-text">Ładowanie…</p>
    </div>
  </section>

  <!-- ===== STATYSTYKI ===== -->
  <section id="statystyki-section" class="section">
    <h2 class="section-title">Moje Statystyki</h2>
    <div class="filters-bar">
      <select id="stat-rok">
        <?php for($y = date('Y'); $y >= date('Y')-3; $y--): ?>
          <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
        <?php endfor; ?>
      </select>
      <button class="btn btn-primary" id="btn-wczytaj-stat">Wczytaj</button>
    </div>
    <div id="stat-summary-user" class="stats-grid" style="margin-top:1rem"></div>
    <div class="charts-row" style="margin-top:1.5rem">
      <div class="chart-box chart-wide">
        <h3>Miesięczne przychody / wydatki / oszczędności</h3>
        <canvas id="chart-roczny-user"></canvas>
      </div>
    </div>
    <div id="stat-tabela-user" class="table-wrapper" style="margin-top:1.5rem"></div>
  </section>

</main>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
<script>const APP_URL = '<?= APP_URL ?>';</script>
<script src="../assets/js/common.js"></script>
<script src="../assets/js/user.js"></script>
</body>
</html>
