<?php
require_once 'database.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$currentPage = basename($_SERVER['PHP_SELF']);
$success = false;
$formError = '';

$fieldErrors = [
    'NAZWA' => '',
    'ADRES' => ''
];

$form = [
    'NAZWA' => '',
    'ADRES' => ''
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $form['NAZWA'] = trim((string)($_POST['NAZWA'] ?? ''));
    $form['ADRES'] = trim((string)($_POST['ADRES'] ?? ''));

    if ($form['NAZWA'] === '' || mb_strlen($form['NAZWA']) < 2 || mb_strlen($form['NAZWA']) > 80) {
        $fieldErrors['NAZWA'] = 'Nazwa zespołu musi mieć od 2 do 80 znaków.';
    }

    if ($form['ADRES'] === '' || mb_strlen($form['ADRES']) < 3 || mb_strlen($form['ADRES']) > 120) {
        $fieldErrors['ADRES'] = 'Adres musi mieć od 3 do 120 znaków.';
    }

    if (!array_filter($fieldErrors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO zespoly (NAZWA, ADRES) VALUES (:NAZWA, :ADRES)");
            $stmt->bindValue(':NAZWA', $form['NAZWA'], PDO::PARAM_STR);
            $stmt->bindValue(':ADRES', $form['ADRES'], PDO::PARAM_STR);
            $stmt->execute();

            $success = true;
            $form = ['NAZWA' => '', 'ADRES' => ''];
        } catch (PDOException $e) {
            if ((int)$e->getCode() === 23000) {
                $fieldErrors['NAZWA'] = 'Taki zespół już istnieje.';
            } else {
                $formError = 'Nie udało się dodać zespołu.';
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
    <title>Dodaj zespół</title>
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
                    <a class="nav-link active fw-bold" aria-current="page" href="dodaj_zespol.php">Dodaj zespół</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h3 class="mb-4">Dodaj zespół</h3>

    <?php if ($success): ?>
        <div class="alert alert-success">Zespół został dodany.</div>
    <?php endif; ?>

    <?php if ($formError): ?>
        <div class="alert alert-danger"><?= h($formError) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Nazwa zespołu</label>
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

            <div class="col-md-6">
                <label class="form-label">Adres</label>
                <input
                    type="text"
                    name="ADRES"
                    class="form-control<?= $fieldErrors['ADRES'] ? ' is-invalid' : '' ?>"
                    value="<?= h($form['ADRES']) ?>"
                >
                <?php if ($fieldErrors['ADRES']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['ADRES']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-success">Zapisz</button>
                <a href="zespoly.php" class="btn btn-secondary">Wróć</a>
            </div>
        </div>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
document.querySelectorAll('input, select, textarea').forEach(function (el) {
    function clearInvalid() {
        el.classList.remove('is-invalid');
        const feedback = el.parentElement.querySelector('.invalid-feedback');
        if (feedback) feedback.style.display = 'none';
    }
    el.addEventListener('input', clearInvalid);
    el.addEventListener('change', clearInvalid);
});
</script>
</body>
</html>