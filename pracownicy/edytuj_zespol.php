<?php
require_once 'database.php';

function h($value): string
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function isValidZespolName(string $name): bool
{
	return (bool)preg_match('/^[\p{L}\d\s\-]+$/u', $name);
}

function detectAddressColumn(PDO $pdo): string
{
	try {
		$addressColumnCheck = $pdo->query("SHOW COLUMNS FROM zespoly LIKE 'ADRES'")->fetch(PDO::FETCH_ASSOC);
		if ($addressColumnCheck) {
			return 'ADRES';
		}

		$addressColumnAltCheck = $pdo->query("SHOW COLUMNS FROM zespoly LIKE 'ADRES_ZESP'")->fetch(PDO::FETCH_ASSOC);
		if ($addressColumnAltCheck) {
			return 'ADRES_ZESP';
		}
	} catch (PDOException $e) {
		return 'ADRES';
	}

	return 'ADRES';
}

function findZespolIdColumn(PDO $pdo): ?string
{
	try {
		$columns = $pdo->query('DESCRIBE zespoly')->fetchAll(PDO::FETCH_COLUMN, 0);
		if (!$columns) {
			return null;
		}

		$preferred = ['ID_ZESP', 'ID'];
		foreach ($preferred as $candidate) {
			if (in_array($candidate, $columns, true)) {
				return $candidate;
			}
		}

		foreach ($columns as $column) {
			if (strpos((string)$column, 'ID_') === 0) {
				return (string)$column;
			}
		}
	} catch (PDOException $e) {
		return null;
	}

	return null;
}

$currentPage = basename($_SERVER['PHP_SELF']);
$formError = '';
$zespolNotFound = false;
$addressMaxLength = 20;
$nameMaxLength = 20;

$addressColumn = detectAddressColumn($pdo);
$idColumn = findZespolIdColumn($pdo);

$idParam = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
$nazwaParam = trim((string)($_GET['nazwa'] ?? $_POST['original_nazwa'] ?? ''));

$lookupById = $idColumn !== null && $idParam !== '';
$lookupValue = $lookupById ? $idParam : $nazwaParam;

$form = [
	'NAZWA' => '',
	'ADRES' => ''
];

$fieldErrors = [
	'NAZWA' => '',
	'ADRES' => ''
];

$selectedIdValue = $lookupById ? $idParam : '';
$originalNazwa = $nazwaParam;

if ($lookupValue !== '') {
	try {
		if ($lookupById) {
			$stmt = $pdo->prepare("SELECT * FROM zespoly WHERE {$idColumn} = :lookup LIMIT 1");
		} else {
			$stmt = $pdo->prepare('SELECT * FROM zespoly WHERE NAZWA = :lookup LIMIT 1');
		}
		$stmt->bindValue(':lookup', $lookupValue, PDO::PARAM_STR);
		$stmt->execute();
		$zespol = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$zespol) {
			$zespolNotFound = true;
		} else {
			$originalNazwa = (string)$zespol['NAZWA'];
			if ($idColumn !== null && isset($zespol[$idColumn])) {
				$selectedIdValue = (string)$zespol[$idColumn];
			}

			$form = [
				'NAZWA' => (string)$zespol['NAZWA'],
				'ADRES' => (string)($zespol[$addressColumn] ?? '')
			];
		}
	} catch (PDOException $e) {
		$formError = 'Nie udało się pobrać danych zespołu.';
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$zespolNotFound) {
	$form['NAZWA'] = trim((string)($_POST['NAZWA'] ?? ''));
	$form['ADRES'] = trim((string)($_POST['ADRES'] ?? ''));

	$originalNazwa = trim((string)($_POST['original_nazwa'] ?? $originalNazwa));
	$selectedIdValue = trim((string)($_POST['id'] ?? $selectedIdValue));

	if ($form['NAZWA'] === '' || mb_strlen($form['NAZWA']) < 2 || mb_strlen($form['NAZWA']) > $nameMaxLength) {
		$fieldErrors['NAZWA'] = 'Nazwa zespołu musi mieć od 2 do 20 znaków.';
	} elseif (!isValidZespolName($form['NAZWA'])) {
		$fieldErrors['NAZWA'] = 'Nazwa zespołu może zawierać tylko litery, cyfry, spacje i myślnik.';
	}

	if ($form['ADRES'] === '' || mb_strlen($form['ADRES']) < 3 || mb_strlen($form['ADRES']) > $addressMaxLength) {
		$fieldErrors['ADRES'] = 'Adres musi mieć od 3 do 20 znaków.';
	}

	if ($originalNazwa === '' && (!$lookupById || $selectedIdValue === '')) {
		$formError = 'Brak identyfikatora zespołu do edycji.';
	}

	if (!array_filter($fieldErrors) && $formError === '') {
		try {
			if ($lookupById && $selectedIdValue !== '' && $idColumn !== null) {
				$update = $pdo->prepare("\n                    UPDATE zespoly\n                    SET NAZWA = :NAZWA, {$addressColumn} = :ADRES\n                    WHERE {$idColumn} = :lookup\n                    LIMIT 1\n                ");
				$update->bindValue(':lookup', $selectedIdValue, PDO::PARAM_STR);
			} else {
				$update = $pdo->prepare("\n                    UPDATE zespoly\n                    SET NAZWA = :NAZWA, {$addressColumn} = :ADRES\n                    WHERE NAZWA = :lookup\n                    LIMIT 1\n                ");
				$update->bindValue(':lookup', $originalNazwa, PDO::PARAM_STR);
			}

			$update->bindValue(':NAZWA', $form['NAZWA'], PDO::PARAM_STR);
			$update->bindValue(':ADRES', $form['ADRES'], PDO::PARAM_STR);
			$update->execute();

			// Return JSON for AJAX, otherwise redirect
			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['success' => true, 'message' => 'Zmiany zostały zapisane.']);
				exit;
			}

			// Zakomentowano redirect dla AJAX
			// if ($lookupById && $selectedIdValue !== '') {
			//	header('Location: edytuj_zespol.php?id=' . urlencode($selectedIdValue) . '&saved=1');
			// } else {
			//	header('Location: edytuj_zespol.php?nazwa=' . urlencode($form['NAZWA']) . '&saved=1');
			// }
			// exit;
		} catch (PDOException $e) {
			if ((int)$e->getCode() === 23000) {
				$fieldErrors['NAZWA'] = 'Taki zespół już istnieje.';
			} elseif ($e->getCode() === '22001' || ((int)($e->errorInfo[1] ?? 0) === 1406)) {
				if (mb_strlen($form['NAZWA']) > $nameMaxLength) {
					$fieldErrors['NAZWA'] = 'Nazwa zespołu musi mieć od 2 do 20 znaków.';
				}
				if (mb_strlen($form['ADRES']) > $addressMaxLength) {
					$fieldErrors['ADRES'] = 'Adres musi mieć od 3 do 20 znaków.';
				}
				if (!$fieldErrors['NAZWA'] && !$fieldErrors['ADRES']) {
					$formError = 'Nazwa lub adres mają niepoprawny format.';
				}
			} else {
				$formError = 'Nie udało się zapisać zmian.';
			}
		}
	}
}

