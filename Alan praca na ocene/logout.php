<?php
// logout.php
require_once __DIR__ . '/includes/auth.php';
wyloguj();
header('Location: ' . APP_URL . '/index.php');
exit;
