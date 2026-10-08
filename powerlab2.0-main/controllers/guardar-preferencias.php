<?php
// Guarda las preferencias del deportista (descanso, hidratación, sonido, vibración, meta semanal)
include_once "sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/preferencias.php";

$volver = "../views/configuracion.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$casillas = ["hidratacion_activa", "sonido", "vibracion"];
$datos = [];
foreach (preferencias::CAMPOS as $campo => [$defecto, $min, $max]) {
    if (in_array($campo, $casillas, true)) {
        $datos[$campo] = isset($_POST[$campo]) ? 1 : 0;
        continue;
    }
    $valor = filter_var($_POST[$campo] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => $min, "max_range" => $max]]);
    if ($valor === false) {
        redirigir($volver, "danger", "Revisa los valores: hay uno fuera de rango.");
    }
    $datos[$campo] = $valor;
}

$resultado = (new preferencias())->guardar((int) $usuario["id"], $datos);
redirigir($volver, $resultado instanceof Exception ? "danger" : "success",
          $resultado instanceof Exception ? "No se pudieron guardar tus preferencias." : "Preferencias guardadas.");
