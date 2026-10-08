<?php
// Panel de inicio del deportista
include_once "../controllers/sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/medidas.php";
include_once "../models/rutinas.php";
include_once "../models/entrenamientos.php";
include_once "../models/preferencias.php";

$idDeportista = (int) $usuario["id"];

// Entrenamiento: avance de la meta semanal, último entrenamiento y rutinas para empezar rápido
$preferencias = (new preferencias())->obtener($idDeportista);
$modeloEntrenos = new entrenamientos();
$entrenosSemana = $modeloEntrenos->deEstaSemana($idDeportista);
$ultimoEntreno = $modeloEntrenos->recientes($idDeportista, 1)[0] ?? null;
$rutinasInicio = array_slice(array_filter((new rutinas())->listar($idDeportista, false, $preferencias["descanso_seg"]),
                                          fn($r) => $r["num_ejercicios"] - $r["no_disponibles"] > 0), 0, 3);

// Progreso corporal a partir del historial de medidas
$historialMedidas = medidas::conDiferencias((new medidas())->ConsultaPorUsuario($idDeportista));
$ultimasMedidas = medidas::ultimosValores($historialMedidas);
$ultimoPeso = $ultimasMedidas["peso_kg"] ?? null;
$ultimaGrasa = $ultimasMedidas["grasa_pct"] ?? null;
$ultimaMedicion = $historialMedidas ? end($historialMedidas)["fecha"] : null;
$diasDesdeMedicion = $ultimaMedicion ? (new DateTime($ultimaMedicion))->diff(new DateTime("today"))->days : null;

function numeroCorto($valor) {
    return rtrim(rtrim(number_format((float) $valor, 2, ",", "."), "0"), ",");
}

$frases = [
    "La constancia vence al talento cuando el talento no es constante.",
    "Un entrenamiento a la vez. Una serie a la vez.",
    "No cuentes los días, haz que los días cuenten.",
    "El dolor de hoy es la fuerza de mañana.",
    "Tu único límite eres tú.",
];
$frase = $frases[(int) date("z") % count($frases)];

$tituloPagina = "Inicio";
$paginaActiva = "inicio";
$estilosExtra = ["../views/css/rutinas.css"];
include "deportista-header.php";
?>

<section class="pl-hero mb-4 pl-reveal">
  <div class="pl-hero__fecha"><?= e(fechaLegible(time())) ?></div>
  <h1 class="pl-hero__titulo">¡Hola, <?= e($usuario["nombre"]) ?>!</h1>
  <p class="pl-hero__frase"><?= e($frase) ?></p>
  <div class="d-flex flex-wrap gap-2">
    <?php if ($menuDeportista["entrenar"]["disponible"]): ?>
      <a href="<?= e($menuDeportista["entrenar"]["url"]) ?>" class="btn btn-pl btn-lg px-4"><i class="bi bi-lightning-charge-fill me-2"></i>Entrenar ahora</a>
    <?php else: ?>
      <a href="mis-medidas.php" class="btn btn-pl btn-lg px-4"><i class="bi bi-rulers me-2"></i>Registrar medidas</a>
    <?php endif; ?>
    <?php if ($menuDeportista["ejercicios"]["disponible"]): ?>
      <a href="<?= e($menuDeportista["ejercicios"]["url"]) ?>" class="btn btn-pl-fantasma btn-lg px-4"><i class="bi bi-collection me-2"></i>Ejercicios</a>
    <?php endif; ?>
  </div>
</section>

