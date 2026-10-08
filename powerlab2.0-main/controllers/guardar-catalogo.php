<?php
// Crea o edita un grupo muscular o un equipamiento (solo administrador)
include_once "sesion-deportista.php";
requerirAdmin();

include_once "subir-imagen.php";
include_once "../models/catalogos.php";

$volver = "../views/admin-catalogos.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$catalogos = new catalogos();
$tipo = $_POST["catalogo"] ?? "";
$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: null;
$nombre = trim(preg_replace('/\s+/', " ", (string) ($_POST["nombre"] ?? "")));
$orden = max(0, min(999, (int) ($_POST["orden"] ?? 0)));

if (!in_array($tipo, ["grupo", "equipo"], true)) {
    redirigir($volver, "danger", "Catálogo no válido.");
}
$actual = $id ? ($tipo === "grupo" ? $catalogos->grupo($id) : $catalogos->equipo($id)) : null;
if ($id && !$actual) {
    redirigir($volver, "danger", "No encontramos ese registro.");
}
if (mb_strlen($nombre) < 2 || mb_strlen($nombre) > 40) {
    redirigir($volver, "danger", "El nombre debe tener entre 2 y 40 caracteres.");
}

if ($tipo === "grupo") {
    $icono = $_POST["icono"] ?? "";
    $tono = (int) ($_POST["tono"] ?? 0);
    $palabras = mb_substr(trim(preg_replace('/\s+/', " ", (string) ($_POST["palabras_clave"] ?? ""))), 0, 255);
    if (!in_array($icono, catalogos::ICONOS, true)) {
        redirigir($volver, "danger", "Elige un ícono de la lista.");
    }
    if ($tono < 1 || $tono > catalogos::TONOS) {
        redirigir($volver, "danger", "Elige un color válido.");
    }
}

// Imagen opcional (respaldo del grupo o foto del equipo): se valida antes de guardar
$subida = guardarImagenSubida($_FILES["imagen"] ?? null, $tipo === "grupo" ? "grupos" : "equipos");
if (isset($subida["error"])) {
    redirigir($volver, "danger", $subida["error"]);
}

$resultado = $tipo === "grupo"
    ? $catalogos->guardarGrupo($id, ["nombre" => $nombre, "icono" => $icono, "tono" => $tono, "palabras_clave" => $palabras ?: null, "orden" => $orden])
    : $catalogos->guardarEquipo($id, ["nombre" => $nombre, "orden" => $orden]);

if (!($resultado instanceof Exception)) {
    $anterior = $actual[$tipo === "grupo" ? "imagen_respaldo" : "imagen"] ?? null;
    if (isset($subida["ruta"])) {
        $catalogos->guardarImagen($tipo, $resultado, $subida["ruta"]);
        borrarImagenSubida($anterior);
    } elseif (isset($_POST["quitar_imagen"]) && $anterior) {
        $catalogos->guardarImagen($tipo, $resultado, null);
        borrarImagenSubida($anterior);
    }
} elseif (isset($subida["ruta"])) {
    borrarImagenSubida($subida["ruta"]); // no dejar archivos huérfanos
}

if ($resultado instanceof Exception) {
    redirigir($volver, "danger", $resultado->getCode() == 23000 ? "Ya existe un registro con ese nombre." : "No se pudo guardar.");
}

redirigir($volver, "success", ($tipo === "grupo" ? "Grupo muscular" : "Equipamiento") . ($id ? " actualizado." : " creado."));
