<?php
require __DIR__ . '/db.php';
$pdo = db();
$rows = $pdo->query('SELECT title, description FROM entries ORDER BY start_at DESC LIMIT 5')->fetchAll(PDO::FETCH_ASSOC);
echo count($rows) . " entries\n";
foreach ($rows as $e) {
    echo $e['title'] . " - " . (isset($e['description']) ? substr($e['description'], 0, 80) : '') . "\n";
}
