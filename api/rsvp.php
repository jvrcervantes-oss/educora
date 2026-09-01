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

$nombre = clean_str($_POST['nombre'] ?? '', 120);
$acompanantes = clean_str($_POST['acompanantes'] ?? '', 600);
$consienteAcompanantes = ($_POST['consiente_acompanantes'] ?? '') === 'si';
$asisteCeremonia = ($_POST['asiste_ceremonia'] ?? '') === 'si';
$asisteBanquete = ($_POST['asiste_banquete'] ?? '') === 'si';
$menu = clean_str($_POST['menu'] ?? 'normal', 20);
$necesitaBus = ($_POST['necesita_bus'] ?? '') === 'si';
$alergias = clean_str($_POST['alergias'] ?? '', 300);
$consienteSalud = ($_POST['consiente_salud'] ?? '') === 'si';
$contacto = clean_str($_POST['contacto'] ?? '', 120);
$cancion = clean_str($_POST['cancion'] ?? '', 150);

if ($nombre === '') {
    json_response(['ok' => false, 'error' => 'Falta el nombre.']);
}
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
if ($acompanantes !== '' && !$consienteAcompanantes) {
    json_response(['ok' => false, 'error' => 'Falta marcar el consentimiento para compartir los datos de tus acompañantes.']);
}
if ($alergias !== '' && !$consienteSalud) {
    json_response(['ok' => false, 'error' => 'Falta marcar el consentimiento para tratar el dato de alergias.']);
}
if (!in_array($menu, ['normal', 'vegetariano', 'infantil'], true)) {
    $menu = 'normal';
}

$record = [
    'id' => bin2hex(random_bytes(8)),
    'fecha_envio' => date('c'),
    'nombre' => $nombre,
    'acompanantes' => $acompanantes,
    'consiente_acompanantes' => $consienteAcompanantes,
    'asiste_ceremonia' => $asisteCeremonia,
    'asiste_banquete' => $asisteBanquete,
    'menu' => $menu,
    'necesita_bus' => $necesitaBus,
    'alergias' => $alergias,
    'consiente_salud' => $consienteSalud,
    'contacto' => $contacto,
    'cancion' => $cancion,
];

$ok = append_record(__DIR__ . '/../guardado/rsvp.json', $record);
if (!$ok) {
    json_response(['ok' => false, 'error' => 'No se ha podido guardar. Inténtalo de nuevo en un momento.'], 500);
}

json_response(['ok' => true]);
