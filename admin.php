<?php
define('BBS_BOOTSTRAP_ONLY', true);
require __DIR__ . '/bbs.php';

$canmanage = false;
$hashedcookie = $_COOKIE[$managecookie] ?? '';

if (isset($_GET['logout']) || ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['logout']))) {
	setcookie($managecookie, '', time() - 3600);
	setcookie($managecookie, '', time() - 3600, '/');
	header('Location: '.$adminfile);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['managepassword'])) {
	if (sha1($_POST['managepassword']) === sha1($managepassword)) {
		setcookie($managecookie, sha1($managepassword), 0);
		$canmanage = true;
	} else {
		bbserror('Contraseña incorrecta.');
	}
} elseif ($hashedcookie === sha1($managepassword)) {
	$canmanage = true;
}

if (!$canmanage) {
	$pagetitle = $title ?: 'Suibaku';
	$pagesubtitle = $subtitle ? '<h3>'.$subtitle.'</h3>' : '';

	echo <<<HTML
<!DOCTYPE html>
<html>
<head>
	<title>$pagetitle - Admin</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="src/style.css">
	<link rel="shortcut icon" href="favicon.png" type="image/x-icon">
</head>
<body>
	<div style="text-align:left; margin-bottom:6px;">
		[<a href="index.php" style="color:$linkcolor;">Inicio</a>]
		[<a href="$adminfile?logout=1" style="color:$linkcolor;">Cerrar sesión</a>]
	</div>
	<center>
		<h1>$pagetitle</h1>
		$pagesubtitle
	</center>
	<hr>
	<center>
		<form method="POST" action="$adminfile" enctype="multipart/form-data" style="margin-bottom:0px;">
			<table>
				<tbody>
					<tr>
						<td style="background-color:$formsidecolor;">Contraseña</td>
						<td>
							<input type="password" name="managepassword">
						</td>
					</tr>
					<tr>
						<td></td>
						<td><input type="submit" value="Entrar"></td>
					</tr>
				</tbody>
			</table>
		</form>
	</center>
	<hr>
	<center><small>© 2026 Suibaku. Todos los derechos reservados.</small></center>
</body>
</html>
HTML;
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['togglelock'])) {
	if (file_exists($lockfile)) {
		unlink($lockfile);
	} else {
		file_put_contents($lockfile, 'Locked');
	}
	$page = (int)($_POST['page'] ?? 0);
	header('Location: '.$adminfile.'?page=' . $page);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['delete']) || isset($_POST['delete_ban']) || isset($_POST['ban']))) {
	$posts = readposts();
	$bans = file($bansfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$page = (int)($_POST['page'] ?? 0);

	$markasdeleted = function (&$posts, $num) {
		foreach ($posts as &$p) {
			if ((int)$p['num'] === $num) {
				$p['deleted'] = 1;
				break;
			}
		}
	};

	if (isset($_POST['delete'])) {
		$num = (int)$_POST['delete'];
		$markasdeleted($posts, $num);
	}

	if (isset($_POST['delete_ban'])) {
		[$numberstring, $hash] = explode('|', $_POST['delete_ban'], 2);
		$num = (int)$numberstring;
		$hash = (string)$hash;

		if ($hash !== '' && !in_array($hash, $bans, true)) {
			file_put_contents($bansfile, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
		}

		$markasdeleted($posts, $num);
	}

	if (isset($_POST['ban'])) {
		$hash = (string)$_POST['ban'];
		if ($hash !== '' && !in_array($hash, $bans, true)) {
			file_put_contents($bansfile, $hash . PHP_EOL, FILE_APPEND | LOCK_EX);
		}
	}

	saveposts(array_values($posts));
	buildpages($posts);
	header('Location: '.$adminfile.'?page=' . $page);
	exit;
}

$posts = array_reverse(readposts());
$allposts = count($posts);
$totalpages = (int) ceil($allposts / $postsperpage);
$pagenumber = (int)($_GET['page'] ?? 0);

if ($pagenumber < 0) $pagenumber = 0;
if ($pagenumber >= $totalpages && $totalpages > 0) $pagenumber = $totalpages - 1;

$pageposts = array_slice($posts, $pagenumber * $postsperpage, $postsperpage);

$locklabel = file_exists($lockfile) ? 'Desbloquear Publicación' : 'Bloquear Publicación';
$lockbutton = '<form method="POST" action="'.$adminfile.'" style="display:inline; margin-right:8px;">'
	.'<input type="hidden" name="togglelock" value="1">'
	.'<input type="hidden" name="page" value="'.$pagenumber.'">'
	.'<button type="submit">'.$locklabel.'</button>'
	.'</form>';

$pagetitle = $title ?: 'Suibaku';
$pagesubtitle = $subtitle ? '<h3>'.$subtitle.'</h3>' : '';

echo <<<HTML
<!DOCTYPE html>
<html>
<head>
	<title>$pagetitle - Panel Admin</title>
	<meta charset="UTF-8">
	<link rel="stylesheet" href="src/style.css">
	<link rel="shortcut icon" href="favicon.png" type="image/x-icon">
</head>
<body>
	<div style="text-align:left; margin-bottom:6px;">
		[<a href="index.php" style="color:$linkcolor;">Inicio</a>]
		[<a href="$adminfile?logout=1" style="color:$linkcolor;">Cerrar sesión</a>]
	</div>
	<center>
		<h1>$pagetitle</h1>
		$pagesubtitle
	</center>
	<hr>
	<center>$lockbutton</center>
	<br>
	<form method="POST" action="$adminfile">
		<input type="hidden" name="page" value="$pagenumber">
		<table border="1" cellpadding="5" style="margin:auto; font-size:11pt;">
			<tr bgcolor="#6080f6">
				<th>#</th>
				<th>Nombre</th>
				<th>Título</th>
				<th>Comentario</th>
				<th>Imagen</th>
				<th>Fecha</th>
				<th>IP Hash</th>
				<th>Acciones</th>
			</tr>
HTML;

foreach ($pageposts as $idx => $p) {
	$num = $p['num'];
	$name = htmlspecialchars($p['name']);
	$titletext = htmlspecialchars(base64_decode($p['title']));
	$titletext = empty($titletext) ? "Sin título" : $titletext;
	$comment = htmlspecialchars(substr($p['comment'], 0, 50)) . (strlen($p['comment']) > 50 ? '...' : '');
	$imageinfo = $p['image'] !== '' ? "<a href='".$uploadsdir.$p['image']."' target='_blank'>Ver</a>" : "N/A";
	$time = $p['time'];
	$posterhash = $p['postiphash'];
	$bg = ($idx % 2) ? "#d6d6f6" : "#f6f6f6";

	echo "<tr bgcolor='$bg'>
			<td>$num</td>
			<td>$name</td>
			<td>$titletext</td>
			<td>$comment</td>
			<td>$imageinfo</td>
			<td>$time</td>
			<td>$posterhash</td>
			<td>";

	if ($p['deleted'] > 0) {
		echo "<i>Post Eliminado</i><br>";
	} else {
		echo "<button type='submit' name='delete' value='".$num."'>Eliminar</button><br>";
	}

	echo "<button type='submit' name='ban' value='$posterhash'>Banear IP</button><br>";

	if ($p['deleted'] == 0) {
		echo "<button type='submit' name='delete_ban' value='".$num."|".$posterhash."'>Eliminar & Banear</button>";
	}

	echo "</td></tr>";
}

echo "</table></form>";

echo '<table align="center" border="1"><tbody><tr>';

if ($pagenumber > 0) {
	$prevpage = $adminfile.'?page='.($pagenumber-1);
	echo '<td><a href="'.$prevpage.'"><button>Anterior</button></a></td><td>';
} else {
	echo '<td>Anterior</td><td>';
}

for ($i = 0; $i < $totalpages; $i++) {
	$href = $adminfile.'?page='.$i;
	echo $i === $pagenumber ? '[<b>'.$i.'</b>]&nbsp;' : '[<a href="'.$href.'">'.$i.'</a>]&nbsp;';
}

if ($pagenumber < $totalpages-1) {
	$nextpage = $adminfile.'?page='.($pagenumber+1);
	echo '</td><td><a href="'.$nextpage.'"><button>Siguiente</button></a></td>';
} else {
	echo '</td><td>Siguiente</td>';
}

echo '</tr></tbody></table>';
echo "<hr><div style='text-align:center;'>[<a href='bbs.html'>Volver</a>]</div>";
echo "<center><small>© 2026 Suibaku. Todos los derechos reservados.</small></center>";
echo "</body></html>";
