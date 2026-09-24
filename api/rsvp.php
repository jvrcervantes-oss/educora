<?php
declare(strict_types=1);
require __DIR__ . '/_store.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['ok' => false, 'error' => 'Método no permitido'], 405);
}

// Honeypot: campo invisible que un humano nunca rellena. Si viene relleno,
// es un bot — se responde "ok" sin escribir nada, para no delatar el filtro.
if (clean_str($_POST['web'] ?? '') !== '') {
    json_response(['ok' => true]);
}

const MENUS = ['carne', 'pescado', 'vegetariano', 'infantil'];
const MAX_INVITADOS = 15;

// Una persona confirma por todo su grupo (24-sep-2026): llega invitados[i][nombre|tipo|menu|alergias].
// El registro guarda SOLO `invitados` — nada derivado (nombre/acompanantes/menu sueltos) que
// pueda contradecirlo. panel.php lee también los registros viejos, de antes de este cambio.
$raw = $_POST['invitados'] ?? null;
$invitados = [];
if (is_array($raw)) {
    foreach (array_slice(array_values($raw), 0, MAX_INVITADOS) as $g) {
        if (!is_array($g)) continue;
        $nombre = clean_str($g['nombre'] ?? '', 120);
        if ($nombre === '') {
            json_response(['ok' => false, 'error' => 'Falta el nombre de alguno de los invitados.']);
        }
        $tipo = ($g['tipo'] ?? '') === 'nino' ? 'nino' : 'adulto';
        $menu = clean_str($g['menu'] ?? '', 20);
        if (!in_array($menu, MENUS, true)) $menu = $tipo === 'nino' ? 'infantil' : 'carne';
        $invitados[] = [
            'nombre' => $nombre,
            'tipo' => $tipo,
            'menu' => $menu,
            'alergias' => clean_str($g['alergias'] ?? '', 300),
        ];
    }
} else {
    // Formulario anterior (HTML en caché de algún navegador): nombre + acompanantes + menu.
    // Se reconstruye para no perder la confirmación; los acompañantes, sin menú conocido.
    $nombre = clean_str($_POST['nombre'] ?? '', 120);
    if ($nombre !== '') {
        $menu = clean_str($_POST['menu'] ?? '', 20);
        $invitados[] = ['nombre' => $nombre, 'tipo' => 'adulto',
            'menu' => in_array($menu, MENUS, true) ? $menu : '',
            'alergias' => clean_str($_POST['alergias'] ?? '', 300)];
        foreach (preg_split('/\n+/', clean_str($_POST['acompanantes'] ?? '', 600)) as $a) {
            $a = trim($a);
            if ($a !== '' && count($invitados) < MAX_INVITADOS) {
                $invitados[] = ['nombre' => clean_str($a, 120), 'tipo' => '', 'menu' => '', 'alergias' => ''];
            }
        }
    }
}

if (!$invitados) {
    json_response(['ok' => false, 'error' => 'Falta el nombre.']);
}

$contacto = clean_str($_POST['contacto'] ?? '', 120);
if ($contacto === '') {
    json_response(['ok' => false, 'error' => 'Falta un teléfono o email de contacto.']);
}
$esEmail = strpos($contacto, '@') !== false;
if ($esEmail && !filter_var($contacto, FILTER_VALIDATE_EMAIL)) {
    json_response(['ok' => false, 'error' => 'El email no parece válido.']);
}
if (!$esEmail && !preg_match('/^[0-9+\s()-]{6,20}$/', $contacto)) {
    json_response(['ok' => false, 'error' => 'El teléfono no parece válido.']);
}

$record = [
    'id' => bin2hex(random_bytes(8)),
    'fecha_envio' => date('c'),
    'invitados' => $invitados,
    'asiste_ceremonia' => ($_POST['asiste_ceremonia'] ?? '') === 'si',
    'asiste_banquete' => ($_POST['asiste_banquete'] ?? '') === 'si',
    'necesita_bus' => ($_POST['necesita_bus'] ?? '') === 'si',
    'contacto' => $contacto,
    'cancion' => clean_str($_POST['cancion'] ?? '', 150),
];

$ok = append_record(__DIR__ . '/../guardado/rsvp.json', $record);
if (!$ok) {
    json_response(['ok' => false, 'error' => 'No se ha podido guardar. Inténtalo de nuevo en un momento.'], 500);
}

json_response(['ok' => true, 'personas' => count($invitados)]);
