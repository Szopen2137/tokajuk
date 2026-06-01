<?php
// includes/config.php
// ---------------------------------------------------------------
//  Konfiguracja połączenia z bazą danych
//  Zmień wartości HOST, USER, PASS i DBNAME na swoje.
// ---------------------------------------------------------------

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // ← zmień
define('DB_PASS', '');            // ← zmień
define('DB_NAME', 'budzet_domowy');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'BudżetDomowy');
define('APP_URL',  'http://localhost/budzet'); // ← zmień na swój URL

// Strefa czasowa
date_default_timezone_set('Europe/Warsaw');

// Tryb debugowania (false na produkcji)
define('DEBUG', false);
