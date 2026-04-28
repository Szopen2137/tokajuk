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

function resolveEtatIdentifier(PDO $pdo): ?array
{
    $idParam = trim((string)($_POST['id'] ?? ''));
    $nazwaParam = trim((string)($_POST['nazwa'] ?? ''));

    if ($idParam !== '') {
        try {
            $columns = $pdo->query('DESCRIBE etaty')->fetchAll(PDO::FETCH_COLUMN, 0);
            foreach (['ID_ETAT', 'ID_ET', 'ID'] as $candidate) {
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

$identifier = resolveEtatIdentifier($pdo);

if ($identifier === null) {
    redirectOrJSON('etaty.php?error=missing', 'Nie wybrano etatu do usunięcia.', $isAjax);
}

try {
    $etatName = $identifier['value'];

    if ($identifier['column'] !== 'NAZWA') {
        $lookup = $pdo->prepare('SELECT NAZWA FROM etaty WHERE ' . $identifier['column'] . ' = :value LIMIT 1');
        $lookup->bindValue(':value', $identifier['value'], PDO::PARAM_STR);
        $lookup->execute();
        $resolvedName = $lookup->fetchColumn();

        if ($resolvedName === false) {
            redirectOrJSON('etaty.php?error=missing', 'Nie znaleziono etatu.', $isAjax);
        }

        $etatName = (string)$resolvedName;
    }

    $check = $pdo->prepare('SELECT COUNT(*) FROM pracownicy WHERE ETAT = :etat');
    $check->bindValue(':etat', $etatName, PDO::PARAM_STR);
    $check->execute();

    if ((int)$check->fetchColumn() > 0) {
        redirectOrJSON('etaty.php?error=used', 'Nie można usunąć etatu, ponieważ jest przypisany do pracowników.', $isAjax);
    }

    $delete = $pdo->prepare('DELETE FROM etaty WHERE ' . $identifier['column'] . ' = :value LIMIT 1');
    $delete->bindValue(':value', $identifier['value'], PDO::PARAM_STR);
    $delete->execute();

    if ($isAjax) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'message' => 'Etat został usunięty.', 'action' => 'delete']);
    } else {
        header('Location: etaty.php?deleted=1');
    }
    exit;
} catch (PDOException $e) {
    redirectOrJSON('etaty.php?error=failed', 'Nie udało się usunąć etatu.', $isAjax);
}