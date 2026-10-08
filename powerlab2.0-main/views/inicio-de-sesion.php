<?php
// Inicio de sesión. El formulario se envía a controllers/login.php
include_once "../controllers/sesion-deportista.php"; // inicia la sesión y aporta e() / mensajes flash

$sesionActiva = $_SESSION["usuario"] ?? null;
$mensajesFlash = obtenerFlash();
$correoPrevio = $_SESSION["login_correo"] ?? "";
unset($_SESSION["login_correo"]);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#15171c">
  <title>Iniciar sesión · PowerLab</title>
  <link rel="icon" href="../imagenes/logopower.png" type="image/png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Oswald:wght@500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../views/css/deportista.css" rel="stylesheet">
  <link href="../views/css/publico.css" rel="stylesheet">
</head>
<body class="pl-publico">

  <div class="pub-auth">
    <!-- Lado visual (escritorio) -->
    <aside class="pub-auth__visual">
      <img src="../imagenes/anuncio2.png" alt="">
      <h2>Vuelve a tu <span>entrenamiento</span></h2>
      <p>Tus rutinas, tus medidas y tu progreso te están esperando.</p>
    </aside>

    <!-- Formulario -->
    <main class="pub-auth__panel">
      <div class="pub-auth__cabecera">
        <a href="index.html"><img src="../imagenes/logopower.png" alt="PowerLab"></a>
        <a href="Elije-tu-rol.html" class="pub-volver"><i class="bi bi-arrow-left me-1"></i>Volver</a>
      </div>

      <div class="pub-auth__caja">
        <h1>Iniciar sesión</h1>
        <p class="pub-auth__sub">Ingresa con tu correo y contraseña.</p>

        <?php if ($sesionActiva): ?>
          <div class="pub-sesion-activa">
            <i class="bi bi-info-circle-fill me-1"></i>
            Ya iniciaste sesión como <strong><?= e($sesionActiva["nombre"] ?? "") ?></strong>.
            <div class="d-flex flex-wrap gap-2 mt-2">
              <a href="<?= e(panelPorRol($sesionActiva["rol"] ?? "")) ?>" class="btn btn-sm btn-pl">Ir a mi panel</a>
              <a href="../controllers/logout.php" class="btn btn-sm btn-light border">Cerrar sesión</a>
            </div>
          </div>
        <?php endif; ?>

        <form action="../controllers/login.php" method="POST" class="needs-validation" novalidate>
          <?= csrfCampo() ?>
          <div class="form-floating mb-3">
            <input required name="correo" type="email" maxlength="50" class="form-control" id="correo" placeholder="nombre@correo.com" autocomplete="email" value="<?= e($correoPrevio) ?>" <?= $correoPrevio ? "" : "autofocus" ?>>
            <label for="correo"><i class="bi bi-envelope me-2"></i>Correo electrónico</label>
            <div class="invalid-feedback">Escribe un correo válido.</div>
          </div>

          <div class="form-floating pub-clave mb-4">
            <input required name="contrasenia" type="password" maxlength="35" class="form-control" id="contrasenia" placeholder="Contraseña" autocomplete="current-password" <?= $correoPrevio ? "autofocus" : "" ?>>
            <label for="contrasenia"><i class="bi bi-lock me-2"></i>Contraseña</label>
            <button type="button" class="pub-clave__ver" data-pub-ver-clave="#contrasenia" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
            <div class="invalid-feedback">Escribe tu contraseña.</div>
          </div>

          <button type="submit" class="btn btn-pl btn-lg w-100" data-no-bloquear="1">
            Ingresar <i class="bi bi-arrow-right ms-1"></i>
          </button>
        </form>

        <div class="pub-separador">¿Aún no tienes cuenta?</div>
        <a href="registro.php" class="btn btn-pl-outline w-100">Crear cuenta</a>
      </div>
    </main>
  </div>

  <div class="pl-toasts" id="plToasts" aria-live="polite" aria-atomic="true"></div>
  <div id="plFlash" hidden data-mensajes="<?= e(json_encode($mensajesFlash)) ?>"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../views/js/deportista.js"></script>
  <script src="../views/js/publico.js"></script>
</body>
</html>
