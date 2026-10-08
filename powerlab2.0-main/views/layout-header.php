<?php
// Layout común de la app con sesión (parte superior). Lo usan deportista-header.php y admin-header.php.
// Variables que define el header de cada rol:
//   $menuApp     -> [clave => [texto, icono, url, disponible]]
//   $urlInicio   -> página de inicio del rol
//   $etiquetaRol -> texto bajo el nombre del usuario
// Variables que define cada vista:
//   $tituloPagina, $paginaActiva, $estilosExtra (opcional)

$tituloPagina = $tituloPagina ?? "Inicio";
$paginaActiva = $paginaActiva ?? "";
$estilosExtra = $estilosExtra ?? [];

$usuarioActual = $_SESSION["usuario"];
$nombreCompleto = trim(($usuarioActual["nombre"] ?? "") . " " . ($usuarioActual["apellido"] ?? ""));
$iniciales = mb_substr($usuarioActual["nombre"] ?? "", 0, 1) . mb_substr($usuarioActual["apellido"] ?? "", 0, 1);

// Pinta un enlace del menú lateral / offcanvas
function enlaceMenu($clave, $item, $paginaActiva) {
    $clases = "pl-nav-link" . ($clave === $paginaActiva ? " active" : "") . ($item["disponible"] ? "" : " is-disabled");
    $actual = $clave === $paginaActiva ? ' aria-current="page"' : "";
    $href = $item["disponible"] ? e($item["url"]) : "#";
    $extra = $item["disponible"] ? "" : ' aria-disabled="true" tabindex="-1"';
    $pronto = $item["disponible"] ? "" : '<span class="pl-badge-pronto">Pronto</span>';
    return '<a class="' . $clases . '" href="' . $href . '"' . $actual . $extra . '><i class="bi ' . e($item["icono"]) . '"></i><span>' . e($item["texto"]) . '</span>' . $pronto . '</a>';
}

// Pinta una pestaña de la barra inferior (móvil)
function pestaniaMenu($clave, $item, $paginaActiva, $principal = false) {
    $clases = "pl-tab" . ($principal ? " pl-tab--principal" : "") . ($clave === $paginaActiva ? " active" : "") . ($item["disponible"] ? "" : " is-disabled");
    $href = $item["disponible"] ? e($item["url"]) : "#";
    $extra = $item["disponible"] ? "" : ' aria-disabled="true" tabindex="-1"';
    $icono = '<i class="bi ' . e($item["icono"]) . '"></i>';
    if ($principal) {
        $icono = '<span class="pl-tab__circulo">' . $icono . '</span>';
    }
    return '<a class="' . $clases . '" href="' . $href . '"' . $extra . '>' . $icono . '<span>' . e($item["texto"]) . '</span></a>';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
  <meta name="theme-color" content="#15171c">
  <title><?= e($tituloPagina) ?> · PowerLab</title>
  <link rel="icon" href="../imagenes/logopower.png" type="image/png">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Oswald:wght@500;600&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="../views/css/deportista.css" rel="stylesheet">
<?php foreach ($estilosExtra as $hoja): ?>
  <link href="<?= e($hoja) ?>" rel="stylesheet">
<?php endforeach; ?>
</head>
<body class="pl-app">

  <!-- Sidebar (escritorio) -->
  <aside class="pl-sidebar d-none d-lg-flex" aria-label="Menú principal">
    <a href="<?= e($urlInicio) ?>" class="pl-sidebar__logo">
      <img src="../imagenes/logopower.png" alt="PowerLab">
    </a>
    <nav>
      <ul class="pl-sidebar__nav">
<?php foreach ($menuApp as $clave => $item): ?>
        <li><?= enlaceMenu($clave, $item, $paginaActiva) ?></li>
<?php endforeach; ?>
      </ul>
    </nav>
    <div class="pl-sidebar__pie">
      <div class="pl-usuario">
        <span class="pl-avatar"><?= e($iniciales) ?></span>
        <div class="overflow-hidden">
          <div class="pl-usuario__nombre text-truncate"><?= e($nombreCompleto) ?></div>
          <div class="pl-usuario__rol"><?= e($etiquetaRol) ?></div>
        </div>
      </div>
      <a class="pl-nav-link pl-nav-link--salir mt-1" href="../controllers/logout.php">
        <i class="bi bi-box-arrow-right"></i><span>Cerrar sesión</span>
      </a>
    </div>
  </aside>

  <div class="pl-contenido">

    <!-- Topbar (móvil) -->
    <header class="pl-topbar d-lg-none">
      <a href="<?= e($urlInicio) ?>" class="pl-topbar__logo"><img src="../imagenes/logopower.png" alt="PowerLab"></a>
      <button class="pl-avatar" type="button" data-bs-toggle="offcanvas" data-bs-target="#plMenuMas" aria-controls="plMenuMas" aria-label="Abrir menú de cuenta">
        <?= e($iniciales) ?>
      </button>
    </header>

    <main class="pl-main" id="contenido">
