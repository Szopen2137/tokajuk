<?php
require_once 'database.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function toMoneyOrNull(string $value): ?float
{
    $value = trim(str_replace(',', '.', $value));
    if ($value === '') {
        return null;
    }
    return is_numeric($value) ? (float)$value : null;
}

function isValidEtatName(string $name): bool
{
    return (bool)preg_match('/^[\p{L}\d\s\-\.\/,\(\)]+$/u', $name);
}

$currentPage = basename($_SERVER['PHP_SELF']);
$success = false;
$formError = '';
$minimumSalary = 3500;

$fieldErrors = [
    'NAZWA' => '',
    'PLACA_OD' => '',
    'PLACA_DO' => ''
];

$form = [
    'NAZWA' => '',
    'PLACA_OD' => '',
    'PLACA_DO' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['NAZWA'] = trim((string)($_POST['NAZWA'] ?? ''));
    $form['PLACA_OD'] = trim((string)($_POST['PLACA_OD'] ?? ''));
    $form['PLACA_DO'] = trim((string)($_POST['PLACA_DO'] ?? ''));

    $placaOd = toMoneyOrNull($form['PLACA_OD']);
    $placaDo = toMoneyOrNull($form['PLACA_DO']);

    if ($form['NAZWA'] === '' || mb_strlen($form['NAZWA']) < 2 || mb_strlen($form['NAZWA']) > 30) {
        $fieldErrors['NAZWA'] = 'Nazwa etatu musi mieć od 2 do 30 znaków.';
    } elseif (!isValidEtatName($form['NAZWA'])) {
        $fieldErrors['NAZWA'] = 'Nazwa etatu może zawierać litery, cyfry, spacje oraz znaki: - . / , ( ).';
    }

    if ($placaOd === null || $placaOd < $minimumSalary) {
        $fieldErrors['PLACA_OD'] = 'Płaca minimalna musi być liczbą większą lub równą 3500.';
    }

    if ($placaDo === null || $placaDo < $minimumSalary) {
        $fieldErrors['PLACA_DO'] = 'Płaca maksymalna musi być liczbą większą lub równą 3500.';
    }

    if (!$fieldErrors['PLACA_OD'] && !$fieldErrors['PLACA_DO'] && $placaOd > $placaDo) {
        $fieldErrors['PLACA_DO'] = 'Płaca maksymalna nie może być mniejsza niż minimalna.';
    }

    if (!array_filter($fieldErrors)) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO etaty (NAZWA, PLACA_OD, PLACA_DO)
                VALUES (:NAZWA, :PLACA_OD, :PLACA_DO)
            ");
            $stmt->bindValue(':NAZWA', $form['NAZWA'], PDO::PARAM_STR);
            $stmt->bindValue(':PLACA_OD', (string)$placaOd, PDO::PARAM_STR);
            $stmt->bindValue(':PLACA_DO', (string)$placaDo, PDO::PARAM_STR);
            $stmt->execute();

            $success = true;

            // Return JSON for AJAX requests
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => true,
                    'message' => 'Etat został dodany.'
                ]);
                exit;
            }

            $form = [
                'NAZWA' => '',
                'PLACA_OD' => '',
                'PLACA_DO' => ''
            ];
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $fieldErrors['NAZWA'] = 'Taki etat już istnieje.';
            } elseif ($e->getCode() === '22001' || ((int)($e->errorInfo[1] ?? 0) === 1406)) {
                $fieldErrors['NAZWA'] = 'Nazwa etatu jest za długa (maksymalnie 30 znaków).';
            } else {
                $formError = 'Nie udało się dodać etatu.';
            }
        }
    }
}
?>
<!doctype html>
<html lang="pl" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Dodaj etat</title>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <div class="collapse navbar-collapse show" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'index.php' ? 'active fw-bold' : '' ?>" href="index.php">Pracownicy</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'etaty.php' ? 'active fw-bold' : '' ?>" href="etaty.php">Etaty</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $currentPage === 'zespoly.php' ? 'active fw-bold' : '' ?>" href="zespoly.php">Zespoły</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active fw-bold" aria-current="page" href="Dodaj_etat.php">Dodaj etat</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h3 class="mb-4">Dodaj etat</h3>

    <?php /* Server-side alert commented out for AJAX usage; feedback via javascript */?>
    <?php /* if ($success): ?>
        <div class="alert alert-success">Etat został dodany.</div>
    <?php endif; */ ?>

    <?php if ($formError): ?>
        <div class="alert alert-danger"><?= h($formError) ?></div>
    <?php endif; ?>

    <form method="post" novalidate class="ajax-form" data-ajax="true">
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nazwa etatu</label>
                <input
                    type="text"
                    name="NAZWA"
                    class="form-control<?= $fieldErrors['NAZWA'] ? ' is-invalid' : '' ?>"
                    value="<?= h($form['NAZWA']) ?>"
                >
                <?php if ($fieldErrors['NAZWA']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['NAZWA']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-3">
                <label class="form-label">Płaca minimalna</label>
                <input
                    type="number"
                    step="0.01"
                    name="PLACA_OD"
                    class="form-control<?= $fieldErrors['PLACA_OD'] ? ' is-invalid' : '' ?>"
                    value="<?= h($form['PLACA_OD']) ?>"
                >
                <?php if ($fieldErrors['PLACA_OD']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['PLACA_OD']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-3">
                <label class="form-label">Płaca maksymalna</label>
                <input
                    type="number"
                    step="0.01"
                    name="PLACA_DO"
                    class="form-control<?= $fieldErrors['PLACA_DO'] ? ' is-invalid' : '' ?>"
                    value="<?= h($form['PLACA_DO']) ?>"
                >
                <?php if ($fieldErrors['PLACA_DO']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['PLACA_DO']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-success">Zapisz</button>
                <a href="etaty.php" class="btn btn-secondary">Wróć</a>
            </div>
        </div>
    </form>

    <div id="ajax-feedback" class="mt-3"></div>

    <script>
    (function(){
      var form = document.querySelector('form.ajax-form');
      if (!form) return;
      var feedback = document.getElementById('ajax-feedback');
      form.addEventListener('ajax:success', function (ev) {
        var data = ev.detail || {};
        var msg = data.message || 'OK';
        var html = '<div class="alert alert-success" role="alert">' + msg + '</div>';
        feedback.innerHTML = html;
        try { form.reset(); } catch (e) {}
      });
      form.addEventListener('ajax:error', function (ev) {
        var d = ev.detail || {};
        feedback.innerHTML = '<div class="alert alert-danger">' + (d.error || 'Błąd serwera') + '</div>';
      });
    })();
    </script>
</div>

<style>
#ajax-loader{position:fixed;left:50%;top:20%;transform:translateX(-50%);display:none;z-index:2000}
</style>
<div id="ajax-loader"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
<script src="ajax.js"></script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
// Zakomentowano - nie potrzebne dla AJAX
/*
document.querySelectorAll('input, select, textarea').forEach(function (el) {
    function clearInvalid() {
        el.classList.remove('is-invalid');
        const feedback = el.parentElement.querySelector('.invalid-feedback');
        if (feedback) feedback.style.display = 'none';
    }
    el.addEventListener('input', clearInvalid);
    el.addEventListener('change', clearInvalid);
});
*/
</script>
</body>
</html>