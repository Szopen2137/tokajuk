<?php
try {
    $conn = new PDO('mysql:host=172.19.0.1;charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (PDOException $e) {
    die("Błąd połączenia: " . $e->getMessage());
}

$conn->exec("CREATE DATABASE IF NOT EXISTS budzet_domowy CHARACTER SET utf8mb4 COLLATE utf8mb4_polish_ci");
$conn->exec("USE budzet_domowy");

$conn->exec("DROP TABLE IF EXISTS wydatki");
$conn->exec("DROP TABLE IF EXISTS kategorie_wydatkow");
$conn->exec("DROP TABLE IF EXISTS uzytkownicy");

$conn->exec("CREATE TABLE uzytkownicy (
    id INT AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(50) NOT NULL UNIQUE,
    haslo VARCHAR(255) NOT NULL,
    imie VARCHAR(100) NOT NULL,
    nazwisko VARCHAR(100) NOT NULL,
    rola ENUM('admin', 'uzytkownik') NOT NULL DEFAULT 'uzytkownik',
    data_dodania DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$conn->exec("CREATE TABLE kategorie_wydatkow (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzytkownik_id INT NOT NULL,
    nazwa VARCHAR(100) NOT NULL,
    typ ENUM('staly', 'zmienny') NOT NULL DEFAULT 'zmienny',
    kwota_domyslna DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id) ON DELETE CASCADE
)");
$conn->exec("CREATE TABLE przychody (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzytkownik_id INT NOT NULL,
    kwota DECIMAL(10,2) NOT NULL,
    data_przychodu DATE NOT NULL,
    opis VARCHAR(255) DEFAULT '',
    data_dodania DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id) ON DELETE CASCADE
)");

$conn->exec("CREATE TABLE wydatki (
    id INT AUTO_INCREMENT PRIMARY KEY,
    uzytkownik_id INT NOT NULL,
    kategoria_id INT NOT NULL,
    kwota DECIMAL(10,2) NOT NULL,
    data_wydatku DATE NOT NULL,
    opis VARCHAR(255) DEFAULT '',
    data_dodania DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (uzytkownik_id) REFERENCES uzytkownicy(id) ON DELETE CASCADE,
    FOREIGN KEY (kategoria_id) REFERENCES kategorie_wydatkow(id) ON DELETE CASCADE
)");

$adminPass = password_hash('admin123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO uzytkownicy (login, haslo, imie, nazwisko, rola) VALUES (?, ?, ?, ?, ?)");
$stmt->execute(['admin', $adminPass, 'Administrator', 'System', 'admin']);

$conn = null;
?>
<!DOCTYPE html>
<html lang="pl">
<head>
    <meta charset="UTF-8">
    <title>Instalacja bazy danych</title>
    <style>
        body { font-family: Arial; display: flex; justify-content: center; align-items: center; min-height: 100vh; background: #f0f2f5; }
        .box { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); text-align: center; }
        .ok { color: #27ae60; font-size: 24px; }
        a { display: inline-block; margin-top: 20px; padding: 12px 30px; background: #3498db; color: white; text-decoration: none; border-radius: 8px; }
        a:hover { background: #2980b9; }
    </style>
</head>
<body>
    <div class="box">
        <div class="ok">✔ Baza danych została utworzona!</div>
        <p>Utworzono tabele: uzytkownicy, kategorie_wydatkow, wydatki</p>
        <p>Dane logowania administratora:<br><strong>login: admin</strong><br><strong>hasło: admin123</strong></p>
        <a href="index.php">Przejdź do aplikacji</a>
    </div>
</body>
</html>
