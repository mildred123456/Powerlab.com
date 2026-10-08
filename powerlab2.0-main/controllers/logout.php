<?php
session_start(); // Reanuda la sesión actual para poder destruirla

// Vacía los datos de la sesión
$_SESSION = [];

// Elimina la cookie de sesión del navegador
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), "", time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

session_destroy();

header("Location: ../views/inicio-de-sesion.php");
exit();
