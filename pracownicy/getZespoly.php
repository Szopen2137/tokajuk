<?php
require_once 'database.php';

$stmt = $pdo->query("SELECT * FROM zespoly ORDER BY ID_ZESP");

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function zespolEditLink(array $row): string
{
    return 'edytuj_zespol.php?id=' . urlencode((string)($row['ID_ZESP'] ?? ''));
}

function zespolDeletePayload(array $row): array
{
    return ['name' => 'id', 'value' => (string)($row['ID_ZESP'] ?? '')];
}

foreach ($stmt as $row) {
    $deletePayload = zespolDeletePayload($row);
    $deleteFormId = 'delete-zespol-' . md5($deletePayload['name'] . ':' . $deletePayload['value']);
    echo '<tr>';
    echo '<td>' . h($row['ID_ZESP']) . '</td>';
    echo '<td>' . h($row['NAZWA']) . '</td>';
    echo '<td>' . h($row['ADRES']) . '</td>';
    echo '<td>';
    echo '<div class="d-flex gap-2 flex-wrap">';
    echo '<a class="btn btn-sm btn-outline-warning" href="' . h(zespolEditLink($row)) . '">Edytuj</a>';
    echo '<form method="post" action="usun_zespol.php" class="d-inline ajax-form" data-ajax="true" id="' . h($deleteFormId) . '">';
    echo '<input type="hidden" name="' . h($deletePayload['name']) . '" value="' . h($deletePayload['value']) . '">';
    echo '<button type="button" class="btn btn-sm btn-outline-danger js-delete-button" data-delete-form="' . h($deleteFormId) . '" data-delete-label="tego zespołu">Usuń</button>';
    echo '</form>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
}
?>
