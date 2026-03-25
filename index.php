<?php
require_once 'database.php';

if (isset($_POST['submit']) && ($_POST['search'] ?? '') !== '') {
    $search = trim((string)$_POST['search']);

    $stmt = $pdo->prepare(
        "SELECT p.*, z.NAZWA AS NAZWA_ZESPOLU, sz.IMIE AS IMIE_SZEFA, sz.NAZWISKO AS NAZWISKO_SZEFA
         FROM pracownicy p
         LEFT JOIN zespoly z ON p.ID_ZESP = z.ID_ZESP
         LEFT JOIN pracownicy sz ON p.ID_SZEFA = sz.ID_PRAC
         WHERE p.IMIE LIKE :szukaj
            OR p.NAZWISKO LIKE :szukaj
            OR sz.IMIE LIKE :szukaj
            OR sz.NAZWISKO LIKE :szukaj"
    );
    $stmt->bindValue(':szukaj', '%' . $search . '%', PDO::PARAM_STR);
    $stmt->execute();
} else {
    $stmt = $pdo->query(
        "SELECT p.*, z.NAZWA AS NAZWA_ZESPOLU, sz.IMIE AS IMIE_SZEFA, sz.NAZWISKO AS NAZWISKO_SZEFA
         FROM pracownicy p
         LEFT JOIN zespoly z ON p.ID_ZESP = z.ID_ZESP
         LEFT JOIN pracownicy sz ON p.ID_SZEFA = sz.ID_PRAC"
    );
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <title>pracownicy</title>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="index.php">Pracownicy</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="etaty.php">Etaty</a>
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
            <div class="col-md-2 text-right">
                <a class="btn btn-success" href="Dodaj_pracownika.php" role="button">Dodaj Pracownika</a>
            </div>
        </div>
    </form>

    <div class="row">
        <div class="col-12">
            <table class="table">
                <thead>
                <tr>
                    <th>Id prac</th>
                    <th>Imię</th>
                    <th>Nazwisko</th>
                    <th>Etat</th>
                    <th>Szef</th>
                    <th>Zatrudniony</th>
                    <th>Placa pod</th>
                    <th>Placa dod</th>
                    <th>Nazwa zespołu</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($stmt as $row): ?>
                    <tr>
                        <td><?= h($row['ID_PRAC']) ?></td>
                        <td><?= h($row['IMIE']) ?></td>
                        <td><?= h($row['NAZWISKO']) ?></td>
                        <td><?= h($row['ETAT']) ?></td>
                        <td><?= h($row['IMIE_SZEFA'] ? $row['IMIE_SZEFA'] . ' ' . $row['NAZWISKO_SZEFA'] : '-') ?></td>
                        <td><?= h($row['ZATRUDNIONY']) ?></td>
                        <td><?= h($row['PLACA_POD']) ?></td>
                        <td><?= h($row['PLACA_DOD']) ?></td>
                        <td><?= h($row['NAZWA_ZESPOLU']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>