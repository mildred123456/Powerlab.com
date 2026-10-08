<?php
// Layout del administrador (parte inferior). Variable opcional de la vista: $scriptsExtra

$pestanasApp = [
    ["ejercicios", "Ejercicios", false],
    ["nuevo-ejercicio", "Nuevo", true],
    ["catalogos", "Catálogos", false],
];
$scriptsExtra = array_merge(["../views/js/admin.js"], $scriptsExtra ?? []);
include "layout-footer.php";
