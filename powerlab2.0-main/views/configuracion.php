<?php
// Configuración del deportista: preferencias del Modo entrenamiento y meta semanal
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/preferencias.php";

$pref = (new preferencias())->obtener((int) $usuario["id"]);

// Opciones de los selectores (si el valor guardado no está en la lista, se agrega)
function conValor($opciones, $valor) {
    $opciones[] = $valor;
    $opciones = array_unique($opciones);
    sort($opciones);
    return $opciones;
}
$opcionesDescanso = conValor([30, 45, 60, 75, 90, 120, 150, 180, 240, 300], $pref["descanso_seg"]);
$opcionesHidratacion = conValor([10, 15, 20, 30, 45, 60], $pref["hidratacion_min"]);

function textoSegundos($s) {
    return $s < 60 ? "$s segundos" : (intdiv($s, 60) . " min" . ($s % 60 ? " " . ($s % 60) . " s" : ""));
}

// Interruptor de Bootstrap
function interruptor($nombre, $activo, $titulo, $ayuda) {
    $id = "pref_" . $nombre;
    return '<div class="pl-pref-fila">'
         . '<div><label class="pl-pref-fila__titulo" for="' . $id . '">' . e($titulo) . '</label><div class="pl-pref-fila__ayuda">' . e($ayuda) . "</div></div>"
         . '<div class="form-check form-switch fs-4 mb-0"><input class="form-check-input" type="checkbox" role="switch" id="' . $id . '" name="' . e($nombre) . '" value="1"' . ($activo ? " checked" : "") . "></div>"
         . "</div>";
}

$tituloPagina = "Configuración";
$paginaActiva = "configuracion";
$estilosExtra = ["../views/css/rutinas.css"];
$scriptsExtra = ["../views/js/entrenamiento.js"];
include "deportista-header.php";
?>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo">Configuración</h1>
    <p class="pl-page-header__sub">Ajusta cómo te acompaña PowerLab mientras entrenas.</p>
  </div>
</header>

<form method="POST" action="../controllers/guardar-preferencias.php" class="needs-validation" novalidate>
  <?= csrfCampo() ?>
  <div class="row g-4">
    <div class="col-lg-8">
      <section class="pl-card mb-4" aria-labelledby="tituloPrefEntreno">
        <h2 class="pl-form-titulo" id="tituloPrefEntreno"><i class="bi bi-stopwatch me-2"></i>Entrenamiento</h2>
        <div class="pl-pref-fila">
          <div>
            <label class="pl-pref-fila__titulo" for="pref_descanso">Descanso entre series</label>
            <div class="pl-pref-fila__ayuda">Se usa en los ejercicios de tus rutinas que no tienen un descanso propio.</div>
          </div>
          <select class="form-select pl-pref-fila__control" id="pref_descanso" name="descanso_seg">
            <?php foreach ($opcionesDescanso as $s): ?>
              <option value="<?= $s ?>"<?= $s === $pref["descanso_seg"] ? " selected" : "" ?>><?= e(textoSegundos($s)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?= interruptor("sonido", $pref["sonido"], "Sonido al terminar el descanso", "Un pitido corto cuando termina el descanso o el cronómetro.") ?>
        <?= interruptor("vibracion", $pref["vibracion"], "Vibración", "En móviles compatibles, vibra al terminar el descanso.") ?>
        <div class="d-flex flex-wrap gap-2 mt-3">
          <button type="button" class="btn btn-sm btn-light border" data-probar-sonido><i class="bi bi-volume-up me-1"></i>Probar sonido</button>
          <button type="button" class="btn btn-sm btn-light border" data-probar-vibracion><i class="bi bi-phone-vibrate me-1"></i>Probar vibración</button>
        </div>
      </section>

      <section class="pl-card mb-4" aria-labelledby="tituloPrefAgua">
        <h2 class="pl-form-titulo" id="tituloPrefAgua"><i class="bi bi-droplet me-2"></i>Hidratación</h2>
        <?= interruptor("hidratacion_activa", $pref["hidratacion_activa"], "Recordarme beber agua", "Mientras entrenas, aparece un aviso para que tomes unos sorbos.") ?>
        <div class="pl-pref-fila">
          <div>
            <label class="pl-pref-fila__titulo" for="pref_hidratacion">Cada cuánto</label>
            <div class="pl-pref-fila__ayuda">Tiempo entre un recordatorio y el siguiente.</div>
          </div>
          <select class="form-select pl-pref-fila__control" id="pref_hidratacion" name="hidratacion_min">
            <?php foreach ($opcionesHidratacion as $m): ?>
              <option value="<?= $m ?>"<?= $m === $pref["hidratacion_min"] ? " selected" : "" ?>>Cada <?= $m ?> min</option>
            <?php endforeach; ?>
          </select>
        </div>
      </section>

      <section class="pl-card mb-4" aria-labelledby="tituloPrefMeta">
        <h2 class="pl-form-titulo" id="tituloPrefMeta"><i class="bi bi-calendar-week me-2"></i>Meta semanal</h2>
        <span class="pl-pref-fila__ayuda d-block mb-2" id="ayudaMeta">¿Cuántos días por semana quieres entrenar? Verás tu avance en Inicio y en Entrenar.</span>
        <div class="pl-dias" role="radiogroup" aria-describedby="ayudaMeta">
          <?php for ($d = 1; $d <= 7; $d++): ?>
            <input type="radio" class="btn-check" name="meta_semanal" id="meta<?= $d ?>" value="<?= $d ?>"<?= $d === $pref["meta_semanal"] ? " checked" : "" ?> required>
            <label class="pl-dias__opcion" for="meta<?= $d ?>"><?= $d ?><span class="visually-hidden"> día<?= $d > 1 ? "s" : "" ?></span></label>
          <?php endfor; ?>
        </div>
      </section>

      <div class="d-flex justify-content-end">
        <button type="submit" class="btn btn-pl btn-lg px-4"><i class="bi bi-check2 me-2"></i>Guardar preferencias</button>
      </div>
    </div>

    <aside class="col-lg-4">
      <section class="pl-card" aria-labelledby="tituloCuenta">
        <h2 class="pl-form-titulo" id="tituloCuenta"><i class="bi bi-person me-2"></i>Tu cuenta</h2>
        <dl class="pl-cuenta mb-0">
          <div><dt>Nombre</dt><dd><?= e(trim($usuario["nombre"] . " " . $usuario["apellido"])) ?></dd></div>
          <div><dt>Correo</dt><dd class="text-break"><?= e($usuario["correo"]) ?></dd></div>
          <?php if (!empty($usuario["fecha_nacimiento"])): ?>
            <div><dt>Fecha de nacimiento</dt><dd><?= e(fechaLegible($usuario["fecha_nacimiento"], true)) ?></dd></div>
          <?php endif; ?>
        </dl>
        <a href="../controllers/logout.php" class="btn btn-light border w-100 mt-3"><i class="bi bi-box-arrow-right me-2"></i>Cerrar sesión</a>
      </section>
    </aside>
  </div>
</form>

<?php include "deportista-footer.php"; ?>
