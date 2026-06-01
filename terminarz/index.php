<?php

declare(strict_types=1);

session_start();
require __DIR__ . '/db.php';
$user = current_user();
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Terminarz Ajax</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT3pQbsb7Y5qU8KX0v1p4+2y" crossorigin="anonymous">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <main class="shell container-xxl py-4 py-lg-5">
        <section class="hero school-hero">
            <div>
                <p class="eyebrow text-uppercase fw-semibold mb-3">Szkolny terminarz</p>
                <h1 class="display-5 fw-semibold">Kalendarz klasowy z wpisami i przypomnieniami</h1>
                <p class="lead">Prosty projekt na szkołę: logowanie admin/admin, wpisy z datą, godziną, kategorią i opcjonalnym e-mailem. Wszystko działa bez przeładowywania strony.</p>
            </div>
            <div class="hero-card card text-bg-dark border-secondary shadow-lg">
                <span class="text-uppercase small text-secondary">Logowanie</span>
                <strong class="display-6 fw-semibold">admin / admin</strong>
                <p class="mb-0 text-secondary">Gotowe od razu po uruchomieniu.</p>
            </div>
        </section>

        <section id="loginView" class="panel card text-bg-dark border-secondary shadow-lg <?php echo $user ? 'hidden' : ''; ?>">
            <h2 class="mb-3">Logowanie</h2>
            <form id="loginForm" class="form-grid">
                <label>
                    Użytkownik
                    <input type="text" name="username" value="admin" autocomplete="username" required class="form-control">
                </label>
                <label>
                    Hasło
                    <input type="password" name="password" value="admin" autocomplete="current-password" required class="form-control">
                </label>
                <button type="submit" class="primary btn btn-primary btn-lg">Zaloguj</button>
            </form>
            <p class="hint">Wpisz admin/admin lub zmień dane w bazie SQLite po uruchomieniu.</p>
        </section>

        <section id="appView" class="panel card text-bg-dark border-secondary shadow-lg <?php echo $user ? '' : 'hidden'; ?>">
            <div class="toolbar">
                <div>
                    <h2 id="welcomeText" class="mb-1">Panel terminarza</h2>
                    <p class="hint">Widok kalendarza i formularz wpisów w prostym układzie szkolnym.</p>
                </div>
                <button id="logoutButton" class="secondary btn btn-outline-light">Wyloguj</button>
            </div>

            <section class="calendar-panel calendar-hero card text-bg-dark border-secondary shadow-sm">
                <div class="calendar-head">
                    <button type="button" id="prevMonthButton" class="calendar-nav btn btn-outline-light rounded-circle">‹</button>
                    <div>
                        <p class="eyebrow tiny text-uppercase fw-semibold mb-1">Miesiąc</p>
                        <h3 id="calendarTitle" class="mb-1">Kalendarz</h3>
                        <p id="selectedDayLabel" class="hint">Wybierz dzień w kalendarzu</p>
                    </div>
                    <button type="button" id="nextMonthButton" class="calendar-nav btn btn-outline-light rounded-circle">›</button>
                </div>
                <div class="weekday-row">
                    <span>Pn</span><span>Wt</span><span>Śr</span><span>Cz</span><span>Pt</span><span>Sb</span><span>Nd</span>
                </div>
                <div id="calendarGrid" class="calendar-grid"></div>
            </section>

            <div class="dashboard">
                <aside class="form-panel card text-bg-dark border-secondary shadow-sm">
                    <h3 class="mb-3">Nowy wpis</h3>
                    <form id="entryForm" class="entry-form">
                        <input type="hidden" name="id" id="entryId" value="">
                        <label>
                            Tytuł
                            <input type="text" name="title" id="title" required class="form-control">
                        </label>
                        <label>
                            Kategoria
                            <select name="category_id" id="categoryId" required class="form-select"></select>
                        </label>
                        <label>
                            Start
                            <input type="datetime-local" name="start_at" id="startAt" required class="form-control">
                        </label>
                        <label>
                            Koniec
                            <input type="datetime-local" name="end_at" id="endAt" required class="form-control">
                        </label>
                        <label class="full">
                            Opis
                            <textarea name="description" id="description" rows="4" class="form-control"></textarea>
                        </label>
                        <label>
                            E-mail przypomnienia
                            <input type="email" name="reminder_email" id="reminderEmail" placeholder="opcjonalnie" class="form-control">
                        </label>
                        <label>
                            Minuty przed terminem
                            <input type="number" name="reminder_minutes" id="reminderMinutes" min="1" step="1" placeholder="opcjonalnie" class="form-control">
                        </label>
                        <div class="actions full">
                            <button type="submit" class="primary btn btn-primary" id="saveButton">Zapisz wpis</button>
                            <button type="button" class="secondary btn btn-outline-light" id="resetButton">Wyczyść formularz</button>
                        </div>
                    </form>
                    <div id="message" class="message"></div>
                </aside>

                <section class="list-panel card text-bg-dark border-secondary shadow-sm">
                    <div class="list-head">
                        <h3 class="mb-1">Wpisy</h3>
                        <p class="hint">Lista pokazuje wydarzenia dla wybranego dnia.</p>
                    </div>
                    <div class="table-wrap table-responsive">
                        <table class="entries-table table table-dark table-hover align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Tytuł</th>
                                    <th>Kategoria</th>
                                    <th>Początek</th>
                                    <th>Koniec</th>
                                    <th>Przypomnienie</th>
                                    <th>Akcje</th>
                                </tr>
                            </thead>
                            <tbody id="entriesBody"></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </section>
    </main>

    <script>
        window.__BOOTSTRAP__ = <?php echo json_encode($user ? ['loggedIn' => true, 'user' => $user] : ['loggedIn' => false], JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <script src="assets/app.js"></script>
</body>
</html>
