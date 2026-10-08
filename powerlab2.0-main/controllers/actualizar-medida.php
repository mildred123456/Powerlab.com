<?php
// Corrige una medición existente del deportista (solo las suyas)
include_once "sesion-deportista.php";
$usuario = requerirDeportista();

include_once "validar-medida.php";

$volver = "../views/mis-medidas.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$modelo = new medidas();

// Verifica que la medición exista y sea del usuario en sesión
if ($id === false || !$modelo->ConsultaEspecifica($id, (int) $usuario["id"])) {
    redirigir($volver, "danger", "No encontramos esa medición.");
}

[$fecha, $valores, $errores] = validarMedida($_POST, $usuario);

if ($errores) {
    foreach ($errores as $error) {
        flash("danger", $error);
    }
    redirigir($volver);
}

$respuesta = $modelo->ACTUALIZAR($id, (int) $usuario["id"], $fecha, $valores);

if ($respuesta instanceof Exception) {
    redirigir($volver, "danger", "No se pudo actualizar la medición. Inténtalo más tarde.");
}

redirigir($volver, "success", "Medición actualizada.");
