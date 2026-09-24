<?php
declare(strict_types=1);
require __DIR__ . '/api/_auth.php';
require __DIR__ . '/api/_store.php';

require_panel_auth();

$rsvps = array_reverse(read_records(__DIR__ . '/guardado/rsvp.json'));
$canciones = read_records(__DIR__ . '/guardado/canciones.json');
usort($canciones, function ($a, $b) { return ($b['votos'] ?? 0) <=> ($a['votos'] ?? 0); });

// Una respuesta = un grupo (desde 24-sep-2026 una persona confirma por su familia).
// personas() es la ÚNICA forma de leer quién viene: registros nuevos (`invitados`) y
// viejos (nombre + acompanantes en texto, sin menú por persona). En los viejos, lo
// que no se sabe se queda en "sin indicar": nunca se inventa carne ni adulto.
const MENUS_OK = ['carne', 'pescado', 'vegetariano', 'infantil'];
function personas(array $r): array {
    if (isset($r['invitados']) && is_array($r['invitados'])) {
        return array_map(fn($g) => [
            'nombre' => (string) ($g['nombre'] ?? ''),
            'tipo' => in_array($g['tipo'] ?? '', ['adulto', 'nino'], true) ? $g['tipo'] : '',
            'menu' => in_array($g['menu'] ?? '', MENUS_OK, true) ? $g['menu'] : '',
            'alergias' => (string) ($g['alergias'] ?? ''),
        ], array_values(array_filter($r['invitados'], 'is_array')));
    }
    $menu = in_array($r['menu'] ?? '', MENUS_OK, true) ? $r['menu'] : '';
    $out = [['nombre' => (string) ($r['nombre'] ?? ''), 'tipo' => '', 'menu' => $menu, 'alergias' => (string) ($r['alergias'] ?? '')]];
    foreach (preg_split('/\n+/', (string) ($r['acompanantes'] ?? '')) as $a) {
        if (trim($a) !== '') $out[] = ['nombre' => trim($a), 'tipo' => '', 'menu' => '', 'alergias' => ''];
    }
    return $out;
}
function clave_nombre(string $n): string {
    $n = mb_strtolower(trim((string) preg_replace('/\s+/', ' ', $n)), 'UTF-8');
    return strtr($n, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);
}

$totalRespuestas = count($rsvps);
$st = ['personas' => 0, 'adultos' => 0, 'ninos' => 0, 'ceremonia' => 0, 'banquete' => 0, 'bus' => 0];
// Los menús solo cuentan si el grupo va al banquete (si no, se infla el catering)
$menus = ['carne' => 0, 'pescado' => 0, 'vegetariano' => 0, 'infantil' => 0, '' => 0];
$vistos = [];   // nombre normalizado => [índice de respuesta => true]
foreach ($rsvps as $idx => $r) {
    $ps = personas($r);
    $n = count($ps);
    $st['personas'] += $n;
    foreach ($ps as $p) {
        if ($p['tipo'] === 'adulto') $st['adultos']++;
        if ($p['tipo'] === 'nino') $st['ninos']++;
        if (!empty($r['asiste_banquete'])) $menus[$p['menu']]++;
        $k = clave_nombre($p['nombre']);
        if ($k !== '') $vistos[$k][$idx] = true;
    }
    if (!empty($r['asiste_ceremonia'])) $st['ceremonia'] += $n;
    if (!empty($r['asiste_banquete'])) $st['banquete'] += $n;
    if (!empty($r['necesita_bus'])) $st['bus'] += $n;
}
// Nombres que aparecen en más de una respuesta (p. ej. la madre confirma por todos y el hijo aparte)
$repetidos = array_keys(array_filter($vistos, fn($ids) => count($ids) > 1));
$tipoTxt = fn($t) => $t === 'nino' ? 'niño/a' : ($t === 'adulto' ? 'adulto' : '');
function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// Descarga para Excel: UNA FILA POR PERSONA (lo que necesita el catering). `;` y BOM
// UTF-8 porque es lo que el Excel en español abre bien a doble clic. Una celda que
// empieza por = + - @ se neutraliza con ' delante: la escribe un invitado, no la pareja.
if (($_GET['export'] ?? '') === 'csv') {
    $celda = function ($v): string {
        $v = (string) $v;
        return ($v !== '' && strpbrk($v[0], "=+-@\t\r") !== false) ? "'" . $v : $v;
    };
    $sino = fn($v) => !empty($v) ? 'Sí' : 'No';
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="confirmaciones-edu-cora-' . date('Y-m-d') . '.csv"');
    header('Cache-Control: no-store');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['Grupo', 'Nombre', 'Tipo', 'Menú', 'Alergias', 'Ceremonia', 'Banquete', 'Bus', 'Contacto', 'Canción', 'Enviado', 'Nombre repetido'], ';');
    $g = 0;
    foreach (array_reverse($rsvps) as $r) {
        $g++;
        foreach (personas($r) as $p) {
            fputcsv($out, [
                $g,
                $celda($p['nombre']),
                $tipoTxt($p['tipo']),
                $p['menu'] !== '' ? $p['menu'] : 'sin indicar',
                $celda($p['alergias']),
                $sino($r['asiste_ceremonia'] ?? null),
                $sino($r['asiste_banquete'] ?? null),
                $sino($r['necesita_bus'] ?? null),
                $celda($r['contacto'] ?? ''),
                $celda($r['cancion'] ?? ''),
                isset($r['fecha_envio']) ? date('d/m/Y H:i', strtotime($r['fecha_envio'])) : '',
                in_array(clave_nombre($p['nombre']), $repetidos, true) ? 'Sí' : '',
            ], ';');
        }
    }
    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Panel privado — Eduardo &amp; Cora</title>
