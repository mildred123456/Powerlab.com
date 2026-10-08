<?php
// Utilidades compartidas por las vistas y controladores de la app:
// validación de sesión y rol, token CSRF, mensajes flash y escape de salidas.
// Roles: "deportista" y "administrador".

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

const ROLES_VALIDOS = ["deportista", "administrador"];

// Panel propio de cada rol (rutas relativas a views/ y controllers/)
function panelPorRol($rol) {
    return $rol === "administrador" ? "../views/admin-ejercicios.php" : "../views/deportista.php";
}

// Para páginas: exige sesión con el rol indicado. Sin sesión -> login; otro rol -> su propio panel.
function requerirRol($rol) {
    $usuario = $_SESSION["usuario"] ?? null;
    if (!isset($usuario["id"], $usuario["rol"]) || !in_array($usuario["rol"], ROLES_VALIDOS, true)) {
        unset($_SESSION["usuario"]);
        header("Location: ../views/inicio-de-sesion.php");
        exit();
    }
    if ($usuario["rol"] !== $rol) {
        header("Location: " . panelPorRol($usuario["rol"]));
        exit();
    }
    return $usuario;
}

function requerirDeportista() {
    return requerirRol("deportista");
}

function requerirAdmin() {
    return requerirRol("administrador");
}

// Para peticiones fetch/JSON: responde 401/403 en lugar de redirigir
function requerirRolJson($rol) {
    $usuario = $_SESSION["usuario"] ?? null;
    if (!isset($usuario["id"], $usuario["rol"])) {
        responderJson(["ok" => false, "mensaje" => "Tu sesión expiró. Inicia sesión de nuevo."], 401);
    }
    if ($usuario["rol"] !== $rol) {
        responderJson(["ok" => false, "mensaje" => "Acceso no autorizado."], 403);
    }
    return $usuario;
}

// Guarda en sesión los datos públicos del usuario (nunca la contraseña)
function iniciarSesionUsuario($fila) {
    session_regenerate_id(true);
    $_SESSION["usuario"] = [
        "id" => (int) $fila["id"],
        "nombre" => $fila["nombre"],
        "apellido" => $fila["apellido"],
        "correo" => $fila["correo"],
        "fecha_nacimiento" => $fila["fecha_nacimiento"],
        "genero" => $fila["genero"],
        "rol" => $fila["rol"],
    ];
}

function responderJson($datos, $codigo = 200) {
    http_response_code($codigo);
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode($datos);
    exit();
}

// Escapa texto para imprimirlo en HTML
function e($texto) {
    return htmlspecialchars((string) ($texto ?? ""), ENT_QUOTES, "UTF-8");
}

// Fecha legible en español, ej: "lunes, 6 de octubre" o "6 oct 2026"
function fechaLegible($fecha, $corta = false) {
    $ts = is_numeric($fecha) ? (int) $fecha : strtotime((string) $fecha);
    if (!$ts) {
        return "";
    }
    $dias = ["domingo", "lunes", "martes", "miércoles", "jueves", "viernes", "sábado"];
    $meses = ["enero", "febrero", "marzo", "abril", "mayo", "junio", "julio", "agosto", "septiembre", "octubre", "noviembre", "diciembre"];
    $mes = $meses[(int) date("n", $ts) - 1];
    if ($corta) {
        return date("j", $ts) . " " . mb_substr($mes, 0, 3) . " " . date("Y", $ts);
    }
    return $dias[(int) date("w", $ts)] . ", " . date("j", $ts) . " de " . $mes;
}

function csrfToken() {
    if (empty($_SESSION["csrf_token"])) {
        $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
    }
    return $_SESSION["csrf_token"];
}

function csrfCampo() {
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function csrfValido($token) {
    return is_string($token) && !empty($_SESSION["csrf_token"]) && hash_equals($_SESSION["csrf_token"], $token);
}

// Mensajes que se muestran como toast en la siguiente página
function flash($tipo, $mensaje) {
    $_SESSION["flash"][] = ["tipo" => $tipo, "mensaje" => $mensaje];
}

function obtenerFlash() {
    $mensajes = $_SESSION["flash"] ?? [];
    unset($_SESSION["flash"]);
    return $mensajes;
}

// Redirige con un mensaje flash
function redirigir($url, $tipo = null, $mensaje = null) {
    if ($tipo !== null) {
        flash($tipo, $mensaje);
    }
    header("Location: " . $url);
    exit();
}
