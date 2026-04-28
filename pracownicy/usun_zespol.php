<?php
require_once 'database.php';

function resolveZespolIdentifier(PDO $pdo): ?array
{
    $idParam = trim((string)($_POST['id'] ?? ''));
    $nazwaParam = trim((string)($_POST['nazwa'] ?? ''));

    if ($idParam !== '') {
        try {
            $columns = $pdo->query('DESCRIBE zespoly')->fetchAll(PDO::FETCH_COLUMN, 0);
            foreach (['ID_ZESP', 'ID'] as $candidate) {
                if (in_array($candidate, $columns, true)) {
                    return ['column' => $candidate, 'value' => $idParam];
                }
            }

            foreach ($columns as $column) {
                if (strpos((string)$column, 'ID_') === 0) {
                    return ['column' => (string)$column, 'value' => $idParam];
                }
            }
        } catch (PDOException $e) {
            // fall through to name based deletion
        }
    }

    if ($nazwaParam !== '') {
        return ['column' => 'NAZWA', 'value' => $nazwaParam];
    }

    return null;
}

$identifier = resolveZespolIdentifier($pdo);

if ($identifier === null) {
    header('Location: zespoly.php?error=missing');
    exit;
}

try {
    $zespId = $identifier['value'];

    if ($identifier['column'] !== 'ID_ZESP' && $identifier['column'] !== 'ID') {
        $lookup = $pdo->prepare('SELECT ID_ZESP FROM zespoly WHERE ' . $identifier['column'] . ' = :value LIMIT 1');
        $lookup->bindValue(':value', $identifier['value'], PDO::PARAM_STR);
        $lookup->execute();
        $resolvedId = $lookup->fetchColumn();

        if ($resolvedId === false) {
            header('Location: zespoly.php?error=missing');
            exit;
        }

        $zespId = (string)$resolvedId;
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM pracownicy WHERE ID_ZESP = :id_zesp');
    $check->bindValue(':id_zesp', $zespId, PDO::PARAM_STR);
    $check->execute();

    if ((int)$check->fetchColumn() > 0) {
        header('Location: zespoly.php?error=used');
        exit;
    }

    $delete = $pdo->prepare('DELETE FROM zespoly WHERE ' . $identifier['column'] . ' = :value LIMIT 1');
    $delete->bindValue(':value', $identifier['value'], PDO::PARAM_STR);
    $delete->execute();

    header('Location: zespoly.php?deleted=1');
    exit;
} catch (PDOException $e) {
    header('Location: zespoly.php?error=failed');
    exit;
}