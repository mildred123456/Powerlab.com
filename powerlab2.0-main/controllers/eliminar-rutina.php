<?php
// Borra una rutina propia. El historial de entrenamientos se conserva (guarda el nombre de la rutina).
include_once "sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/rutinas.php";

$lista = "../views/mis-rutinas.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($lista, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
if (!$id || !(new rutinas())->eliminar($id, (int) $usuario["id"])) {
    redirigir($lista, "danger", "No encontramos esa rutina.");
}
redirigir($lista, "success", "Rutina eliminada. Tu historial de entrenamientos se conserva.");
