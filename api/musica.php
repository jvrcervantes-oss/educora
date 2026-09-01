<?php
declare(strict_types=1);
require __DIR__ . '/_store.php';

$file = __DIR__ . '/../guardado/canciones.json';

$action = $_GET['action'] ?? $_POST['action'] ?? 'add';

if ($action === 'list' && $_SERVER['REQUEST_METHOD'] === 'GET') {
    $canciones = array_map(function ($r) {
        return [
            'id' => $r['id'],
            'artista' => $r['artista'],
            'cancion' => $r['cancion'],
            'votos' => $r['votos'] ?? 0,
        ];
    }, read_records($file));
    json_response(['ok' => true, 'canciones' => $canciones]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
}

if ($action === 'vote') {
    $id = clean_str($_POST['id'] ?? '', 40);
    if ($id === '') {
        json_response(['ok' => false, 'error' => 'Falta el identificador de la canción.']);
    }
    $updated = update_record_by_id($file, $id, function (array &$rec) {
        $rec['votos'] = (int)($rec['votos'] ?? 0) + 1;
    });
    if ($updated === null) {
        json_response(['ok' => false, 'error' => 'No se ha encontrado esa canción.'], 404);
    }
    json_response(['ok' => true, 'votes' => $updated['votos']]);
}

// action=add (por defecto): proponer canción nueva
if (clean_str($_POST['web'] ?? '') !== '') {
    // honeypot de bot: se responde ok sin escribir, sin delatar el filtro
    json_response(['ok' => true]);
}

$artista = clean_str($_POST['artista'] ?? '', 120);
$cancion = clean_str($_POST['cancion'] ?? '', 120);

if ($artista === '' || $cancion === '') {
    json_response(['ok' => false, 'error' => 'Indica artista y canción.']);
}

$record = [
    'id' => bin2hex(random_bytes(8)),
    'fecha' => date('c'),
    'artista' => $artista,
    'cancion' => $cancion,
    'votos' => 0,
];

$ok = append_record($file, $record);
if (!$ok) {
    json_response(['ok' => false, 'error' => 'No se ha podido guardar. Inténtalo de nuevo.'], 500);
}

json_response(['ok' => true]);
