<?php
// Crea o edita una rutina del deportista: nombre, descripción y ejercicios en orden
// (series, repeticiones o segundos, peso, descanso y notas por ejercicio)
include_once "sesion-deportista.php";
$usuario = requerirDeportista();

include_once "../models/rutinas.php";

$idUsuario = (int) $usuario["id"];
$lista = "../views/mis-rutinas.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($lista, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$modelo = new rutinas();
$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: null;
$actual = $id ? $modelo->obtener($id, $idUsuario) : null;
if ($id && !$actual) {
    redirigir($lista, "danger", "No encontramos esa rutina.");
}
if (!$id && $modelo->contar($idUsuario) >= rutinas::MAX_RUTINAS) {
    redirigir($lista, "warning", "Llegaste al máximo de " . rutinas::MAX_RUTINAS . " rutinas. Borra alguna que ya no uses.");
}
$volver = "../views/rutina-form.php" . ($id ? "?id=$id" : "");

// ---- Datos generales ----
$limpiar = fn($texto) => trim(preg_replace('/\s+/u', " ", (string) $texto));
$nombre = $limpiar($_POST["nombre"] ?? "");
$descripcion = $limpiar($_POST["descripcion"] ?? "") ?: null;

$errores = [];
if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 80) {
    $errores[] = "El nombre debe tener entre 2 y 80 caracteres.";
}
if ($descripcion !== null && mb_strlen($descripcion) > 255) {
    $errores[] = "La descripción no puede superar 255 caracteres.";
}

// ---- Ejercicios ----
$entrada = is_array($_POST["items"] ?? null) ? array_values(array_filter($_POST["items"], "is_array")) : [];
if (!$entrada) {
    $errores[] = "Agrega al menos un ejercicio.";
} elseif (count($entrada) > rutinas::MAX_EJERCICIOS) {
    $errores[] = "Una rutina puede tener como máximo " . rutinas::MAX_EJERCICIOS . " ejercicios.";
}

$estado = $modelo->estadoEjercicios(array_column($entrada, "id_ejercicio"));     // [id => activo]
$yaEstaban = $actual ? array_column($actual["items"], "id_ejercicio") : [];      // los inactivos solo si ya estaban
$entero = fn($valor, $min, $max) => filter_var($valor, FILTER_VALIDATE_INT, ["options" => ["min_range" => $min, "max_range" => $max]]);
$vacio = fn($valor) => trim((string) $valor) === "";

$items = [];   // validados, para guardar
$previos = []; // lo que escribió el usuario, para devolverlo al formulario si hay errores
foreach (array_slice($entrada, 0, rutinas::MAX_EJERCICIOS) as $n => $fila) {
    $pos = $n + 1;
    $idEjercicio = (int) ($fila["id_ejercicio"] ?? 0);
    $porTiempo = ($fila["meta"] ?? "") === "tiempo";
    $peso = $vacio($fila["peso_kg"] ?? "") ? null : filter_var(str_replace(",", ".", trim((string) $fila["peso_kg"])), FILTER_VALIDATE_FLOAT);
    $descanso = $vacio($fila["descanso_seg"] ?? "") ? null : $entero(trim((string) $fila["descanso_seg"]), 0, 600);
    $item = [
        "id_ejercicio" => $idEjercicio,
        "series" => $entero($fila["series"] ?? null, 1, 20),
        "repeticiones" => $porTiempo ? null : $entero($fila["valor"] ?? null, 1, 200),
        "duracion_seg" => $porTiempo ? $entero($fila["valor"] ?? null, 5, 3600) : null,
        "peso_kg" => $peso,
        "descanso_seg" => $descanso,
        "notas" => mb_substr($limpiar($fila["notas"] ?? ""), 0, 255),
    ];
    $valorEscrito = (int) ($fila["valor"] ?? 0) ?: null;
    $previos[] = [
        "id_ejercicio" => $idEjercicio,
        "series" => (int) ($fila["series"] ?? 0) ?: 3,
        "repeticiones" => $porTiempo ? null : $valorEscrito,
        "duracion_seg" => $porTiempo ? ($valorEscrito ?? 30) : null,
        "peso_kg" => is_float($peso) ? $peso : null,
        "descanso_seg" => is_int($descanso) ? $descanso : null,
        "notas" => $item["notas"],
    ];

    if (!isset($estado[$idEjercicio])) {
        $errores[] = "El ejercicio #$pos ya no existe en la biblioteca.";
        continue;
    }
    if (!$estado[$idEjercicio] && !in_array($idEjercicio, $yaEstaban, true)) {
        $errores[] = "El ejercicio #$pos ya no está disponible; elige otro.";
        continue;
    }
    if ($item["series"] === false) {
        $errores[] = "Ejercicio #$pos: las series deben estar entre 1 y 20.";
    }
    if ($porTiempo && $item["duracion_seg"] === false) {
        $errores[] = "Ejercicio #$pos: la duración debe estar entre 5 y 3600 segundos.";
    }
    if (!$porTiempo && $item["repeticiones"] === false) {
        $errores[] = "Ejercicio #$pos: las repeticiones deben estar entre 1 y 200.";
    }
    if ($peso === false || ($peso !== null && ($peso < 0 || $peso > 500))) {
        $errores[] = "Ejercicio #$pos: el peso debe estar entre 0 y 500 kg.";
    }
    if ($descanso === false) {
        $errores[] = "Ejercicio #$pos: el descanso debe estar entre 0 y 600 segundos.";
    }
    $items[] = $item;
}

if ($errores) {
    foreach (array_unique($errores) as $error) {
        flash("danger", $error);
    }
    $_SESSION["rutina_previa"] = ["nombre" => $nombre, "descripcion" => $descripcion, "items" => $previos];
    redirigir($volver);
}

$resultado = $modelo->guardar($id, $idUsuario, $nombre, $descripcion, $items);
if ($resultado instanceof Exception) {
    $_SESSION["rutina_previa"] = ["nombre" => $nombre, "descripcion" => $descripcion, "items" => $previos];
    redirigir($volver, "danger", "No se pudo guardar la rutina. Inténtalo de nuevo.");
}

redirigir($lista, "success", $id ? "Rutina actualizada." : "¡Rutina creada! Ya puedes entrenarla.");