<!-- Resumen -->
<section class="row g-3 mb-4" aria-label="Resumen">
  <div class="col-6 col-lg-3 pl-reveal">
    <a href="mis-medidas.php" class="pl-stat pl-stat--enlace">
      <span class="pl-stat__icono"><i class="bi bi-speedometer2"></i></span>
      <?php if ($ultimoPeso): ?>
        <span class="pl-stat__valor"><?= e(numeroCorto($ultimoPeso["valor"])) ?><small class="pl-stat__unidad">kg</small></span>
        <span class="pl-stat__etiqueta">
          Último peso
          <?php if ($ultimoPeso["diferencia"] !== null && abs($ultimoPeso["diferencia"]) >= 0.005): ?>
            · <?= $ultimoPeso["diferencia"] > 0 ? "+" : "−" ?><?= e(numeroCorto(abs($ultimoPeso["diferencia"]))) ?> kg
          <?php endif; ?>
        </span>
      <?php else: ?>
        <span class="pl-stat__valor pl-stat__valor--texto">Registrar</span>
        <span class="pl-stat__etiqueta">Tu peso aún no está</span>
      <?php endif; ?>
    </a>
  </div>
  <div class="col-6 col-lg-3 pl-reveal">
    <div class="pl-stat">
      <span class="pl-stat__icono pl-stat__icono--info"><i class="bi bi-droplet-half"></i></span>
      <span class="pl-stat__valor"><?= $ultimaGrasa ? e(numeroCorto($ultimaGrasa["valor"])) . '<small class="pl-stat__unidad">%</small>' : "—" ?></span>
      <span class="pl-stat__etiqueta">Grasa corporal</span>
    </div>
  </div>
  <div class="col-6 col-lg-3 pl-reveal">
    <a href="modo-entrenamiento.php" class="pl-stat pl-stat--enlace">
      <span class="pl-stat__icono pl-stat__icono--exito"><i class="bi bi-calendar-week"></i></span>
      <span class="pl-stat__valor"><?= $entrenosSemana ?><small class="pl-stat__unidad">/ <?= (int) $preferencias["meta_semanal"] ?></small></span>
      <span class="pl-stat__etiqueta">
        Entrenamientos esta semana<?= $entrenosSemana >= $preferencias["meta_semanal"] ? " · ¡meta cumplida!" : "" ?>
      </span>
    </a>
  </div>
  <div class="col-6 col-lg-3 pl-reveal">
    <div class="pl-stat">
      <span class="pl-stat__icono pl-stat__icono--alerta"><i class="bi bi-calendar-check"></i></span>
      <span class="pl-stat__valor"><?= $diasDesdeMedicion !== null ? $diasDesdeMedicion : "—" ?></span>
      <span class="pl-stat__etiqueta"><?= $diasDesdeMedicion === 1 ? "Día" : "Días" ?> desde tu última medición</span>
    </div>
  </div>
</section>

