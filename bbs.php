<?php
// Suibaku
// Base de datos: archivos .dat

/*
===============================================================================
[CFG_ESTILO]     Colores, fuentes, textos por defecto
[CFG_REGLAS]     Límites, cooldown, archivos permitidos, paginación
[DATA_IO]        Lectura/escritura de posts (.dat)
[RENDER_POST]    Cómo se dibuja cada post (cabecera, imagen, metadatos)
[RENDER_PORTADA] Estructura de la portada (bbs.html)
[RENDER_HILO]    Estructura de la página de hilo/respuestas
[ADMIN_PANEL]    Panel de administración (login, borrar, banear)
[FLOW_THREAD]    Lógica para ver un hilo específico
[FLOW_POST]      Validación y guardado de nuevo post/respuesta
===============================================================================
*/

// ============ [CFG_ESTILO] CONFIGURACIÓN ESTÉTICA ============
$title = 'Suibaku';
$subtitle = 'Comparte mensajes e imágenes';
$linkcolor = '#4b2e83';
$formsidecolor = '#aaa2d8';
$postbackground = '#d4cff0';
$posternamecolor = '#5b3aa1';
$errortextcolor = '#c00000';
$defaultname = 'Anónimo';
$tripsymbol = '!';
$tripfake = '?';
$deletionphrase = 'Eliminado';

// ============ [CFG_REGLAS] CONFIGURACIÓN DE COMPORTAMIENTO ============
$managepassword = '###Contraseña###123###';
$managecookie = 'bbs_manage';
$lockfile = 'bbs.lock';
$bbsfile = 'bbs.php';
$adminfile = 'admin.php';
$dbdir = 'database/';
$datafile = $dbdir.'posts.dat';
$bansfile = $dbdir.'bans.dat';
$uploadsdir = 'uploads/';
$postsperpage = 15;
$maxpages = 15;
$forcedanonymity = false;
$cooldown = 5;
$namelimit = 20;
$titlelimit = 80;
$commentlimit = 500;
$maxfilesize = 5242880;
$allowedextensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

// ============ INICIALIZACIÓN ============
ini_set('default_charset', 'UTF-8');
ini_set('upload_max_filesize', '5M');
ini_set('post_max_size', '6M');

if (version_compare(PHP_VERSION, '7.4.0', '<')) {
    die('Este BBS requiere PHP 7.4 o superior. Actualmente: PHP ' . PHP_VERSION);
}

// Crear directorio de uploads si no existe
if (!is_dir($uploadsdir)) {
    mkdir($uploadsdir, 0755, true);
}

// Crear directorio de base de datos si no existe
if (!is_dir($dbdir)) {
    mkdir($dbdir, 0755, true);
}

// Migración automática de archivos .dat legacy (si estaban en raíz)
if (file_exists('posts.dat') && !file_exists($datafile)) {
    rename('posts.dat', $datafile);
}

if (file_exists('bans.dat') && !file_exists($bansfile)) {
    rename('bans.dat', $bansfile);
}

// Obtener IP del usuario
$ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['REMOTE_ADDR'];
$hashedip = substr(sha1($ip), 0, 16);

