<?php
// Crea o edita un ejercicio de la biblioteca (solo administrador)
include_once "sesion-deportista.php";
requerirAdmin();

include_once "subir-imagen.php";
include_once "../models/ejercicios.php";
include_once "../models/catalogos.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir("../views/admin-ejercicios.php", "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

$modelo = new ejercicios();
$catalogos = new catalogos();

// Edición: el ejercicio debe existir (activo o no)
$id = filter_var($_POST["id"] ?? null, FILTER_VALIDATE_INT, ["options" => ["min_range" => 1]]) ?: null;
$actual = $id ? $modelo->ConsultaEspecifica($id, false) : null;
if ($id && !$actual) {
    redirigir("../views/admin-ejercicios.php", "danger", "No encontramos ese ejercicio.");
}
$volver = "../views/admin-ejercicio-form.php" . ($id ? "?id=$id" : "");

// ---- Datos del formulario ----
$limpiar = fn($campo) => trim(preg_replace('/[ \t]+/', " ", (string) ($_POST[$campo] ?? "")));
$secundarios = implode(", ", array_filter(array_map("trim", explode(",", $limpiar("musculos_secundarios")))));
$pasos = array_filter(array_map("trim", preg_split("/\r\n|\n|\r/", (string) ($_POST["instrucciones"] ?? ""))));
$datos = [
    "nombre" => $limpiar("nombre"),
    "descripcion" => $limpiar("descripcion"),
    "id_grupo" => (int) ($_POST["id_grupo"] ?? 0),
    "musculo_principal" => $limpiar("musculo_principal"),
    "musculos_secundarios" => $secundarios !== "" ? $secundarios : null,
    "id_tipo" => (int) ($_POST["id_tipo"] ?? 0),
    "id_equipamiento" => (int) ($_POST["id_equipamiento"] ?? 0),
    "id_nivel" => (int) ($_POST["id_nivel"] ?? 0),
    "video_url" => $limpiar("video_url") ?: null,
    "instrucciones" => implode("\n", $pasos),
    "activo" => isset($_POST["activo"]) ? 1 : 0,
    "imagen" => $actual["imagen"] ?? null,
];

// ---- Validación en el servidor ----
$errores = [];
$largo = fn($texto, $min, $max) => mb_strlen($texto) >= $min && mb_strlen($texto) <= $max;

if (!$largo($datos["nombre"], 3, 120)) {
    $errores[] = "El nombre debe tener entre 3 y 120 caracteres.";
} elseif ($modelo->existeNombre($datos["nombre"], $id ?? 0)) {
    $errores[] = "Ya existe un ejercicio con ese nombre.";
}
if (!$largo($datos["descripcion"], 10, 500)) {
    $errores[] = "La descripción debe tener entre 10 y 500 caracteres.";
}
if (!$largo($datos["musculo_principal"], 2, 80)) {
    $errores[] = "Indica el músculo principal.";
}
if (mb_strlen((string) $datos["musculos_secundarios"]) > 255) {
    $errores[] = "Los músculos secundarios no pueden superar 255 caracteres.";
}
// Catálogos: deben existir; grupos y equipamiento además activos (salvo que sea el valor que ya tenía)
if (!$catalogos->existe("grupo", $datos["id_grupo"], $datos["id_grupo"] !== (int) ($actual["id_grupo"] ?? 0))) {
    $errores[] = "Elige un grupo muscular válido.";
}
if (!$catalogos->existe("equipo", $datos["id_equipamiento"], $datos["id_equipamiento"] !== (int) ($actual["id_equipamiento"] ?? 0))) {
    $errores[] = "Elige un equipamiento válido.";
}
if (!$catalogos->existe("tipo", $datos["id_tipo"])) {
    $errores[] = "Elige un tipo de ejercicio válido.";
}
if (!$catalogos->existe("nivel", $datos["id_nivel"])) {
    $errores[] = "Elige un nivel válido.";
}
if ($datos["video_url"] !== null && (mb_strlen($datos["video_url"]) > 255 || !ejercicios::idYoutube($datos["video_url"]))) {
    $errores[] = "El video debe ser una URL de YouTube válida (youtube.com/watch?v=… o youtu.be/…).";
}
if (count($pasos) < 2 || mb_strlen($datos["instrucciones"]) > 4000) {
    $errores[] = "Escribe al menos 2 pasos en las instrucciones (uno por línea, máximo 4000 caracteres).";
}

// ---- Imagen propia (opcional): archivo subido > quitar > se conserva ----
// Sin imagen propia, las vistas muestran la del equipo (ver ejercicios::imagenRespaldo).
$imagenNueva = null;
if (!$errores) {
    $subida = guardarImagenSubida($_FILES["imagen"] ?? null);
    if (isset($subida["error"])) {
        $errores[] = $subida["error"];
    } elseif (isset($subida["ruta"])) {
        $imagenNueva = $subida["ruta"];
    }
}

if ($errores) {
    foreach ($errores as $error) {
        flash("danger", $error);
    }
    $_SESSION["ejercicio_previo"] = $datos;
    redirigir($volver);
}

$imagenAnterior = $datos["imagen"];
if ($imagenNueva) {
    $datos["imagen"] = $imagenNueva;
} elseif (isset($_POST["quitar_imagen"])) {
    $datos["imagen"] = null;
}

$resultado = $id ? $modelo->ACTUALIZAR($id, $datos) : $modelo->REGISTRAR($datos);

if ($resultado instanceof Exception) {
    if ($imagenNueva) {
        borrarImagenSubida($imagenNueva); // no dejar archivos huérfanos
    }
    $_SESSION["ejercicio_previo"] = $datos;
    redirigir($volver, "danger", $resultado->getCode() == 23000 ? "Ya existe un ejercicio con ese nombre." : "No se pudo guardar el ejercicio.");
}

// La imagen reemplazada o quitada se borra del disco
if ($imagenAnterior && $imagenAnterior !== $datos["imagen"]) {
    borrarImagenSubida($imagenAnterior);
}

redirigir("../views/admin-ejercicios.php", "success", $id ? "Ejercicio actualizado." : "Ejercicio creado.");
