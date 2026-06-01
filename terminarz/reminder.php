<?php

declare(strict_types=1);

require __DIR__ . '/db.php';

$db = db();
$now = new DateTimeImmutable('now');
$entries = $db->query(entry_with_category_sql() . " WHERE e.reminder_email IS NOT NULL AND e.reminder_email <> '' AND e.reminder_minutes IS NOT NULL AND e.reminder_sent = 0")->fetchAll();

$sent = 0;
foreach ($entries as $entry) {
    $startAt = new DateTimeImmutable($entry['start_at']);
    $minutesBefore = (int) $entry['reminder_minutes'];
    $reminderTime = $startAt->modify(sprintf('-%d minutes', $minutesBefore));

    if ($now < $reminderTime) {
        continue;
    }

    $subject = 'Przypomnienie o terminie: ' . $entry['title'];
    $body = "Tytuł: {$entry['title']}\nKategoria: {$entry['category_name']}\nStart: {$entry['start_at']}\nKoniec: {$entry['end_at']}\nOpis: {$entry['description']}\n";
    $headers = "From: noreply@localhost\r\nContent-Type: text/plain; charset=UTF-8\r\n";

    if (@mail($entry['reminder_email'], $subject, $body, $headers)) {
        $update = $db->prepare('UPDATE entries SET reminder_sent = 1, updated_at = CURRENT_TIMESTAMP WHERE id = :id');
        $update->execute([':id' => $entry['id']]);
        $sent++;
    }
}

echo "Wysłano przypomnienia: {$sent}\n";
