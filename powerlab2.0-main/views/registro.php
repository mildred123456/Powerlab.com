<?php
// Registro de nuevos usuarios
include_once "../controllers/sesion-deportista.php";

$sesionActiva = $_SESSION["usuario"] ?? null;
$mensajesFlash = obtenerFlash();

// Valores enviados antes (si hubo un error) para no obligar a reescribirlos
$previo = $_SESSION["registro_previo"] ?? [];
unset($_SESSION["registro_previo"]);
$generoPrevio = $previo["genero"] ?? "";

$fechaMaxima = date("Y-m-d", strtotime("-12 years"));
$fechaMinima = date("Y-m-d", strtotime("-100 years"));
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#15171c">
  <title>Crear cuenta · PowerLab</title>
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
    <aside class="pub-auth__visual">
      <img src="../imagenes/instructores.png" alt="">
      <h2>¡Únete a <span>PowerLab</span>!</h2>
      <p>Arma tus rutinas, entrena con temporizador y sigue tu progreso. Tu primer mes, sin cláusulas.</p>
    </aside>

    <main class="pub-auth__panel">
      <div class="pub-auth__cabecera">
        <a href="index.html"><img src="../imagenes/logopower.png" alt="PowerLab"></a>
        <a href="Elije-tu-rol.html" class="pub-volver"><i class="bi bi-arrow-left me-1"></i>Volver</a>
      </div>

      <div class="pub-auth__caja pub-auth__caja--ancha">
        <h1>Crea tu cuenta</h1>
        <p class="pub-auth__sub">Completa tus datos. Te tomará menos de un minuto.</p>

        <?php if ($sesionActiva): ?>
          <div class="pub-sesion-activa">
            <i class="bi bi-info-circle-fill me-1"></i>
            Ya iniciaste sesión como <strong><?= e($sesionActiva["nombre"] ?? "") ?></strong>. Cierra sesión para crear otra cuenta.
            <div class="d-flex flex-wrap gap-2 mt-2">
              <a href="<?= e(panelPorRol($sesionActiva["rol"] ?? "")) ?>" class="btn btn-sm btn-pl">Ir a mi panel</a>
              <a href="../controllers/logout.php" class="btn btn-sm btn-light border">Cerrar sesión</a>
            </div>
          </div>
        <?php endif; ?>

        <form method="POST" action="../controllers/registro-publico.php" class="needs-validation" novalidate>
          <?= csrfCampo() ?>

          <div class="row g-3">
            <div class="col-sm-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="nombre" name="nombre" placeholder="Nombre" required minlength="2" maxlength="100" autocomplete="given-name" value="<?= e($previo["nombre"] ?? "") ?>">
                <label for="nombre">Nombre</label>
                <div class="invalid-feedback">Escribe tu nombre (mínimo 2 letras).</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="form-floating">
                <input type="text" class="form-control" id="apellido" name="apellido" placeholder="Apellido" required minlength="2" maxlength="100" autocomplete="family-name" value="<?= e($previo["apellido"] ?? "") ?>">
                <label for="apellido">Apellido</label>
                <div class="invalid-feedback">Escribe tu apellido.</div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="form-floating">
                <input type="date" class="form-control" id="fecha_nacimiento" name="fecha_nacimiento" required min="<?= $fechaMinima ?>" max="<?= $fechaMaxima ?>" value="<?= e($previo["fecha_nacimiento"] ?? "") ?>">
                <label for="fecha_nacimiento">Fecha de nacimiento</label>
                <div class="invalid-feedback">Debes tener al menos 12 años.</div>
              </div>
            </div>
            <div class="col-sm-6">
              <div class="form-floating">
                <select class="form-select" id="genero" name="genero" required>
                  <option value="" <?= $generoPrevio === "" ? "selected" : "" ?> disabled>Selecciona</option>
                  <option value="masculino" <?= $generoPrevio === "masculino" ? "selected" : "" ?>>Masculino</option>
                  <option value="femenino" <?= $generoPrevio === "femenino" ? "selected" : "" ?>>Femenino</option>
                </select>
                <label for="genero">Género</label>
                <div class="invalid-feedback">Selecciona una opción.</div>
              </div>
            </div>

            <div class="col-12">
              <div class="form-floating">
                <input type="email" class="form-control" id="correo" name="correo" placeholder="correo@ejemplo.com" required maxlength="50" autocomplete="email" value="<?= e($previo["correo"] ?? "") ?>">
                <label for="correo"><i class="bi bi-envelope me-2"></i>Correo electrónico</label>
                <div class="invalid-feedback">Escribe un correo válido.</div>
              </div>
            </div>

            <div class="col-sm-6">
              <div class="form-floating pub-clave">
                <input type="password" class="form-control" id="contrasenia" name="contrasenia" placeholder="Contraseña" required minlength="6" maxlength="35" autocomplete="new-password" data-pub-fuerza="#fuerzaClave">
                <label for="contrasenia"><i class="bi bi-lock me-2"></i>Contraseña</label>
                <button type="button" class="pub-clave__ver" data-pub-ver-clave="#contrasenia" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                <div class="invalid-feedback">Mínimo 6 caracteres.</div>
              </div>
              <div class="pub-fuerza" id="fuerzaClave"><div class="pub-fuerza__barra"></div></div>
              <div class="pub-fuerza__texto" id="fuerzaClaveTexto"></div>
            </div>
            <div class="col-sm-6">
              <div class="form-floating pub-clave">
                <input type="password" class="form-control" id="confirmar" name="confirmar" placeholder="Repite la contraseña" required maxlength="35" autocomplete="new-password" data-pub-igual-a="#contrasenia">
                <label for="confirmar">Repite la contraseña</label>
                <button type="button" class="pub-clave__ver" data-pub-ver-clave="#confirmar" aria-label="Mostrar contraseña"><i class="bi bi-eye"></i></button>
                <div class="invalid-feedback">Las contraseñas no coinciden.</div>
              </div>
            </div>
          </div>

          <button class="btn btn-pl btn-lg w-100 mt-4" type="submit" <?= $sesionActiva ? "disabled" : "" ?>>
            <i class="bi bi-person-plus me-2"></i>Crear mi cuenta
          </button>
        </form>

        <div class="pub-separador">¿Ya tienes cuenta?</div>
        <a href="inicio-de-sesion.php" class="btn btn-pl-outline w-100">Iniciar sesión</a>
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