// Verificar si está baneado
if (!file_exists($bansfile)) {
    file_put_contents($bansfile, '');
}
$bannedips = file($bansfile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (in_array($hashedip, $bannedips)) {
    bbserror('Has sido baneado de este BBS.');
}

// ============ [DATA_IO] FUNCIONES DE DATOS ============

function readposts(): array {
    global $datafile;
    if (!file_exists($datafile)) return [];
    
    $lines = file($datafile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $posts = [];
    
    foreach ($lines as $line) {
        $parts = explode('|', $line, 11);
        if (count($parts) < 10) continue;

        if (count($parts) === 10) {
            [$encname, $encomment, $time, $num, $now, $postiphash, $title, $deleted, $image, $thumb] = $parts;
            $parent = '0';
        } else {
            [$encname, $encomment, $time, $num, $now, $postiphash, $title, $deleted, $image, $thumb, $parent] = $parts;
        }
        
        $posts[] = [
            'name' => base64_decode($encname),
            'comment' => base64_decode($encomment),
            'time' => $time,
            'num' => $num,
            'now' => (int)$now,
            'postiphash' => $postiphash,
            'title' => $title,
            'deleted' => $deleted,
            'image' => $image,
            'thumb' => $thumb,
            'parent' => (int)$parent
        ];
    }
    
    return $posts;
}

function saveposts(array $posts): void {
    global $datafile;
    $lines = [];
    
    foreach ($posts as $p) {
        $lines[] = base64_encode($p['name']).'|'.
                   base64_encode($p['comment']).'|'.
                   $p['time'].'|'.
                   $p['num'].'|'.
                   $p['now'].'|'.
                   $p['postiphash'].'|'.
                   $p['title'].'|'.
                   $p['deleted'].'|'.
                   $p['image'].'|'.
                   $p['thumb'].'|'.
                   ((int)($p['parent'] ?? 0));
    }
    
    file_put_contents($datafile, implode(PHP_EOL, $lines).PHP_EOL, LOCK_EX);
}

// ============ [DATA_IO] UTILIDADES DE ID/TIEMPO ============

function nextnum(): int {
    global $datafile;
    if (!file_exists($datafile)) return 1;
    
    $lines = file($datafile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines) return 1;
    
    $lastline = end($lines);
    if (!$lastline) return 1;
    
    $parts = explode('|', $lastline, 10);
    return isset($parts[3]) ? ((int)$parts[3] + 1) : 1;
}

function bbserror(string $message): void {
    global $title, $subtitle, $linkcolor, $errortextcolor, $adminfile;
    
    $pagetitle = $title ?: 'Suibaku';
    $pagesubtitle = $subtitle ? '<h3>'.$subtitle.'</h3>' : '';
    
    echo <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>$pagetitle</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="src/style.css">
    <link rel="shortcut icon" href="favicon.png" type="image/x-icon">
</head>
<body>
    <div style="text-align:left; margin-bottom:6px;">
        [<a href="index.php" style="color:$linkcolor;">Inicio</a>]
        [<a href="$adminfile" style="color:$linkcolor;">Administrar</a>]
    </div>
    <center>
        <h1>$pagetitle</h1>
        $pagesubtitle
    </center>
    <center>
        <span style="color:$errortextcolor;font-weight:bold;">$message</span><br>
        [<a href="bbs.html" style="color:$linkcolor;">Volver</a>]
    </center>
    <center><small>© 2026 Suibaku. Todos los derechos reservados.</small></center>
</body>
</html>
HTML;
    exit;
}

// ============ [RENDER_POST] IMAGEN/MINIATURA Y RENDER DE POST ============

function createThumbnail($source, $destination, $maxWidth = 300, $maxHeight = 300) {
    list($width, $height, $type) = getimagesize($source);
    
    $ratio = min($maxWidth / $width, $maxHeight / $height);
    $newWidth = (int)($width * $ratio);
    $newHeight = (int)($height * $ratio);
    
    $thumb = imagecreatetruecolor($newWidth, $newHeight);
    
    switch ($type) {
        case IMAGETYPE_JPEG:
            $image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $image = imagecreatefrompng($source);
            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            break;
        case IMAGETYPE_GIF:
            $image = imagecreatefromgif($source);
            break;
        case IMAGETYPE_WEBP:
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
    
    imagejpeg($thumb, $destination, 85);
    imagedestroy($thumb);
    imagedestroy($image);
    
    return true;
}

function buildthreads(array $posts): array {
    $roots = [];
    $replies = [];

    foreach ($posts as $post) {
        $num = (int)$post['num'];
        $parent = (int)($post['parent'] ?? 0);

        if ($parent <= 0) {
            $roots[$num] = $post;
            continue;
        }

        $replies[$parent][] = $post;
    }

    $threads = [];

    foreach ($roots as $rootnum => $rootpost) {
        $threadreplies = $replies[$rootnum] ?? [];

        usort($threadreplies, fn($a, $b) => $a['now'] <=> $b['now']);

        $lastactivity = (int)$rootpost['now'];
        foreach ($threadreplies as $reply) {
            if ((int)$reply['now'] > $lastactivity) {
                $lastactivity = (int)$reply['now'];
            }
        }

        $threads[] = [
            'root' => $rootpost,
            'replies' => $threadreplies,
            'lastactivity' => $lastactivity
        ];
    }

    usort($threads, function($a, $b) {
        if ($a['lastactivity'] === $b['lastactivity']) {
            return ((int)$b['root']['num']) <=> ((int)$a['root']['num']);
        }

        return $b['lastactivity'] <=> $a['lastactivity'];
    });

    return $threads;
}

// [RENDER_POST] Línea de metadatos de archivo (nombre, tamaño, dimensiones, formato)
function imageinfohtml(array $post): string {
    global $uploadsdir;

    if ($post['deleted'] > 0 || $post['image'] === '') {
        return '';
    }

    $imagepath = $uploadsdir . $post['image'];
    if (!is_file($imagepath)) {
        return '';
    }

    $sizekb = number_format(filesize($imagepath) / 1024, 1);
    $dimensions = 'N/A';
    $imagesize = @getimagesize($imagepath);
    if (is_array($imagesize) && isset($imagesize[0], $imagesize[1])) {
        $dimensions = $imagesize[0] . 'x' . $imagesize[1];
    }

    $filename = htmlspecialchars($post['image']);
    $imageurl = htmlspecialchars($uploadsdir . $post['image']);

    return '<div class="post-fileinfo"><b>Archivo:</b> <a href="'.$imageurl.'" target="_blank">'.$filename.'</a> ('.$sizekb.' KB, '.$dimensions.')</div>';
}

// [RENDER_POST] Bloque visual individual del post/respuesta
function renderpost(array $post, int $threadnum, bool $compact = false, bool $floatright = false, bool $showreplylink = false, bool $showimageincompact = false): string {
    global $defaultname, $deletionphrase, $posternamecolor, $postbackground, $uploadsdir, $bbsfile;

    $comment = $post['deleted'] > 0 ? "<i>".$deletionphrase."</i>" : nl2br(htmlspecialchars($post['comment']));
    if ($compact && $post['deleted'] == 0) {
        $comment = nl2br(htmlspecialchars(substr($post['comment'], 0, 140))) . (strlen($post['comment']) > 140 ? '...' : '');
    }

    $displayname = htmlspecialchars($post['name']);
    $displaytitle = $post['deleted'] > 0 ? '' : htmlspecialchars(base64_decode($post['title']));
    if ($post['deleted'] > 0) {
        $displayname = $defaultname;
    }

    $numlink = '<a class="post-num" href="'.$bbsfile.'?mode=thread&id='.$threadnum.'">No. '.(int)$post['num'].'</a>';
    $replylink = $showreplylink ? ' <a href="'.$bbsfile.'?mode=thread&id='.$threadnum.'">[Responder]</a>' : '';
    $fileinfo = imageinfohtml($post);

    $imagehtml = '';
    if ((!$compact || $showimageincompact) && $post['image'] !== '' && $post['deleted'] == 0) {
        $imagehtml = '<div class="post-image">
                        <a href="'.$uploadsdir.$post['image'].'" target="_blank">
                            <img src="'.$uploadsdir.$post['thumb'].'" alt="Imagen">
                        </a>
                      </div>';
    }

    $titlehtml = $displaytitle !== '' ? '<span><b>'.$displaytitle.'</b></span>' : '';
    $replyclass = $floatright ? 'post-content reply-float' : '';
    $offsetstyle = ($compact && !$floatright) ? ' margin-left:20px;' : '';
    $backgroundstyle = ((int)($post['parent'] ?? 0) > 0) ? 'background-color:'.$postbackground.';' : '';

    return '<div class="'.$replyclass.'" style="'.$backgroundstyle.$offsetstyle.'">
                '.$fileinfo.'
                '.$imagehtml.'
                <div class="post-header">
                    '.$titlehtml.'
                    <span style="color:'.$posternamecolor.';"><b>'.$displayname.'</b></span>
                    <span>'.$post['time'].'</span>
                    <span>'.$numlink.$replylink.'</span>
                </div>
                <div class="post-comment">'.$comment.'</div>
            </div>';
}

// ============ [RENDER_PORTADA] GENERACIÓN DE PORTADA/PÁGINAS ============

function buildpages(array $posts): void {
    global $postsperpage, $maxpages;

    $threads = buildthreads($posts);
    $threads = array_slice($threads, 0, $postsperpage * $maxpages);

    $totalpages = (int) ceil(count($threads) / $postsperpage);
    if ($totalpages === 0) $totalpages = 1;

    $pages = array_chunk($threads, $postsperpage);
    if (empty($pages)) {
        $pages = [[]];
    }
    
    foreach ($pages as $i => $pagethreads) {
        $html = genpage($pagethreads, $i, $totalpages);
        $filename = $i === 0 ? 'bbs.html' : $i.'.html';
        file_put_contents($filename, $html, LOCK_EX);
    }
    
    saveposts($posts);
}

// [RENDER_PORTADA] HTML de la portada (bbs.html y páginas 1,2,3...)
function genpage(array $threads, int $pagenumber, int $totalpages): string {
    global $title, $subtitle, $linkcolor;
    global $formsidecolor, $bbsfile, $adminfile, $defaultname, $forcedanonymity;
    global $titlelimit;
    
    $pagetitle = $title ?: 'Suibaku';
    $pagesubtitle = $subtitle ? '<h3>'.$subtitle.'</h3>' : '';
    
    $posthtml = '';
    foreach ($threads as $thread) {
        $root = $thread['root'];
        $threadnum = (int)$root['num'];
        $replies = $thread['replies'];

        $posthtml .= renderpost($root, $threadnum, false, false, true);

        if (!empty($replies)) {
            $recentreplies = array_slice($replies, -5);
            foreach ($recentreplies as $reply) {
                $posthtml .= renderpost($reply, $threadnum, true, true, false, true);
            }
        }

        $posthtml .= '<div style="clear:both;"></div>';
        $posthtml .= '<hr>';
    }
    
    $pagehtml = '<table align="left" border="1"><tbody><tr>';
    $prevpage = $pagenumber > 0 ? ($pagenumber === 1 ? 'bbs.html' : ($pagenumber-1).'.html') : '#';
    $pagehtml .= $pagenumber > 0 ? '<td><a href="'.$prevpage.'"><button>Anterior</button></a></td><td>' : '<td>Anterior</td><td>';
    
    for ($i = 0; $i < $totalpages; $i++) {
        $href = $i === 0 ? 'bbs.html' : $i.'.html';
        $pagehtml .= $i === $pagenumber ? '[<b>'.$i.'</b>]&nbsp;' : '[<a href="'.$href.'">'.$i.'</a>]&nbsp;';
    }
    
    if ($pagenumber < $totalpages-1) {
        $nextpage = ($pagenumber+1).'.html';
        $pagehtml .= '</td><td><a href="'.$nextpage.'"><button>Siguiente</button></a></td></tr></tbody></table>';
    } else {
        $pagehtml .= '</td><td>Siguiente</td></tr></tbody></table>';
    }
    
    $namefield = $forcedanonymity ? 
        '<input type="text" name="name" size="28" value="'.$defaultname.'" disabled="disabled">' :
        '<input type="text" name="name" size="28" placeholder="'.$defaultname.'">';
    
        $titlesection = '<tr><td style="background-color:'.$formsidecolor.';">Título</td>
            <td><input type="text" name="title" size="28" maxlength="'.$titlelimit.'" placeholder="Título de la publicación" required></td></tr>';
    
    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>$pagetitle</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="src/style.css">
    <link rel="shortcut icon" href="favicon.png" type="image/x-icon">
    <script src="src/script.js" defer></script>
</head>
<body>
    <div style="text-align:left; margin-bottom:6px;">
        [<a href="index.php" style="color:$linkcolor;">Inicio</a>]
        [<a href="$adminfile" style="color:$linkcolor;">Administrar</a>]
    </div>
    <center>
        <h1>$pagetitle</h1>
        $pagesubtitle
        <form method="POST" action="$bbsfile" enctype="multipart/form-data" style="margin-bottom:0px;">
            <table>
                <tbody>
                    <tr>
                        <td style="background-color:$formsidecolor;">Nombre</td>
                        <td>$namefield</td>
                    </tr>
                    $titlesection
                    <tr>
                        <td style="background-color:$formsidecolor;">Comentario</td>
                        <td><textarea name="com" cols="48" rows="4" required></textarea></td>
                    </tr>
                    <tr>
                        <td style="background-color:$formsidecolor;">Imagen</td>
                        <td>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="width: 100%;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:$formsidecolor;">Publicar</td>
                        <td>
                            <input type="submit" value="Enviar">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </center>
    <hr>
    $posthtml
    $pagehtml
    <br clear="all">
    <center><small>© 2026 Suibaku. Todos los derechos reservados.</small></center>
</body>
</html>
HTML;
}

// ============ [RENDER_HILO] GENERACIÓN DE PÁGINA DE HILO ============

function genthreadpage(array $rootpost, array $replies): string {
    global $title, $subtitle, $linkcolor;
    global $formsidecolor, $bbsfile, $adminfile, $defaultname, $forcedanonymity;

    $threadnum = (int)$rootpost['num'];
    $pagetitle = $title ?: 'Suibaku';
    $pagesubtitle = $subtitle ? '<h3>'.$subtitle.'</h3>' : '';

    $namefield = $forcedanonymity ?
        '<input type="text" name="name" size="28" value="'.$defaultname.'" disabled="disabled">' :
        '<input type="text" name="name" size="28" placeholder="'.$defaultname.'">';

    $roothtml = renderpost($rootpost, $threadnum, false, false, false);
    $replieshtml = '';

    foreach ($replies as $reply) {
        $replieshtml .= renderpost($reply, $threadnum, false, true);
    }

    if ($replieshtml === '') {
        $replieshtml = '<div style="margin:10px 0;">Aún no hay respuestas en esta publicación.</div>';
    }

    return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <title>$pagetitle - Hilo #$threadnum</title>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="src/style.css">
    <link rel="shortcut icon" href="favicon.png" type="image/x-icon">
    <script src="src/script.js" defer></script>
</head>
<body>
    <div style="text-align:left; margin-bottom:6px;">
        [<a href="index.php" style="color:$linkcolor;">Inicio</a>]
        [<a href="$adminfile" style="color:$linkcolor;">Administrar</a>]
    </div>
    <center>
        <h1>$pagetitle</h1>
        $pagesubtitle
        <div>[<a href="bbs.html" style="color:$linkcolor;">Volver al inicio</a>]</div>
        <form method="POST" action="$bbsfile" enctype="multipart/form-data" style="margin-bottom:0px;">
            <input type="hidden" name="replyto" value="$threadnum">
            <table>
                <tbody>
                    <tr>
                        <td style="background-color:$formsidecolor;">Nombre</td>
                        <td>$namefield</td>
                    </tr>
                    <tr>
                        <td style="background-color:$formsidecolor;">Comentario</td>
                        <td><textarea name="com" cols="48" rows="4" required></textarea></td>
                    </tr>
                    <tr>
                        <td style="background-color:$formsidecolor;">Imagen</td>
                        <td>
                            <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp" style="width: 100%;">
                        </td>
                    </tr>
                    <tr>
                        <td style="background-color:$formsidecolor;">Responder</td>
                        <td>
                            <input type="submit" value="Enviar respuesta">
                        </td>
                    </tr>
                </tbody>
            </table>
        </form>
    </center>
    <hr>
    <h3>Publicación original</h3>
    $roothtml
    $replieshtml
    <div style="clear:both;"></div>
    <hr>
    <center><small>© 2026 Suibaku. Todos los derechos reservados.</small></center>
</body>
</html>
HTML;
}

// ============ [ADMIN_PANEL] MODO ADMINISTRACIÓN ============
$mode = $_GET['mode'] ?? '';
if ($mode === 'manage') {
    header('Location: '.$adminfile);
    exit;
}

if (defined('BBS_BOOTSTRAP_ONLY') && BBS_BOOTSTRAP_ONLY) {
    return;
}

// ============ [FLOW_THREAD] VISUALIZACIÓN DE HILO ============

if ($mode === 'thread') {
    $threadid = (int)($_GET['id'] ?? 0);
    if ($threadid <= 0) {
        bbserror('Publicación inválida.');
    }

    $posts = readposts();
    $rootpost = null;
    $replies = [];

    foreach ($posts as $post) {
        $num = (int)$post['num'];
        $parent = (int)($post['parent'] ?? 0);

        if ($num === $threadid && $parent === 0) {
            $rootpost = $post;
            continue;
        }

        if ($parent === $threadid) {
            $replies[] = $post;
        }
    }

    if ($rootpost === null) {
        bbserror('La publicación no existe o no se puede responder.');
    }

    usort($replies, fn($a, $b) => $a['now'] <=> $b['now']);

    $threadhtml = genthreadpage($rootpost, $replies);
    $threadhtml = str_replace('{{REPLY_COUNT}}', (string)count($replies), $threadhtml);
    echo $threadhtml;
    exit;
}

// ============ VERIFICAR BLOQUEO ============
if (file_exists($lockfile)) {
    $hashedcookie = $_COOKIE[$managecookie] ?? '';
    if ($hashedcookie !== sha1($managepassword)) {
        bbserror('Las publicaciones están actualmente bloqueadas.');
    }
}

// ============ [FLOW_POST] PROCESAR NUEVO POST/RESPUESTA ============
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $titleinput = trim($_POST['title'] ?? '');
    $comment = trim($_POST['com'] ?? '');
    $replyto = (int)($_POST['replyto'] ?? 0);
    
    if ($comment === '') bbserror('El comentario no puede estar vacío.');
    if (strlen($name) > $namelimit) bbserror('El nombre no puede tener más de ' . $namelimit . ' caracteres.');
    if (strlen($comment) > $commentlimit) bbserror('El comentario no puede tener más de ' . $commentlimit . ' caracteres.');
    
    $allposts = readposts();
    $replyroot = 0;

    if ($replyto > 0) {
        $targetpost = null;
        foreach ($allposts as $p) {
            if ((int)$p['num'] === $replyto) {
                $targetpost = $p;
                break;
            }
        }

        if ($targetpost === null) {
            bbserror('La publicación a responder no existe.');
        }

        $targetparent = (int)($targetpost['parent'] ?? 0);
        $replyroot = $targetparent > 0 ? $targetparent : (int)$targetpost['num'];

        $titleinput = '';
    } else {
        if ($titleinput === '') bbserror('El título es obligatorio.');
        if (strlen($titleinput) > $titlelimit) bbserror('El título no puede tener más de ' . $titlelimit . ' caracteres.');
    }

    // Procesar tripcode
    $name = preg_replace_callback('/(.)'.$tripsymbol.'(.)/', fn($m) => $m[1].$tripfake.$m[2], $name);
    
    if (strpos($name, '#') !== false) {
        [$displayname, $trip] = explode('#', $name, 2);
        $trip = substr($trip, 0, 255);
        $salt = strtr(preg_replace('/[^\.\/0-9A-Za-z]/', '.', substr($trip.'H.', 1, 2)), ':;<=>?@[\\]^_`', 'A-Ga-f');
        $tripcode = $tripsymbol . substr(crypt($trip, $salt), -10);
        $name = $displayname.$tripcode;
    }
    
    $name = $name ?: $defaultname;
    if ($forcedanonymity) $name = $defaultname;
    
    // Verificar cooldown
    $time = date('d/m/y(D)H:i:s');
    $num = nextnum();
    $now = time();
    
    $posts = array_slice($allposts, -$postsperpage);
    foreach ($posts as $p) {
        if (($now - $p['now']) < $cooldown && $p['postiphash'] === $hashedip) {
            bbserror('Por favor espera '.$cooldown.' segundos antes de publicar de nuevo.');
        }
        if ($p['name'] === $name && $p['comment'] === $comment && $p['postiphash'] === $hashedip && (int)($p['parent'] ?? 0) === $replyroot) {
            bbserror('Ya has dicho eso recientemente.');
        }
    }
    
    // Procesar imagen
    $imagename = '';
    $thumbname = '';
    
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['image'];
        
        if ($file['size'] > $maxfilesize) {
            bbserror('La imagen no puede ser mayor a 5MB.');
        }
        
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedextensions)) {
            bbserror('Formato de imagen no permitido. Usa: JPG, PNG, GIF o WEBP.');
        }
        
        $imagename = $num . '_' . time() . '.' . $ext;
        $thumbname = $num . '_' . time() . '_thumb.jpg';
        
        $imagepath = $uploadsdir . $imagename;
        $thumbpath = $uploadsdir . $thumbname;
        
        if (!move_uploaded_file($file['tmp_name'], $imagepath)) {
            bbserror('Error al subir la imagen.');
        }
        
        if (!createThumbnail($imagepath, $thumbpath)) {
            bbserror('Error al crear miniatura.');
        }
    }
    
    // Guardar post
    $entry = base64_encode($name).'|'.
             base64_encode($comment).'|'.
             $time.'|'.
             $num.'|'.
             $now.'|'.
             $hashedip.'|'.
             base64_encode($titleinput).'|'.
             '0|'.
             $imagename.'|'.
             $thumbname.'|'.
             $replyroot;
    
    file_put_contents($datafile, $entry.PHP_EOL, FILE_APPEND | LOCK_EX);
    buildpages(readposts());
    
    if ($replyroot > 0) {
        header('Location: '.$bbsfile.'?mode=thread&id='.$replyroot);
    } else {
        header('Location: bbs.html');
    }
    exit;
}

// ============ [FLOW_PORTADA] MOSTRAR PÁGINA INICIAL ============
if (file_exists('bbs.html')) {
    header('Location: bbs.html');
    exit;
} else {
    echo genpage([], 0, 1);
}
