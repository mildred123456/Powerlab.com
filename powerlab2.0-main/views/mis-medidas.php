<?php
// Mis medidas: registro, historial, diferencias y gráfica de evolución
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/medidas.php";

$historial = medidas::conDiferencias((new medidas())->ConsultaPorUsuario((int) $usuario["id"]));
$ultimos = medidas::ultimosValores($historial);
$recientes = array_reverse($historial); // más reciente primero para la tabla
$campos = medidas::CAMPOS;

// IMC con el último peso y la última altura conocidos
$imc = null;
if (isset($ultimos["peso_kg"], $ultimos["altura_cm"])) {
    $imc = $ultimos["peso_kg"]["valor"] / (($ultimos["altura_cm"]["valor"] / 100) ** 2);
}

// Datos para la gráfica (una serie por campo, solo con los registros que tienen valor)
$datosGrafica = ["campos" => [], "series" => []];
foreach ($campos as $campo => [$etiqueta, $unidad]) {
    $datosGrafica["campos"][$campo] = ["etiqueta" => $etiqueta, "unidad" => $unidad];
    $datosGrafica["series"][$campo] = [];
    foreach ($historial as $fila) {
        if ($fila[$campo] !== null) {
            $datosGrafica["series"][$campo][] = ["fecha" => fechaLegible($fila["fecha"], true), "valor" => (float) $fila[$campo]];
        }
    }
}

// Número en formato local: 65,1 (sin ceros sobrantes)
function numero($valor) {
    $texto = number_format((float) $valor, 2, ",", ".");
    return rtrim(rtrim($texto, "0"), ",");
}

// Insignia de diferencia respecto a la medición anterior: +1 kg / -1 cm / = 0
function diferencia($valor, $unidad) {
    if ($valor === null) {
        return "";
    }
    if (abs($valor) < 0.005) {
        return '<span class="pl-diff pl-diff--igual" title="Sin cambio respecto a la medición anterior">= 0 ' . e($unidad) . '</span>';
    }
    $sube = $valor > 0;
    return '<span class="pl-diff ' . ($sube ? "pl-diff--sube" : "pl-diff--baja") . '" title="Respecto a la medición anterior">'
        . '<i class="bi ' . ($sube ? "bi-arrow-up-short" : "bi-arrow-down-short") . '" aria-hidden="true"></i>'
        . ($sube ? "+" : "−") . numero(abs($valor)) . " " . e($unidad) . "</span>";
}

$resumen = ["peso_kg", "grasa_pct", "cintura_cm", "cadera_cm"];
$iconosResumen = ["peso_kg" => "bi-speedometer2", "grasa_pct" => "bi-droplet-half", "cintura_cm" => "bi-rulers", "cadera_cm" => "bi-arrows-expand"];
$grupos = [];
foreach ($campos as $campo => $info) {
    $grupos[$info[4]][$campo] = $info;
}

$tituloPagina = "Mis medidas";
$paginaActiva = "medidas";
$estilosExtra = ["../views/css/medidas.css"];
$scriptsExtra = ["https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js", "../views/js/medidas.js"];
include "deportista-header.php";
?>

<header class="pl-page-header">
  <div>
    <h1 class="pl-page-header__titulo">Mis medidas</h1>
    <p class="pl-page-header__sub">Registra tu progreso cuando quieras. Cada medición se guarda en tu historial.</p>
  </div>
  <button type="button" class="btn btn-pl btn-lg" data-medida-nueva>
    <i class="bi bi-plus-lg me-2"></i>Nueva medición
  </button>
</header>

<?php if (!$historial): ?>
  <div class="pl-vacio pl-reveal">
    <div class="pl-vacio__icono"><i class="bi bi-rulers"></i></div>
    <div class="pl-vacio__titulo">Aún no tienes mediciones</div>
    <p class="pl-vacio__texto">Registra tu peso y tus perímetros para empezar a ver tu evolución en gráficas.</p>
    <button type="button" class="btn btn-pl px-4" data-medida-nueva>Registrar mi primera medición</button>
  </div>
