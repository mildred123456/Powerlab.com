<?php
// Activa o desactiva un grupo muscular o un equipamiento (solo administrador).
// No se permite desactivar si hay ejercicios activos que lo usan.
include_once "sesion-deportista.php";
requerirAdmin();

include_once "../models/catalogos.php";

$volver = "../views/admin-catalogos.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$tipo = $_POST["catalogo"] ?? "";
$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$activo = ($_POST["activo"] ?? "") === "1";
$catalogos = new catalogos();
$registro = $id && $tipo === "grupo" ? $catalogos->grupo($id) : ($id && $tipo === "equipo" ? $catalogos->equipo($id) : null);

if (!$registro) {
    redirigir($volver, "danger", "No encontramos ese registro.");
}

$resultado = $catalogos->cambiarEstado($tipo, $id, $activo);

if (is_array($resultado) && isset($resultado["bloqueado"])) {
    $n = $resultado["bloqueado"];
    redirigir($volver, "warning", "No se puede desactivar «" . $registro["nombre"] . "». Primero mueve o desactiva sus $n ejercicio" . ($n === 1 ? "" : "s") . ".");
}
if ($resultado !== true) {
    redirigir($volver, "danger", "No se pudo cambiar el estado.");
}

redirigir($volver, "success", "«" . $registro["nombre"] . "» " . ($activo ? "activado." : "desactivado."));
