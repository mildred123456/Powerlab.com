<?php
// Registra una NUEVA medición corporal del deportista (nunca reemplaza las anteriores)
include_once "sesion-deportista.php";
$usuario = requerirDeportista();

include_once "validar-medida.php";

$volver = "../views/mis-medidas.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

[$fecha, $valores, $errores] = validarMedida($_POST, $usuario);

if ($errores) {
    foreach ($errores as $error) {
        flash("danger", $error);
    }
    redirigir($volver);
}

$respuesta = (new medidas())->REGISTRAR((int) $usuario["id"], $fecha, $valores);

if ($respuesta instanceof Exception) {
    redirigir($volver, "danger", "No se pudo guardar la medición. Inténtalo más tarde.");
}

redirigir($volver, "success", "¡Medición guardada! Sigue así.");