<?php else: ?>

  <!-- Resumen -->
  <section class="row g-3 mb-4" aria-label="Últimas medidas">
    <?php foreach ($resumen as $campo):
      [$etiqueta, $unidad] = $campos[$campo];
      $dato = $ultimos[$campo] ?? null; ?>
      <div class="col-6 col-lg pl-reveal">
        <div class="pl-stat">
          <span class="pl-stat__icono"><i class="bi <?= $iconosResumen[$campo] ?>"></i></span>
          <span class="pl-stat__valor"><?= $dato ? numero($dato["valor"]) . '<small class="pl-medida__unidad">' . e($unidad) . "</small>" : "—" ?></span>
          <span class="pl-stat__etiqueta"><?= e($etiqueta) ?></span>
          <?= $dato ? diferencia($dato["diferencia"], $unidad) : "" ?>
        </div>
      </div>
    <?php endforeach; ?>
    <div class="col-6 col-lg pl-reveal">
      <div class="pl-stat">
        <span class="pl-stat__icono pl-stat__icono--info"><i class="bi bi-calculator"></i></span>
        <span class="pl-stat__valor"><?= $imc ? numero(round($imc, 1)) : "—" ?></span>
        <span class="pl-stat__etiqueta">IMC <span class="text-body-tertiary">(peso / altura²)</span></span>
      </div>
    </div>
  </section>

  <!-- Gráfica -->
  <section class="pl-card mb-4 pl-reveal" aria-labelledby="tituloGrafica">
    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
      <div>
        <h2 class="pl-seccion-titulo mb-1" id="tituloGrafica">Evolución</h2>
        <div class="pl-grafica__resumen" data-grafica-resumen aria-live="polite"></div>
      </div>
      <div class="pl-grafica__selector">
        <label for="selectorMedida" class="form-label mb-1">Medida a graficar</label>
        <select id="selectorMedida" class="form-select" data-grafica-selector>
          <?php foreach ($grupos as $grupo => $camposGrupo): ?>
            <optgroup label="<?= e($grupo) ?>">
              <?php foreach ($camposGrupo as $campo => $info):
                $puntos = count($datosGrafica["series"][$campo]); ?>
                <option value="<?= e($campo) ?>" <?= $campo === "peso_kg" ? "selected" : "" ?> <?= $puntos ? "" : "disabled" ?>>
                  <?= e($info[0]) ?><?= $puntos ? "" : " (sin datos)" ?>
                </option>
              <?php endforeach; ?>
            </optgroup>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="pl-grafica" data-grafica="<?= e(json_encode($datosGrafica)) ?>">
      <canvas id="graficaMedidas" role="img" aria-label="Gráfica de evolución de la medida seleccionada"></canvas>
    </div>
    <p class="pl-grafica__aviso pl-oculto" data-grafica-aviso>
      <i class="bi bi-info-circle me-1"></i>Registra otra medición para ver la línea de evolución.
    </p>
  </section>

  <!-- Historial -->
  <section aria-labelledby="tituloHistorial">
    <h2 class="pl-seccion-titulo" id="tituloHistorial">
      <span><i class="bi bi-clock-history me-2 text-body-tertiary"></i>Historial</span>
      <span class="small text-body-secondary fw-semibold"><?= count($historial) ?> medicion<?= count($historial) === 1 ? "" : "es" ?></span>
    </h2>

    <!-- Escritorio: tabla -->
    <div class="pl-card p-0 d-none d-md-block pl-reveal">
      <div class="table-responsive pl-tabla-medidas">
        <table class="table align-middle mb-0">
          <thead>
            <tr>
              <th scope="col">Fecha</th>
              <?php foreach ($campos as $campo => [$etiqueta, $unidad]): ?>
                <th scope="col"><?= e($etiqueta) ?> <span class="pl-medida__unidad"><?= e($unidad) ?></span></th>
              <?php endforeach; ?>
              <th scope="col"><span class="visually-hidden">Acciones</span></th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($recientes as $fila): ?>
              <tr>
                <th scope="row"><?= e(fechaLegible($fila["fecha"], true)) ?></th>
                <?php foreach ($campos as $campo => [$etiqueta, $unidad]): ?>
                  <td>
                    <?php if ($fila[$campo] === null): ?>
                      <span class="text-body-tertiary">—</span>
                    <?php else: ?>
                      <span class="pl-medida__valor"><?= numero($fila[$campo]) ?></span>
                      <?= diferencia($fila["diferencias"][$campo] ?? null, $unidad) ?>
                    <?php endif; ?>
                  </td>
                <?php endforeach; ?>
                <td>
                  <button type="button" class="btn btn-sm btn-light border" data-medida-editar="<?= e(json_encode($fila)) ?>" aria-label="Editar medición del <?= e(fechaLegible($fila["fecha"], true)) ?>">
                    <i class="bi bi-pencil"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Móvil: tarjetas -->
    <div class="d-md-none d-flex flex-column gap-3" id="listaMedidasMovil">
      <?php foreach ($recientes as $i => $fila): ?>
        <article class="pl-card pl-medida-card<?= $i >= 5 ? " pl-oculto" : "" ?>" <?= $i >= 5 ? "data-medida-extra" : "" ?>>
          <div class="d-flex align-items-center justify-content-between mb-3">
            <div class="fw-bold"><i class="bi bi-calendar3 me-2 text-body-tertiary"></i><?= e(fechaLegible($fila["fecha"], true)) ?></div>
            <button type="button" class="btn btn-sm btn-light border" data-medida-editar="<?= e(json_encode($fila)) ?>">
              <i class="bi bi-pencil me-1"></i>Editar
            </button>
          </div>
          <dl class="pl-medida-card__grid">
            <?php foreach ($campos as $campo => [$etiqueta, $unidad]):
              if ($fila[$campo] === null) continue; ?>
              <div>
                <dt><?= e($etiqueta) ?></dt>
                <dd><span class="pl-medida__valor"><?= numero($fila[$campo]) ?></span> <span class="pl-medida__unidad"><?= e($unidad) ?></span>
                  <?= diferencia($fila["diferencias"][$campo] ?? null, $unidad) ?></dd>
              </div>
            <?php endforeach; ?>
          </dl>
        </article>
      <?php endforeach; ?>
      <?php if (count($recientes) > 5): ?>
        <button type="button" class="btn btn-light border w-100" data-medida-ver-mas>
          <i class="bi bi-chevron-down me-1"></i>Ver <?= count($recientes) - 5 ?> mediciones más
        </button>
      <?php endif; ?>
    </div>
  </section>

