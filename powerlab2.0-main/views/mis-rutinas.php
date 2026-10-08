<?php
// Mis rutinas: rutinas propias del deportista y plantillas de ejemplo para copiar
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/rutinas.php";
include_once "../models/preferencias.php";

$idUsuario = (int) $usuario["id"];
$preferencias = (new preferencias())->obtener($idUsuario);
$modelo = new rutinas();
$misRutinas = $modelo->listar($idUsuario, false, $preferencias["descanso_seg"]);
$plantillas = $modelo->listar($idUsuario, true, $preferencias["descanso_seg"]);
foreach ($plantillas as &$p) {
    $p["items"] = $modelo->obtener((int) $p["id"], $idUsuario, true)["items"] ?? [];
}
unset($p);
$alLimite = count($misRutinas) >= rutinas::MAX_RUTINAS;

// Etiquetas de grupo muscular de una rutina (máx. 3 + "+N")
function gruposRutina($grupos) {
    $html = "";
    foreach (array_slice($grupos, 0, 3) as $g) {
        $html .= '<span class="pl-grupo pl-tono-' . (int) $g["tono"] . '">' . e($g["nombre"]) . "</span>";
    }
    if (count($grupos) > 3) {
        $html .= '<span class="pl-grupo">+' . (count($grupos) - 3) . "</span>";
    }
    return $html;
}

// Resumen: "6 ejercicios · 18 series · ~35 min"
function resumenRutina($r) {
    return '<span><i class="bi bi-list-ol me-1" aria-hidden="true"></i>' . (int) $r["num_ejercicios"] . " ejercicio" . ((int) $r["num_ejercicios"] === 1 ? "" : "s") . "</span>"
         . '<span><i class="bi bi-layers me-1" aria-hidden="true"></i>' . (int) $r["num_series"] . " series</span>"
         . '<span><i class="bi bi-clock me-1" aria-hidden="true"></i>~' . rutinas::minutos($r["segundos"]) . " min</span>";
}

$tituloPagina = "Mis rutinas";
$paginaActiva = "rutinas";
$estilosExtra = ["../views/css/ejercicios.css", "../views/css/rutinas.css"];
include "deportista-header.php";
?>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo">Mis rutinas</h1>
    <p class="pl-page-header__sub">Arma tus rutinas con los ejercicios de la biblioteca y entrénalas paso a paso.</p>
  </div>
  <?php if (!$alLimite): ?>
    <a href="rutina-form.php" class="btn btn-pl btn-lg"><i class="bi bi-plus-lg me-2"></i>Nueva rutina</a>
  <?php endif; ?>
</header>

<?php if ($alLimite): ?>
  <div class="pl-aviso pl-aviso--pendiente mb-4"><i class="bi bi-info-circle"></i><div>Llegaste al máximo de <?= rutinas::MAX_RUTINAS ?> rutinas. Borra alguna que ya no uses para crear otra.</div></div>
<?php endif; ?>

<?php if (!$misRutinas): ?>
  <div class="pl-vacio pl-reveal mb-5">
    <div class="pl-vacio__icono"><i class="bi bi-journal-plus"></i></div>
    <div class="pl-vacio__titulo">Aún no tienes rutinas</div>
    <p class="pl-vacio__texto">Crea la tuya desde cero o copia una de las plantillas de abajo y ajústala a tu gusto.</p>
    <a href="rutina-form.php" class="btn btn-pl px-4">Crear mi primera rutina</a>
  </div>
