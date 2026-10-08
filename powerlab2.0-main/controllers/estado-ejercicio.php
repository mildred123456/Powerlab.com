<?php
// Activa o desactiva un ejercicio (solo administrador). Nunca se borra, para no romper rutinas.
include_once "sesion-deportista.php";
requerirAdmin();

include_once "../models/ejercicios.php";

// Vuelve al listado conservando sus filtros (solo se acepta una URL del propio listado)
$volver = (string) ($_POST["volver"] ?? "");
$volver = preg_match('~^admin-ejercicios\.php(\?[A-Za-z0-9_=&%+.\-]*)?$~', $volver) ? "../views/" . $volver : "../views/admin-ejercicios.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$activo = ($_POST["activo"] ?? "") === "1";
$modelo = new ejercicios();
$ejercicio = $id ? $modelo->ConsultaEspecifica($id, false) : null;

if (!$ejercicio) {
    redirigir($volver, "danger", "No encontramos ese ejercicio.");
}

$modelo->CAMBIAR_ESTADO($id, $activo);
redirigir($volver, "success", "«" . $ejercicio["nombre"] . "» " . ($activo ? "está activo de nuevo." : "fue desactivado. Ya no aparece para los deportistas."));
