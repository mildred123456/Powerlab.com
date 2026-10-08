<?php
// Layout común de la app con sesión (parte inferior).
// Variables que define el footer de cada rol:
//   $pestanasApp -> pestañas de la barra inferior móvil: [[clave, texto corto, principal?], ...]
// Variables opcionales de la vista:
//   $scriptsExtra -> scripts adicionales

$scriptsExtra = $scriptsExtra ?? [];
$mensajesFlash = obtenerFlash();
$clavesEnPestanas = array_column($pestanasApp, 0);
$masActivo = $paginaActiva !== "" && !in_array($paginaActiva, $clavesEnPestanas, true) && isset($menuApp[$paginaActiva]);
?>
    </main>

    <footer class="pl-footer">
      © <?= date("Y") ?> PowerLab · Diseñado con enfoque en el rendimiento deportivo.
    </footer>
  </div>

  <!-- Barra inferior (móvil) -->
  <nav class="pl-tabbar d-lg-none" aria-label="Navegación principal">
<?php foreach ($pestanasApp as [$clave, $textoCorto, $principal]): ?>
    <?= pestaniaMenu($clave, array_merge($menuApp[$clave], ["texto" => $textoCorto]), $paginaActiva, $principal) ?>
<?php endforeach; ?>
    <button class="pl-tab<?= $masActivo ? " active" : "" ?>" type="button" data-bs-toggle="offcanvas" data-bs-target="#plMenuMas" aria-controls="plMenuMas">
      <i class="bi bi-grid"></i><span>Más</span>
    </button>
  </nav>

  <!-- Menú "Más" (móvil) -->
  <div class="offcanvas offcanvas-bottom pl-offcanvas" tabindex="-1" id="plMenuMas" aria-labelledby="plMenuMasTitulo">
    <div class="offcanvas-header">
      <div class="pl-usuario p-0">
        <span class="pl-avatar"><?= e($iniciales) ?></span>
        <div>
          <div class="pl-usuario__nombre" id="plMenuMasTitulo"><?= e($nombreCompleto) ?></div>
          <div class="pl-usuario__rol"><?= e($etiquetaRol) ?></div>
        </div>
      </div>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body pt-0">
<?php foreach ($menuApp as $clave => $item): ?>
      <?= enlaceMenu($clave, $item, $paginaActiva) ?>
<?php endforeach; ?>
      <hr>
      <a class="pl-nav-link pl-nav-link--salir" href="../controllers/logout.php">
        <i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span>
      </a>
    </div>
  </div>

  <!-- Contenedor de toasts + mensajes pendientes del servidor -->
  <div class="pl-toasts" id="plToasts" aria-live="polite" aria-atomic="true"></div>
  <div id="plFlash" hidden data-mensajes="<?= e(json_encode($mensajesFlash)) ?>"></div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="../views/js/deportista.js"></script>
<?php foreach ($scriptsExtra as $script): ?>
  <script src="<?= e($script) ?>"></script>
<?php endforeach; ?>
</body>
</html>
