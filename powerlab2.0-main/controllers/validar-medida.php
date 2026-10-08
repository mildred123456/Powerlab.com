<?php
// Validación de una medición enviada por formulario (usada por registrar-medida.php y actualizar-medida.php).
// Devuelve [fecha, valores, errores].
include_once "../models/medidas.php";

function validarMedida($post, $usuario) {
    $errores = [];

    // Fecha: formato válido, no futura y posterior al nacimiento
    $fecha = trim($post["fecha"] ?? "");
    $fechaObj = DateTime::createFromFormat("Y-m-d", $fecha);
    if (!$fechaObj || $fechaObj->format("Y-m-d") !== $fecha) {
        $errores[] = "La fecha no es válida.";
    } elseif ($fecha > date("Y-m-d")) {
        $errores[] = "La fecha no puede ser futura.";
    } elseif (!empty($usuario["fecha_nacimiento"]) && $fecha < $usuario["fecha_nacimiento"]) {
        $errores[] = "La fecha no puede ser anterior a tu nacimiento.";
    }

    // Medidas: opcionales, numéricas y dentro de rango
    $valores = [];
    foreach (medidas::CAMPOS as $campo => [$etiqueta, $unidad, $min, $max]) {
        $texto = str_replace(",", ".", trim((string) ($post[$campo] ?? "")));
        if ($texto === "") {
            $valores[$campo] = null;
            continue;
        }
        if (!is_numeric($texto)) {
            $errores[] = "$etiqueta debe ser un número.";
            continue;
        }
        $numero = round((float) $texto, 2);
        if ($numero < $min || $numero > $max) {
            $errores[] = "$etiqueta debe estar entre $min y $max $unidad.";
            continue;
        }
        $valores[$campo] = $numero;
    }

    if (!array_filter($valores, fn($v) => $v !== null) && !$errores) {
        $errores[] = "Registra al menos una medida.";
    }

    return [$fecha, $valores, $errores];
}
