<?php
// Modo entrenamiento.
//   sin parámetros   -> elegir rutina, progreso de la semana e historial
//   ?rutina=ID       -> sesión guiada (serie a serie, descansos, hidratación) — la maneja views/js/entrenamiento.js
//   ?resumen=ID      -> resumen de una sesión guardada
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/rutinas.php";
include_once "../models/entrenamientos.php";
include_once "../models/preferencias.php";

$idUsuario = (int) $usuario["id"];
$preferencias = (new preferencias())->obtener($idUsuario);
$modeloRutinas = new rutinas();
$modeloEntrenos = new entrenamientos();

$idRutina = filter_var($_GET["rutina"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: null;
$idResumen = filter_var($_GET["resumen"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: null;

function duracionLegible($segundos) {
    $segundos = (int) $segundos;
    $h = intdiv($segundos, 3600);
    $m = intdiv($segundos % 3600, 60);
    return $h ? "{$h} h {$m} min" : max(1, $m) . " min";
}
function kilos($valor) {
    return rtrim(rtrim(number_format((float) $valor, 2, ",", "."), "0"), ",");
}

$modo = "elegir";
if ($idResumen) {
    $sesion = $modeloEntrenos->obtener($idResumen, $idUsuario);
    if (!$sesion) {
        redirigir("modo-entrenamiento.php", "warning", "No encontramos ese entrenamiento.");
    }
    $modo = "resumen";
    // Series agrupadas por ejercicio, en el orden en que se hicieron
    $porEjercicio = [];
    foreach ($sesion["series"] as $s) {
        $porEjercicio[$s["id_ejercicio"]]["nombre"] = $s["nombre_ejercicio"];
        $porEjercicio[$s["id_ejercicio"]]["series"][] = $s;
    }
} elseif ($idRutina) {
    $rutina = $modeloRutinas->obtener($idRutina, $idUsuario);
    if (!$rutina) {
        redirigir("modo-entrenamiento.php", "warning", "No encontramos esa rutina.");
    }
    $disponibles = array_values(array_filter($rutina["items"], fn($i) => $i["disponible"]));
    if (!$disponibles) {
        redirigir("rutina-form.php?id=$idRutina", "warning", "Esta rutina no tiene ejercicios disponibles. Agrega alguno para entrenarla.");
    }
    $modo = "entrenar";
    $ultimas = $modeloEntrenos->ultimasSeries($idUsuario, array_column($disponibles, "id_ejercicio"));
    $config = [
        "usuario" => $idUsuario,
        "rutina" => ["id" => (int) $rutina["id"], "nombre" => $rutina["nombre"]],
        "omitidos" => count($rutina["items"]) - count($disponibles),
        "items" => array_map(fn($i) => [
            "id_ejercicio" => $i["id_ejercicio"],
            "ejercicio" => $i["ejercicio"],
            "pasos" => $i["pasos"],
            "series" => $i["series"],
            "repeticiones" => $i["repeticiones"],
            "duracion_seg" => $i["duracion_seg"],
            "peso_kg" => $i["peso_kg"],
            "descanso_seg" => $i["descanso_seg"] ?? $preferencias["descanso_seg"],
            "notas" => $i["notas"],
            "ultima" => $ultimas[$i["id_ejercicio"]] ?? null,
        ], $disponibles),
        "preferencias" => $preferencias,
        "url_guardar" => "../controllers/guardar-entrenamiento.php",
        "url_salir" => "modo-entrenamiento.php",
    ];
} else {
    $misRutinas = $modeloRutinas->listar($idUsuario, false, $preferencias["descanso_seg"]);
    $recientes = $modeloEntrenos->recientes($idUsuario, 8);
    $semana = $modeloEntrenos->deEstaSemana($idUsuario);
    $totales = $modeloEntrenos->totales($idUsuario);
}

$tituloPagina = $modo === "entrenar" ? "Entrenando · " . $rutina["nombre"] : "Entrenar";
$paginaActiva = "entrenar";
$estilosExtra = ["../views/css/ejercicios.css", "../views/css/rutinas.css", "../views/css/entrenamiento.css"];
$scriptsExtra = $modo === "entrenar" ? ["../views/js/entrenamiento.js"] : [];
include "deportista-header.php";
?>

<?php if ($modo === "elegir"): ?>

  <header class="pl-page-header">
    <div>
      <h1 class="pl-page-header__titulo">Entrenar</h1>
      <p class="pl-page-header__sub">Elige una rutina y te guiamos serie a serie, con descansos y recordatorios.</p>
    </div>
  </header>

  <section class="row g-3 mb-4" aria-label="Tu progreso">
    <div class="col-6 col-lg-4 pl-reveal">
      <div class="pl-stat">
        <span class="pl-stat__icono"><i class="bi bi-calendar-week"></i></span>
        <span class="pl-stat__valor"><?= $semana ?><small class="pl-stat__unidad">/ <?= (int) $preferencias["meta_semanal"] ?></small></span>
        <span class="pl-stat__etiqueta">Entrenamientos esta semana</span>
        <div class="progress pl-meta-progreso mt-2" role="progressbar" aria-label="Meta semanal" aria-valuemin="0" aria-valuemax="<?= (int) $preferencias["meta_semanal"] ?>" aria-valuenow="<?= $semana ?>">
          <div class="progress-bar" style="width: <?= min(100, round($semana / max(1, $preferencias["meta_semanal"]) * 100)) ?>%"></div>
        </div>
      </div>
    </div>
    <div class="col-6 col-lg-4 pl-reveal">
      <div class="pl-stat">
        <span class="pl-stat__icono pl-stat__icono--exito"><i class="bi bi-trophy"></i></span>
        <span class="pl-stat__valor"><?= $totales["sesiones"] ?></span>
        <span class="pl-stat__etiqueta">Entrenamientos en total</span>
      </div>
    </div>
    <div class="col-12 col-lg-4 pl-reveal">
      <div class="pl-stat">
        <span class="pl-stat__icono pl-stat__icono--info"><i class="bi bi-stopwatch"></i></span>
        <span class="pl-stat__valor pl-stat__valor--texto"><?= $totales["segundos"] ? e(duracionLegible($totales["segundos"])) : "—" ?></span>
        <span class="pl-stat__etiqueta">Tiempo entrenado · <?= $totales["series"] ?> series</span>
      </div>
    </div>
  </section>

  <section class="mb-5" aria-labelledby="tituloElegir">
    <h2 class="pl-seccion-titulo" id="tituloElegir">
      <span><i class="bi bi-play-circle me-2 text-body-tertiary"></i>¿Qué entrenas hoy?</span>
      <a href="mis-rutinas.php">Mis rutinas <i class="bi bi-arrow-right"></i></a>
    </h2>
    <?php if (!$misRutinas): ?>
      <div class="pl-vacio pl-reveal">
        <div class="pl-vacio__icono"><i class="bi bi-journal-plus"></i></div>
        <div class="pl-vacio__titulo">Primero necesitas una rutina</div>
        <p class="pl-vacio__texto">Crea una o copia una plantilla en «Mis rutinas» y vuelve aquí para entrenarla.</p>
        <a href="mis-rutinas.php" class="btn btn-pl px-4">Ir a Mis rutinas</a>
      </div>
    <?php else: ?>
      <div class="row g-3">
        <?php foreach ($misRutinas as $r):
          $entrenables = (int) $r["num_ejercicios"] - (int) $r["no_disponibles"]; ?>
          <div class="col-md-6 col-xl-4 pl-reveal">
            <div class="pl-card pl-elegir-rutina">
              <div class="overflow-hidden">
                <div class="pl-elegir-rutina__nombre text-truncate"><?= e($r["nombre"]) ?></div>
                <div class="pl-elegir-rutina__meta">
                  <?= (int) $entrenables ?> ejercicios · ~<?= rutinas::minutos($r["segundos"]) ?> min
                  <?= $r["ultimo_entreno"] ? " · última: " . e(fechaLegible($r["ultimo_entreno"], true)) : "" ?>
                </div>
              </div>
              <?php if ($entrenables > 0): ?>
                <a href="modo-entrenamiento.php?rutina=<?= (int) $r["id"] ?>" class="btn btn-pl text-nowrap flex-shrink-0" aria-label="Empezar <?= e($r["nombre"]) ?>"><i class="bi bi-play-fill"></i><span class="ms-1">Empezar</span></a>
              <?php else: ?>
                <a href="rutina-form.php?id=<?= (int) $r["id"] ?>" class="btn btn-light border btn-sm">Sin ejercicios</a>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>

  <section aria-labelledby="tituloHistorialEntrenos">
    <h2 class="pl-seccion-titulo" id="tituloHistorialEntrenos"><span><i class="bi bi-clock-history me-2 text-body-tertiary"></i>Historial reciente</span></h2>
    <?php if (!$recientes): ?>
      <p class="text-body-secondary">Cuando termines tu primer entrenamiento, aparecerá aquí.</p>
    <?php else: ?>
      <div class="pl-card p-0">
        <ul class="list-unstyled mb-0">
          <?php foreach ($recientes as $s):
            [$dia, $mes] = explode(" ", fechaLegible($s["inicio"], true)); // "6 oct 2026" ?>
            <li>
              <a class="pl-historial-fila" href="modo-entrenamiento.php?resumen=<?= (int) $s["id"] ?>">
                <span class="pl-historial-fila__fecha"><span><?= e($dia) ?></span><?= e($mes) ?></span>
                <span class="flex-grow-1 overflow-hidden">
                  <span class="pl-historial-fila__nombre text-truncate d-block"><?= e($s["nombre_rutina"]) ?></span>
                  <span class="pl-historial-fila__meta"><?= e(duracionLegible($s["duracion_seg"])) ?> · <?= (int) $s["series_completadas"] ?> series<?= (float) $s["volumen_kg"] > 0 ? " · " . e(kilos($s["volumen_kg"])) . " kg levantados" : "" ?></span>
                </span>
                <i class="bi bi-chevron-right text-body-tertiary"></i>
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
    <?php endif; ?>
  </section>

<?php elseif ($modo === "resumen"): ?>

  <a href="modo-entrenamiento.php" class="pl-volver"><i class="bi bi-arrow-left"></i> Entrenar</a>
  <header class="pl-page-header">
    <div>
      <h1 class="pl-page-header__titulo"><?= e($sesion["nombre_rutina"]) ?></h1>
      <p class="pl-page-header__sub"><?= e(ucfirst(fechaLegible($sesion["inicio"]))) ?> · <?= e(date("H:i", strtotime($sesion["inicio"]))) ?></p>
    </div>
    <?php if ($sesion["id_rutina"] && $modeloRutinas->obtener((int) $sesion["id_rutina"], $idUsuario)): ?>
      <a href="modo-entrenamiento.php?rutina=<?= (int) $sesion["id_rutina"] ?>" class="btn btn-pl btn-lg"><i class="bi bi-arrow-repeat me-2"></i>Entrenar otra vez</a>
    <?php endif; ?>
  </header>

  <section class="row g-3 mb-4" aria-label="Resumen de la sesión">
    <div class="col-4"><div class="pl-stat"><span class="pl-stat__icono"><i class="bi bi-stopwatch"></i></span><span class="pl-stat__valor pl-stat__valor--texto"><?= e(duracionLegible($sesion["duracion_seg"])) ?></span><span class="pl-stat__etiqueta">Duración</span></div></div>
    <div class="col-4"><div class="pl-stat"><span class="pl-stat__icono pl-stat__icono--exito"><i class="bi bi-layers"></i></span><span class="pl-stat__valor"><?= (int) $sesion["series_completadas"] ?></span><span class="pl-stat__etiqueta">Series</span></div></div>
    <div class="col-4"><div class="pl-stat"><span class="pl-stat__icono pl-stat__icono--info"><i class="bi bi-box"></i></span><span class="pl-stat__valor"><?= (float) $sesion["volumen_kg"] > 0 ? e(kilos($sesion["volumen_kg"])) . '<small class="pl-stat__unidad">kg</small>' : "—" ?></span><span class="pl-stat__etiqueta">Volumen (reps × kg)</span></div></div>
  </section>

  <div class="row g-3">
    <?php foreach ($porEjercicio as $ej): ?>
      <div class="col-md-6 col-xl-4">
        <div class="pl-card h-100">
          <h2 class="h6 fw-bold mb-3"><?= e($ej["nombre"]) ?></h2>
          <ol class="pl-series-hechas">
            <?php foreach ($ej["series"] as $s): ?>
              <li>
                <span>Serie <?= (int) $s["numero_serie"] ?></span>
                <strong>
                  <?= $s["duracion_seg"] !== null ? e(rutinas::tiempo($s["duracion_seg"])) : (int) $s["repeticiones"] . " reps" ?>
                  <?= $s["peso_kg"] !== null && (float) $s["peso_kg"] > 0 ? " × " . e(kilos($s["peso_kg"])) . " kg" : "" ?>
                </strong>
              </li>
            <?php endforeach; ?>
          </ol>
        </div>
      </div>
    <?php endforeach; ?>
  </div>

<?php else: /* entrenar */ ?>

  <div class="pl-entreno" data-entreno data-config="<?= e(json_encode($config)) ?>">
    <?= csrfCampo() ?>
    <header class="pl-entreno__barra">
      <button type="button" class="btn btn-sm btn-light border" data-entreno-salir><i class="bi bi-x-lg me-1"></i>Salir</button>
      <div class="text-center overflow-hidden">
        <div class="pl-entreno__rutina text-truncate"><?= e($rutina["nombre"]) ?></div>
        <div class="pl-entreno__reloj" data-entreno-reloj aria-label="Tiempo transcurrido">0:00</div>
      </div>
      <button type="button" class="btn btn-sm btn-pl-outline" data-entreno-terminar>Terminar</button>
    </header>
    <div class="progress pl-entreno__progreso" role="progressbar" aria-label="Series completadas" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0" data-entreno-progreso>
      <div class="progress-bar"></div>
    </div>
    <p class="pl-entreno__progreso-texto" data-entreno-progreso-texto></p>

    <!-- Ejercicio actual -->
    <section class="pl-entreno__pantalla" data-pantalla="ejercicio" aria-live="polite">
      <div class="row g-4">
        <div class="col-lg-6">
          <div class="pl-ej-visual pl-ej-media" data-ej="visual">
            <img class="pl-ej-visual__img" alt="" data-ej="img" hidden>
            <i class="pl-ej-visual__icono bi" aria-hidden="true" data-ej="icono"></i>
          </div>
          <details class="pl-entreno__tecnica">
            <summary><i class="bi bi-list-check me-1"></i>Cómo hacerlo</summary>
            <ol data-entreno-pasos></ol>
          </details>
        </div>
        <div class="col-lg-6">
          <span class="pl-grupo" data-entreno-grupo></span>
          <h1 class="pl-entreno__nombre" data-entreno-nombre></h1>
          <div class="pl-entreno__serie" data-entreno-serie></div>
          <div class="pl-entreno__objetivo" data-entreno-objetivo></div>
          <div class="pl-entreno__ultima" data-entreno-ultima hidden></div>
          <div class="pl-entreno__nota" data-entreno-nota hidden><i class="bi bi-sticky me-1"></i><span></span></div>

          <div class="pl-entreno__registro">
            <div class="pl-stepper" data-registro="reps">
              <label for="entradaReps" class="form-label">Repeticiones hechas</label>
              <div class="pl-stepper__control">
                <button type="button" class="btn btn-light border" data-paso="reps" data-delta="-1" aria-label="Una repetición menos"><i class="bi bi-dash-lg"></i></button>
                <input type="number" id="entradaReps" class="form-control" min="0" max="500" inputmode="numeric" data-entrada="reps">
                <button type="button" class="btn btn-light border" data-paso="reps" data-delta="1" aria-label="Una repetición más"><i class="bi bi-plus-lg"></i></button>
              </div>
            </div>
            <div class="pl-cronometro" data-registro="tiempo" hidden>
              <div class="pl-cronometro__tiempo" data-crono aria-live="off">0:30</div>
              <button type="button" class="btn btn-pl-outline" data-crono-boton><i class="bi bi-play-fill me-1"></i>Iniciar</button>
            </div>
            <div class="pl-stepper">
              <label for="entradaPeso" class="form-label">Peso <span class="text-body-tertiary fw-normal">(kg, opcional)</span></label>
              <div class="pl-stepper__control">
                <button type="button" class="btn btn-light border" data-paso="peso" data-delta="-2.5" aria-label="2,5 kg menos"><i class="bi bi-dash-lg"></i></button>
                <input type="number" id="entradaPeso" class="form-control" min="0" max="500" step="0.5" inputmode="decimal" placeholder="—" data-entrada="peso">
                <button type="button" class="btn btn-light border" data-paso="peso" data-delta="2.5" aria-label="2,5 kg más"><i class="bi bi-plus-lg"></i></button>
              </div>
            </div>
          </div>

          <div class="d-grid gap-2 mt-3">
            <button type="button" class="btn btn-pl btn-lg" data-entreno-hecha><i class="bi bi-check2-circle me-2"></i>Serie completada</button>
            <button type="button" class="btn btn-light border" data-entreno-saltar><i class="bi bi-skip-forward me-1"></i>Saltar este ejercicio</button>
          </div>
          <div class="pl-entreno__siguiente" data-entreno-siguiente></div>
        </div>
      </div>
    </section>

    <!-- Descanso -->
    <section class="pl-entreno__pantalla pl-descanso" data-pantalla="descanso" hidden>
      <div class="pl-descanso__titulo">Descanso</div>
      <div class="pl-descanso__anillo">
        <svg viewBox="0 0 120 120" aria-hidden="true">
          <circle cx="60" cy="60" r="54" class="pl-descanso__fondo"></circle>
          <circle cx="60" cy="60" r="54" class="pl-descanso__avance" data-descanso-anillo></circle>
        </svg>
        <div class="pl-descanso__tiempo" data-descanso-tiempo role="timer" aria-live="off">1:30</div>
      </div>
      <div class="d-flex flex-wrap justify-content-center gap-2 mb-3">
        <button type="button" class="btn btn-light border" data-descanso-ajuste="-15">−15 s</button>
        <button type="button" class="btn btn-light border" data-descanso-ajuste="15">+15 s</button>
        <button type="button" class="btn btn-pl" data-descanso-saltar><i class="bi bi-skip-forward-fill me-1"></i>Saltar descanso</button>
      </div>
      <p class="pl-descanso__siguiente" data-descanso-siguiente></p>
    </section>

    <!-- Fin -->
    <section class="pl-entreno__pantalla pl-entreno__fin" data-pantalla="fin" hidden>
      <div class="pl-vacio__icono"><i class="bi bi-trophy"></i></div>
      <h1 class="pl-entreno__fin-titulo">¡Entrenamiento terminado!</h1>
      <div class="row g-3 my-3 justify-content-center">
        <div class="col-4 col-md-3"><div class="pl-stat"><span class="pl-stat__valor pl-stat__valor--texto" data-fin="duracion"></span><span class="pl-stat__etiqueta">Duración</span></div></div>
        <div class="col-4 col-md-3"><div class="pl-stat"><span class="pl-stat__valor" data-fin="series"></span><span class="pl-stat__etiqueta">Series</span></div></div>
        <div class="col-4 col-md-3"><div class="pl-stat"><span class="pl-stat__valor" data-fin="volumen"></span><span class="pl-stat__etiqueta">Kg levantados</span></div></div>
      </div>
      <p class="text-body-secondary" data-fin-texto></p>
      <div class="d-flex flex-wrap justify-content-center gap-2">
        <button type="button" class="btn btn-pl btn-lg px-4" data-entreno-guardar><i class="bi bi-cloud-check me-2"></i>Guardar entrenamiento</button>
        <button type="button" class="btn btn-light border btn-lg" data-entreno-continuar>Seguir entrenando</button>
        <button type="button" class="btn btn-link text-danger" data-entreno-descartar>Descartar</button>
      </div>
    </section>

    <!-- Recordatorio de hidratación -->
    <div class="pl-hidratacion" data-hidratacion hidden role="alert">
      <i class="bi bi-droplet-fill" aria-hidden="true"></i>
      <div><strong>Hora de hidratarte.</strong> Toma unos sorbos de agua.</div>
      <button type="button" class="btn btn-sm btn-light" data-hidratacion-ok>Listo</button>
    </div>
  </div>

<?php endif; ?>

<?php include "deportista-footer.php"; ?>
