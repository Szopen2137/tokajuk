<?php
require_once 'database.php';

function h($value): string
{
	return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function toMoneyOrNull(string $value): ?float
{
	$value = trim(str_replace(',', '.', $value));
	if ($value === '') {
		return null;
	}
	return is_numeric($value) ? (float)$value : null;
}

function isValidEtatName(string $name): bool
{
	return (bool)preg_match('/^[\p{L}\d\s\-\.\/,\(\)]+$/u', $name);
}

function findEtatIdColumn(PDO $pdo): ?string
{
	try {
		$columns = $pdo->query('DESCRIBE etaty')->fetchAll(PDO::FETCH_COLUMN, 0);
		if (!$columns) {
			return null;
		}

		$preferred = ['ID_ETAT', 'ID_ET', 'ID'];
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
$fieldErrors = [
	'NAZWA' => '',
	'PLACA_OD' => '',
	'PLACA_DO' => ''
];
$formError = '';
$etatNotFound = false;
$minimumSalary = 3500;

$idColumn = findEtatIdColumn($pdo);
$idParam = trim((string)($_GET['id'] ?? $_POST['id'] ?? ''));
$nazwaParam = trim((string)($_GET['nazwa'] ?? $_POST['original_nazwa'] ?? ''));

$lookupById = $idColumn !== null && $idParam !== '';
$lookupValue = $lookupById ? $idParam : $nazwaParam;

$form = [
	'NAZWA' => '',
	'PLACA_OD' => '',
	'PLACA_DO' => ''
];

$selectedIdValue = $lookupById ? $idParam : '';
$originalNazwa = $nazwaParam;

if ($lookupValue !== '') {
	try {
		if ($lookupById) {
			$stmt = $pdo->prepare("SELECT * FROM etaty WHERE {$idColumn} = :lookup LIMIT 1");
		} else {
			$stmt = $pdo->prepare('SELECT * FROM etaty WHERE NAZWA = :lookup LIMIT 1');
		}
		$stmt->bindValue(':lookup', $lookupValue, PDO::PARAM_STR);
		$stmt->execute();
		$etat = $stmt->fetch(PDO::FETCH_ASSOC);

		if (!$etat) {
			$etatNotFound = true;
		} else {
			$originalNazwa = (string)$etat['NAZWA'];
			if ($idColumn !== null && isset($etat[$idColumn])) {
				$selectedIdValue = (string)$etat[$idColumn];
			}

			$form = [
				'NAZWA' => (string)$etat['NAZWA'],
				'PLACA_OD' => (string)$etat['PLACA_OD'],
				'PLACA_DO' => (string)$etat['PLACA_DO']
			];
		}
	} catch (PDOException $e) {
		$formError = 'Nie udało się pobrać danych etatu.';
	}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$etatNotFound) {
	$form['NAZWA'] = trim((string)($_POST['NAZWA'] ?? ''));
	$form['PLACA_OD'] = trim((string)($_POST['PLACA_OD'] ?? ''));
	$form['PLACA_DO'] = trim((string)($_POST['PLACA_DO'] ?? ''));

	$originalNazwa = trim((string)($_POST['original_nazwa'] ?? $originalNazwa));
	$selectedIdValue = trim((string)($_POST['id'] ?? $selectedIdValue));

	$placaOd = toMoneyOrNull($form['PLACA_OD']);
	$placaDo = toMoneyOrNull($form['PLACA_DO']);

	if ($form['NAZWA'] === '' || mb_strlen($form['NAZWA']) < 2 || mb_strlen($form['NAZWA']) > 30) {
		$fieldErrors['NAZWA'] = 'Nazwa etatu musi mieć od 2 do 30 znaków.';
	} elseif (!isValidEtatName($form['NAZWA'])) {
		$fieldErrors['NAZWA'] = 'Nazwa etatu może zawierać litery, cyfry, spacje oraz znaki: - . / , ( ).';
	}

	if ($placaOd === null || $placaOd < $minimumSalary) {
		$fieldErrors['PLACA_OD'] = 'Płaca minimalna musi być liczbą większą lub równą 3500.';
	}

	if ($placaDo === null || $placaDo < $minimumSalary) {
		$fieldErrors['PLACA_DO'] = 'Płaca maksymalna musi być liczbą większą lub równą 3500.';
	}

	if (!$fieldErrors['PLACA_OD'] && !$fieldErrors['PLACA_DO'] && $placaOd > $placaDo) {
		$fieldErrors['PLACA_DO'] = 'Płaca maksymalna nie może być mniejsza niż minimalna.';
	}

	if ($originalNazwa === '' && (!$lookupById || $selectedIdValue === '')) {
		$formError = 'Brak identyfikatora etatu do edycji.';
	}

	if (!array_filter($fieldErrors) && $formError === '') {
		try {
			if ($lookupById && $selectedIdValue !== '' && $idColumn !== null) {
				$update = $pdo->prepare("\n                    UPDATE etaty\n                    SET NAZWA = :NAZWA, PLACA_OD = :PLACA_OD, PLACA_DO = :PLACA_DO\n                    WHERE {$idColumn} = :lookup\n                    LIMIT 1\n                ");
				$update->bindValue(':lookup', $selectedIdValue, PDO::PARAM_STR);
			} else {
				$update = $pdo->prepare("\n                    UPDATE etaty\n                    SET NAZWA = :NAZWA, PLACA_OD = :PLACA_OD, PLACA_DO = :PLACA_DO\n                    WHERE NAZWA = :lookup\n                    LIMIT 1\n                ");
				$update->bindValue(':lookup', $originalNazwa, PDO::PARAM_STR);
			}

			$update->bindValue(':NAZWA', $form['NAZWA'], PDO::PARAM_STR);
			$update->bindValue(':PLACA_OD', (string)$placaOd, PDO::PARAM_STR);
			$update->bindValue(':PLACA_DO', (string)$placaDo, PDO::PARAM_STR);
			$update->execute();

			// Return JSON for AJAX, otherwise redirect
			if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
				header('Content-Type: application/json; charset=utf-8');
				echo json_encode(['success' => true, 'message' => 'Zmiany zostały zapisane.']);
				exit;
			}

			// Zakomentowano redirect dla AJAX
			// if ($lookupById && $selectedIdValue !== '') {
			//	header('Location: edytuj_etat.php?id=' . urlencode($selectedIdValue) . '&saved=1');
			// } else {
			//	header('Location: edytuj_etat.php?nazwa=' . urlencode($form['NAZWA']) . '&saved=1');
			// }
			// exit;
		} catch (PDOException $e) {
			if ((int)$e->getCode() === 23000) {
				$fieldErrors['NAZWA'] = 'Taki etat już istnieje.';
			} elseif ($e->getCode() === '22001' || ((int)($e->errorInfo[1] ?? 0) === 1406)) {
				$fieldErrors['NAZWA'] = 'Nazwa etatu jest za długa (maksymalnie 30 znaków).';
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
	<title>Edytuj etat</title>
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
					<a class="nav-link active fw-bold" aria-current="page" href="#">Edytuj etat</a>
				</li>
			</ul>
		</div>
	</div>
</nav>

<div class="container my-5">
	<h3 class="mb-4">Edytuj etat</h3>

	<?php /* Zakomentowano alert - feedback przez JS */ ?>
	<?php /* if ($isSaved): ?>
		<div class="alert alert-success">Zmiany zostały zapisane.</div>
	<?php endif; */ ?>

	<?php if ($formError): ?>
		<div class="alert alert-danger"><?= h($formError) ?></div>
	<?php endif; ?>

	<?php if ($lookupValue === ''): ?>
		<div class="alert alert-warning">Nie wybrano etatu do edycji. Wejdź na stronę z parametrem <code>?id=...</code> lub <code>?nazwa=...</code>.</div>
		<a href="etaty.php" class="btn btn-secondary">Wróć do listy etatów</a>
	<?php elseif ($etatNotFound): ?>
		<div class="alert alert-warning">Nie znaleziono wskazanego etatu.</div>
		<a href="etaty.php" class="btn btn-secondary">Wróć do listy etatów</a>
	<?php else: ?>
		<form method="post" novalidate class="ajax-form" data-ajax="true" data-refresh-on-success="true">
			<?php if ($selectedIdValue !== ''): ?>
				<input type="hidden" name="id" value="<?= h($selectedIdValue) ?>">
			<?php endif; ?>
			<input type="hidden" name="original_nazwa" value="<?= h($originalNazwa) ?>">

			<div class="row g-3">
				<div class="col-md-6">
					<label class="form-label">Nazwa etatu</label>
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

				<div class="col-md-3">
					<label class="form-label">Płaca minimalna</label>
					<input
						type="number"
						step="0.01"
						name="PLACA_OD"
						class="form-control<?= $fieldErrors['PLACA_OD'] ? ' is-invalid' : '' ?>"
						value="<?= h($form['PLACA_OD']) ?>"
					>
					<?php if ($fieldErrors['PLACA_OD']): ?>
						<div class="invalid-feedback"><?= h($fieldErrors['PLACA_OD']) ?></div>
					<?php endif; ?>
				</div>

				<div class="col-md-3">
					<label class="form-label">Płaca maksymalna</label>
					<input
						type="number"
						step="0.01"
						name="PLACA_DO"
						class="form-control<?= $fieldErrors['PLACA_DO'] ? ' is-invalid' : '' ?>"
						value="<?= h($form['PLACA_DO']) ?>"
					>
					<?php if ($fieldErrors['PLACA_DO']): ?>
						<div class="invalid-feedback"><?= h($fieldErrors['PLACA_DO']) ?></div>
					<?php endif; ?>
				</div>

				<div class="col-12 mt-3">
					<button type="submit" class="btn btn-success">Zapisz zmiany</button>
					<a href="etaty.php" class="btn btn-secondary">Wróć</a>
				</div>
			</div>
		</form>

		<div id="ajax-feedback" class="mt-3"></div>

		<script>
		(function(){
		  var form = document.querySelector('form.ajax-form');
		  if (!form) return;
		  var feedback = document.getElementById('ajax-feedback');
		  form.addEventListener('ajax:success', function (ev) {
		    var data = ev.detail || {};
		    var msg = data.message || 'OK';
		    var html = '<div class="alert alert-success" role="alert">' + msg + '</div>';
		    feedback.innerHTML = html;
		  });
		  form.addEventListener('ajax:error', function (ev) {
		    var d = ev.detail || {};
		    feedback.innerHTML = '<div class="alert alert-danger">' + (d.error || 'Błąd serwera') + '</div>';
		  });
		})();
		</script>
	<?php endif; ?>
</div>

<style>
#ajax-loader{position:fixed;left:50%;top:20%;transform:translateX(-50%);display:none;z-index:2000}
</style>
<div id="ajax-loader"><div class="spinner-border text-primary" role="status"><span class="visually-hidden">Loading...</span></div></div>
<script src="ajax.js"></script>

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
