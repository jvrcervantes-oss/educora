<?php
declare(strict_types=1);
require __DIR__ . '/api/_auth.php';

start_secure_session();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (panel_is_locked_out()) {
        $error = 'Demasiados intentos fallidos. Vuelve a intentarlo en ' . ceil(panel_seconds_until_unlock() / 60) . ' minutos.';
    } else {
        $password = (string) ($_POST['password'] ?? '');
        if (panel_verify_password($password)) {
            panel_record_success();
            session_regenerate_id(true);
            $_SESSION['panel_auth'] = true;
            header('Location: panel');
            exit;
        }
        panel_record_failed_attempt();
        $error = 'Contraseña incorrecta.';
    }
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
</head>
<body>
<main>
  <div class="wrap" style="max-width:420px;padding-top:var(--section);">
    <h1>Panel privado</h1>
    <hr class="divider">
    <form method="post" class="stack">
      <div class="field">
        <label for="password">Contraseña</label>
        <input type="password" id="password" name="password" required autofocus>
      </div>
      <?php if ($error !== ''): ?>
        <div class="form-msg" style="min-height:auto;"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>
      <div class="form-actions">
        <button type="submit" class="btn">Entrar</button>
      </div>
    </form>
  </div>
</main>
</body>
</html>
