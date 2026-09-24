<?php
declare(strict_types=1);
require __DIR__ . '/api/_auth.php';
require __DIR__ . '/api/_store.php';

require_panel_auth();

$rsvps = array_reverse(read_records(__DIR__ . '/guardado/rsvp.json'));
$canciones = read_records(__DIR__ . '/guardado/canciones.json');
usort($canciones, function ($a, $b) { return ($b['votos'] ?? 0) <=> ($a['votos'] ?? 0); });

$totalRespuestas = count($rsvps);
$vanCeremonia = count(array_filter($rsvps, fn($r) => !empty($r['asiste_ceremonia'])));
$vanBanquete = count(array_filter($rsvps, fn($r) => !empty($r['asiste_banquete'])));
$necesitanBus = count(array_filter($rsvps, fn($r) => !empty($r['necesita_bus'])));
// 'normal' = menú de antes del 24-sep (Carne/Pescado aún no existían): se sigue contando
$menus = ['carne' => 0, 'pescado' => 0, 'vegetariano' => 0, 'infantil' => 0, 'normal' => 0];
foreach ($rsvps as $r) {
    $m = $r['menu'] ?? 'normal';
    if (isset($menus[$m])) $menus[$m]++;
}
function h($v): string { return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8'); }

// Descarga de las confirmaciones para abrir en Excel: `;` y BOM UTF-8 porque
// es lo que el Excel en español abre bien a doble clic. Una celda que empieza
// por = + - @ se neutraliza con ' delante: la escribe un invitado, no la pareja.
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
    fputcsv($out, ['Nombre', 'Acompañantes', 'Ceremonia', 'Banquete', 'Menú', 'Bus', 'Alergias', 'Contacto', 'Canción', 'Enviado'], ';');
    foreach (array_reverse($rsvps) as $r) {
        fputcsv($out, [
            $celda($r['nombre'] ?? ''),
            $celda(str_replace(["\r\n", "\n"], ', ', (string) ($r['acompanantes'] ?? ''))),
            $sino($r['asiste_ceremonia'] ?? null),
            $sino($r['asiste_banquete'] ?? null),
            $celda($r['menu'] ?? ''),
            $sino($r['necesita_bus'] ?? null),
            $celda($r['alergias'] ?? ''),
            $celda($r['contacto'] ?? ''),
            $celda($r['cancion'] ?? ''),
            isset($r['fecha_envio']) ? date('d/m/Y H:i', strtotime($r['fecha_envio'])) : '',
        ], ';');
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
<link rel="stylesheet" href="styles.css?v=a1e50487">
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
        <div class="stat"><b><?= $totalRespuestas ?></b><span>respuestas</span></div>
        <div class="stat"><b><?= $vanCeremonia ?></b><span>van a ceremonia</span></div>
        <div class="stat"><b><?= $vanBanquete ?></b><span>van a banquete</span></div>
        <div class="stat"><b><?= $necesitanBus ?></b><span>necesitan bus</span></div>
        <div class="stat"><b><?= $menus['carne'] ?></b><span>carne</span></div>
        <div class="stat"><b><?= $menus['pescado'] ?></b><span>pescado</span></div>
        <div class="stat"><b><?= $menus['vegetariano'] ?></b><span>vegetariano</span></div>
        <div class="stat"><b><?= $menus['infantil'] ?></b><span>infantil</span></div>
        <?php if ($menus['normal']): ?><div class="stat"><b><?= $menus['normal'] ?></b><span>normal (antiguo)</span></div><?php endif; ?>
      </div>
    </section>

    <section class="section">
      <h1>Confirmaciones</h1>
      <hr class="divider">
      <p style="text-align:center;margin:0 0 var(--s4);"><a class="btn" href="panel?export=csv">Descargar en Excel</a></p>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Nombre</th><th>Acompañantes</th><th>Ceremonia</th><th>Banquete</th>
              <th>Menú</th><th>Bus</th><th>Alergias</th><th>Contacto</th><th>Canción</th><th>Enviado</th>
            </tr>
          </thead>
          <tbody>
          <?php if (!$rsvps): ?>
            <tr><td colspan="10" style="text-align:center;color:var(--ink-soft);font-style:italic;">Todavía no hay confirmaciones.</td></tr>
          <?php endif; ?>
          <?php foreach ($rsvps as $r): ?>
            <tr>
              <td><?= h($r['nombre'] ?? '') ?></td>
              <td><?= nl2br(h($r['acompanantes'] ?? '')) ?></td>
              <td class="<?= !empty($r['asiste_ceremonia']) ? 'yes' : 'no' ?>"><?= !empty($r['asiste_ceremonia']) ? 'Sí' : 'No' ?></td>
              <td class="<?= !empty($r['asiste_banquete']) ? 'yes' : 'no' ?>"><?= !empty($r['asiste_banquete']) ? 'Sí' : 'No' ?></td>
              <td><?= h($r['menu'] ?? '') ?></td>
              <td class="<?= !empty($r['necesita_bus']) ? 'yes' : 'no' ?>"><?= !empty($r['necesita_bus']) ? 'Sí' : 'No' ?></td>
              <td><?= h($r['alergias'] ?? '') ?></td>
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
