<?php
$allCookiesCleared = false;
$cookieNames = ['test', 'login', 'rola', 'visits', 'kolor', 'user'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['usun_wszystkie'])) {
	foreach ($cookieNames as $cookieName) {
		setcookie($cookieName, '', time() - 3600);
		unset($_COOKIE[$cookieName]);
	}
	$allCookiesCleared = true;
}

if (!$allCookiesCleared) {
	if (!isset($_COOKIE['test'])) {
		setcookie('test', 'Test ciasteczka jest Ok', time() + 604800);
		$_COOKIE['test'] = 'Test ciasteczka jest Ok';
	}

	if (!isset($_COOKIE['login'])) {
		setcookie('login', 'admin', time() + 604800);
		$_COOKIE['login'] = 'admin';
	}

	if (!isset($_COOKIE['rola'])) {
		setcookie('rola', 'user', time() + 604800);
		$_COOKIE['rola'] = 'user';
	}
}

$licznik = 0;
if (!$allCookiesCleared) {
	$licznik = 1;
	if (isset($_COOKIE['visits'])) {
		$licznik = (int) $_COOKIE['visits'] + 1;
	}
	setcookie('visits', (string) $licznik, time() + 604800);
	$_COOKIE['visits'] = (string) $licznik;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (isset($_POST['kolor'])) {
		$selectedColor = trim($_POST['kolor']);
		if ($selectedColor === '') {
			$selectedColor = 'white';
		}
		setcookie('kolor', $selectedColor, time() + 604800);
		$_COOKIE['kolor'] = $selectedColor;
	}

	if (isset($_POST['imie'])) {
		$imie = trim($_POST['imie']);
		if ($imie !== '') {
			setcookie('user', $imie, time() + 604800);
			$_COOKIE['user'] = $imie;
		}
	}

}

$kolor = $_COOKIE['kolor'] ?? 'white';

$backgroundColor = preg_match('/^([a-zA-Z]+|#[0-9a-fA-F]{3}|#[0-9a-fA-F]{6})$/', $kolor)
	? $kolor
	: 'white';
?>

<!DOCTYPE html>
<html lang="pl">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>ciasteczka</title>
	<style>
		body {
			margin: 0;
			font-family: Arial, sans-serif;
			background: #f4f4f4;
			color: #222;
		}

		.container {
			max-width: 700px;
			margin: 60px auto;
			padding: 24px;
			background: #fff;
			border-radius: 12px;
			box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
			text-align: center;
		}

		h1 {
			margin-bottom: 10px;
		}

		p {
			line-height: 1.5;
		}
	</style>
</head>
<body style="background-color: <?php echo htmlspecialchars($backgroundColor, ENT_QUOTES, 'UTF-8'); ?>;">
	<div class="container">
		<h2>Witaj na stronie</h2>
		<p>Prosty przyklad ciasteczek w PHP</p>
		<p>Liczba odwiedzin: <strong><?php echo $licznik; ?></strong></p>
		<?php if (isset($_COOKIE['user'])): ?>
			<p>Witaj ponownie <?php echo htmlspecialchars($_COOKIE['user'], ENT_QUOTES, 'UTF-8'); ?></p>
		<?php else: ?>
			<p>Brak cookie user. Wpisz imie, aby je zapisac.</p>
		<?php endif; ?>
	</div>
	<div class="container">
		<h2>COOKIE</h2>
		<p><?php echo $_COOKIE['test'] ?? 'Cookie test nie istnieje.'; ?></p>
		<?php if (isset($_COOKIE['login']) && isset($_COOKIE['rola'])): ?>
			<p>Login: <?php echo htmlspecialchars($_COOKIE['login'], ENT_QUOTES, 'UTF-8'); ?>, Rola: <?php echo htmlspecialchars($_COOKIE['rola'], ENT_QUOTES, 'UTF-8'); ?></p>
		<?php endif; ?>
	</div>
	<div class="container">
		<h2>Uzytkownik</h2>
		<form method="post">
			<label for="imie">Podaj imie:</label><br><br>
			<input type="text" id="imie" name="imie" placeholder="np. Jan">
			<button type="submit">Zapisz imie</button>
		</form>
		<form method="post" style="margin-top: 12px;">
			<input type="hidden" name="usun_wszystkie" value="1">
			<button type="submit">Usun wszystkie cookie</button>
		</form>
	</div>
	<div class="container">
		<h2>Kolor</h2>
		<form method="post">
			<label for="kolor">Wpisz kolor (np. red, blue, green):</label><br><br>
			<input type="text" id="kolor" name="kolor" value="<?php echo htmlspecialchars($kolor, ENT_QUOTES, 'UTF-8'); ?>">
			<button type="submit">Zapisz kolor</button>
		</form>
		<?php if (isset($_COOKIE['kolor'])): ?>
			<p>Aktualny kolor z cookie: <strong><?php echo htmlspecialchars($kolor, ENT_QUOTES, 'UTF-8'); ?></strong></p>
		<?php else: ?>
			<p>Brak cookie koloru. Uzywany jest domyslny kolor: white.</p>
		<?php endif; ?>
	
	</div>

</body>
</html>