<?php else: ?>
  <section class="row g-3 mb-5" aria-label="Tus rutinas">
    <?php foreach ($misRutinas as $r):
      $entrenables = (int) $r["num_ejercicios"] - (int) $r["no_disponibles"]; ?>
      <div class="col-md-6 col-xl-4 pl-reveal">
        <article class="pl-card pl-rutina-card">
          <div class="pl-rutina-card__grupos"><?= gruposRutina($r["grupos"]) ?></div>
          <h2 class="pl-rutina-card__nombre"><a href="rutina-form.php?id=<?= (int) $r["id"] ?>"><?= e($r["nombre"]) ?></a></h2>
          <?php if ($r["descripcion"]): ?><p class="pl-rutina-card__desc"><?= e($r["descripcion"]) ?></p><?php endif; ?>
          <div class="pl-rutina-card__meta"><?= resumenRutina($r) ?></div>
          <p class="pl-rutina-card__ultimo">
            <?php if ($r["ultimo_entreno"]): ?>
              <i class="bi bi-check2-circle me-1 text-success" aria-hidden="true"></i>Entrenada <?= (int) $r["veces"] ?> <?= (int) $r["veces"] === 1 ? "vez" : "veces" ?> · última: <?= e(fechaLegible($r["ultimo_entreno"], true)) ?>
            <?php else: ?>
              <i class="bi bi-hourglass me-1" aria-hidden="true"></i>Aún sin entrenar
            <?php endif; ?>
          </p>
          <?php if ($r["no_disponibles"] > 0): ?>
            <div class="pl-rutina-card__aviso"><i class="bi bi-exclamation-triangle me-1"></i><?= (int) $r["no_disponibles"] ?> ejercicio<?= $r["no_disponibles"] > 1 ? "s" : "" ?> ya no disponible<?= $r["no_disponibles"] > 1 ? "s" : "" ?>; se saltará<?= $r["no_disponibles"] > 1 ? "n" : "" ?> al entrenar.</div>
          <?php endif; ?>
          <div class="pl-rutina-card__acciones">
            <?php if ($entrenables > 0): ?>
              <a href="modo-entrenamiento.php?rutina=<?= (int) $r["id"] ?>" class="btn btn-pl flex-grow-1"><i class="bi bi-play-fill me-1"></i>Entrenar</a>
            <?php else: ?>
              <a href="rutina-form.php?id=<?= (int) $r["id"] ?>" class="btn btn-pl-outline flex-grow-1">Agregar ejercicios</a>
            <?php endif; ?>
            <a href="rutina-form.php?id=<?= (int) $r["id"] ?>" class="btn btn-light border" aria-label="Editar <?= e($r["nombre"]) ?>" title="Editar"><i class="bi bi-pencil"></i></a>
            <?php if (!$alLimite): ?>
              <form method="POST" action="../controllers/copiar-rutina.php">
                <?= csrfCampo() ?>
                <input type="hidden" name="id" value="<?= (int) $r["id"] ?>">
                <button type="submit" class="btn btn-light border" aria-label="Duplicar <?= e($r["nombre"]) ?>" title="Duplicar" data-no-bloquear="1"><i class="bi bi-copy"></i></button>
              </form>
            <?php endif; ?>
            <form method="POST" action="../controllers/eliminar-rutina.php" data-pl-confirmar="¿Eliminar «<?= e($r["nombre"]) ?>»? Tu historial de entrenamientos se conserva.">
              <?= csrfCampo() ?>
              <input type="hidden" name="id" value="<?= (int) $r["id"] ?>">
              <button type="submit" class="btn btn-light border text-danger" aria-label="Eliminar <?= e($r["nombre"]) ?>" title="Eliminar"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </article>
      </div>
    <?php endforeach; ?>
  </section>
<?php endif; ?>

<?php if ($plantillas): ?>
  <section aria-labelledby="tituloPlantillas">
    <h2 class="pl-seccion-titulo" id="tituloPlantillas"><span><i class="bi bi-stars me-2 text-body-tertiary"></i>Plantillas para empezar</span></h2>
    <p class="text-body-secondary small mb-3">Cópialas a tus rutinas para entrenarlas o modificarlas. La plantilla original no cambia.</p>
    <div class="row g-3">
      <?php foreach ($plantillas as $p): ?>
        <div class="col-md-6 col-xxl-3 pl-reveal">
          <article class="pl-card pl-rutina-card pl-rutina-card--plantilla">
            <div class="pl-rutina-card__grupos"><?= gruposRutina($p["grupos"]) ?></div>
            <h3 class="pl-rutina-card__nombre"><?= e($p["nombre"]) ?></h3>
            <?php if ($p["descripcion"]): ?><p class="pl-rutina-card__desc"><?= e($p["descripcion"]) ?></p><?php endif; ?>
            <div class="pl-rutina-card__meta"><?= resumenRutina($p) ?></div>
            <details class="pl-rutina-card__detalle">
              <summary>Ver ejercicios</summary>
              <ol>
                <?php foreach ($p["items"] as $item): ?>
                  <li><?= e($item["ejercicio"]["nombre"]) ?> <span class="text-body-tertiary">· <?= (int) $item["series"] ?> × <?= e(rutinas::meta($item)) ?></span></li>
                <?php endforeach; ?>
              </ol>
            </details>
            <div class="pl-rutina-card__acciones">
              <?php if ($alLimite): ?>
                <button type="button" class="btn btn-pl-outline flex-grow-1" disabled>Límite de rutinas alcanzado</button>
              <?php else: ?>
                <form method="POST" action="../controllers/copiar-rutina.php" class="flex-grow-1 d-flex">
                  <?= csrfCampo() ?>
                  <input type="hidden" name="id" value="<?= (int) $p["id"] ?>">
                  <button type="submit" class="btn btn-pl-outline flex-grow-1 text-nowrap" aria-label="Copiar «<?= e($p["nombre"]) ?>» a mis rutinas"><i class="bi bi-copy me-1"></i>Copiar a mis rutinas</button>
                </form>
              <?php endif; ?>
            </div>
          </article>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php include "deportista-footer.php"; ?>