$isSaved = isset($_GET['saved'])
	&& $_GET['saved'] === '1'
	&& $_SERVER['REQUEST_METHOD'] !== 'POST'
	&& $formError === ''
	&& !array_filter($fieldErrors);
?>
<!doctype html>
<html lang="pl" data-bs-theme="dark">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
	<title>Edytuj zespół</title>
</head>
<body>

<nav class="navbar navbar-expand-lg bg-body-tertiary">
	<div class="container-fluid">
		<div class="collapse navbar-collapse show" id="navbarNav">
			<ul class="navbar-nav">
				<li class="nav-item">
					<a class="nav-link <?= $currentPage === 'index.php' ? 'active fw-bold' : '' ?>" href="index.php">Pracownicy</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?= $currentPage === 'etaty.php' ? 'active fw-bold' : '' ?>" href="etaty.php">Etaty</a>
				</li>
				<li class="nav-item">
					<a class="nav-link <?= $currentPage === 'zespoly.php' ? 'active fw-bold' : '' ?>" href="zespoly.php">Zespoły</a>
				</li>
				<li class="nav-item">
					<a class="nav-link active fw-bold" aria-current="page" href="#">Edytuj zespół</a>
				</li>
			</ul>
		</div>
	</div>
</nav>

<div class="container my-5">
	<h3 class="mb-4">Edytuj zespół</h3>

	<?php /* Zakomentowano alert - feedback przez JS */ ?>
	<?php /* if ($isSaved): ?>
		<div class="alert alert-success">Zmiany zostały zapisane.</div>
	<?php endif; */ ?>

	<?php if ($formError): ?>
		<div class="alert alert-danger"><?= h($formError) ?></div>
	<?php endif; ?>

	<?php if ($lookupValue === ''): ?>
		<div class="alert alert-warning">Nie wybrano zespołu do edycji. Wejdź na stronę z parametrem <code>?id=...</code> lub <code>?nazwa=...</code>.</div>
		<a href="zespoly.php" class="btn btn-secondary">Wróć do listy zespołów</a>
	<?php elseif ($zespolNotFound): ?>
		<div class="alert alert-warning">Nie znaleziono wskazanego zespołu.</div>
		<a href="zespoly.php" class="btn btn-secondary">Wróć do listy zespołów</a>
	<?php else: ?>
		<form method="post" novalidate class="ajax-form" data-ajax="true">
			<?php if ($selectedIdValue !== ''): ?>
				<input type="hidden" name="id" value="<?= h($selectedIdValue) ?>">
			<?php endif; ?>
			<input type="hidden" name="original_nazwa" value="<?= h($originalNazwa) ?>">

			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label">Nazwa zespołu</label>
					<input
						type="text"
						name="NAZWA"
						class="form-control<?= $fieldErrors['NAZWA'] ? ' is-invalid' : '' ?>"
						value="<?= h($form['NAZWA']) ?>"
					>
					<?php if ($fieldErrors['NAZWA']): ?>
						<div class="invalid-feedback"><?= h($fieldErrors['NAZWA']) ?></div>
					<?php endif; ?>
				</div>

				<div class="col-md-6">
					<label class="form-label">Adres</label>
					<input
						type="text"
						name="ADRES"
						class="form-control<?= $fieldErrors['ADRES'] ? ' is-invalid' : '' ?>"
						value="<?= h($form['ADRES']) ?>"
					>
					<?php if ($fieldErrors['ADRES']): ?>
						<div class="invalid-feedback"><?= h($fieldErrors['ADRES']) ?></div>
					<?php endif; ?>
				</div>

				<div class="col-12 mt-3">
					<button type="submit" class="btn btn-success">Zapisz zmiany</button>
					<a href="zespoly.php" class="btn btn-secondary">Wróć</a>
				</div>
			</div>
		</form>
	<?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
<script>
// Zakomentowano - nie potrzebne dla AJAX
/*
document.querySelectorAll('input, select, textarea').forEach(function (el) {
	function clearInvalid() {
		el.classList.remove('is-invalid');
		var feedback = el.parentElement.querySelector('.invalid-feedback');
		if (feedback) feedback.style.display = 'none';
	}
	el.addEventListener('input', clearInvalid);
	el.addEventListener('change', clearInvalid);
});
*/
</script>
</body>
</html>
