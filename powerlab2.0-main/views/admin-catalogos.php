<?php
// Administrador: gestión simple de catálogos (grupos musculares y equipamiento).
// Tipos de ejercicio y niveles son fijos (se cargan por SQL).
include_once "../controllers/sesion-deportista.php";
$usuario = requerirAdmin();

include_once "../models/catalogos.php";
include_once "../models/ejercicios.php";

$catalogos = new catalogos();
$grupos = $catalogos->grupos();
$equipos = $catalogos->equipamiento();

$tituloPagina = "Catálogos";
$paginaActiva = "catalogos";
$estilosExtra = ["../views/css/ejercicios.css"];
include "admin-header.php";

// Botón activar/desactivar de un registro de catálogo
function botonEstadoCatalogo($catalogo, $fila) {
    $activo = (bool) $fila["activo"];
    ob_start(); ?>
    <form method="POST" action="../controllers/estado-catalogo.php"
          data-pl-confirmar="<?= $activo ? "¿Desactivar «" . e($fila["nombre"]) . "»?" : "¿Activar «" . e($fila["nombre"]) . "»?" ?>">
      <?= csrfCampo() ?>
      <input type="hidden" name="catalogo" value="<?= e($catalogo) ?>">
      <input type="hidden" name="id" value="<?= (int) $fila["id"] ?>">
      <input type="hidden" name="activo" value="<?= $activo ? "0" : "1" ?>">
      <button type="submit" class="btn btn-sm btn-light border <?= $activo ? "text-danger" : "text-success" ?>" aria-label="<?= $activo ? "Desactivar" : "Activar" ?> <?= e($fila["nombre"]) ?>">
        <i class="bi <?= $activo ? "bi-eye-slash" : "bi-eye" ?>"></i><span class="d-none d-md-inline ms-1"><?= $activo ? "Desactivar" : "Activar" ?></span>
      </button>
    </form>
    <?php
    return ob_get_clean();
}
?>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo">Catálogos</h1>
    <p class="pl-page-header__sub">Grupos musculares y equipamiento que se usan en los filtros de la biblioteca.</p>
  </div>
</header>

