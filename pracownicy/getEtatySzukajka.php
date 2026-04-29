<?php
require_once 'database.php';

if ($_POST['search'] ?? '') {
    $search = trim((string)$_POST['search']);
    $stmt = $pdo->prepare("SELECT * FROM etaty WHERE NAZWA LIKE :nazwa ORDER BY NAZWA");
    $stmt->bindValue(':nazwa', '%' . $search . '%', PDO::PARAM_STR);
    $stmt->execute();
} else {
    $stmt = $pdo->query("SELECT 1 WHERE 0");
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

$hasResults = false;
foreach ($stmt as $row) {
    $hasResults = true;
    $deletePayload = etatDeletePayload($row);
    $deleteFormId = 'delete-etat-' . md5($deletePayload['name'] . ':' . $deletePayload['value']);
    echo '<tr>';
    echo '<td>' . h($row['NAZWA']) . '</td>';
    echo '<td>' . h($row['PLACA_OD']) . '</td>';
    echo '<td>' . h($row['PLACA_DO']) . '</td>';
    echo '<td>';
    echo '<div class="d-flex gap-2 flex-wrap">';
    echo '<a class="btn btn-sm btn-outline-warning" href="' . h(etatEditLink($row)) . '">Edytuj</a>';
    echo '<form method="post" action="usun_etat.php" class="d-inline ajax-form" data-ajax="true" id="' . h($deleteFormId) . '">';
    echo '<input type="hidden" name="' . h($deletePayload['name']) . '" value="' . h($deletePayload['value']) . '">';
    echo '<button type="button" class="btn btn-sm btn-outline-danger js-delete-button" data-delete-form="' . h($deleteFormId) . '" data-delete-label="tego etatu">Usuń</button>';
    echo '</form>';
    echo '</div>';
    echo '</td>';
    echo '</tr>';
}

if (!$hasResults) {
    echo '<tr><td colspan="4" class="text-center">Brak wyników</td></tr>';
}
?>
