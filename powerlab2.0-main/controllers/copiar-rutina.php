<?php
// Copia una plantilla (o una rutina propia) a "Mis rutinas" para poder editarla
include_once "sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/rutinas.php";

$lista = "../views/mis-rutinas.php";
if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($lista, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$modelo = new rutinas();
$idUsuario = (int) $usuario["id"];
if ($modelo->contar($idUsuario) >= rutinas::MAX_RUTINAS) {
    redirigir($lista, "warning", "Llegaste al máximo de " . rutinas::MAX_RUTINAS . " rutinas. Borra alguna que ya no uses.");
}

$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]);
$copia = $id ? $modelo->copiar($id, $idUsuario) : new Exception("No encontramos esa rutina.");
if ($copia instanceof Exception) {
    redirigir($lista, "danger", "No se pudo copiar la rutina.");
}

$aviso = $copia["omitidos"] ? " Se omitieron {$copia["omitidos"]} ejercicio(s) que ya no están disponibles." : "";
redirigir("../views/rutina-form.php?id=" . $copia["id"], "success", "Rutina copiada. Ajústala a tu gusto y guarda." . $aviso);
