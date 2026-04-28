<?php
require_once 'database.php';

if (isset($_POST['submit']) && ($_POST['search'] ?? '') !== '') {
    $search = trim((string)$_POST['search']);

    $stmt = $pdo->prepare("SELECT * FROM etaty WHERE NAZWA LIKE :nazwa");
    $stmt->bindValue(':nazwa', '%' . $search . '%', PDO::PARAM_STR);
    $stmt->execute();
} else {
    $stmt = $pdo->query('SELECT * FROM etaty');
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function etatEditLink(array $row): string
{
    $idCandidates = ['ID_ETAT', 'ID_ET', 'ID'];
    foreach ($idCandidates as $idKey) {
        if (array_key_exists($idKey, $row) && $row[$idKey] !== null && $row[$idKey] !== '') {
            return 'edytuj_etat.php?id=' . urlencode((string)$row[$idKey]);
        }
    }

    foreach ($row as $key => $value) {
        if (strpos((string)$key, 'ID_') === 0 && $value !== null && $value !== '') {
            return 'edytuj_etat.php?id=' . urlencode((string)$value);
        }
    }

    return 'edytuj_etat.php?nazwa=' . urlencode((string)($row['NAZWA'] ?? ''));
}

function etatDeletePayload(array $row): array
{
    $idCandidates = ['ID_ETAT', 'ID_ET', 'ID'];
    foreach ($idCandidates as $idKey) {
        if (array_key_exists($idKey, $row) && $row[$idKey] !== null && $row[$idKey] !== '') {
            return ['name' => 'id', 'value' => (string)$row[$idKey]];
        }
    }

    foreach ($row as $key => $value) {
        if (strpos((string)$key, 'ID_') === 0 && $value !== null && $value !== '') {
            return ['name' => 'id', 'value' => (string)$value];
        }
    }

    return ['name' => 'nazwa', 'value' => (string)($row['NAZWA'] ?? '')];
}

$flashDeleted = isset($_GET['deleted']) && $_GET['deleted'] === '1';
$flashError = (string)($_GET['error'] ?? '');
?>
<!doctype html>
<html lang="pl" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>Etaty</title>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" aria-current="page" href="index.php">Pracownicy</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="etaty.php">Etaty</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="zespoly.php">Zespoły</a>
                </li>
            </ul>
        </div>
    </div>
</nav>

<div class="container">
    <form action="" method="post">
        <div class="row my-5">
            <div class="col-md-4">
                <input type="text" class="form-control" name="search" />
            </div>
            <div class="col-md-6 text-left">
                <input type="submit" class="btn btn-primary" name="submit" value="Szukaj" />
            </div>
            <div class="col-md-2 text-end">
                <a class="btn btn-success" href="Dodaj_etat.php" role="button">Dodaj Etat</a>
            </div>
        </div>
    </form>

    <?php /* Zakomentowano flash alerts dla AJAX */ ?>
    <?php /* if ($flashDeleted): ?>
        <div class="alert alert-success">Etat został usunięty.</div>
    <?php endif; ?>

    <?php if ($flashError === 'used'): ?>
        <div class="alert alert-warning">Nie można usunąć etatu, ponieważ jest przypisany do pracowników.</div>
    <?php elseif ($flashError === 'missing'): ?>
        <div class="alert alert-warning">Nie wybrano etatu do usunięcia.</div>
    <?php elseif ($flashError === 'failed'): ?>
        <div class="alert alert-danger">Nie udało się usunąć etatu.</div>
    <?php endif; */ ?>

    <div class="row">
        <div class="col-12">
            <table class="table">
                <thead>
                <tr>
                    <th>Nazwa Etatu</th>
                    <th>Płaca od</th>
                    <th>Płaca do</th>
                    <th>Akcje</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stmt as $row): ?>
                    <?php $deletePayload = etatDeletePayload($row); ?>
                    <?php $deleteFormId = 'delete-etat-' . md5($deletePayload['name'] . ':' . $deletePayload['value']); ?>
                    <tr>
                        <td><?= h($row['NAZWA']) ?></td>
                        <td><?= h($row['PLACA_OD']) ?></td>
                        <td><?= h($row['PLACA_DO']) ?></td>
                        <td>
                            <div class="d-flex gap-2 flex-wrap">
                                <a class="btn btn-sm btn-outline-warning" href="<?= h(etatEditLink($row)) ?>">Edytuj</a>
                                <form method="post" action="usun_etat.php" class="d-inline ajax-form" data-ajax="true" id="<?= h($deleteFormId) ?>">
                                    <input type="hidden" name="<?= h($deletePayload['name']) ?>" value="<?= h($deletePayload['value']) ?>">
                                    <button type="button" class="btn btn-sm btn-outline-danger js-delete-button" data-delete-form="<?= h($deleteFormId) ?>" data-delete-label="tego etatu">Usuń</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Potwierdzenie usuwania</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Zamknij"></button>
            </div>
            <div class="modal-body">
                Czy na pewno chcesz usunąć <span id="deleteConfirmLabel">ten rekord</span>?
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Anuluj</button>
                <button type="button" class="btn btn-danger" id="deleteConfirmSubmit">Usuń</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var modalElement = document.getElementById('deleteConfirmModal');
    var modal = new bootstrap.Modal(modalElement);
    var pendingForm = null;
    var deleteLabel = document.getElementById('deleteConfirmLabel');
    var submitButton = document.getElementById('deleteConfirmSubmit');

    document.querySelectorAll('.js-delete-button').forEach(function (button) {
        button.addEventListener('click', function () {
            pendingForm = document.getElementById(button.dataset.deleteForm);
            deleteLabel.textContent = button.dataset.deleteLabel || 'ten rekord';
            modal.show();
        });
    });

    submitButton.addEventListener('click', function () {
        if (!pendingForm) return;
        if (pendingForm.classList.contains('ajax-form') || pendingForm.dataset.ajax === 'true') {
            pendingForm.requestSubmit();
            modal.hide();
            return;
        }
        pendingForm.submit();
    });  
});
</script>

<!-- AJAX loader and integration -->
<style>
#ajax-loader{position:fixed;left:50%;top:20%;transform:translateX(-50%);display:none;z-index:2000}
</style>
<div id="ajax-loader"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
<script src="ajax.js"></script>
</body>
</html>