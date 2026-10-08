<?php
// Layout del deportista (parte inferior).
// Variable opcional de la vista: $scriptsExtra -> scripts adicionales

// Pestañas de la barra inferior móvil: [clave del menú, texto corto, ¿botón central?]
$pestanasApp = [
    ["inicio", "Inicio", false],
    ["rutinas", "Rutinas", false],
    ["entrenar", "Entrenar", true],
    ["medidas", "Medidas", false],
];
include "layout-footer.php";
