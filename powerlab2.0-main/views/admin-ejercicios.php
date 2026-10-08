<?php
// Administrador: listado de ejercicios con buscador, filtros y paginación
include_once "../controllers/sesion-deportista.php";
$usuario = requerirAdmin();

include_once "../models/ejercicios.php";
include_once "../models/catalogos.php";
include_once "ejercicio-tarjeta.php";

const POR_PAGINA_ADMIN = 15;

$estados = ["todos" => "Todos", "activos" => "Activos", "inactivos" => "Inactivos"];
$filtros = [
    "q" => mb_substr(trim((string) ($_GET["q"] ?? "")), 0, 100),
    "grupo" => (int) ($_GET["grupo"] ?? 0),
    "equipo" => (int) ($_GET["equipo"] ?? 0),
    "nivel" => (int) ($_GET["nivel"] ?? 0),
    "estado" => isset($estados[$_GET["estado"] ?? ""]) ? $_GET["estado"] : "todos",
];
$resultado = (new ejercicios())->buscar($filtros, (int) ($_GET["pagina"] ?? 1), POR_PAGINA_ADMIN);

$catalogos = new catalogos();
$grupos = $catalogos->grupos();
$equipos = $catalogos->equipamiento();
$niveles = $catalogos->niveles();

// Query string actual sin la página (para paginación y para volver tras activar/desactivar)
$base = array_filter(["q" => $filtros["q"], "grupo" => $filtros["grupo"] ?: null, "equipo" => $filtros["equipo"] ?: null,
                      "nivel" => $filtros["nivel"] ?: null, "estado" => $filtros["estado"] !== "todos" ? $filtros["estado"] : null]);
$urlPagina = fn($p) => "admin-ejercicios.php?" . http_build_query($base + ["pagina" => $p]);
$urlActual = "admin-ejercicios.php" . ($base || $resultado["pagina"] > 1 ? "?" . http_build_query($base + ($resultado["pagina"] > 1 ? ["pagina" => $resultado["pagina"]] : [])) : "");
$hayFiltros = (bool) $base;

$tituloPagina = "Ejercicios";
$paginaActiva = "ejercicios";
$estilosExtra = ["../views/css/ejercicios.css"];
include "admin-header.php";
?>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo">Biblioteca de ejercicios</h1>
    <p class="pl-page-header__sub">Crea, edita y desactiva los ejercicios que ven los deportistas.</p>
  </div>
  <a href="admin-ejercicio-form.php" class="btn btn-pl btn-lg"><i class="bi bi-plus-lg me-2"></i>Nuevo ejercicio</a>
</header>

<!-- Filtros (GET: se pueden compartir y paginar) -->
<form method="GET" action="admin-ejercicios.php" class="pl-card mb-3 pl-admin-filtros" data-admin-filtros>
  <div class="row g-2 align-items-center">
    <div class="col-lg-4">
      <label for="q" class="visually-hidden">Buscar</label>
      <div class="pl-buscador">
        <i class="bi bi-search"></i>
        <input type="search" id="q" name="q" class="form-control" placeholder="Buscar por nombre, músculo, grupo…" value="<?= e($filtros["q"]) ?>" maxlength="100" data-admin-buscar>
      </div>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <label for="fGrupo" class="visually-hidden">Grupo muscular</label>
      <select id="fGrupo" name="grupo" class="form-select" data-admin-auto>
        <option value="">Grupo</option>
        <?php foreach ($grupos as $g): ?>
          <option value="<?= (int) $g["id"] ?>"<?= (int) $g["id"] === $filtros["grupo"] ? " selected" : "" ?>><?= e($g["nombre"]) ?><?= $g["activo"] ? "" : " (inactivo)" ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <label for="fEquipo" class="visually-hidden">Equipamiento</label>
      <select id="fEquipo" name="equipo" class="form-select" data-admin-auto>
        <option value="">Equipamiento</option>
        <?php foreach ($equipos as $q): ?>
          <option value="<?= (int) $q["id"] ?>"<?= (int) $q["id"] === $filtros["equipo"] ? " selected" : "" ?>><?= e($q["nombre"]) ?><?= $q["activo"] ? "" : " (inactivo)" ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <label for="fNivel" class="visually-hidden">Nivel</label>
      <select id="fNivel" name="nivel" class="form-select" data-admin-auto>
        <option value="">Nivel</option>
        <?php foreach ($niveles as $n): ?>
          <option value="<?= (int) $n["id"] ?>"<?= (int) $n["id"] === $filtros["nivel"] ? " selected" : "" ?>><?= e($n["nombre"]) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-6 col-md-3 col-lg-2">
      <label for="fEstado" class="visually-hidden">Estado</label>
      <select id="fEstado" name="estado" class="form-select" data-admin-auto>
        <?php foreach ($estados as $valor => $texto): ?>
          <option value="<?= e($valor) ?>"<?= $valor === $filtros["estado"] ? " selected" : "" ?>><?= e($texto) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="d-flex justify-content-between align-items-center mt-2">
    <span class="small text-body-secondary fw-semibold"><?= $resultado["total"] ?> ejercicio<?= $resultado["total"] === 1 ? "" : "s" ?></span>
    <div class="d-flex gap-2">
      <?php if ($hayFiltros): ?><a href="admin-ejercicios.php" class="btn btn-sm btn-light border"><i class="bi bi-x-lg me-1"></i>Limpiar</a><?php endif; ?>
      <button type="submit" class="btn btn-sm btn-pl-outline">Buscar</button>
    </div>
  </div>
</form>

