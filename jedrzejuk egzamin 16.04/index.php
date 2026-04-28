<?php
require_once 'database.php';



$error = null;
$tables = [];

try {
    $stmt = $pdo->query('SHOW TABLES');
    $tables = $stmt->fetchAll(PDO::FETCH_NUM);
} catch (PDOException $e) {
    $error = $e->getMessage();
}
?>
<!doctype html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Podglad bazy danych</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --surface: #ffffff;
            --primary: #0f4c81;
            --text: #1d2a36;
            --muted: #6a7a8a;
            --border: #dce4ec;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: "Segoe UI", Tahoma, sans-serif;
            background: linear-gradient(180deg, #eef3f9 0%, var(--bg) 100%);
            color: var(--text);
        }

        .container {
            max-width: 1000px;
            margin: 24px auto;
            padding: 0 16px;
            display: grid;
            gap: 16px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 12px;
            padding: 16px;
            box-shadow: 0 10px 20px rgba(28, 51, 74, 0.06);
        }

        h1, h2, h3 {
            margin-top: 0;
            color: var(--primary);
        }

        .muted {
            color: var(--muted);
            margin-bottom: 12px;
        }

        .badge {
            display: inline-block;
            background: #e8f1f9;
            color: #0d3d67;
            padding: 4px 10px;
            border-radius: 999px;
            font-size: 13px;
            margin-right: 8px;
            margin-bottom: 8px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 14px;
        }

        th,
        td {
            border: 1px solid var(--border);
            text-align: left;
            padding: 8px;
            vertical-align: top;
        }

        th {
            background: #f0f5fb;
        }

        .table-block {
            margin-top: 14px;
            padding-top: 14px;
            border-top: 1px dashed var(--border);
        }

        .error {
            color: #a30000;
            background: #ffecec;
            padding: 10px;
            border-radius: 8px;
            border: 1px solid #ffcece;
        }
    </style>
</head>
<body>
<main class="container">
    <section class="card">
        <h1>Podglad bazy danych</h1>
        <p class="muted">Prosty widok tabel i pierwszych 5 rekordow.</p>

        <?php if ($error !== null): ?>
            <div class="error">Blad polaczenia lub zapytania: <?php echo htmlspecialchars($error); ?></div>
        <?php elseif (!$tables): ?>
            <p>Brak tabel w bazie lub brak dostepu.</p>
        <?php else: ?>
            <p>
                <?php foreach ($tables as $t): ?>
                    <span class="badge"><?php echo htmlspecialchars($t[0]); ?></span>
                <?php endforeach; ?>
            </p>

            <?php foreach ($tables as $t): ?>
                <?php
                $rawName = $t[0];
                $tableName = str_replace('`', '``', $rawName);
                $rows = [];
                $rowError = null;

                try {
                    $dataStmt = $pdo->query("SELECT * FROM `{$tableName}` LIMIT 5");
                    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $rowError = $e->getMessage();
                }
                ?>
                <div class="table-block">
                    <h3>Tabela: <?php echo htmlspecialchars($rawName); ?></h3>

                    <?php if ($rowError !== null): ?>
                        <div class="error">Nie mozna pobrac danych: <?php echo htmlspecialchars($rowError); ?></div>
                    <?php elseif (!$rows): ?>
                        <p>Brak rekordow.</p>
                    <?php else: ?>
                        <?php $columns = array_keys($rows[0]); ?>
                        <table>
                            <thead>
                            <tr>
                                <?php foreach ($columns as $col): ?>
                                    <th><?php echo htmlspecialchars($col); ?></th>
                                <?php endforeach; ?>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($rows as $row): ?>
                                <tr>
                                    <?php foreach ($columns as $col): ?>
                                        <td><?php echo htmlspecialchars((string) $row[$col]); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>
</main>
</body>
</html>