<meta name="robots" content="noindex, nofollow">
<link rel="stylesheet" href="styles.css?v=61b5f948">
<style>
  table{ width:100%; border-collapse:collapse; font-size:14px; }
  th,td{ text-align:left; padding:10px 12px; border-bottom:1px solid #E5E5E5; vertical-align:top; }
  th{ font-family:var(--body); font-weight:600; color:var(--ink-soft); text-transform:uppercase; font-size:12px; letter-spacing:.04em; }
  .stat-row{ display:flex; flex-wrap:wrap; gap:var(--s3); justify-content:center; margin-bottom:var(--section); }
  .stat{ text-align:center; min-width:120px; }
  .stat b{ display:block; font-family:var(--display); font-size:32px; color:var(--accent); }
  .stat span{ font-size:13px; color:var(--ink-soft); }
  .table-wrap{ overflow-x:auto; margin-bottom:var(--section); }
  .yes{ color: var(--accent); font-weight:500; }
  .no{ color: var(--ink-soft); }
</style>
</head>
<body>

<header class="site-header" style="padding-top:var(--s4);">
  <h1 class="couple-names" style="font-size:56px;font-family:var(--script);color:var(--name-color);border:0;text-align:center;margin:0;">Panel privado</h1>
</header>

<main>
  <div class="wrap">

    <section class="section">
      <div class="stat-row">
        <div class="stat"><b><?= $st['personas'] ?></b><span>personas</span></div>
        <div class="stat"><b><?= $st['adultos'] ?></b><span>adultos</span></div>
        <div class="stat"><b><?= $st['ninos'] ?></b><span>niños/as</span></div>
        <div class="stat"><b><?= $totalRespuestas ?></b><span>respuestas (grupos)</span></div>
        <div class="stat"><b><?= $st['ceremonia'] ?></b><span>van a ceremonia</span></div>
        <div class="stat"><b><?= $st['banquete'] ?></b><span>van a banquete</span></div>
        <div class="stat"><b><?= $st['bus'] ?></b><span>necesitan bus</span></div>
      </div>
      <p style="text-align:center;margin:0 0 8px;font-size:13px;color:var(--ink-soft);">Menús de quienes van al banquete</p>
      <div class="stat-row">
        <div class="stat"><b><?= $menus['carne'] ?></b><span>carne</span></div>
        <div class="stat"><b><?= $menus['pescado'] ?></b><span>pescado</span></div>
        <div class="stat"><b><?= $menus['vegetariano'] ?></b><span>vegetariano</span></div>
        <div class="stat"><b><?= $menus['infantil'] ?></b><span>infantil</span></div>
        <?php if ($menus['']): ?><div class="stat"><b><?= $menus[''] ?></b><span>sin indicar (respuestas antiguas)</span></div><?php endif; ?>
      </div>
    </section>

    <section class="section">
      <h1>Confirmaciones</h1>
      <hr class="divider">
      <p style="text-align:center;margin:0 0 var(--s4);"><a class="btn" href="panel?export=csv">Descargar en Excel</a></p>
      <div class="table-wrap">
        <?php if ($repetidos): ?>
          <p style="margin:0 0 12px;padding:10px 14px;border-radius:12px;background:#FFF4E5;color:#7A4B00;font-size:14px;">Ojo: hay nombres que aparecen en más de una respuesta (marcados con «repetido»). Puede que alguien haya confirmado dos veces.</p>
        <?php endif; ?>
        <table>
          <thead>
            <tr>
              <th>Quién viene</th><th>Ceremonia</th><th>Banquete</th><th>Bus</th><th>Contacto</th><th>Canción</th><th>Enviado</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rsvps): ?>
            <tr><td colspan="7" style="text-align:center;color:var(--ink-soft);font-style:italic;">Todavía no hay confirmaciones.</td></tr>
          <?php endif; ?>
          <?php foreach ($rsvps as $r): ?>
            <tr>
              <td>
                <?php foreach (personas($r) as $p): ?>
                  <div style="margin-bottom:6px;">
                    <b><?= h($p['nombre']) ?></b>
                    <?php if (in_array(clave_nombre($p['nombre']), $repetidos, true)): ?><span style="color:#B45309;"> · repetido</span><?php endif; ?>
                    <?php if ($p['tipo'] === 'nino'): ?><span style="color:var(--ink-soft);"> · niño/a</span><?php endif; ?>
                    <span style="color:var(--ink-soft);"> · <?= h($p['menu'] !== '' ? $p['menu'] : 'menú sin indicar') ?></span>
                    <?php if ($p['alergias'] !== ''): ?><br><span style="color:#9A3412;">Alergias: <?= h($p['alergias']) ?></span><?php endif; ?>
                  </div>
                <?php endforeach; ?>
              </td>
              <td class="<?= !empty($r['asiste_ceremonia']) ? 'yes' : 'no' ?>"><?= !empty($r['asiste_ceremonia']) ? 'Sí' : 'No' ?></td>
              <td class="<?= !empty($r['asiste_banquete']) ? 'yes' : 'no' ?>"><?= !empty($r['asiste_banquete']) ? 'Sí' : 'No' ?></td>
              <td class="<?= !empty($r['necesita_bus']) ? 'yes' : 'no' ?>"><?= !empty($r['necesita_bus']) ? 'Sí' : 'No' ?></td>
              <td><?= h($r['contacto'] ?? '') ?></td>
              <td><?= h($r['cancion'] ?? '') ?></td>
              <td><?= h(isset($r['fecha_envio']) ? date('d/m/Y H:i', strtotime($r['fecha_envio'])) : '') ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="section">
      <h1>Canciones propuestas</h1>
      <hr class="divider">
      <div class="table-wrap">
        <table>
          <thead><tr><th>Canción</th><th>Artista</th><th>Votos</th></tr></thead>
          <tbody>
          <?php if (!$canciones): ?>
            <tr><td colspan="3" style="text-align:center;color:var(--ink-soft);font-style:italic;">Todavía no hay canciones propuestas.</td></tr>
          <?php endif; ?>
          <?php foreach ($canciones as $c): ?>
            <tr>
              <td><?= h($c['cancion'] ?? '') ?></td>
              <td><?= h($c['artista'] ?? '') ?></td>
              <td><?= h($c['votos'] ?? 0) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </section>

    <p style="text-align:center;"><a href="panel-logout" style="color:var(--ink-soft);text-decoration:underline;">Cerrar sesión</a></p>

  </div>
</main>

</body>
</html>