<?php if (!$resultado["items"]): ?>
  <div class="pl-vacio">
    <div class="pl-vacio__icono"><i class="bi bi-search"></i></div>
    <div class="pl-vacio__titulo"><?= $hayFiltros ? "Ningún ejercicio coincide" : "Aún no hay ejercicios" ?></div>
    <p class="pl-vacio__texto"><?= $hayFiltros ? "Cambia la búsqueda o los filtros." : "Crea el primero para la biblioteca." ?></p>
    <a href="<?= $hayFiltros ? "admin-ejercicios.php" : "admin-ejercicio-form.php" ?>" class="btn btn-pl px-4"><?= $hayFiltros ? "Limpiar filtros" : "Nuevo ejercicio" ?></a>
  </div>
<?php else: ?>

  <div class="pl-card p-0 pl-admin-lista">
    <ul class="list-unstyled mb-0">
      <?php foreach ($resultado["items"] as $fila): $t = ejercicios::tarjeta($fila); ?>
        <li class="pl-admin-fila<?= $fila["activo"] ? "" : " is-inactivo" ?>">
          <?= visualEjercicio($t, "pl-ej-visual pl-admin-fila__miniatura") ?>
          <div class="pl-admin-fila__info">
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
              <span class="pl-grupo pl-tono-<?= (int) $fila["grupo_tono"] ?>"><?= e($fila["grupo"]) ?></span>
              <?php if (!$fila["activo"]): ?><span class="pl-estado">Inactivo</span><?php endif; ?>
            </div>
            <a href="admin-ejercicio-form.php?id=<?= (int) $fila["id"] ?>" class="pl-admin-fila__nombre"><?= e($fila["nombre"]) ?></a>
            <div class="pl-admin-fila__meta">
              <span><i class="bi bi-bullseye me-1"></i><?= e($fila["musculo_principal"]) ?></span>
              <span><i class="bi bi-gear me-1"></i><?= e($fila["equipamiento"]) ?></span>
              <span class="pl-nivel pl-nivel--<?= e($fila["nivel_clave"]) ?>"><?= e($fila["nivel"]) ?></span>
              <?php if ($fila["imagen"]): ?><span title="Tiene imagen"><i class="bi bi-image"></i></span><?php endif; ?>
              <?php if ($fila["video_url"]): ?><span title="Tiene video"><i class="bi bi-play-btn"></i></span><?php endif; ?>
            </div>
          </div>
          <div class="pl-admin-fila__acciones">
            <a href="admin-ejercicio-form.php?id=<?= (int) $fila["id"] ?>" class="btn btn-sm btn-light border" aria-label="Editar <?= e($fila["nombre"]) ?>"><i class="bi bi-pencil"></i><span class="d-none d-md-inline ms-1">Editar</span></a>
            <form method="POST" action="../controllers/estado-ejercicio.php"
                  data-pl-confirmar="<?= $fila["activo"] ? "¿Desactivar «" . e($fila["nombre"]) . "»? Dejará de verse en la biblioteca, pero no se borra." : "¿Activar «" . e($fila["nombre"]) . "» de nuevo?" ?>">
              <?= csrfCampo() ?>
              <input type="hidden" name="id" value="<?= (int) $fila["id"] ?>">
              <input type="hidden" name="activo" value="<?= $fila["activo"] ? "0" : "1" ?>">
              <input type="hidden" name="volver" value="<?= e($urlActual) ?>">
              <?php if ($fila["activo"]): ?>
                <button type="submit" class="btn btn-sm btn-light border text-danger" aria-label="Desactivar <?= e($fila["nombre"]) ?>"><i class="bi bi-eye-slash"></i><span class="d-none d-md-inline ms-1">Desactivar</span></button>
              <?php else: ?>
                <button type="submit" class="btn btn-sm btn-light border text-success" aria-label="Activar <?= e($fila["nombre"]) ?>"><i class="bi bi-eye"></i><span class="d-none d-md-inline ms-1">Activar</span></button>
              <?php endif; ?>
            </form>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
  </div>

  <?php if ($resultado["paginas"] > 1): ?>
    <nav class="mt-4" aria-label="Páginas">
      <ul class="pagination pl-paginacion justify-content-center flex-wrap">
        <li class="page-item<?= $resultado["pagina"] <= 1 ? " disabled" : "" ?>">
          <a class="page-link" href="<?= e($urlPagina($resultado["pagina"] - 1)) ?>" aria-label="Anterior"><i class="bi bi-chevron-left"></i></a>
        </li>
        <?php for ($p = 1; $p <= $resultado["paginas"]; $p++):
          if ($p !== 1 && $p !== $resultado["paginas"] && abs($p - $resultado["pagina"]) > 2) {
              if (abs($p - $resultado["pagina"]) === 3) echo '<li class="page-item disabled"><span class="page-link">…</span></li>';
              continue;
          } ?>
          <li class="page-item<?= $p === $resultado["pagina"] ? " active" : "" ?>">
            <a class="page-link" href="<?= e($urlPagina($p)) ?>"<?= $p === $resultado["pagina"] ? ' aria-current="page"' : "" ?>><?= $p ?></a>
          </li>
        <?php endfor; ?>
        <li class="page-item<?= $resultado["pagina"] >= $resultado["paginas"] ? " disabled" : "" ?>">
          <a class="page-link" href="<?= e($urlPagina($resultado["pagina"] + 1)) ?>" aria-label="Siguiente"><i class="bi bi-chevron-right"></i></a>
        </li>
      </ul>
    </nav>
  <?php endif; ?>

<?php endif; ?>

<?php include "admin-footer.php"; ?>