<div class="row g-4">
  <!-- Progreso -->
  <section class="col-lg-7">
    <h2 class="pl-seccion-titulo">
      <span><i class="bi bi-activity me-2 text-body-tertiary"></i>Tu progreso</span>
      <?php if ($historialMedidas): ?><a href="mis-medidas.php">Ver medidas <i class="bi bi-arrow-right"></i></a><?php endif; ?>
    </h2>

    <?php if (!$historialMedidas): ?>
      <div class="pl-vacio pl-reveal">
        <div class="pl-vacio__icono"><i class="bi bi-rulers"></i></div>
        <div class="pl-vacio__titulo">Empieza registrando tus medidas</div>
        <p class="pl-vacio__texto">Tu peso y tus perímetros de hoy serán el punto de partida para ver tu evolución.</p>
        <a href="mis-medidas.php" class="btn btn-pl px-4">Registrar medidas</a>
      </div>
    <?php else: ?>
      <div class="pl-card pl-reveal">
        <div class="d-flex flex-wrap gap-3 justify-content-between align-items-start mb-3">
          <div>
            <div class="small text-body-secondary fw-semibold">Última medición</div>
            <div class="fw-bold fs-5"><?= e(fechaLegible($ultimaMedicion, true)) ?></div>
          </div>
          <span class="pl-etiqueta"><i class="bi bi-clock-history"></i><?= count($historialMedidas) ?> registro<?= count($historialMedidas) === 1 ? "" : "s" ?></span>
        </div>
        <div class="row g-3">
          <?php foreach (["cintura_cm", "cadera_cm", "pecho_cm", "brazo_der_cm"] as $campo):
            $dato = $ultimasMedidas[$campo] ?? null;
            [$etiqueta, $unidad] = medidas::CAMPOS[$campo]; ?>
            <div class="col-6 col-md-3">
              <div class="small text-body-secondary fw-semibold"><?= e($etiqueta) ?></div>
              <div class="fw-bold"><?= $dato ? e(numeroCorto($dato["valor"]) . " " . $unidad) : "—" ?></div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php if ($diasDesdeMedicion !== null && $diasDesdeMedicion >= 14): ?>
          <div class="pl-aviso pl-aviso--pendiente mt-3">
            <i class="bi bi-bell"></i>
            <div>Hace <?= $diasDesdeMedicion ?> días que no te mides. <a href="mis-medidas.php">Registra una medición nueva</a> para seguir tu evolución.</div>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </section>

  <!-- Rutinas y accesos -->
  <section class="col-lg-5">
    <h2 class="pl-seccion-titulo">
      <span><i class="bi bi-lightning-charge me-2 text-body-tertiary"></i>Entrena hoy</span>
      <a href="mis-rutinas.php">Mis rutinas <i class="bi bi-arrow-right"></i></a>
    </h2>
    <?php if ($rutinasInicio): ?>
      <div class="d-grid gap-2 mb-2">
        <?php foreach ($rutinasInicio as $r): ?>
          <div class="pl-card pl-elegir-rutina pl-reveal">
            <div class="overflow-hidden">
              <div class="pl-elegir-rutina__nombre text-truncate"><?= e($r["nombre"]) ?></div>
              <div class="pl-elegir-rutina__meta"><?= (int) $r["num_ejercicios"] ?> ejercicios · ~<?= rutinas::minutos($r["segundos"]) ?> min</div>
            </div>
            <a href="modo-entrenamiento.php?rutina=<?= (int) $r["id"] ?>" class="btn btn-pl btn-sm" aria-label="Entrenar <?= e($r["nombre"]) ?>"><i class="bi bi-play-fill"></i></a>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($ultimoEntreno): ?>
        <p class="small text-body-secondary mb-4"><i class="bi bi-check2-circle me-1 text-success"></i>Último entrenamiento: <a href="modo-entrenamiento.php?resumen=<?= (int) $ultimoEntreno["id"] ?>"><?= e($ultimoEntreno["nombre_rutina"]) ?></a>, <?= e(fechaLegible($ultimoEntreno["inicio"], true)) ?>.</p>
      <?php else: ?>
        <p class="small text-body-secondary mb-4">Aún no registras entrenamientos. ¡Empieza hoy!</p>
      <?php endif; ?>
    <?php else: ?>
      <div class="pl-card pl-reveal mb-4">
        <p class="mb-2 fw-semibold">Todavía no tienes rutinas.</p>
        <p class="small text-body-secondary">Copia una plantilla o crea la tuya para entrenar con guía serie a serie.</p>
        <a href="mis-rutinas.php" class="btn btn-pl btn-sm px-3">Ir a Mis rutinas</a>
      </div>
    <?php endif; ?>

    <h2 class="pl-seccion-titulo"><span><i class="bi bi-grid me-2 text-body-tertiary"></i>Accesos rápidos</span></h2>
    <div class="row g-3">
      <?php foreach (["rutinas", "entrenar", "ejercicios", "medidas", "configuracion"] as $clave):
        $item = $menuDeportista[$clave]; ?>
        <div class="col-6 pl-reveal">
          <a href="<?= $item["disponible"] ? e($item["url"]) : "#" ?>" class="pl-acceso<?= $item["disponible"] ? "" : " is-disabled" ?>"<?= $item["disponible"] ? "" : ' aria-disabled="true" tabindex="-1"' ?>>
            <span class="pl-acceso__icono"><i class="bi <?= e($item["icono"]) ?>"></i></span>
            <span class="overflow-hidden">
              <span class="pl-acceso__titulo text-truncate"><?= e($item["texto"]) ?></span>
              <span class="pl-acceso__desc"><?= $item["disponible"] ? "Abrir" : "Próximamente" ?></span>
            </span>
          </a>
        </div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<?php include "deportista-footer.php"; ?>
