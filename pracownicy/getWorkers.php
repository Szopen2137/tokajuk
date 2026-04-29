<?php
require_once 'database.php';

$stmt = $pdo->query(
    "SELECT p.*, z.NAZWA AS NAZWA_ZESPOLU, sz.IMIE AS IMIE_SZEFA, sz.NAZWISKO AS NAZWISKO_SZEFA
     FROM pracownicy p
     LEFT JOIN zespoly z ON p.ID_ZESP = z.ID_ZESP
     LEFT JOIN pracownicy sz ON p.ID_SZEFA = sz.ID_PRAC
     ORDER BY p.ID_PRAC"
);

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

foreach ($stmt as $row) {
    echo '<tr>';
    echo '<td>' . h($row['ID_PRAC']) . '</td>';
    echo '<td>' . h($row['IMIE']) . '</td>';
    echo '<td>' . h($row['NAZWISKO']) . '</td>';
    echo '<td>' . h($row['ETAT']) . '</td>';
    echo '<td>' . h($row['IMIE_SZEFA'] ? $row['IMIE_SZEFA'] . ' ' . $row['NAZWISKO_SZEFA'] : '-') . '</td>';
    echo '<td>' . h($row['ZATRUDNIONY']) . '</td>';
    echo '<td>' . h($row['PLACA_POD']) . '</td>';
    echo '<td>' . h($row['PLACA_DOD']) . '</td>';
    echo '<td>' . h($row['NAZWA_ZESPOLU']) . '</td>';
    echo '<td>';
    echo '<div class="d-flex gap-2 flex-wrap">';
    echo '<a class="btn btn-sm btn-outline-warning" href="edytuj_pracownika.php?id=' . urlencode((string)$row['ID_PRAC']) . '">Edytuj</a>';
    echo '<form method="post" action="usun_pracownika.php" class="d-inline ajax-form" data-ajax="true" id="delete-pracownik-' . h($row['ID_PRAC']) . '">';
    echo '<input type="hidden" name="id" value="' . h($row['ID_PRAC']) . '">';
    echo '<button type="button" class="btn btn-sm btn-outline-danger js-delete-button" data-delete-form="delete-pracownik-' . h($row['ID_PRAC']) . '" data-delete-label="pracownika">Usuń</button>';
    echo '</form>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
}
?>
