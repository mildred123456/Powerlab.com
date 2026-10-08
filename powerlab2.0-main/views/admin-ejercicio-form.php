<?php
// Administrador: crear / editar un ejercicio, con vista previa de la tarjeta que verá el deportista
include_once "../controllers/sesion-deportista.php";
$usuario = requerirAdmin();

include_once "../controllers/subir-imagen.php";
include_once "../models/ejercicios.php";
include_once "../models/catalogos.php";
include_once "ejercicio-tarjeta.php";

$id = filter_var($_GET["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: null;
$ejercicio = $id ? (new ejercicios())->ConsultaEspecifica($id, false) : null;
if ($id && !$ejercicio) {
    redirigir("admin-ejercicios.php", "warning", "No encontramos ese ejercicio.");
}

// Valores del formulario: los enviados antes (si hubo errores) > los guardados > vacíos
$datos = array_merge(
    ["nombre" => "", "descripcion" => "", "id_grupo" => 0, "musculo_principal" => "", "musculos_secundarios" => "",
     "id_tipo" => 0, "id_equipamiento" => 0, "id_nivel" => 0, "imagen" => null, "video_url" => "", "instrucciones" => "", "activo" => 1],
    $ejercicio ?? [],
    $_SESSION["ejercicio_previo"] ?? []
);
unset($_SESSION["ejercicio_previo"]);

$catalogos = new catalogos();
$grupos = $catalogos->grupos();
$equipos = $catalogos->equipamiento();
$tipos = $catalogos->tipos();
$niveles = $catalogos->niveles();

// Datos iniciales de la vista previa
$grupoSel = current(array_filter($grupos, fn($g) => (int) $g["id"] === (int) $datos["id_grupo"])) ?: ($grupos[0] ?? null);
$nivelSel = current(array_filter($niveles, fn($n) => (int) $n["id"] === (int) $datos["id_nivel"])) ?: null;
$equipoSel = current(array_filter($equipos, fn($q) => (int) $q["id"] === (int) $datos["id_equipamiento"])) ?: null;
$imagenActual = ejercicios::imagenSrc($datos["imagen"]);
$vistaPrevia = [
    "id" => (int) $id, "nombre" => $datos["nombre"] ?: "Nombre del ejercicio",
    "grupo" => $grupoSel["nombre"] ?? "Grupo", "grupo_icono" => $grupoSel["icono"] ?? "bi-collection", "grupo_tono" => (int) ($grupoSel["tono"] ?? 1),
    "musculo" => $datos["musculo_principal"] ?: "Músculo principal", "equipamiento" => $equipoSel["nombre"] ?? "Equipamiento",
    "nivel" => $nivelSel["nombre"] ?? "Nivel", "nivel_clave" => $nivelSel["clave"] ?? "principiante",
    "imagen" => $imagenActual, "respaldo" => ejercicios::imagenRespaldo($equipoSel["imagen"] ?? null, $grupoSel["imagen_respaldo"] ?? null),
    "video" => (bool) ejercicios::idYoutube($datos["video_url"]),
];

// Opción de un select de catálogo (los inactivos solo aparecen si ya estaban elegidos)
function opcionesCatalogo($lista, $seleccionado, $atributos = null) {
    $html = "";
    foreach ($lista as $item) {
        $elegido = (int) $item["id"] === (int) $seleccionado;
        if (isset($item["activo"]) && !$item["activo"] && !$elegido) {
            continue;
        }
        $extra = $atributos ? $atributos($item) : "";
        $html .= '<option value="' . (int) $item["id"] . '"' . ($elegido ? " selected" : "") . $extra . ">"
               . e($item["nombre"]) . (isset($item["activo"]) && !$item["activo"] ? " (inactivo)" : "") . "</option>";
    }
    return $html;
}

$tituloPagina = $id ? "Editar ejercicio" : "Nuevo ejercicio";
$paginaActiva = $id ? "ejercicios" : "nuevo-ejercicio";
$estilosExtra = ["../views/css/ejercicios.css"];
include "admin-header.php";
?>

<a href="admin-ejercicios.php" class="pl-volver"><i class="bi bi-arrow-left"></i> Ejercicios</a>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo"><?= $id ? "Editar ejercicio" : "Nuevo ejercicio" ?></h1>
    <p class="pl-page-header__sub"><?= $id ? "Los cambios se ven de inmediato en la biblioteca del deportista." : "Completa los datos; a la derecha ves cómo quedará la tarjeta." ?></p>
  </div>
</header>

<form method="POST" action="../controllers/guardar-ejercicio.php" enctype="multipart/form-data" class="needs-validation row g-4" novalidate data-admin-form>
  <?= csrfCampo() ?>
  <input type="hidden" name="id" value="<?= (int) $id ?>">
  <input type="hidden" name="MAX_FILE_SIZE" value="<?= IMG_MAX_BYTES ?>">

  <div class="col-lg-8">
    <!-- Datos básicos -->
    <section class="pl-card mb-4">
      <h2 class="pl-form-titulo">Datos básicos</h2>
      <div class="mb-3">
        <label for="nombre" class="form-label">Nombre</label>
        <input type="text" class="form-control" id="nombre" name="nombre" required minlength="3" maxlength="120" value="<?= e($datos["nombre"]) ?>" data-previa="nombre">
        <div class="invalid-feedback">Entre 3 y 120 caracteres.</div>
      </div>
      <div>
        <label for="descripcion" class="form-label">Descripción corta</label>
        <textarea class="form-control" id="descripcion" name="descripcion" rows="2" required minlength="10" maxlength="500" data-contador="descripcion"><?= e($datos["descripcion"]) ?></textarea>
        <div class="invalid-feedback">Entre 10 y 500 caracteres.</div>
        <div class="text-end"><small class="text-body-secondary" data-contador-de="descripcion"></small></div>
      </div>
    </section>

    <!-- Clasificación -->
    <section class="pl-card mb-4">
      <h2 class="pl-form-titulo">Clasificación</h2>
      <div class="row g-3">
        <div class="col-sm-6">
          <label for="id_grupo" class="form-label">Grupo muscular</label>
          <select class="form-select" id="id_grupo" name="id_grupo" required data-previa="grupo">
            <option value="">Elige…</option>
            <?= opcionesCatalogo($grupos, $datos["id_grupo"], fn($g) =>
                ' data-icono="' . e($g["icono"]) . '" data-tono="' . (int) $g["tono"] . '" data-respaldo="' . e(ejercicios::imagenSrc($g["imagen_respaldo"]) ?? "") . '"') ?>
          </select>
          <div class="invalid-feedback">Elige un grupo.</div>
        </div>
        <div class="col-sm-6">
          <label for="id_tipo" class="form-label">Tipo de ejercicio</label>
          <select class="form-select" id="id_tipo" name="id_tipo" required>
            <option value="">Elige…</option>
            <?= opcionesCatalogo($tipos, $datos["id_tipo"]) ?>
          </select>
          <div class="invalid-feedback">Elige un tipo.</div>
        </div>
        <div class="col-sm-6">
          <label for="id_equipamiento" class="form-label">Equipamiento</label>
          <select class="form-select" id="id_equipamiento" name="id_equipamiento" required data-previa="equipo">
            <option value="">Elige…</option>
            <?= opcionesCatalogo($equipos, $datos["id_equipamiento"], fn($q) => ' data-imagen="' . e(ejercicios::imagenSrc($q["imagen"] ?? null) ?? "") . '"') ?>
          </select>
          <div class="invalid-feedback">Elige el equipamiento.</div>
        </div>
        <div class="col-sm-6">
          <label for="id_nivel" class="form-label">Nivel de dificultad</label>
          <select class="form-select" id="id_nivel" name="id_nivel" required data-previa="nivel">
            <option value="">Elige…</option>
            <?= opcionesCatalogo($niveles, $datos["id_nivel"], fn($n) => ' data-clave="' . e($n["clave"]) . '"') ?>
          </select>
          <div class="invalid-feedback">Elige un nivel.</div>
        </div>
      </div>
    </section>

    <!-- Músculos -->
    <section class="pl-card mb-4">
      <h2 class="pl-form-titulo">Músculos</h2>
      <div class="row g-3">
        <div class="col-sm-6">
          <label for="musculo_principal" class="form-label">Músculo principal</label>
          <input type="text" class="form-control" id="musculo_principal" name="musculo_principal" required minlength="2" maxlength="80" value="<?= e($datos["musculo_principal"]) ?>" data-previa="musculo">
          <div class="invalid-feedback">Indica el músculo principal.</div>
        </div>
        <div class="col-sm-6">
          <label for="musculos_secundarios" class="form-label">Músculos secundarios <span class="text-body-tertiary fw-normal">(separados por coma)</span></label>
          <input type="text" class="form-control" id="musculos_secundarios" name="musculos_secundarios" maxlength="255" value="<?= e($datos["musculos_secundarios"]) ?>" placeholder="Tríceps, Deltoides anterior">
        </div>
      </div>
    </section>

    <!-- Video e imagen -->
    <section class="pl-card mb-4">
      <h2 class="pl-form-titulo">Video e imagen</h2>

      <div class="mb-4">
        <label for="video_url" class="form-label">Video (URL de YouTube, opcional)</label>
        <input type="url" class="form-control" id="video_url" name="video_url" maxlength="255" value="<?= e($datos["video_url"]) ?>" placeholder="https://www.youtube.com/watch?v=…" pattern="https?://(www\.|m\.)?(youtube\.com/(watch\?.*v=|embed/|shorts/)|youtu\.be/)[A-Za-z0-9_\-]{11}.*" data-previa="video">
        <div class="invalid-feedback">Usa un enlace de YouTube (youtube.com/watch?v=… o youtu.be/…).</div>
      </div>

      <div class="pl-admin-imagen">
        <div class="pl-admin-imagen__actual">
          <img src="<?= e($imagenActual ?? "") ?>" alt="Imagen actual" data-imagen-actual<?= $imagenActual ? "" : " hidden" ?>>
          <span class="text-body-tertiary small" data-imagen-actual-vacia<?= $imagenActual ? " hidden" : "" ?>><i class="bi bi-image me-1"></i>Sin imagen propia</span>
        </div>
        <div class="flex-grow-1">
          <label for="imagen" class="form-label">Imagen propia del ejercicio <span class="text-body-tertiary fw-normal">(opcional)</span></label>
          <input type="file" class="form-control" id="imagen" name="imagen" accept="image/jpeg,image/png,image/webp" data-imagen-archivo>
          <div class="form-text">Si no subes una, se muestra la imagen del equipo que usa (se pone en Catálogos → Equipamiento); si el equipo no tiene, el color e ícono del grupo. JPG, PNG o WEBP · máx. 2 MB · mín. 300×200 px.</div>
          <div class="form-check mt-2"<?= $imagenActual ? "" : " hidden" ?>>
            <input class="form-check-input" type="checkbox" id="quitar_imagen" name="quitar_imagen" value="1" data-quitar-imagen>
            <label class="form-check-label" for="quitar_imagen">Quitar la imagen propia</label>
          </div>
        </div>
      </div>
    </section>

    <!-- Instrucciones -->
    <section class="pl-card mb-4">
      <h2 class="pl-form-titulo">Instrucciones</h2>
      <label for="instrucciones" class="form-label">Un paso por línea</label>
      <textarea class="form-control" id="instrucciones" name="instrucciones" rows="6" required maxlength="4000" data-min-lineas="2"><?= e($datos["instrucciones"]) ?></textarea>
      <div class="invalid-feedback">Escribe al menos 2 pasos, uno por línea.</div>
    </section>

    <!-- Estado -->
    <section class="pl-card mb-4 d-flex align-items-center justify-content-between gap-3">
      <div>
        <h2 class="pl-form-titulo mb-1">Visible para los deportistas</h2>
        <p class="small text-body-secondary mb-0">Si lo desactivas, deja de aparecer en la biblioteca pero no se borra.</p>
      </div>
      <div class="form-check form-switch fs-4 mb-0">
        <input class="form-check-input" type="checkbox" role="switch" id="activo" name="activo" value="1"<?= $datos["activo"] ? " checked" : "" ?> aria-label="Ejercicio activo">
      </div>
    </section>

    <div class="d-flex gap-2 justify-content-end pl-admin-acciones">
      <a href="admin-ejercicios.php" class="btn btn-light border px-4">Cancelar</a>
      <button type="submit" class="btn btn-pl px-4"><i class="bi bi-check2 me-2"></i><?= $id ? "Guardar cambios" : "Crear ejercicio" ?></button>
    </div>
  </div>

  <!-- Vista previa -->
  <aside class="col-lg-4">
    <div class="pl-admin-previa">
      <h2 class="pl-form-titulo"><i class="bi bi-phone me-2"></i>Así lo verá el deportista</h2>
      <div class="pl-admin-previa__tarjeta" data-previa-tarjeta>
        <?= tarjetaEjercicio($vistaPrevia, false) ?>
      </div>
      <p class="small text-body-secondary mt-3 mb-0">Se actualiza mientras escribes. Sin imagen propia se usa la del equipo, luego la de respaldo del grupo y, si no hay, su color e ícono.</p>
    </div>
  </aside>
</form>

<?php include "admin-footer.php"; ?>
