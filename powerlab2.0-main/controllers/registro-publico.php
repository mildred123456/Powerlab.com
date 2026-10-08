<?php
// Registro de nuevos usuarios (formulario views/registro.php)
include_once "sesion-deportista.php";
include_once "../models/usuario.php";

$volver = "../views/registro.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La solicitud no es válida. Recarga la página e inténtalo de nuevo.");
}

// Con una sesión abierta no se crea otra cuenta (evita reemplazar la sesión actual)
if (isset($_SESSION["usuario"])) {
    redirigir($volver, "warning", "Ya tienes una sesión iniciada. Ciérrala para crear otra cuenta.");
}

$nombre      = trim($_POST["nombre"] ?? "");
$apellido    = trim($_POST["apellido"] ?? "");
$correo      = mb_strtolower(trim($_POST["correo"] ?? ""));
$fecha       = trim($_POST["fecha_nacimiento"] ?? "");
$genero      = $_POST["genero"] ?? "";
$contrasenia = $_POST["contrasenia"] ?? "";
$confirmar   = $_POST["confirmar"] ?? "";

// Se guardan los datos escritos (sin contraseñas) para volver a mostrarlos si hay error
$_SESSION["registro_previo"] = [
    "nombre" => $nombre,
    "apellido" => $apellido,
    "correo" => $correo,
    "fecha_nacimiento" => $fecha,
    "genero" => $genero,
];

// ---- Validaciones en el servidor ----
$errores = [];
$patronNombre = "/^[\p{L}][\p{L}\s'\-]{1,99}$/u"; // letras, espacios, apóstrofo y guion

if (!preg_match($patronNombre, $nombre)) {
    $errores[] = "El nombre solo puede tener letras (mínimo 2).";
}
if (!preg_match($patronNombre, $apellido)) {
    $errores[] = "El apellido solo puede tener letras (mínimo 2).";
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || mb_strlen($correo) > 50) {
    $errores[] = "El correo no es válido.";
}
$fechaObj = DateTime::createFromFormat("Y-m-d", $fecha);
if (!$fechaObj || $fechaObj->format("Y-m-d") !== $fecha) {
    $errores[] = "La fecha de nacimiento no es válida.";
} else {
    $edad = $fechaObj->diff(new DateTime("today"))->y;
    if ($fechaObj > new DateTime("today") || $edad < 12 || $edad > 100) {
        $errores[] = "Debes tener entre 12 y 100 años.";
    }
}
if (!in_array($genero, ["masculino", "femenino"], true)) {
    $errores[] = "Selecciona un género válido.";
}
if (mb_strlen($contrasenia) < 6 || mb_strlen($contrasenia) > 35) {
    $errores[] = "La contraseña debe tener entre 6 y 35 caracteres.";
} elseif ($contrasenia !== $confirmar) {
    $errores[] = "Las contraseñas no coinciden.";
}

if ($errores) {
    foreach ($errores as $error) {
        flash("danger", $error);
    }
    redirigir($volver);
}

// ---- Registro ----
$respuesta = (new usuario())->REGISTRAR($nombre, $apellido, $correo, $fecha, $genero, $contrasenia);

if ($respuesta instanceof Exception) {
    if ($respuesta->getCode() == 23000) {
        redirigir($volver, "warning", "Ese correo ya está registrado. ¿Quieres iniciar sesión?");
    }
    redirigir($volver, "danger", "No pudimos crear tu cuenta. Inténtalo más tarde.");
}

if (empty($respuesta["id"])) {
    redirigir($volver, "danger", "No pudimos crear tu cuenta. Inténtalo más tarde.");
}

// ---- Inicia la sesión igual que controllers/login.php ----
unset($_SESSION["registro_previo"]);
iniciarSesionUsuario($respuesta);
redirigir(panelPorRol("deportista"), "success", "¡Bienvenido a PowerLab, " . $respuesta["nombre"] . "!");
