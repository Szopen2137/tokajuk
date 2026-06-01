<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Zarządzanie Budżetem Domowym</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; color: #333; }
        
        .navbar { background: linear-gradient(135deg, #2c3e50, #3498db); padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; color: white; box-shadow: 0 2px 10px rgba(0,0,0,0.2); }
        .navbar h1 { font-size: 20px; }
        .navbar .user-info { display: flex; align-items: center; gap: 15px; }
        .navbar .user-info span { font-size: 14px; opacity: 0.9; }
        .navbar a { color: white; text-decoration: none; padding: 8px 16px; border-radius: 6px; background: rgba(255,255,255,0.15); font-size: 13px; transition: background 0.2s; }
        .navbar a:hover { background: rgba(255,255,255,0.3); }
        .admin-badge { background: #e74c3c !important; font-weight: bold; }

        .container { max-width: 1200px; margin: 20px auto; padding: 0 20px; }

        .login-box { max-width: 400px; margin: 80px auto; background: white; border-radius: 12px; padding: 40px; box-shadow: 0 2px 15px rgba(0,0,0,0.1); }
        .login-box h2 { text-align: center; margin-bottom: 25px; color: #2c3e50; }
        .login-box .tabs { display: flex; margin-bottom: 20px; border-bottom: 2px solid #eee; }
        .login-box .tabs button { flex: 1; padding: 10px; border: none; background: none; cursor: pointer; font-size: 14px; color: #888; border-bottom: 2px solid transparent; margin-bottom: -2px; transition: all 0.2s; }
        .login-box .tabs button.active { color: #3498db; border-bottom-color: #3498db; font-weight: bold; }

        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 600; font-size: 13px; color: #555; }
        .form-group input, .form-group select { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; transition: border-color 0.2s; }
        .form-group input:focus, .form-group select:focus { outline: none; border-color: #3498db; }
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .btn-primary { background: #3498db; color: white; width: 100%; }
        .btn-primary:hover { background: #2980b9; }
        .btn-success { background: #27ae60; color: white; }
        .btn-success:hover { background: #219a52; }
        .btn-danger { background: #e74c3c; color: white; }
        .btn-danger:hover { background: #c0392b; }
        .btn-warning { background: #f39c12; color: white; }
        .btn-warning:hover { background: #d68910; }
        .btn-sm { padding: 6px 12px; font-size: 12px; }

        .dashboard { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); }
        .stat-card h3 { font-size: 13px; color: #888; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 10px; }
        .stat-card .value { font-size: 28px; font-weight: bold; }
        .stat-card.income .value { color: #27ae60; }
        .stat-card.expense .value { color: #e74c3c; }
        .stat-card.savings .value { color: #3498db; }

        .panel { background: white; border-radius: 12px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.08); margin-bottom: 20px; }
        .panel h2 { font-size: 18px; color: #2c3e50; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f2f5; }
        .panel-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }

        .tabs-nav { display: flex; gap: 5px; margin-bottom: 20px; background: #f0f2f5; padding: 5px; border-radius: 10px; flex-wrap: wrap; }
        .tabs-nav button { flex: 1; padding: 10px 15px; border: none; background: none; cursor: pointer; border-radius: 8px; font-size: 13px; font-weight: 600; color: #666; transition: all 0.2s; min-width: 120px; }
        .tabs-nav button.active { background: white; color: #3498db; box-shadow: 0 1px 5px rgba(0,0,0,0.1); }

        table { width: 100%; border-collapse: collapse; }
        table th { background: #f8f9fa; padding: 12px; text-align: left; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; color: #666; border-bottom: 2px solid #eee; }
        table td { padding: 12px; border-bottom: 1px solid #f0f2f5; font-size: 14px; }
        table tr:hover { background: #f8f9fa; }

        .tag { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .tag-staly { background: #ffeaa7; color: #d68910; }
        .tag-zmienny { background: #dfe6e9; color: #636e72; }

        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-overlay.active { display: flex; }
        .modal { background: white; border-radius: 12px; padding: 30px; width: 90%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
        .modal h3 { margin-bottom: 20px; color: #2c3e50; }
        .modal-actions { display: flex; gap: 10px; justify-content: flex-end; margin-top: 20px; }

        .empty-state { text-align: center; padding: 40px; color: #aaa; }
        .empty-state p { margin-bottom: 15px; }

        .month-selector { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
        .month-selector button { padding: 8px 15px; border: 2px solid #e0e0e0; background: white; border-radius: 8px; cursor: pointer; font-size: 16px; }
        .month-selector button:hover { border-color: #3498db; }
        .month-selector span { font-size: 18px; font-weight: 600; min-width: 200px; text-align: center; }

        .chart-bar { display: flex; align-items: center; margin-bottom: 8px; }
        .chart-bar .label { width: 120px; font-size: 13px; text-align: right; padding-right: 10px; }
        .chart-bar .bar { flex: 1; height: 24px; border-radius: 4px; position: relative; }
        .chart-bar .bar-fill { height: 100%; border-radius: 4px; transition: width 0.5s; display: flex; align-items: center; padding-left: 8px; font-size: 11px; color: white; font-weight: 600; }
        .chart-bar .bar-fill.income { background: linear-gradient(90deg, #27ae60, #2ecc71); }
        .chart-bar .bar-fill.expense { background: linear-gradient(90deg, #e74c3c, #e67e22); }

        #msg { position: fixed; top: 20px; right: 20px; padding: 15px 25px; border-radius: 10px; color: white; font-weight: 600; z-index: 2000; display: none; animation: slideIn 0.3s; }
        #msg.success { background: #27ae60; }
        #msg.error { background: #e74c3c; }
        @keyframes slideIn { from { transform: translateX(100px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .hidden { display: none !important; }
        
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 10px; }
            .container { padding: 0 10px; }
            .tabs-nav { flex-direction: column; }
            .tabs-nav button { min-width: auto; }
        }
    </style>
</head>
<body>

<div id="msg"></div>

<!-- NAWIGACJA -->
<div class="navbar" id="navbar" style="display:none;">
    <h1>Budżet Domowy</h1>
    <div class="user-info">
        <span id="nav-user"></span>
        <a href="#" id="nav-admin-link" class="admin-badge hidden" onclick="showAdminPanel(); return false;">Panel Admina</a>
        <a href="#" onclick="logout(); return false;">Wyloguj</a>
    </div>
</div>

<!-- LOGOWANIE / REJESTRACJA -->
<div class="container" id="auth-section">
    <div class="login-box">
        <h2>Budżet Domowy</h2>
        <div class="tabs">
            <button class="active" onclick="showAuthTab('login')">Logowanie</button>
            <button onclick="showAuthTab('register')">Rejestracja</button>
        </div>
        
        <form id="login-form" onsubmit="return login(event)">
            <div class="form-group">
                <label>Login</label>
                <input type="text" id="login-login" required>
            </div>
            <div class="form-group">
                <label>Hasło</label>
                <input type="password" id="login-pass" required>
            </div>
            <button type="submit" class="btn btn-primary">Zaloguj się</button>
        </form>

        <form id="register-form" class="hidden" onsubmit="return register(event)">
            <div class="form-group">
                <label>Imię</label>
                <input type="text" id="reg-name" required>
            </div>
            <div class="form-group">
                <label>Nazwisko</label>
                <input type="text" id="reg-surname" required>
            </div>
            <div class="form-group">
                <label>Login</label>
                <input type="text" id="reg-login" required>
            </div>
            <div class="form-group">
                <label>Hasło</label>
                <input type="password" id="reg-pass" required>
            </div>
            <button type="submit" class="btn btn-primary">Zarejestruj się</button>
        </form>
    </div>
</div>

<!-- GŁÓWNA APLIKACJA -->
<div class="navbar" id="navbar-app" style="display:none;">
    <h1>Budżet Domowy</h1>
    <div class="user-info">
        <span id="nav-user-app"></span>
        <a href="#" id="nav-admin-link-app" class="admin-badge hidden" onclick="showAdminPanel(); return false;">Panel Admina</a>
        <a href="#" onclick="logout(); return false;">Wyloguj</a>
    </div>
</div>

<div class="container" id="app-section" style="display:none;">
    
    <!-- Zakładki główne -->
    <div class="tabs-nav" id="main-tabs">
        <button class="active" onclick="switchTab('dashboard')">Podsumowanie</button>
        <button onclick="switchTab('income')">Przychody</button>
        <button onclick="switchTab('categories')">Kategorie wydatków</button>
        <button onclick="switchTab('expenses')">Wydatki</button>
        <button onclick="switchTab('stats')">Statystyki</button>
    </div>

    <!-- PODSUMOWANIE -->
    <div id="tab-dashboard">
        <div class="month-selector">
            <button onclick="changeMonth(-1)">◀</button>
            <span id="current-month-label"></span>
            <button onclick="changeMonth(1)">▶</button>
        </div>
        <div class="dashboard">
            <div class="stat-card income">
                <h3>Przychody</h3>
                <div class="value" id="stat-income">0,00 zł</div>
            </div>
            <div class="stat-card expense">
                <h3>Wydatki</h3>
                <div class="value" id="stat-expense">0,00 zł</div>
            </div>
            <div class="stat-card savings">
                <h3>Oszczędności</h3>
                <div class="value" id="stat-savings">0,00 zł</div>
            </div>
        </div>
        
        <div class="panel">
            <h2>Wydatki w tym miesiącu</h2>
            <div id="dashboard-expenses-list"></div>
        </div>
    </div>

    <!-- KATEGORIE WYDATKÓW -->
    <div id="tab-categories" class="hidden">
        <div class="panel">
            <div class="panel-header">
                <h2>Moje kategorie wydatków</h2>
                <button class="btn btn-success" onclick="openCategoryModal()">+ Nowa kategoria</button>
            </div>
            <div id="categories-list"></div>
        </div>
    </div>
    <!-- PRZYCHODY -->
<div id="tab-income" class="hidden">
    <div class="month-selector">
        <button onclick="changeMonth(-1)">&#9664;</button>
        <span id="current-month-label-inc"></span>
        <button onclick="changeMonth(1)">&#9654;</button>
    </div>
    <div class="panel">
        <div class="panel-header">
            <h2>Moje przychody</h2>
            <button class="btn btn-success" onclick="openIncomeModal()">+ Nowy przychód</button>
        </div>
        <div id="income-list"></div>
    </div>
</div>


    <!-- WYDATKI -->
    <div id="tab-expenses" class="hidden">
        <div class="month-selector">
            <button onclick="changeMonth(-1)">◀</button>
            <span id="current-month-label-exp"></span>
            <button onclick="changeMonth(1)">▶</button>
        </div>
        <div class="panel">
            <div class="panel-header">
                <h2>Moje wydatki</h2>
                <button class="btn btn-success" onclick="openExpenseModal()">+ Nowy wydatek</button>
            </div>
            <div id="expenses-list"></div>
        </div>
    </div>

    <!-- STATYSTYKI -->
    <div id="tab-stats" class="hidden">
        <div class="month-selector">
            <button onclick="changeMonth(-1)">◀</button>
            <span id="current-month-label-stats"></span>
            <button onclick="changeMonth(1)">▶</button>
        </div>
        <div class="panel">
            <h2>Porównanie przychodów i wydatków</h2>
            <div id="stats-chart"></div>
        </div>
        <div class="panel">
            <h2>Wydatki wg kategorii</h2>
            <div id="stats-categories"></div>
        </div>
    </div>
</div>

<!-- PANEL ADMINISTRATORA -->
<div class="container" id="admin-section" style="display:none;">
    <div class="tabs-nav">
        <button class="active" onclick="switchAdminTab('users')">Użytkownicy</button>
        <button onclick="switchAdminTab('all-expenses')">Wydatki wszystkich</button>
        <button onclick="switchAdminTab('admin-stats')">Statystyki ogólne</button>
        <button onclick="switchAdminTab('add-user')">Dodaj użytkownika</button>
    </div>

    <!-- Lista użytkowników -->
    <div id="adm-tab-users" class="panel">
        <h2>Lista użytkowników</h2>
        <div id="admin-users-list"></div>
    </div>

    <!-- Wydatki wszystkich -->
    <div id="adm-tab-all-expenses" class="panel hidden">
        <div class="panel-header">
            <h2>Wydatki wszystkich użytkowników</h2>
            <select id="admin-user-filter" onchange="loadAdminExpenses()">
                <option value="0">Wszyscy użytkownicy</option>
            </select>
        </div>
        <div class="month-selector">
            <button onclick="changeMonth(-1)">◀</button>
            <span id="current-month-label-admin"></span>
            <button onclick="changeMonth(1)">▶</button>
        </div>
        <div id="admin-expenses-list"></div>
    </div>

    <!-- Statystyki ogólne -->
    <div id="adm-tab-admin-stats" class="panel hidden">
        <div class="month-selector">
            <button onclick="changeMonth(-1)">◀</button>
            <span id="current-month-label-admin-stats"></span>
            <button onclick="changeMonth(1)">▶</button>
        </div>
        <h2>Statystyki budżetu domowego</h2>
        <div id="admin-stats-content"></div>
    </div>

    <!-- Dodawanie użytkownika -->
    <div id="adm-tab-add-user" class="panel hidden">
        <h2>Dodaj nowego użytkownika</h2>
        <form onsubmit="return adminAddUser(event)" style="max-width: 400px;">
            <div class="form-group">
                <label>Imię</label>
                <input type="text" id="adm-user-name" required>
            </div>
            <div class="form-group">
                <label>Nazwisko</label>
                <input type="text" id="adm-user-surname" required>
            </div>
            <div class="form-group">
                <label>Login</label>
                <input type="text" id="adm-user-login" required>
            </div>
            <div class="form-group">
                <label>Hasło</label>
                <input type="password" id="adm-user-pass" required>
            </div>
            <div class="form-group">
                <label>Rola</label>
                <select id="adm-user-role">
                    <option value="uzytkownik">Zwykły użytkownik</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit" class="btn btn-success">Dodaj użytkownika</button>
        </form>
    </div>
    
    <div style="margin-top: 15px;">
        <button class="btn btn-warning" onclick="hideAdminPanel()">Wróć do panelu głównego</button>
    </div>
</div>

<!-- MODAL - KATEGORIA -->
<div class="modal-overlay" id="category-modal">
    <div class="modal">
        <h3 id="category-modal-title">Nowa kategoria</h3>
        <input type="hidden" id="cat-edit-id">
        <div class="form-group">
            <label>Nazwa kategorii</label>
            <input type="text" id="cat-name" placeholder="np. Jedzenie, Transport...">
        </div>
        <div class="form-group">
            <label>Typ</label>
            <select id="cat-type">
                <option value="zmienny">Zmienny</option>
                <option value="staly">Stały</option>
            </select>
        </div>
        <div class="form-group">
            <label>Kwota domyślna (stałe)</label>
            <input type="number" id="cat-amount" step="0.01" min="0" value="0">
        </div>
        <div class="modal-actions">
            <button class="btn btn-warning" onclick="closeModal('category-modal')">Anuluj</button>
            <button class="btn btn-success" onclick="saveCategory()">Zapisz</button>
        </div>
    </div>
</div>

<!-- MODAL - WYDATEK -->
<div class="modal-overlay" id="expense-modal">
    <div class="modal">
        <h3 id="expense-modal-title">Nowy wydatek</h3>
        <input type="hidden" id="exp-edit-id">
        <div class="form-group">
            <label>Kategoria</label>
            <select id="exp-category"></select>
        </div>
        <div class="form-group">
            <label>Kwota</label>
            <input type="number" id="exp-amount" step="0.01" min="0.01">
        </div>
        <div class="form-group">
            <label>Data</label>
            <input type="date" id="exp-date">
        </div>
        <div class="form-group">
            <label>Opis</label>
            <input type="text" id="exp-desc" placeholder="Opcjonalny opis">
        </div>
        <div class="modal-actions">
            <button class="btn btn-warning" onclick="closeModal('expense-modal')">Anuluj</button>
            <button class="btn btn-success" onclick="saveExpense()">Zapisz</button>
        </div>
    </div>
</div>
<!-- MODAL - PRZYCHÓD -->
<div class="modal-overlay" id="income-modal">
    <div class="modal">
        <h3 id="income-modal-title">Nowy przychód</h3>
        <input type="hidden" id="inc-edit-id">
        <div class="form-group">
            <label>Kwota</label>
            <input type="number" id="inc-amount" step="0.01" min="0.01">
        </div>
        <div class="form-group">
            <label>Data</label>
            <input type="date" id="inc-date">
        </div>
        <div class="form-group">
            <label>Opis</label>
            <input type="text" id="inc-desc" placeholder="Opcjonalny opis">
        </div>
        <div class="modal-actions">
            <button class="btn btn-warning" onclick="closeModal('income-modal')">Anuluj</button>
            <button class="btn btn-success" onclick="saveIncome()">Zapisz</button>
        </div>
    </div>
</div>
<script>
let currentUser = null;
let currentMonth = new Date().getMonth();
let currentYear = new Date().getFullYear();

const API = 'api.php';

function showMsg(text, type = 'success') {
    const el = document.getElementById('msg');
    el.textContent = text;
    el.className = type;
    el.style.display = 'block';
    setTimeout(() => el.style.display = 'none', 3000);
}

function showAuthTab(tab) {
    document.getElementById('login-form').classList.toggle('hidden', tab !== 'login');
    document.getElementById('register-form').classList.toggle('hidden', tab !== 'register');
    document.querySelectorAll('.tabs button').forEach((b, i) => {
        b.classList.toggle('active', (tab === 'login' && i === 0) || (tab === 'register' && i === 1));
    });
}

async function apiCall(action, data = {}) {
    data.action = action;
    const resp = await fetch(API, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return await resp.json();
}

async function login(e) {
    e.preventDefault();
    const res = await apiCall('login', {
        login: document.getElementById('login-login').value,
        haslo: document.getElementById('login-pass').value
    });
    if (res.error) { showMsg(res.error, 'error'); return false; }
    currentUser = res;
    showApp();
    return false;
}

async function register(e) {
    e.preventDefault();
    const res = await apiCall('register', {
        imie: document.getElementById('reg-name').value,
        nazwisko: document.getElementById('reg-surname').value,
        login: document.getElementById('reg-login').value,
        haslo: document.getElementById('reg-pass').value
    });
    if (res.error) { showMsg(res.error, 'error'); return false; }
    showMsg('Konto zostało utworzone! Możesz się zalogować.');
    showAuthTab('login');
    return false;
}

async function logout() {
    await apiCall('logout');
    currentUser = null;
    document.getElementById('app-section').style.display = 'none';
    document.getElementById('admin-section').style.display = 'none';
    document.getElementById('navbar-app').style.display = 'none';
    document.getElementById('auth-section').style.display = 'block';
    document.getElementById('navbar').style.display = 'none';
}

function showApp() {
    document.getElementById('auth-section').style.display = 'none';
    document.getElementById('app-section').style.display = 'block';
    document.getElementById('admin-section').style.display = 'none';
    document.getElementById('navbar-app').style.display = 'flex';
    document.getElementById('nav-user-app').textContent = currentUser.imie + ' ' + currentUser.nazwisko;
    
    if (currentUser.rola === 'admin') {
        document.getElementById('nav-admin-link-app').classList.remove('hidden');
    } else {
        document.getElementById('nav-admin-link-app').classList.add('hidden');
    }
    loadDashboard();
    loadCategories();
    loadExpenses();
    loadStats();
}

function formatMonth(y, m) {
    const months = ['Styczeń','Luty','Marzec','Kwiecień','Maj','Czerwiec',
                    'Lipiec','Sierpień','Wrzesień','Październik','Listopad','Grudzień'];
    return months[m] + ' ' + y;
}

function updateMonthLabels() {
    const label = formatMonth(currentYear, currentMonth);
    document.getElementById('current-month-label').textContent = label;
    const expLabel = document.getElementById('current-month-label-exp');
    if (expLabel) expLabel.textContent = label;
    const incLabel = document.getElementById('current-month-label-inc');
if (incLabel) incLabel.textContent = label;
    const statsLabel = document.getElementById('current-month-label-stats');
    if (statsLabel) statsLabel.textContent = label;
    const adminLabel = document.getElementById('current-month-label-admin');
    if (adminLabel) adminLabel.textContent = label;
    const adminStatsLabel = document.getElementById('current-month-label-admin-stats');
    if (adminStatsLabel) adminStatsLabel.textContent = label;
}

function changeMonth(delta) {
    currentMonth += delta;
    if (currentMonth > 11) { currentMonth = 0; currentYear++; }
    if (currentMonth < 0) { currentMonth = 11; currentYear--; }
    updateMonthLabels();
    loadDashboard();
    loadExpenses();
    loadStats();
    if (document.getElementById('admin-section').style.display !== 'none') {
        loadAdminExpenses();
        loadAdminStats();
    }
}

function switchTab(tab) {
    ['dashboard','income','categories','expenses','stats'].forEach(t => {
        document.getElementById('tab-' + t).classList.toggle('hidden', t !== tab);
    });
    document.querySelectorAll('#main-tabs button').forEach((b, i) => {
        const tabs = ['dashboard','income','categories','expenses','stats'];
        b.classList.toggle('active', tabs[i] === tab);
    });
     if (tab === 'income') loadIncome();
}

// ===== DASHBOARD =====
async function loadDashboard() {
    updateMonthLabels();
    const res = await apiCall('dashboard', { month: currentMonth + 1, year: currentYear });
    if (res.error) return;
    
    document.getElementById('stat-income').textContent = formatMoney(res.income);
    document.getElementById('stat-expense').textContent = formatMoney(res.expense);
    document.getElementById('stat-savings').textContent = formatMoney(res.income - res.expense);
    
    let html = '<table><thead><tr><th>Kwota</th><th>Kategoria</th><th>Data</th><th>Opis</th></tr></thead><tbody>';
    if (res.expenses && res.expenses.length > 0) {
        res.expenses.forEach(e => {
            html += `<tr><td style="color:#e74c3c;font-weight:bold;">-${formatMoney(e.kwota)}</td>
                <td>${e.kat_nazwa} <span class="tag tag-${e.typ}">${e.typ}</span></td>
                <td>${e.data_wydatku}</td><td>${e.opis || '-'}</td></tr>`;
        });
    } else {
        html += '<tr><td colspan="4" style="text-align:center;color:#aaa;">Brak wydatków w tym miesiącu</td></tr>';
    }
    html += '</tbody></table>';
    document.getElementById('dashboard-expenses-list').innerHTML = html;
}

function formatMoney(val) {
    return parseFloat(val || 0).toFixed(2).replace('.', ',') + ' zł';
}

// ===== KATEGORIE =====
async function loadCategories() {
    const res = await apiCall('get_categories');
    if (res.error) return;
    
    let html = '<table><thead><tr><th>Nazwa</th><th>Typ</th><th>Kwota domyślna</th><th>Akcje</th></tr></thead><tbody>';
    if (res.length > 0) {
        res.forEach(c => {
            html += `<tr>
                <td>${c.nazwa}</td>
                <td><span class="tag tag-${c.typ}">${c.typ === 'staly' ? 'Stały' : 'Zmienny'}</span></td>
                <td>${formatMoney(c.kwota_domyslna)}</td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editCategory(${c.id},'${c.nazwa}','${c.typ}',${c.kwota_domyslna})">Edytuj</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteCategory(${c.id})">Usuń</button>
                </td>
            </tr>`;
        });
    } else {
        html += '<tr><td colspan="4" style="text-align:center;color:#aaa;">Brak kategorii. Dodaj pierwszą!</td></tr>';
    }
    html += '</tbody></table>';
    document.getElementById('categories-list').innerHTML = html;
}

function openCategoryModal(id, name, type, amount) {
    document.getElementById('cat-edit-id').value = '';
    document.getElementById('cat-name').value = '';
    document.getElementById('cat-type').value = 'zmienny';
    document.getElementById('cat-amount').value = '0';
    document.getElementById('category-modal-title').textContent = 'Nowa kategoria';
    document.getElementById('category-modal').classList.add('active');
}

function editCategory(id, name, type, amount) {
    document.getElementById('cat-edit-id').value = id;
    document.getElementById('cat-name').value = name;
    document.getElementById('cat-type').value = type;
    document.getElementById('cat-amount').value = amount;
    document.getElementById('category-modal-title').textContent = 'Edytuj kategorię';
    document.getElementById('category-modal').classList.add('active');
}

async function saveCategory() {
    const id = document.getElementById('cat-edit-id').value;
    const data = {
        nazwa: document.getElementById('cat-name').value,
        typ: document.getElementById('cat-type').value,
        kwota_domyslna: document.getElementById('cat-amount').value
    };
    if (!data.nazwa) { showMsg('Podaj nazwę kategorii', 'error'); return; }
    
    if (id) data.id = parseInt(id);
    const res = await apiCall(id ? 'update_category' : 'add_category', data);
    if (res.error) { showMsg(res.error, 'error'); return; }
    showMsg(id ? 'Kategoria zaktualizowana' : 'Kategoria dodana');
    closeModal('category-modal');
    loadCategories();
}

async function deleteCategory(id) {
    if (!confirm('Na pewno usunąć tę kategorię?')) return;
    const res = await apiCall('delete_category', { id });
    if (res.error) { showMsg(res.error, 'error'); return; }
    showMsg('Kategoria usunięta');
    loadCategories();
}

// ===== WYDATKI =====
async function loadExpenses() {
    const res = await apiCall('get_expenses', { month: currentMonth + 1, year: currentYear });
    if (res.error) return;
    
    let html = '<table><thead><tr><th>Kwota</th><th>Kategoria</th><th>Data</th><th>Opis</th><th>Akcje</th></tr></thead><tbody>';
    if (res.length > 0) {
        res.forEach(e => {
            html += `<tr>
                <td style="color:#e74c3c;font-weight:bold;">${formatMoney(e.kwota)}</td>
                <td>${e.kat_nazwa} <span class="tag tag-${e.typ}">${e.typ === 'staly' ? 'Stały' : 'Zmienny'}</span></td>
                <td>${e.data_wydatku}</td>
                <td>${e.opis || '-'}</td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editExpense(${e.id},${e.kategoria_id},${e.kwota},'${e.data_wydatku}','${(e.opis||'').replace(/'/g,"\\'")}')">Edytuj</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteExpense(${e.id})">Usuń</button>
                </td>
            </tr>`;
        });
    } else {
        html += '<tr><td colspan="5" style="text-align:center;color:#aaa;">Brak wydatków w tym miesiącu</td></tr>';
    }
    html += '</tbody></table>';
    document.getElementById('expenses-list').innerHTML = html;
}

async function loadCategoryOptions() {
    const res = await apiCall('get_categories');
    const sel = document.getElementById('exp-category');
    sel.innerHTML = '';
    if (res && !res.error) {
        res.forEach(c => {
            sel.innerHTML += `<option value="${c.id}">${c.nazwa} (${c.typ === 'staly' ? 'stały' : 'zmienny'})</option>`;
        });
    }
}

function openExpenseModal() {
    document.getElementById('exp-edit-id').value = '';
    document.getElementById('exp-amount').value = '';
    document.getElementById('exp-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('exp-desc').value = '';
    document.getElementById('expense-modal-title').textContent = 'Nowy wydatek';
    loadCategoryOptions().then(() => {
        document.getElementById('expense-modal').classList.add('active');
    });
}

function editExpense(id, catId, amount, date, desc) {
    document.getElementById('exp-edit-id').value = id;
    document.getElementById('exp-amount').value = amount;
    document.getElementById('exp-date').value = date;
    document.getElementById('exp-desc').value = desc;
    document.getElementById('expense-modal-title').textContent = 'Edytuj wydatek';
    loadCategoryOptions().then(() => {
        document.getElementById('exp-category').value = catId;
        document.getElementById('expense-modal').classList.add('active');
    });
}

async function saveExpense() {
    const id = document.getElementById('exp-edit-id').value;
    const data = {
        kategoria_id: parseInt(document.getElementById('exp-category').value),
        kwota: parseFloat(document.getElementById('exp-amount').value),
        data_wydatku: document.getElementById('exp-date').value,
        opis: document.getElementById('exp-desc').value
    };
    if (!data.kwota || data.kwota <= 0) { showMsg('Podaj kwotę', 'error'); return; }
    if (!data.data_wydatku) { showMsg('Podaj datę', 'error'); return; }
    
    if (id) data.id = parseInt(id);
    const res = await apiCall(id ? 'update_expense' : 'add_expense', data);
    if (res.error) { showMsg(res.error, 'error'); return; }
    showMsg(id ? 'Wydatek zaktualizowany' : 'Wydatek dodany');
    closeModal('expense-modal');
    loadExpenses();
    loadDashboard();
}

async function deleteExpense(id) {
    if (!confirm('Na pewno usunąć ten wydatek?')) return;
    const res = await apiCall('delete_expense', { id });
    if (res.error) { showMsg(res.error, 'error'); return; }
    showMsg('Wydatek usunięty');
    loadExpenses();
    loadDashboard();
}
// ===== PRZYCHODY =====
async function loadIncome() {
    const res = await apiCall('get_income', { month: currentMonth + 1, year: currentYear });
    if (res.error) return;
    
    let html = '<table><thead><tr><th>Kwota</th><th>Data</th><th>Opis</th><th>Akcje</th></tr></thead><tbody>';
    if (res.length > 0) {
        res.forEach(e => {
            html += `<tr>
                <td style="color:#27ae60;font-weight:bold;">${formatMoney(e.kwota)}</td>
                <td>${e.data_przychodu}</td>
                <td>${e.opis || '-'}</td>
                <td>
                    <button class="btn btn-warning btn-sm" onclick="editIncome(${e.id},${e.kwota},'${e.data_przychodu}','${(e.opis||'').replace(/'/g,"\\'")}')">Edytuj</button>
                    <button class="btn btn-danger btn-sm" onclick="deleteIncome(${e.id})">Usuń</button>
                </td>
            </tr>`;
        });
    } else {
        html += '<tr><td colspan="4" style="text-align:center;color:#aaa;">Brak przychodów w tym miesiącu</td></tr>';
    }
    html += '</tbody></table>';
    document.getElementById('income-list').innerHTML = html;
}

function openIncomeModal() {
    document.getElementById('inc-edit-id').value = '';
    document.getElementById('inc-amount').value = '';
    document.getElementById('inc-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('inc-desc').value = '';
    document.getElementById('income-modal-title').textContent = 'Nowy przychód';
    document.getElementById('income-modal').classList.add('active');
}

function editIncome(id, amount, date, desc) {
    document.getElementById('inc-edit-id').value = id;
    document.getElementById('inc-amount').value = amount;
    document.getElementById('inc-date').value = date;
    document.getElementById('inc-desc').value = desc;
    document.getElementById('income-modal-title').textContent = 'Edytuj przychód';
    document.getElementById('income-modal').classList.add('active');
}

async function saveIncome() {
    const id = document.getElementById('inc-edit-id').value;
    const data = {
        kwota: parseFloat(document.getElementById('inc-amount').value),
        data_przychodu: document.getElementById('inc-date').value,
        opis: document.getElementById('inc-desc').value
    };
    if (!data.kwota || data.kwota <= 0) { showMsg('Podaj kwotę', 'error'); return; }
    if (!data.data_przychodu) { showMsg('Podaj datę', 'error'); return; }
    
    if (id) data.id = parseInt(id);
    const res = await apiCall(id ? 'update_income' : 'add_income', data);
    if (res.error) { showMsg(res.error, 'error'); return; }
    showMsg(id ? 'Przychód zaktualizowany' : 'Przychód dodany');
    closeModal('income-modal');
    loadIncome();
    loadDashboard();
}

async function deleteIncome(id) {
    if (!confirm('Na pewno usunąć ten przychód?')) return;
    const res = await apiCall('delete_income', { id });
    if (res.error) { showMsg(res.error, 'error'); return; }
    showMsg('Przychód usunięty');
    loadIncome();
    loadDashboard();
}

// ===== STATYSTYKI =====
async function loadStats() {
    const res = await apiCall('get_stats', { month: currentMonth + 1, year: currentYear });
    if (res.error) return;
    
    let html = '';
    const maxVal = Math.max(...res.by_category.map(c => parseFloat(c.total)), 1);
    
    if (res.by_category.length > 0) {
        res.by_category.forEach(c => {
            const pct = (parseFloat(c.total) / maxVal * 100);
            html += `<div class="chart-bar">
                <div class="label">${c.nazwa}</div>
                <div class="bar"><div class="bar-fill expense" style="width:${pct}%">${formatMoney(c.total)}</div></div>
            </div>`;
        });
    } else {
        html = '<p style="text-align:center;color:#aaa;">Brak danych do wyświetlenia</p>';
    }
    document.getElementById('stats-categories').innerHTML = html;
    
    let html2 = `<div class="dashboard">
        <div class="stat-card income"><h3>Przychody</h3><div class="value">${formatMoney(res.income)}</div></div>
        <div class="stat-card expense"><h3>Wydatki stałe</h3><div class="value">${formatMoney(res.fixed)}</div></div>
        <div class="stat-card savings"><h3>Wydatki zmienne</h3><div class="value">${formatMoney(res.variable)}</div></div>
    </div>`;
    document.getElementById('stats-chart').innerHTML = html2;
}

// ===== ADMIN =====
function showAdminPanel() {
    document.getElementById('app-section').style.display = 'none';
    document.getElementById('admin-section').style.display = 'block';
    loadAdminUsers();
    loadAdminExpenses();
    loadAdminStats();
}

function hideAdminPanel() {
    document.getElementById('admin-section').style.display = 'none';
    document.getElementById('app-section').style.display = 'block';
}

function switchAdminTab(tab) {
    ['users','all-expenses','admin-stats','add-user'].forEach(t => {
        document.getElementById('adm-tab-' + t).classList.toggle('hidden', t !== tab);
    });
    document.querySelectorAll('#admin-section .tabs-nav:first-of-type button').forEach((b, i) => {
        const tabs = ['users','all-expenses','admin-stats','add-user'];
        b.classList.toggle('active', tabs[i] === tab);
    });
}

async function loadAdminUsers() {
    const res = await apiCall('admin_get_users');
    if (res.error) return;
    
    const filter = document.getElementById('admin-user-filter');
    filter.innerHTML = '<option value="0">Wszyscy użytkownicy</option>';
    
    let html = '<table><thead><tr><th>Imię i nazwisko</th><th>Login</th><th>Rola</th><th>Data dodania</th><th>Akcje</th></tr></thead><tbody>';
    res.forEach(u => {
        filter.innerHTML += `<option value="${u.id}">${u.imie} ${u.nazwisko}</option>`;
        html += `<tr>
            <td>${u.imie} ${u.nazwisko}</td>
            <td>${u.login}</td>
            <td><span class="tag ${u.rola === 'admin' ? 'tag-staly' : 'tag-zmienny'}">${u.rola === 'admin' ? 'Administrator' : 'Użytkownik'}</span></td>
            <td>${u.data_dodania}</td>
            <td>
                <button class="btn btn-danger btn-sm" onclick="adminDeleteUser(${u.id})" ${u.login === 'admin' ? 'disabled title="Nie można usunąć głównego administratora"' : ''}>Usuń</button>
            </td>
        </tr>`;
    });
    html += '</tbody></table>';
    document.getElementById('admin-users-list').innerHTML = html;
}

async function adminAddUser(e) {
    e.preventDefault();
    const data = {
        imie: document.getElementById('adm-user-name').value,
        nazwisko: document.getElementById('adm-user-surname').value,
        login: document.getElementById('adm-user-login').value,
        haslo: document.getElementById('adm-user-pass').value,
        rola: document.getElementById('adm-user-role').value
    };
    const res = await apiCall('admin_add_user', data);
    if (res.error) { showMsg(res.error, 'error'); return false; }
    showMsg('Użytkownik dodany pomyślnie');
    document.getElementById('adm-user-name').value = '';
    document.getElementById('adm-user-surname').value = '';
    document.getElementById('adm-user-login').value = '';
    document.getElementById('adm-user-pass').value = '';
    loadAdminUsers();
    return false;
}

async function adminDeleteUser(id) {
    if (!confirm('Na pewno usunąć tego użytkownika i wszystkie jego dane?')) return;
    const res = await apiCall('admin_delete_user', { id });
    if (res.error) { showMsg(res.error, 'error'); return; }
    showMsg('Użytkownik usunięty');
    loadAdminUsers();
}

async function loadAdminExpenses() {
    const userId = document.getElementById('admin-user-filter').value;
    const res = await apiCall('admin_get_expenses', { 
        month: currentMonth + 1, 
        year: currentYear, 
        user_id: parseInt(userId) 
    });
    if (res.error) return;
    
    let html = '<table><thead><tr><th>Użytkownik</th><th>Kwota</th><th>Kategoria</th><th>Data</th><th>Opis</th></tr></thead><tbody>';
    if (res.length > 0) {
        res.forEach(e => {
            html += `<tr>
                <td>${e.imie} ${e.nazwisko}</td>
                <td style="color:#e74c3c;font-weight:bold;">${formatMoney(e.kwota)}</td>
                <td>${e.kat_nazwa} <span class="tag tag-${e.typ}">${e.typ === 'staly' ? 'Stały' : 'Zmienny'}</span></td>
                <td>${e.data_wydatku}</td>
                <td>${e.opis || '-'}</td>
            </tr>`;
        });
    } else {
        html += '<tr><td colspan="5" style="text-align:center;color:#aaa;">Brak wydatków</td></tr>';
    }
    html += '</tbody></table>';
    document.getElementById('admin-expenses-list').innerHTML = html;
}

async function loadAdminStats() {
    updateMonthLabels();
    const res = await apiCall('admin_get_stats', { month: currentMonth + 1, year: currentYear });
    if (res.error) return;
    
    let html = `<div class="dashboard">
        <div class="stat-card income"><h3>Przychody ogółem</h3><div class="value">${formatMoney(res.income)}</div></div>
        <div class="stat-card expense"><h3>Wydatki ogółem</h3><div class="value">${formatMoney(res.expense)}</div></div>
        <div class="stat-card savings"><h3>Oszczędności ogółem</h3><div class="value">${formatMoney(res.income - res.expense)}</div></div>
    </div>`;
    
    html += '<h3 style="margin:20px 0 10px;">Wydatki wg użytkownika</h3>';
    const maxUser = Math.max(...res.by_user.map(u => parseFloat(u.total)), 1);
    if (res.by_user.length > 0) {
        res.by_user.forEach(u => {
            const pct = (parseFloat(u.total) / maxUser * 100);
            html += `<div class="chart-bar">
                <div class="label">${u.imie} ${u.nazwisko}</div>
                <div class="bar"><div class="bar-fill expense" style="width:${pct}%">${formatMoney(u.total)}</div></div>
            </div>`;
        });
    } else {
        html += '<p style="color:#aaa;text-align:center;">Brak danych</p>';
    }
    
    html += '<h3 style="margin:20px 0 10px;">Wydatki wg kategorii</h3>';
    const maxCat = Math.max(...res.by_category.map(c => parseFloat(c.total)), 1);
    if (res.by_category.length > 0) {
        res.by_category.forEach(c => {
            const pct = (parseFloat(c.total) / maxCat * 100);
            html += `<div class="chart-bar">
                <div class="label">${c.nazwa}</div>
                <div class="bar"><div class="bar-fill expense" style="width:${pct}%">${formatMoney(c.total)}</div></div>
            </div>`;
        });
    }
    
    document.getElementById('admin-stats-content').innerHTML = html;
}

function closeModal(id) {
    document.getElementById(id).classList.remove('active');
}

document.querySelectorAll('.modal-overlay').forEach(m => {
    m.addEventListener('click', e => {
        if (e.target === m) m.classList.remove('active');
    });
});

// Sprawdź sesję przy starcie
(async function init() {
    const res = await apiCall('check_session');
    if (res.logged_in) {
        currentUser = res;
        showApp();
    }
})();
</script>
</body>
</html>
