<?php
// Layout del administrador (parte superior). La vista debe llamar antes a requerirAdmin().
// El administrador solo gestiona la biblioteca de ejercicios y sus catálogos.

$menuApp = [
    "ejercicios"      => ["texto" => "Ejercicios",      "icono" => "bi-collection",  "url" => "admin-ejercicios.php",    "disponible" => true],
    "nuevo-ejercicio" => ["texto" => "Nuevo ejercicio", "icono" => "bi-plus-square", "url" => "admin-ejercicio-form.php", "disponible" => true],
    "catalogos"       => ["texto" => "Catálogos",       "icono" => "bi-tags",        "url" => "admin-catalogos.php",     "disponible" => true],
];
$urlInicio = "admin-ejercicios.php";
$etiquetaRol = "Administrador";
$estilosExtra = array_merge(["../views/css/admin.css"], $estilosExtra ?? []);
include "layout-header.php";
