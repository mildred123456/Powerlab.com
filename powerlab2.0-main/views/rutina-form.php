<?php
// Crear / editar una rutina propia: nombre, descripción y ejercicios de la biblioteca en orden.
// La lista de ejercicios la arma views/js/rutinas.js a partir de los datos de abajo.
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/rutinas.php";
include_once "../models/preferencias.php";
include_once "../models/catalogos.php";
include_once "ejercicio-tarjeta.php";

$idUsuario = (int) $usuario["id"];
$modelo = new rutinas();
$id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: null;
$rutina = $id ? $modelo->obtener($id, $idUsuario) : null;
if ($id && !$rutina) {
    redirigir("mis-rutinas.php", "warning", "No encontramos esa rutina.");
}
if (!$id && $modelo->contar($idUsuario) >= rutinas::MAX_RUTINAS) {
    redirigir("mis-rutinas.php", "warning", "Llegaste al máximo de " . rutinas::MAX_RUTINAS . " rutinas.");
}

// Datos del formulario: lo enviado antes (si hubo errores) > lo guardado > vacío
$previa = $_SESSION["rutina_previa"] ?? null;
unset($_SESSION["rutina_previa"]);
$nombre = $previa["nombre"] ?? $rutina["nombre"] ?? "";
$descripcion = $previa["descripcion"] ?? $rutina["descripcion"] ?? "";
$items = $previa ? rutinas::completarItems($previa["items"]) : ($rutina["items"] ?? []);

$preferencias = (new preferencias())->obtener($idUsuario);
$grupos = array_values(array_filter((new catalogos())->grupos(true), fn($g) => $g["ejercicios_activos"] > 0));

$tituloPagina = $id ? "Editar rutina" : "Nueva rutina";
$paginaActiva = "rutinas";
$estilosExtra = ["../views/css/ejercicios.css", "../views/css/rutinas.css"];
$scriptsExtra = ["../views/js/rutinas.js"];
include "deportista-header.php";
?>

<a href="mis-rutinas.php" class="pl-volver"><i class="bi bi-arrow-left"></i> Mis rutinas</a>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo"><?= $id ? "Editar rutina" : "Nueva rutina" ?></h1>
    <p class="pl-page-header__sub">Agrega ejercicios, define series y repeticiones (o segundos) y ordénalos como los vas a hacer.</p>
  </div>
</header>

