<?php
// Detalle de un ejercicio (deportista): imagen o video, músculos, instrucciones y relacionados
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/ejercicios.php";
include_once "ejercicio-tarjeta.php";

$id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$modelo = new ejercicios();
$ejercicio = $id ? $modelo->ConsultaEspecifica($id, true) : null; // solo activos

if (!$ejercicio) {
    redirigir("ejercicios.php", "warning", "No encontramos ese ejercicio.");
}

$tarjeta = ejercicios::tarjeta($ejercicio);
$video = ejercicios::videoEmbed($ejercicio["video_url"]);$secundarios = ejercicios::secundarios($ejercicio);
$pasos = ejercicios::pasos($ejercicio);
$relacionados = $modelo->Relacionados($ejercicio["id_grupo"], $ejercicio["id"]);

$tituloPagina = $ejercicio["nombre"];
$paginaActiva = "ejercicios";
$estilosExtra = ["../views/css/ejercicios.css"];
$scriptsExtra = ["../views/js/ejercicios.js"];
include "deportista-header.php";
?>

<a href="ejercicios.php" class="pl-volver" data-ej-volver><i class="bi bi-arrow-left"></i> Ejercicios</a>

<article class="row g-4 mb-5">
  <!-- Imagen y video -->
  <div class="col-lg-6">
    <?= visualEjercicio($tarjeta, "pl-ej-visual pl-ej-media") ?>
    <?php if ($video): ?>
      <div class="ratio ratio-16x9 pl-ej-video mt-3">
        <iframe src="<?= e($video) ?>" title="Video: <?= e($ejercicio["nombre"]) ?>" loading="lazy"
                allow="accelerometer; encrypted-media; gyroscope; picture-in-picture" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
      </div>
    <?php endif; ?>
  </div>

  <!-- Información -->
  <div class="col-lg-6">
    <span class="pl-grupo pl-tono-<?= (int) $ejercicio["grupo_tono"] ?>"><i class="bi <?= e($ejercicio["grupo_icono"]) ?> me-1" aria-hidden="true"></i><?= e($ejercicio["grupo"]) ?></span>
    <h1 class="pl-ej-detalle__titulo"><?= e($ejercicio["nombre"]) ?></h1>
    <p class="pl-ej-detalle__desc"><?= e($ejercicio["descripcion"]) ?></p>

    <div class="d-flex flex-wrap gap-2 mb-4">
      <span class="pl-nivel pl-nivel--<?= e($ejercicio["nivel_clave"]) ?>"><?= e($ejercicio["nivel"]) ?></span>
      <span class="pl-etiqueta pl-etiqueta--neutra"><i class="bi bi-gear" aria-hidden="true"></i><?= e($ejercicio["equipamiento"]) ?></span>
      <span class="pl-etiqueta pl-etiqueta--neutra"><i class="bi bi-diagram-3" aria-hidden="true"></i><?= e($ejercicio["tipo"]) ?></span>
    </div>

    <div class="pl-card">
      <h2 class="pl-seccion-titulo mb-3"><span><i class="bi bi-bullseye me-2 text-body-tertiary"></i>Músculos</span></h2>
      <dl class="pl-ej-musculos mb-0">
        <div>
          <dt>Principal</dt>
          <dd><span class="pl-musculo pl-musculo--principal"><?= e($ejercicio["musculo_principal"]) ?></span></dd>
        </div>
        <div>
          <dt>Secundarios</dt>
          <dd>
            <?php if ($secundarios): ?>
              <?php foreach ($secundarios as $m): ?><span class="pl-musculo"><?= e($m) ?></span><?php endforeach; ?>
            <?php else: ?>
              <span class="text-body-tertiary">Ninguno destacado</span>
            <?php endif; ?>
          </dd>
        </div>
      </dl>
    </div>
  </div>
</article>

<!-- Instrucciones -->
<section class="mb-5" aria-labelledby="tituloInstrucciones">
  <h2 class="pl-seccion-titulo" id="tituloInstrucciones"><span><i class="bi bi-list-ol me-2 text-body-tertiary"></i>Cómo hacerlo</span></h2>
  <ol class="pl-pasos">
    <?php foreach ($pasos as $paso): ?>
      <li><?= e($paso) ?></li>
    <?php endforeach; ?>
  </ol>
</section>

<?php if ($relacionados): ?>
  <section aria-labelledby="tituloRelacionados">
    <h2 class="pl-seccion-titulo" id="tituloRelacionados">
      <span><i class="bi bi-collection me-2 text-body-tertiary"></i>Más de <?= e($ejercicio["grupo"]) ?></span>
      <a href="ejercicios.php?grupo=<?= (int) $ejercicio["id_grupo"] ?>">Ver todos <i class="bi bi-arrow-right"></i></a>
    </h2>
    <div class="row g-3">
      <?php foreach ($relacionados as $rel): ?>
        <div class="col-6 col-lg-3"><?= tarjetaEjercicio(ejercicios::tarjeta($rel)) ?></div>
      <?php endforeach; ?>
    </div>
  </section>
<?php endif; ?>

<?php include "deportista-footer.php"; ?>
