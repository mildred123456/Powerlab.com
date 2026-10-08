<?php
// Biblioteca de ejercicios (deportista, solo lectura): buscador en vivo, filtros y scroll infinito.
// Todo se consulta en la base de datos; solo se muestran ejercicios activos.
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/ejercicios.php";
include_once "../models/catalogos.php";
include_once "ejercicio-tarjeta.php";

const POR_PAGINA = 24;

// Filtros iniciales desde la URL (para poder volver desde el detalle con el mismo estado)
$filtros = [
    "q" => mb_substr(trim((string) ($_GET["q"] ?? "")), 0, 100),
    "grupo" => (int) ($_GET["grupo"] ?? 0),
    "equipo" => (int) ($_GET["equipo"] ?? 0),
    "nivel" => (int) ($_GET["nivel"] ?? 0),
    "estado" => "activos",
];
$resultado = (new ejercicios())->buscar($filtros, 1, POR_PAGINA);

$catalogos = new catalogos();
$grupos = array_filter($catalogos->grupos(true), fn($g) => $g["ejercicios_activos"] > 0);
$equipos = array_filter($catalogos->equipamiento(true), fn($q) => $q["ejercicios_activos"] > 0);
$niveles = $catalogos->niveles();
$totalBiblioteca = array_sum(array_column($grupos, "ejercicios_activos"));

$tituloPagina = "Ejercicios";
$paginaActiva = "ejercicios";
$estilosExtra = ["../views/css/ejercicios.css"];
$scriptsExtra = ["../views/js/ejercicios.js"];
include "deportista-header.php";
?>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo">Ejercicios</h1>
    <p class="pl-page-header__sub"><?= $totalBiblioteca ?> ejercicios con técnica paso a paso para armar tus rutinas.</p>
  </div>
</header>

<?php if (!$totalBiblioteca): ?>
  <div class="pl-vacio">
    <div class="pl-vacio__icono"><i class="bi bi-collection"></i></div>
    <div class="pl-vacio__titulo">La biblioteca aún no tiene ejercicios</div>
    <p class="pl-vacio__texto mb-0">Vuelve pronto: estamos preparando el catálogo.</p>
  </div>
<?php else: ?>

  <!-- Filtros -->
  <section class="pl-ej-filtros" aria-label="Buscar y filtrar ejercicios" data-ej-filtros data-url="../controllers/consulta-ejercicios.php">
    <label for="buscarEjercicio" class="visually-hidden">Buscar ejercicio</label>
    <div class="pl-buscador mb-3">
      <i class="bi bi-search"></i>
      <input type="search" id="buscarEjercicio" class="form-control" placeholder="Busca: pecho, pierna, sentadilla, mancuernas…" autocomplete="off" maxlength="100" value="<?= e($filtros["q"]) ?>" data-ej-buscar>
    </div>

    <div class="pl-chips mb-3" role="group" aria-label="Grupo muscular" data-ej-grupos>
      <button type="button" class="pl-chip<?= $filtros["grupo"] ? "" : " active" ?>" data-valor="" aria-pressed="<?= $filtros["grupo"] ? "false" : "true" ?>">Todos</button>
      <?php foreach ($grupos as $g): $activo = (int) $g["id"] === $filtros["grupo"]; ?>
        <button type="button" class="pl-chip pl-chip--tono pl-tono-<?= (int) $g["tono"] ?><?= $activo ? " active" : "" ?>" data-valor="<?= (int) $g["id"] ?>" aria-pressed="<?= $activo ? "true" : "false" ?>">
          <i class="bi <?= e($g["icono"]) ?> me-1" aria-hidden="true"></i><?= e($g["nombre"]) ?><span class="pl-chip__cuenta"><?= (int) $g["ejercicios_activos"] ?></span>
        </button>
      <?php endforeach; ?>
    </div>

    <div class="row g-2 align-items-center">
      <div class="col-6 col-md-3">
        <label for="filtroEquipo" class="visually-hidden">Equipamiento</label>
        <select id="filtroEquipo" class="form-select" data-ej-equipo>
          <option value="">Equipamiento</option>
          <?php foreach ($equipos as $q): ?>
            <option value="<?= (int) $q["id"] ?>"<?= (int) $q["id"] === $filtros["equipo"] ? " selected" : "" ?>><?= e($q["nombre"]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-6 col-md-3">
        <label for="filtroNivel" class="visually-hidden">Nivel</label>
        <select id="filtroNivel" class="form-select" data-ej-nivel>
          <option value="">Nivel</option>
          <?php foreach ($niveles as $n): ?>
            <option value="<?= (int) $n["id"] ?>"<?= (int) $n["id"] === $filtros["nivel"] ? " selected" : "" ?>><?= e($n["nombre"]) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-6 d-flex align-items-center justify-content-between justify-content-md-end gap-3">
        <span class="small text-body-secondary fw-semibold" aria-live="polite"><span data-ej-contador><?= $resultado["total"] ?></span> ejercicios</span>
        <button type="button" class="btn btn-light border btn-sm" data-ej-limpiar hidden><i class="bi bi-x-lg me-1"></i>Limpiar filtros</button>
      </div>
    </div>
  </section>

  <!-- Resultados (la primera página la imprime el servidor; las siguientes llegan por JSON) -->
  <div class="row g-3" data-ej-lista data-pagina="<?= $resultado["pagina"] ?>" data-paginas="<?= $resultado["paginas"] ?>" aria-busy="false">
    <?php foreach ($resultado["items"] as $fila): ?>
      <div class="col-6 col-md-4 col-xl-3"><?= tarjetaEjercicio(ejercicios::tarjeta($fila)) ?></div>
    <?php endforeach; ?>
  </div>

  <div class="pl-ej-cargando" data-ej-cargando hidden>
    <span class="spinner-border spinner-border-sm me-2" aria-hidden="true"></span>Cargando ejercicios…
  </div>
  <div class="text-center mt-4">
    <button type="button" class="btn btn-light border px-4" data-ej-mas<?= $resultado["pagina"] < $resultado["paginas"] ? "" : " hidden" ?>>
      <i class="bi bi-arrow-down-circle me-2"></i>Cargar más
    </button>
  </div>
  <div data-ej-centinela aria-hidden="true"></div>

  <div class="pl-vacio mt-2" data-ej-vacio<?= $resultado["total"] ? " hidden" : "" ?>>
    <div class="pl-vacio__icono"><i class="bi bi-search"></i></div>
    <div class="pl-vacio__titulo">No encontramos ejercicios</div>
    <p class="pl-vacio__texto">Prueba con otra palabra o quita algún filtro.</p>
    <button type="button" class="btn btn-pl px-4" data-ej-limpiar>Limpiar filtros</button>
  </div>

  <?= plantillaTarjetaEjercicio() ?>

<?php endif; ?>

<?php include "deportista-footer.php"; ?>