<form method="POST" action="../controllers/guardar-rutina.php" class="needs-validation" novalidate data-rutina-form
      data-items="<?= e(json_encode($items)) ?>" data-descanso-defecto="<?= (int) $preferencias["descanso_seg"] ?>"
      data-max="<?= rutinas::MAX_EJERCICIOS ?>" data-seg-rep="<?= rutinas::SEG_POR_REPETICION ?>">
  <?= csrfCampo() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">

  <div class="row g-4">
    <div class="col-lg-8">
      <section class="pl-card mb-4">
        <div class="mb-3">
          <label for="nombreRutina" class="form-label">Nombre de la rutina</label>
          <input type="text" class="form-control" id="nombreRutina" name="nombre" required minlength="2" maxlength="80" value="<?= e($nombre) ?>" placeholder="Ej.: Pierna lunes, Torso fuerza…">
          <div class="invalid-feedback">Entre 2 y 80 caracteres.</div>
        </div>
        <div>
          <label for="descRutina" class="form-label">Descripción <span class="text-body-tertiary fw-normal">(opcional)</span></label>
          <input type="text" class="form-control" id="descRutina" name="descripcion" maxlength="255" value="<?= e($descripcion) ?>" placeholder="Objetivo, días de la semana, observaciones…">
        </div>
      </section>

      <section aria-labelledby="tituloEjerciciosRutina">
        <div class="pl-seccion-titulo">
          <h2 class="h5 mb-0" id="tituloEjerciciosRutina"><i class="bi bi-list-ol me-2 text-body-tertiary"></i>Ejercicios</h2>
          <button type="button" class="btn btn-sm btn-pl" data-rutina-agregar><i class="bi bi-plus-lg me-1"></i>Agregar ejercicios</button>
        </div>
        <ol class="pl-rutina-items" data-rutina-items aria-live="polite"></ol>
        <div class="pl-vacio pl-vacio--compacto" data-rutina-vacio hidden>
          <div class="pl-vacio__icono"><i class="bi bi-plus-circle-dotted"></i></div>
          <div class="pl-vacio__titulo">Tu rutina aún no tiene ejercicios</div>
          <p class="pl-vacio__texto">Búscalos en la biblioteca y agrégalos en el orden en que los harás.</p>
          <button type="button" class="btn btn-pl px-4" data-rutina-agregar>Agregar ejercicios</button>
        </div>
        <noscript><div class="pl-aviso pl-aviso--pendiente">Activa JavaScript en tu navegador para armar la rutina.</div></noscript>
      </section>
    </div>

    <!-- Resumen fijo -->
    <aside class="col-lg-4">
      <div class="pl-card pl-rutina-resumen">
        <h2 class="pl-form-titulo"><i class="bi bi-clipboard-data me-2"></i>Resumen</h2>
        <dl class="pl-rutina-resumen__datos">
          <div><dt>Ejercicios</dt><dd data-resumen="ejercicios">0</dd></div>
          <div><dt>Series</dt><dd data-resumen="series">0</dd></div>
          <div><dt>Duración aprox.</dt><dd data-resumen="minutos">0 min</dd></div>
        </dl>
        <p class="small text-body-secondary">Sin descanso propio, cada ejercicio usa tu descanso por defecto: <strong><?= (int) $preferencias["descanso_seg"] ?> s</strong> (<a href="configuracion.php">cambiar</a>).</p>
        <div class="d-grid gap-2 pl-rutina-resumen__acciones">
          <button type="submit" class="btn btn-pl btn-lg"><i class="bi bi-check2 me-2"></i><?= $id ? "Guardar cambios" : "Crear rutina" ?></button>
          <a href="mis-rutinas.php" class="btn btn-light border">Cancelar</a>
        </div>
      </div>
    </aside>
  </div>
</form>

