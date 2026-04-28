<?php
require_once 'database.php';

$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

function redirectOrJSON($url, $message, $isAjax) {
    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => $message, 'redirect' => $url]);
    } else {
        header('Location: ' . $url);
    }
    exit;
}

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
    redirectOrJSON('zespoly.php?error=missing', 'Nie wybrano zespołu do usunięcia.', $isAjax);
}

try {
    $zespId = $identifier['value'];

    if ($identifier['column'] !== 'ID_ZESP' && $identifier['column'] !== 'ID') {
        $lookup = $pdo->prepare('SELECT ID_ZESP FROM zespoly WHERE ' . $identifier['column'] . ' = :value LIMIT 1');
        $lookup->bindValue(':value', $identifier['value'], PDO::PARAM_STR);
        $lookup->execute();
        $resolvedId = $lookup->fetchColumn();

        if ($resolvedId === false) {
            redirectOrJSON('zespoly.php?error=missing', 'Nie znaleziono zespołu.', $isAjax);
        }

        $zespId = (string)$resolvedId;
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM pracownicy WHERE ID_ZESP = :id_zesp');
    $check->bindValue(':id_zesp', $zespId, PDO::PARAM_STR);
    $check->execute();

    if ((int)$check->fetchColumn() > 0) {
        redirectOrJSON('zespoly.php?error=used', 'Nie można usunąć zespołu, ponieważ ma przypisanych pracowników.', $isAjax);
    }

    $delete = $pdo->prepare('DELETE FROM zespoly WHERE ' . $identifier['column'] . ' = :value LIMIT 1');
    $delete->bindValue(':value', $identifier['value'], PDO::PARAM_STR);
    $delete->execute();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Zespół został usunięty.']);
    } else {
        header('Location: zespoly.php?deleted=1');
    }
    exit;
} catch (PDOException $e) {
    redirectOrJSON('zespoly.php?error=failed', 'Nie udało się usunąć zespołu.', $isAjax);
}