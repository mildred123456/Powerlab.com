<?php

class usuario {

    // Devuelve el usuario activo con ese correo y contraseña (arreglo vacío si no coincide)
    public function Login($correo, $contrasenia) {
        try {
            include "conexion.php";
            $consulta = $conexion->prepare("SELECT * FROM usuario WHERE correo = ? AND contrasenia = ? AND estado = 'A'");
            $consulta->execute([$correo, $contrasenia]);
            return $consulta->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return $e;
        }
    }

    // Registra un DEPORTISTA y devuelve su fila (o la excepción si falla, p. ej. correo duplicado).
    // El rol administrador solo se crea desde el script SQL.
    public function REGISTRAR($nombre, $apellido, $correo, $fecha_nacimiento, $genero, $contrasenia) {
        try {
            include "conexion.php";
            $insertar = $conexion->prepare("INSERT INTO usuario (nombre, apellido, correo, fecha_nacimiento, genero, rol, estado, contrasenia) VALUES (?, ?, ?, ?, ?, 'deportista', 'A', ?)");
            $insertar->execute([$nombre, $apellido, $correo, $fecha_nacimiento, $genero, $contrasenia]);

            $consulta = $conexion->prepare("SELECT * FROM usuario WHERE id = ?");
            $consulta->execute([$conexion->lastInsertId()]);
            return $consulta->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return $e;
        }
    }
}
