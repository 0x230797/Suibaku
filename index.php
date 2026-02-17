<?php
declare(strict_types=1);

$siteName = 'Suibaku';
$siteIntro = 'Suibaku es un espacio comunitario pensado para publicar imágenes, abrir hilos y conversar de manera directa. La idea es mantener una experiencia rápida, anónima y fácil de usar: entras, publicas y participas sin complicaciones.';
$bbsUrl = 'bbs.php';
$manageUrl = 'bbs.php?mode=manage';
$uploadsDir = __DIR__ . '/uploads';
$candidateDataFiles = __DIR__ . '/database/posts.dat';
$candidateBanFiles = __DIR__ . '/database/bans.dat';

$dataFile = '';
if (is_file($candidateDataFiles)) {
	$dataFile = $candidateDataFiles;
}

$posts = [];
if ($dataFile !== '') {
	$lines = file($dataFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
	foreach ($lines as $line) {
		$parts = explode('|', $line, 11);
		if (count($parts) < 10) {
			continue;
		}

		if (count($parts) === 10) {
			[$encname, $encomment, $time, $num, $now, $postiphash, $title, $deleted, $image, $thumb] = $parts;
		} else {
			[$encname, $encomment, $time, $num, $now, $postiphash, $title, $deleted, $image, $thumb, $parent] = $parts;
		}

		$posts[] = [
			'num' => (int)$num,
			'title' => base64_decode($title, true) ?: '',
			'deleted' => (int)$deleted,
			'image' => $image,
			'thumb' => $thumb,
			'now' => (int)$now,
		];
	}
}

usort($posts, fn($a, $b) => $b['now'] <=> $a['now']);

$recentImages = [];
foreach ($posts as $post) {
	if ($post['deleted'] > 0 || $post['image'] === '' || $post['thumb'] === '') {
		continue;
	}

	$thumbPath = __DIR__ . '/uploads/' . $post['thumb'];
	if (!is_file($thumbPath)) {
		continue;
	}

	$recentImages[] = [
		'num' => $post['num'],
		'title' => $post['title'] !== '' ? $post['title'] : 'Sin título',
		'thumbUrl' => 'uploads/' . rawurlencode($post['thumb']),
	];

	if (count($recentImages) >= 4) {
		break;
	}
}

$totalPosts = count($posts);
$totalImages = 0;
foreach ($posts as $post) {
	if ($post['deleted'] === 0 && $post['image'] !== '') {
		$totalImages++;
	}
}

function folderSizeBytes(string $directory): int {
	if (!is_dir($directory)) {
		return 0;
	}

	$total = 0;
	$iterator = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS)
	);

	foreach ($iterator as $file) {
		if ($file->isFile()) {
			$total += $file->getSize();
		}
	}

	return $total;
}

$usedMb = number_format(folderSizeBytes($uploadsDir) / (1024 * 1024), 2);
$totalBans = 0;
if (is_file($candidateBanFiles)) {
	$banLines = file($candidateBanFiles, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
	$totalBans = count($banLines);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title><?= htmlspecialchars($siteName) ?> - Inicio</title>
    <link rel="stylesheet" href="src/style.css">
    <link rel="shortcut icon" href="favicon.png" type="image/x-icon">
</head>
<body>
	<div class="wrapper">
		<div class="logo"><?= htmlspecialchars($siteName) ?></div>

		<section class="welcome">
			<h3>¡Bienvenido a <?= htmlspecialchars($siteName) ?>!</h3>
			<div class="body">
				<p><?= htmlspecialchars($siteIntro) ?></p>
			</div>
		</section>

		<section class="grid">
			<article class="card">
				<h3>Imágenes Recientes</h3>
				<div class="content">
					<?php if (count($recentImages) === 0): ?>
						<p>Aún no hay imágenes recientes.</p>
					<?php else: ?>
						<div class="recent-list">
							<?php foreach ($recentImages as $item): ?>
								<div class="recent-item">
									<a href="<?= htmlspecialchars($bbsUrl) ?>?mode=thread&amp;id=<?= (int)$item['num'] ?>">
										<img src="<?= htmlspecialchars($item['thumbUrl']) ?>" alt="Miniatura <?= (int)$item['num'] ?>">
									</a>
									<div>
										<a href="<?= htmlspecialchars($bbsUrl) ?>?mode=thread&amp;id=<?= (int)$item['num'] ?>">&gt;&gt;/b/<?= (int)$item['num'] ?></a>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>
			</article>

			<article>
				<div class="card">
					<h3>Tablones</h3>
					<div class="content">
						<a class="board-link" href="<?= htmlspecialchars($bbsUrl) ?>">/b/ - Random</a>
						<div class="note">
							Aviso: este sitio es para mayores de edad. Usa el tablón de forma responsable.
						</div>
					</div>
				</div>

				<div class="card" style="margin-top:10px;">
					<h3>Estadísticas</h3>
					<div class="content stats">
						<div>Total de posts: <b><?= (int)$totalPosts ?></b></div>
						<div>Total de imágenes: <b><?= (int)$totalImages ?></b></div>
						<div>Espacio usado: <b><?= htmlspecialchars($usedMb) ?> MB</b></div>
						<div>Total de baneos: <b><?= (int)$totalBans ?></b></div>
					</div>
				</div>
			</article>
		</section>

		<div class="copyright">© <?= date('Y') ?> <?= htmlspecialchars($siteName) ?>. Todos los derechos reservados.</div>
	</div>
</body>
</html>
