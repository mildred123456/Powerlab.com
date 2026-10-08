<?php
// Inicio de sesión: cada rol entra a su propio panel
include_once "sesion-deportista.php"; // inicia o reanuda la sesión
include "../models/usuario.php";

$volver = "../views/inicio-de-sesion.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST" || !csrfValido($_POST["csrf_token"] ?? null)) {
    redirigir($volver, "danger", "La sesión del formulario expiró. Inténtalo de nuevo.");
}

$correo = mb_strtolower(trim($_POST["correo"] ?? ""));
$contrasenia = $_POST["contrasenia"] ?? "";

if (!filter_var($correo, FILTER_VALIDATE_EMAIL) || $contrasenia === "") {
    redirigir($volver, "danger", "Escribe tu correo y tu contraseña.");
}

$datos = (new usuario())->Login($correo, $contrasenia);

// Verifica si se encontró un usuario con esas credenciales
if (is_array($datos) && count($datos) === 1) {
    iniciarSesionUsuario($datos[0]);
    redirigir(panelPorRol($datos[0]["rol"]));
}

if ($datos instanceof Exception) {
    redirigir($volver, "danger", "No pudimos conectar. Inténtalo más tarde.");
}

// Conserva el correo escrito para no tener que reescribirlo
$_SESSION["login_correo"] = $correo;
redirigir($volver, "danger", "Correo o contraseña incorrectos.");