<?php endif; ?>

<!-- Modal: nueva / editar medición -->
<div class="modal fade" id="modalMedida" tabindex="-1" aria-labelledby="modalMedidaTitulo" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable modal-fullscreen-sm-down">
    <form class="modal-content needs-validation" method="POST" action="../controllers/registrar-medida.php" novalidate
          data-accion-nueva="../controllers/registrar-medida.php" data-accion-editar="../controllers/actualizar-medida.php"
          data-altura-previa="<?= isset($ultimos["altura_cm"]) ? e($ultimos["altura_cm"]["valor"]) : "" ?>">
      <?= csrfCampo() ?>
      <input type="hidden" name="id" value="">
      <div class="modal-header">
        <h2 class="modal-title h5" id="modalMedidaTitulo">Nueva medición</h2>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>
      <div class="modal-body">
        <div class="pl-aviso pl-aviso--pendiente mb-3 pl-oculto" data-aviso-edicion>
          <i class="bi bi-pencil-square"></i>
          <div>Estás <strong>corrigiendo</strong> una medición existente. Para registrar tu progreso usa «Nueva medición».</div>
        </div>

        <div class="row g-3 mb-2">
          <div class="col-sm-6">
            <label for="campoFecha" class="form-label">Fecha</label>
            <input type="date" class="form-control" id="campoFecha" name="fecha" required max="<?= date("Y-m-d") ?>" min="<?= e($usuario["fecha_nacimiento"] ?? "1900-01-01") ?>">
            <div class="invalid-feedback">Elige una fecha válida (no futura).</div>
          </div>
          <div class="col-sm-6 d-flex align-items-end">
            <p class="small text-body-secondary mb-2"><i class="bi bi-info-circle me-1"></i>Llena solo las medidas que tomaste hoy.</p>
          </div>
        </div>

        <?php foreach ($grupos as $grupo => $camposGrupo): ?>
          <fieldset class="pl-grupo-medidas">
            <legend><?= e($grupo) ?></legend>
            <div class="row g-3">
              <?php foreach ($camposGrupo as $campo => [$etiqueta, $unidad, $min, $max]): ?>
                <div class="col-6 col-md-4">
                  <label for="campo_<?= e($campo) ?>" class="form-label"><?= e($etiqueta) ?></label>
                  <div class="input-group">
                    <input type="number" class="form-control" id="campo_<?= e($campo) ?>" name="<?= e($campo) ?>"
                           min="<?= $min ?>" max="<?= $max ?>" step="<?= $campo === "peso_kg" ? "0.01" : "0.1" ?>" inputmode="decimal" data-campo-medida>
                    <span class="input-group-text"><?= e($unidad) ?></span>
                    <div class="invalid-feedback">Entre <?= $min ?> y <?= $max ?> <?= e($unidad) ?>.</div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </fieldset>
        <?php endforeach; ?>
        <div class="text-danger small fw-semibold pl-oculto" data-aviso-vacio><i class="bi bi-exclamation-circle me-1"></i>Registra al menos una medida.</div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-pl px-4"><i class="bi bi-check2 me-2"></i><span data-texto-guardar>Guardar medición</span></button>
      </div>
    </form>
  </div>
</div>

<?php include "deportista-footer.php"; ?>
