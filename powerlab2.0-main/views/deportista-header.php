<?php
// Layout del deportista (parte superior).
// Antes de incluirlo, la vista debe llamar a requerirDeportista() y puede definir:
//   $tituloPagina  -> texto del <title>
//   $paginaActiva  -> clave del menú resaltado (inicio, rutinas, entrenar, ejercicios, medidas, configuracion)
//   $estilosExtra  -> arreglo de hojas de estilo adicionales

// Menú del deportista. "disponible" en false muestra el módulo como "Pronto".
$menuDeportista = [
    "inicio"        => ["texto" => "Inicio",        "icono" => "bi-house-door",    "url" => "deportista.php",          "disponible" => true],
    "rutinas"       => ["texto" => "Mis rutinas",   "icono" => "bi-journal-text",  "url" => "mis-rutinas.php",         "disponible" => true],
    "entrenar"      => ["texto" => "Entrenar",      "icono" => "bi-stopwatch",     "url" => "modo-entrenamiento.php",  "disponible" => true],
    "ejercicios"    => ["texto" => "Ejercicios",    "icono" => "bi-collection",    "url" => "ejercicios.php",          "disponible" => true],
    "medidas"       => ["texto" => "Mis medidas",   "icono" => "bi-rulers",        "url" => "mis-medidas.php",         "disponible" => true],
    "configuracion" => ["texto" => "Configuración", "icono" => "bi-gear",          "url" => "configuracion.php",       "disponible" => true],
];

$menuApp = $menuDeportista;
$urlInicio = "deportista.php";
$etiquetaRol = "Deportista";
include "layout-header.php";