<!-- Plantilla de un ejercicio de la rutina (la clona el JS) -->
<template id="plPlantillaItemRutina">
  <li class="pl-rutina-item" data-item>
    <div class="pl-rutina-item__cabecera">
      <span class="pl-rutina-item__numero" data-item-numero></span>
      <div class="pl-ej-visual pl-rutina-item__miniatura" data-ej="visual">
        <img class="pl-ej-visual__img" alt="" data-ej="img" hidden>
        <i class="pl-ej-visual__icono bi" aria-hidden="true" data-ej="icono"></i>
      </div>
      <div class="pl-rutina-item__info">
        <span class="pl-grupo" data-item-grupo></span>
        <div class="pl-rutina-item__nombre" data-item-nombre></div>
        <div class="pl-rutina-item__equipo" data-item-equipo></div>
        <span class="pl-estado pl-estado--pendiente" data-item-no-disponible hidden><i class="bi bi-exclamation-triangle me-1"></i>No disponible: se saltará al entrenar</span>
      </div>
      <div class="pl-rutina-item__orden">
        <button type="button" class="btn btn-sm btn-light border" data-item-subir aria-label="Subir"><i class="bi bi-chevron-up"></i></button>
        <button type="button" class="btn btn-sm btn-light border" data-item-bajar aria-label="Bajar"><i class="bi bi-chevron-down"></i></button>
        <button type="button" class="btn btn-sm btn-light border text-danger" data-item-quitar aria-label="Quitar"><i class="bi bi-x-lg"></i></button>
      </div>
    </div>
    <input type="hidden" data-campo="id_ejercicio">
    <div class="pl-rutina-item__campos">
      <div>
        <label class="form-label" data-etiqueta="series">Series</label>
        <input type="number" class="form-control" min="1" max="20" required inputmode="numeric" data-campo="series">
      </div>
      <div>
        <span class="form-label d-block">Meta por serie</span>
        <div class="btn-group w-100 pl-meta" role="group" aria-label="Medir por">
          <input type="radio" class="btn-check" value="reps" data-campo="meta" data-meta="reps">
          <label class="btn btn-sm btn-outline-secondary" data-etiqueta="meta-reps">Reps</label>
          <input type="radio" class="btn-check" value="tiempo" data-campo="meta" data-meta="tiempo">
          <label class="btn btn-sm btn-outline-secondary" data-etiqueta="meta-tiempo">Tiempo</label>
        </div>
      </div>
      <div>
        <label class="form-label" data-etiqueta="valor" data-texto-reps="Repeticiones" data-texto-tiempo="Segundos">Repeticiones</label>
        <input type="number" class="form-control" required inputmode="numeric" data-campo="valor">
      </div>
      <div>
        <label class="form-label" data-etiqueta="peso">Peso <span class="text-body-tertiary fw-normal">(kg)</span></label>
        <input type="number" class="form-control" min="0" max="500" step="0.5" inputmode="decimal" placeholder="—" data-campo="peso_kg">
      </div>
      <div>
        <label class="form-label" data-etiqueta="descanso">Descanso <span class="text-body-tertiary fw-normal">(s)</span></label>
        <input type="number" class="form-control" min="0" max="600" step="5" inputmode="numeric" data-campo="descanso_seg">
      </div>
    </div>
    <div class="pl-rutina-item__notas">
      <label class="visually-hidden" data-etiqueta="notas">Notas</label>
      <input type="text" class="form-control form-control-sm" maxlength="255" placeholder="Nota opcional (agarre, tempo, máquina nº…)" data-campo="notas">
    </div>
  </li>
</template>

<!-- Selector de ejercicios de la biblioteca -->
<div class="modal fade" id="modalSelector" tabindex="-1" aria-labelledby="modalSelectorTitulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <div class="modal-content" data-selector data-url="../controllers/consulta-ejercicios.php">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalSelectorTitulo">Agregar ejercicios</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="pl-selector__filtros">
        <label for="buscarSelector" class="visually-hidden">Buscar ejercicio</label>
        <div class="pl-buscador mb-2">
          <i class="bi bi-search"></i>
          <input type="search" id="buscarSelector" class="form-control" placeholder="Busca por nombre, músculo o equipo…" autocomplete="off" maxlength="100" data-selector-buscar>
        </div>
        <div class="pl-chips" role="group" aria-label="Grupo muscular" data-selector-grupos>
          <button type="button" class="pl-chip active" data-valor="" aria-pressed="true">Todos</button>
          <?php foreach ($grupos as $g): ?>
            <button type="button" class="pl-chip pl-chip--tono pl-tono-<?= (int) $g["tono"] ?>" data-valor="<?= (int) $g["id"] ?>" aria-pressed="false"><?= e($g["nombre"]) ?></button>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-body pt-2">
        <ul class="pl-selector__lista" data-selector-lista aria-live="polite"></ul>
        <div class="pl-ej-cargando" data-selector-cargando hidden><span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Buscando…</div>
        <p class="text-center text-body-secondary py-4 mb-0" data-selector-vacio hidden>No encontramos ejercicios con esa búsqueda.</p>
        <div class="text-center mt-2"><button type="button" class="btn btn-light border btn-sm" data-selector-mas hidden>Cargar más</button></div>
      </div>
      <div class="modal-footer justify-content-between">
        <span class="small text-body-secondary fw-semibold" data-selector-contador></span>
        <button type="button" class="btn btn-pl px-4" data-bs-dismiss="modal">Listo</button>
      </div>
    </div>
  </div>
</div>

<?php include "deportista-footer.php"; ?>
