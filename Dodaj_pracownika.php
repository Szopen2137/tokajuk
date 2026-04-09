<?php
require_once 'database.php';

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isValidDate(string $date): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

function toMoneyOrNull(string $value): ?float
{
    $value = trim(str_replace(',', '.', $value));
    if ($value === '') {
        return null;
    }
    return is_numeric($value) ? (float)$value : null;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$success = false;
$formError = '';
$minimumSalary = 3500;

$fieldErrors = [
    'IMIE' => '',
    'NAZWISKO' => '',
    'ETAT' => '',
    'ID_SZEFA' => '',
    'ZATRUDNIONY' => '',
    'PLACA_POD' => '',
    'PLACA_DOD' => '',
    'ID_ZESP' => ''
];

$form = [
    'IMIE' => '',
    'NAZWISKO' => '',
    'ETAT' => '',
    'ID_SZEFA' => '',
    'ZATRUDNIONY' => date('Y-m-d'),
    'PLACA_POD' => '',
    'PLACA_DOD' => '',
    'ID_ZESP' => ''
];

$etaty = $pdo->query("SELECT NAZWA FROM etaty ORDER BY NAZWA")->fetchAll(PDO::FETCH_ASSOC);
$zespoly = $pdo->query("SELECT ID_ZESP, NAZWA FROM zespoly ORDER BY NAZWA")->fetchAll(PDO::FETCH_ASSOC);
$szefowie = $pdo->query("SELECT ID_PRAC, IMIE, NAZWISKO FROM pracownicy ORDER BY NAZWISKO, IMIE")->fetchAll(PDO::FETCH_ASSOC);

$allowedEtaty = array_column($etaty, 'NAZWA');
$allowedZespolyIds = array_map('strval', array_column($zespoly, 'ID_ZESP'));
$allowedSzefIds = array_map('strval', array_column($szefowie, 'ID_PRAC'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $key => $val) {
        $form[$key] = trim((string)($_POST[$key] ?? ''));
    }

    $placaPod = toMoneyOrNull($form['PLACA_POD']);
    $placaDod = toMoneyOrNull($form['PLACA_DOD']);

    if ($form['IMIE'] === '' || !preg_match('/^[\p{L}\s\-]{2,20}$/u', $form['IMIE'])) {
        $fieldErrors['IMIE'] = 'Imię musi mieć 2-20 znaków (litery, spacja, myślnik).';
    }

    if ($form['NAZWISKO'] === '' || !preg_match('/^[\p{L}\s\-]{2,15}$/u', $form['NAZWISKO'])) {
        $fieldErrors['NAZWISKO'] = 'Nazwisko musi mieć 2-15 znaków (litery, spacja, myślnik).';
    }

    if ($form['ETAT'] === '' || !in_array($form['ETAT'], $allowedEtaty, true)) {
        $fieldErrors['ETAT'] = 'Wybierz poprawny etat z listy.';
    }

    if ($form['ZATRUDNIONY'] === '' || !isValidDate($form['ZATRUDNIONY'])) {
        $fieldErrors['ZATRUDNIONY'] = 'Podaj poprawną datę zatrudnienia (RRRR-MM-DD).';
    } else {
        $hireDate = new DateTime($form['ZATRUDNIONY']);
        $minDate = new DateTime('1950-01-01');
        $today = new DateTime('today');

        if ($hireDate < $minDate) {
            $fieldErrors['ZATRUDNIONY'] = 'Data zatrudnienia nie może być wcześniejsza niż 1950-01-01.';
        } elseif ($hireDate > $today) {
            $fieldErrors['ZATRUDNIONY'] = 'Data zatrudnienia nie może być z przyszłości.';
        }
    }

    if ($placaPod === null || $placaPod < $minimumSalary) {
        $fieldErrors['PLACA_POD'] = 'Płaca podstawowa musi być liczbą większą lub równą 3500.';
    }

    if ($form['PLACA_DOD'] !== '' && $placaDod === null) {
        $fieldErrors['PLACA_DOD'] = 'Płaca dodatkowa musi być poprawną liczbą.';
    } elseif ($placaDod !== null && $placaDod < 0) {
        $fieldErrors['PLACA_DOD'] = 'Płaca dodatkowa nie może być ujemna.';
    }

    if (!$fieldErrors['PLACA_POD'] && $placaDod !== null && $placaDod < $placaPod) {
        $fieldErrors['PLACA_DOD'] = 'Płaca dodatkowa nie może być mniejsza niż płaca podstawowa.';
    }

    if ($form['ID_SZEFA'] !== '' && !in_array($form['ID_SZEFA'], $allowedSzefIds, true)) {
        $fieldErrors['ID_SZEFA'] = 'Wybierz poprawnego szefa z listy.';
    }

    if ($form['ID_ZESP'] !== '' && !in_array($form['ID_ZESP'], $allowedZespolyIds, true)) {
        $fieldErrors['ID_ZESP'] = 'Wybierz poprawny zespół z listy.';
    }

    if (!array_filter($fieldErrors)) {
        $sql = "INSERT INTO pracownicy (IMIE, NAZWISKO, ETAT, ID_SZEFA, ZATRUDNIONY, PLACA_POD, PLACA_DOD, ID_ZESP)
                VALUES (:IMIE, :NAZWISKO, :ETAT, :ID_SZEFA, :ZATRUDNIONY, :PLACA_POD, :PLACA_DOD, :ID_ZESP)";
        $stmt = $pdo->prepare($sql);

        $stmt->bindValue(':IMIE', $form['IMIE'], PDO::PARAM_STR);
        $stmt->bindValue(':NAZWISKO', $form['NAZWISKO'], PDO::PARAM_STR);
        $stmt->bindValue(':ETAT', $form['ETAT'], PDO::PARAM_STR);
        $stmt->bindValue(':ZATRUDNIONY', $form['ZATRUDNIONY'], PDO::PARAM_STR);
        $stmt->bindValue(':PLACA_POD', (string)$placaPod, PDO::PARAM_STR);
        $stmt->bindValue(':PLACA_DOD', $placaDod === null ? null : (string)$placaDod, $placaDod === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $stmt->bindValue(':ID_SZEFA', $form['ID_SZEFA'] === '' ? null : (int)$form['ID_SZEFA'], $form['ID_SZEFA'] === '' ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $stmt->bindValue(':ID_ZESP', $form['ID_ZESP'] === '' ? null : (int)$form['ID_ZESP'], $form['ID_ZESP'] === '' ? PDO::PARAM_NULL : PDO::PARAM_INT);

        $stmt->execute();
        $success = true;

        $form = [
            'IMIE' => '',
            'NAZWISKO' => '',
            'ETAT' => '',
            'ID_SZEFA' => '',
            'ZATRUDNIONY' => date('Y-m-d'),
            'PLACA_POD' => '',
            'PLACA_DOD' => '',
            'ID_ZESP' => ''
        ];
    }
}
?>
<!doctype html>
<html lang="pl" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Dodaj pracownika</title>
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
                    <a class="nav-link active fw-bold" aria-current="page" href="Dodaj_pracownika.php">Dodaj pracownika</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container my-5">
    <h3 class="mb-4">Dodaj pracownika</h3>

    <?php if ($success): ?>
        <div class="alert alert-success">Pracownik został dodany.</div>
    <?php endif; ?>

    <?php if ($formError): ?>
        <div class="alert alert-danger"><?= h($formError) ?></div>
    <?php endif; ?>

    <form method="post" novalidate>
        <div class="row g-3">
            <div class="col-md-6">
                <label class="form-label">Imię</label>
                <input type="text" name="IMIE"
                       class="form-control<?= $fieldErrors['IMIE'] ? ' is-invalid' : '' ?>"
                       value="<?= h($form['IMIE']) ?>">
                <?php if ($fieldErrors['IMIE']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['IMIE']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-6">
                <label class="form-label">Nazwisko</label>
                <input type="text" name="NAZWISKO"
                       class="form-control<?= $fieldErrors['NAZWISKO'] ? ' is-invalid' : '' ?>"
                       value="<?= h($form['NAZWISKO']) ?>">
                <?php if ($fieldErrors['NAZWISKO']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['NAZWISKO']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label class="form-label">Etat</label>
                <select name="ETAT" class="form-select<?= $fieldErrors['ETAT'] ? ' is-invalid' : '' ?>">
                    <option value="">-- wybierz --</option>
                    <?php foreach ($etaty as $etat): ?>
                        <option value="<?= h($etat['NAZWA']) ?>" <?= $form['ETAT'] === $etat['NAZWA'] ? 'selected' : '' ?>>
                            <?= h($etat['NAZWA']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if ($fieldErrors['ETAT']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['ETAT']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-md-4">
                <label class="form-label">Szef</label>
                <select name="ID_SZEFA" class="form-select">
                    <option value="">-- brak --</option>
                    <?php foreach ($szefowie as $szef): ?>
                        <option value="<?= (int)$szef['ID_PRAC'] ?>" <?= (string)$form['ID_SZEFA'] === (string)$szef['ID_PRAC'] ? 'selected' : '' ?>>
                            <?= h($szef['IMIE'] . ' ' . $szef['NAZWISKO']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Zespół</label>
                <select name="ID_ZESP" class="form-select">
                    <option value="">-- brak --</option>
                    <?php foreach ($zespoly as $zespol): ?>
                        <option value="<?= (int)$zespol['ID_ZESP'] ?>" <?= (string)$form['ID_ZESP'] === (string)$zespol['ID_ZESP'] ? 'selected' : '' ?>>
                            <?= h($zespol['NAZWA']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label">Data zatrudnienia</label>
                <input type="date" name="ZATRUDNIONY"
                       class="form-control<?= $fieldErrors['ZATRUDNIONY'] ? ' is-invalid' : '' ?>"
                       value="<?= h($form['ZATRUDNIONY']) ?>">
                <?php if ($fieldErrors['ZATRUDNIONY']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['ZATRUDNIONY']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Płaca podstawowa</label>
                <input
                    type="number"
                    step="0.01"
                    name="PLACA_POD"
                    class="form-control<?= $fieldErrors['PLACA_POD'] ? ' is-invalid' : '' ?>"
                    value="<?= h($form['PLACA_POD']) ?>"
                >
                <?php if ($fieldErrors['PLACA_POD']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['PLACA_POD']) ?></div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="form-label">Płaca dodatkowa</label>
                <input
                    type="number"
                    step="0.01"
                    name="PLACA_DOD"
                    class="form-control<?= $fieldErrors['PLACA_DOD'] ? ' is-invalid' : '' ?>"
                    value="<?= h($form['PLACA_DOD']) ?>"
                >
                <?php if ($fieldErrors['PLACA_DOD']): ?>
                    <div class="invalid-feedback"><?= h($fieldErrors['PLACA_DOD']) ?></div>
                <?php endif; ?>
            </div>

            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-success">Zapisz</button>
                <a href="index.php" class="btn btn-secondary">Wróć</a>
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