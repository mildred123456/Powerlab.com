<?php
// Tarjeta de ejercicio: un solo marcado para
//   - las tarjetas que imprime el servidor (biblioteca y relacionados),
//   - la plantilla <template> que el JS clona en el scroll infinito,
//   - la vista previa del formulario del administrador.
// Recibe el arreglo de ejercicios::tarjeta(). Requiere e() de controllers/sesion-deportista.php.

// Imagen del ejercicio, si no la de respaldo del grupo; si no hay ninguna, el ícono sobre el color del grupo
function visualEjercicio($t, $clase = "pl-ej-visual") {
    $src = $t["imagen"] ?? null ?: ($t["respaldo"] ?? null);
    ob_start(); ?>
    <div class="<?= e($clase) ?> pl-tono-<?= (int) $t["grupo_tono"] ?>" data-ej="visual">
      <img class="pl-ej-visual__img" src="<?= e($src ?? "") ?>" alt="<?= e($t["nombre"]) ?>" loading="lazy" data-ej="img"<?= $src ? "" : " hidden" ?>>
      <i class="pl-ej-visual__icono bi <?= e($t["grupo_icono"]) ?>" aria-hidden="true" data-ej="icono"<?= $src ? " hidden" : "" ?>></i>
      <span class="pl-ej-visual__video" data-ej="video"<?= !empty($t["video"]) ? "" : " hidden" ?>><i class="bi bi-play-circle-fill"></i> Video</span>
    </div>
    <?php
    return ob_get_clean();
}

function tarjetaEjercicio($t, $enlace = true) {
    $etiqueta = $enlace ? "a" : "div";
    $href = $enlace ? ' href="ejercicio-detalle.php?id=' . (int) $t["id"] . '"' : "";
    ob_start(); ?>
    <<?= $etiqueta ?> class="pl-ej-card"<?= $href ?> data-ej="enlace">
      <?= visualEjercicio($t) ?>
      <div class="pl-ej-card__cuerpo">
        <span class="pl-grupo pl-tono-<?= (int) $t["grupo_tono"] ?>" data-ej="grupo"><?= e($t["grupo"]) ?></span>
        <h3 class="pl-ej-card__nombre" data-ej="nombre"><?= e($t["nombre"]) ?></h3>
        <p class="pl-ej-card__musculo"><i class="bi bi-bullseye me-1" aria-hidden="true"></i><span data-ej="musculo"><?= e($t["musculo"]) ?></span></p>
        <div class="pl-ej-card__chips">
          <span class="pl-etiqueta pl-etiqueta--neutra pl-ej-card__equipo"><i class="bi bi-gear" aria-hidden="true"></i><span data-ej="equipo"><?= e($t["equipamiento"]) ?></span></span>
          <span class="pl-nivel pl-nivel--<?= e($t["nivel_clave"]) ?>" data-ej="nivel"><?= e($t["nivel"]) ?></span>
        </div>
      </div>
    </<?= $etiqueta ?>>
    <?php
    return ob_get_clean();
}

// Plantilla vacía para que el JS cree tarjetas nuevas con el mismo marcado
function plantillaTarjetaEjercicio($enlace = true) {
    $vacia = ["id" => 0, "nombre" => "", "grupo" => "", "grupo_icono" => "bi-collection", "grupo_tono" => 1,
              "musculo" => "", "equipamiento" => "", "nivel" => "", "nivel_clave" => "principiante",
              "imagen" => null, "respaldo" => null, "video" => false];
    return '<template id="plPlantillaEjercicio">' . tarjetaEjercicio($vacia, $enlace) . "</template>";
}