<div class="row g-4">
  <!-- Grupos musculares -->
  <section class="col-xl-7" aria-labelledby="tituloGrupos">
    <div class="pl-seccion-titulo">
      <h2 class="h5 mb-0" id="tituloGrupos"><i class="bi bi-diagram-3 me-2 text-body-tertiary"></i>Grupos musculares</h2>
      <button type="button" class="btn btn-sm btn-pl" data-catalogo-nuevo="grupo"><i class="bi bi-plus-lg me-1"></i>Nuevo grupo</button>
    </div>
    <div class="pl-card p-0">
      <ul class="list-unstyled mb-0">
        <?php foreach ($grupos as $g):
          $respaldo = ejercicios::imagenSrc($g["imagen_respaldo"]);
          $datosEdicion = ["id" => (int) $g["id"], "nombre" => $g["nombre"], "icono" => $g["icono"], "tono" => (int) $g["tono"],
                           "palabras_clave" => $g["palabras_clave"], "orden" => (int) $g["orden"], "imagen" => $respaldo]; ?>
          <li class="pl-admin-fila<?= $g["activo"] ? "" : " is-inactivo" ?>">
            <div class="pl-ej-visual pl-admin-fila__miniatura pl-admin-fila__miniatura--chica pl-tono-<?= (int) $g["tono"] ?>">
              <?php if ($respaldo): ?><img class="pl-ej-visual__img" src="<?= e($respaldo) ?>" alt=""><?php else: ?><i class="pl-ej-visual__icono bi <?= e($g["icono"]) ?>" aria-hidden="true"></i><?php endif; ?>
            </div>
            <div class="pl-admin-fila__info">
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="pl-admin-fila__nombre"><?= e($g["nombre"]) ?></span>
                <?php if (!$g["activo"]): ?><span class="pl-estado">Inactivo</span><?php endif; ?>
              </div>
              <div class="pl-admin-fila__meta">
                <span><?= (int) $g["ejercicios_activos"] ?> activos · <?= (int) $g["ejercicios_total"] ?> en total</span>
                <?php if ($g["palabras_clave"]): ?><span class="text-truncate" title="Palabras clave del buscador"><i class="bi bi-search me-1"></i><?= e($g["palabras_clave"]) ?></span><?php endif; ?>
              </div>
            </div>
            <div class="pl-admin-fila__acciones">
              <button type="button" class="btn btn-sm btn-light border" data-catalogo-editar="grupo" data-registro="<?= e(json_encode($datosEdicion)) ?>" aria-label="Editar <?= e($g["nombre"]) ?>">
                <i class="bi bi-pencil"></i><span class="d-none d-md-inline ms-1">Editar</span>
              </button>
              <?= botonEstadoCatalogo("grupo", $g) ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <!-- Equipamiento -->
  <section class="col-xl-5" aria-labelledby="tituloEquipos">
    <div class="pl-seccion-titulo">
      <h2 class="h5 mb-0" id="tituloEquipos"><i class="bi bi-gear me-2 text-body-tertiary"></i>Equipamiento</h2>
      <button type="button" class="btn btn-sm btn-pl" data-catalogo-nuevo="equipo"><i class="bi bi-plus-lg me-1"></i>Nuevo</button>
    </div>
    <div class="pl-card p-0">
      <ul class="list-unstyled mb-0">
        <?php foreach ($equipos as $q):
          $foto = ejercicios::imagenSrc($q["imagen"] ?? null);
          $datosEdicion = ["id" => (int) $q["id"], "nombre" => $q["nombre"], "orden" => (int) $q["orden"], "imagen" => $foto]; ?>
          <li class="pl-admin-fila<?= $q["activo"] ? "" : " is-inactivo" ?>">
            <div class="pl-ej-visual pl-admin-fila__miniatura pl-admin-fila__miniatura--chica pl-equipo-miniatura">
              <?php if ($foto): ?><img class="pl-ej-visual__img" src="<?= e($foto) ?>" alt=""><?php else: ?><i class="pl-ej-visual__icono bi bi-gear" aria-hidden="true" title="Sin imagen"></i><?php endif; ?>
            </div>
            <div class="pl-admin-fila__info">
              <div class="d-flex flex-wrap align-items-center gap-2">
                <span class="pl-admin-fila__nombre"><?= e($q["nombre"]) ?></span>
                <?php if (!$q["activo"]): ?><span class="pl-estado">Inactivo</span><?php endif; ?>
              </div>
              <div class="pl-admin-fila__meta"><span><?= (int) $q["ejercicios_activos"] ?> activos · <?= (int) $q["ejercicios_total"] ?> en total</span></div>
            </div>
            <div class="pl-admin-fila__acciones">
              <button type="button" class="btn btn-sm btn-light border" data-catalogo-editar="equipo" data-registro="<?= e(json_encode($datosEdicion)) ?>" aria-label="Editar <?= e($q["nombre"]) ?>">
                <i class="bi bi-pencil"></i><span class="d-none d-md-inline ms-1">Editar</span>
              </button>
              <?= botonEstadoCatalogo("equipo", $q) ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
    </div>
    <p class="small text-body-secondary mt-3"><i class="bi bi-info-circle me-1"></i>Un grupo o equipamiento con ejercicios activos no se puede desactivar. Tipos de ejercicio y niveles son fijos.</p>
  </section>
</div>

