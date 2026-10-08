<?php
// Guarda una sesión terminada del Modo entrenamiento (petición fetch del deportista).
// El cliente envía qué series hizo; los nombres y la pertenencia a la rutina se comprueban aquí.
include_once "sesion-deportista.php";
$usuario = requerirRolJson("deportista");

include_once "../models/rutinas.php";
include_once "../models/entrenamientos.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    responderJson(["ok" => false, "mensaje" => "Solicitud no válida. Recarga la página."], 400);
}

$idUsuario = (int) $usuario["id"];
$datos = json_decode((string) ($_POST["datos"] ?? ""), true);
$idRutina = (int) ($datos["id_rutina"] ?? 0);
$rutina = $idRutina ? (new rutinas())->obtener($idRutina, $idUsuario) : null;
if (!is_array($datos) || !$rutina) {
    responderJson(["ok" => false, "mensaje" => "No encontramos esa rutina."], 404);
}

// Ejercicios de la rutina: solo se aceptan series de estos, con su nombre real
$nombres = [];
foreach ($rutina["items"] as $item) {
    $nombres[$item["id_ejercicio"]] = $item["ejercicio"]["nombre"];
}

$entero = fn($v, $min, $max) => $v === null || $v === "" ? null : filter_var($v, FILTER_VALIDATE_INT, ["options" => ["min_range" => $min, "max_range" => $max]]);
$series = [];
foreach (array_slice(is_array($datos["series"] ?? null) ? $datos["series"] : [], 0, 600) as $s) {
    $idEjercicio = (int) ($s["id_ejercicio"] ?? 0);
    if (!isset($nombres[$idEjercicio])) {
        continue;
    }
    $peso = isset($s["peso_kg"]) && $s["peso_kg"] !== "" ? filter_var($s["peso_kg"], FILTER_VALIDATE_FLOAT) : null;
    $fila = [
        "id_ejercicio" => $idEjercicio,
        "nombre_ejercicio" => $nombres[$idEjercicio],
        "numero_serie" => $entero($s["numero_serie"] ?? null, 1, 20),
        "repeticiones" => $entero($s["repeticiones"] ?? null, 0, 500),
        "duracion_seg" => $entero($s["duracion_seg"] ?? null, 0, 3600),
        "peso_kg" => $peso !== false && $peso !== null && $peso >= 0 && $peso <= 500 ? round($peso, 2) : null,
    ];
    if (!$fila["numero_serie"] || $fila["repeticiones"] === false || $fila["duracion_seg"] === false) {
        continue;
    }
    $series[] = $fila;
}
if (!$series) {
    responderJson(["ok" => false, "mensaje" => "No registraste ninguna serie, así que no hay nada que guardar."], 422);
}

// Hora de inicio enviada por el navegador, acotada a un rango razonable (máx. 6 h)
$fin = time();
$inicio = (int) ($datos["inicio"] ?? $fin);
$inicio = min($fin, max($inicio, $fin - 6 * 3600));

$id = (new entrenamientos())->registrar($idUsuario, $idRutina, $rutina["nombre"], $inicio, $fin, $series);
if ($id instanceof Exception) {
    responderJson(["ok" => false, "mensaje" => "No se pudo guardar el entrenamiento. Inténtalo de nuevo."], 500);
}

flash("success", "¡Entrenamiento guardado! Buen trabajo.");
responderJson(["ok" => true, "id" => $id, "url" => "modo-entrenamiento.php?resumen=" . $id]);