<!-- Modal: grupo muscular -->
<div class="modal fade" id="modalGrupo" tabindex="-1" aria-labelledby="modalGrupoTitulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <form class="modal-content needs-validation" method="POST" action="../controllers/guardar-catalogo.php" enctype="multipart/form-data" novalidate data-catalogo-form="grupo">
      <?= csrfCampo() ?>
      <input type="hidden" name="catalogo" value="grupo">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= 2 * 1024 * 1024 ?>">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalGrupoTitulo">Grupo muscular</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-sm-8">
            <label for="grupoNombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="grupoNombre" name="nombre" required minlength="2" maxlength="40">
            <div class="invalid-feedback">Entre 2 y 40 caracteres.</div>
          </div>
          <div class="col-sm-4">
            <label for="grupoOrden" class="form-label">Orden</label>
            <input type="number" class="form-control" id="grupoOrden" name="orden" min="0" max="999" value="0">
          </div>
          <div class="col-12">
            <label for="grupoPalabras" class="form-label">Palabras clave del buscador <span class="text-body-tertiary fw-normal">(opcional)</span></label>
            <input type="text" class="form-control" id="grupoPalabras" name="palabras_clave" maxlength="255" placeholder="pierna piernas tren inferior">
            <div class="form-text">Si alguien busca una de estas palabras, también verá los ejercicios de este grupo.</div>
          </div>
          <div class="col-md-6">
            <span class="form-label d-block">Color</span>
            <div class="pl-tonos" role="radiogroup" aria-label="Color del grupo">
              <?php for ($t = 1; $t <= catalogos::TONOS; $t++): ?>
                <input type="radio" class="btn-check" name="tono" id="tono<?= $t ?>" value="<?= $t ?>" required<?= $t === 1 ? " checked" : "" ?>>
                <label class="pl-tono-opcion pl-tono-<?= $t ?>" for="tono<?= $t ?>"><span class="visually-hidden">Color <?= $t ?></span></label>
              <?php endfor; ?>
            </div>
          </div>
          <div class="col-md-6">
            <span class="form-label d-block">Ícono de respaldo</span>
            <div class="pl-iconos" role="radiogroup" aria-label="Ícono del grupo">
              <?php foreach (catalogos::ICONOS as $i => $icono): ?>
                <input type="radio" class="btn-check" name="icono" id="icono<?= $i ?>" value="<?= e($icono) ?>" required<?= $i === 0 ? " checked" : "" ?>>
                <label class="pl-icono-opcion" for="icono<?= $i ?>" title="<?= e($icono) ?>"><i class="bi <?= e($icono) ?>"></i></label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-12">
            <label for="grupoImagen" class="form-label">Imagen de respaldo <span class="text-body-tertiary fw-normal">(opcional)</span></label>
            <div class="pl-admin-imagen">
              <div class="pl-admin-imagen__actual" data-catalogo-imagen-actual data-vacio="Sin imagen: se usa el color y el ícono"></div>
              <div class="flex-grow-1">
                <input type="file" class="form-control" id="grupoImagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">Se muestra en los ejercicios de este grupo que no tengan imagen propia ni de su equipo. JPG, PNG o WEBP · máx. 2 MB.</div>
                <div class="form-check mt-2" data-catalogo-quitar hidden>
                  <input class="form-check-input" type="checkbox" id="grupoQuitar" name="quitar_imagen" value="1">
                  <label class="form-check-label" for="grupoQuitar">Quitar la imagen actual</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-pl px-4">Guardar</button>
      </div>
    </form>
  </div>
</div>

<!-- Modal: equipamiento -->
<div class="modal fade" id="modalEquipo" tabindex="-1" aria-labelledby="modalEquipoTitulo" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <form class="modal-content needs-validation" method="POST" action="../controllers/guardar-catalogo.php" enctype="multipart/form-data" novalidate data-catalogo-form="equipo">
      <?= csrfCampo() ?>
      <input type="hidden" name="catalogo" value="equipo">
      <input type="hidden" name="id" value="">
      <input type="hidden" name="MAX_FILE_SIZE" value="<?= 2 * 1024 * 1024 ?>">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalEquipoTitulo">Equipamiento</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="row g-3">
          <div class="col-8">
            <label for="equipoNombre" class="form-label">Nombre</label>
            <input type="text" class="form-control" id="equipoNombre" name="nombre" required minlength="2" maxlength="40">
            <div class="invalid-feedback">Entre 2 y 40 caracteres.</div>
          </div>
          <div class="col-4">
            <label for="equipoOrden" class="form-label">Orden</label>
            <input type="number" class="form-control" id="equipoOrden" name="orden" min="0" max="999" value="0">
          </div>
          <div class="col-12">
            <label for="equipoImagen" class="form-label">Imagen del equipo o máquina <span class="text-body-tertiary fw-normal">(opcional)</span></label>
            <div class="pl-admin-imagen">
              <div class="pl-admin-imagen__actual" data-catalogo-imagen-actual data-vacio="Sin imagen"></div>
              <div class="flex-grow-1">
                <input type="file" class="form-control" id="equipoImagen" name="imagen" accept="image/jpeg,image/png,image/webp">
                <div class="form-text">Se muestra en todos los ejercicios que usan este equipo y no tienen imagen propia. JPG, PNG o WEBP · máx. 2 MB.</div>
                <div class="form-check mt-2" data-catalogo-quitar hidden>
                  <input class="form-check-input" type="checkbox" id="equipoQuitar" name="quitar_imagen" value="1">
                  <label class="form-check-label" for="equipoQuitar">Quitar la imagen actual</label>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-pl px-4">Guardar</button>
      </div>
    </form>
  </div>
</div>

<?php include "admin-footer.php"; ?>
