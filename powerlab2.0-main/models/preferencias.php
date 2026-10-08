<?php
// Preferencias del deportista (Configuración). Si no hay fila, se usan los valores por defecto.
class preferencias {

    // campo => [por defecto, mínimo, máximo]
    const CAMPOS = [
        "descanso_seg"       => [90, 15, 600],
        "hidratacion_activa" => [1, 0, 1],
        "hidratacion_min"    => [20, 5, 120],
        "sonido"             => [1, 0, 1],
        "vibracion"          => [1, 0, 1],
        "meta_semanal"       => [3, 1, 7],
    ];

    public function obtener($idUsuario) {
        $fila = [];
        try {
            include "conexion.php";
            $consulta = $conexion->prepare("SELECT * FROM preferencias WHERE id_usuario = ?");
            $consulta->execute([$idUsuario]);
            $fila = $consulta->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Exception $e) {
        }
        $preferencias = [];
        foreach (self::CAMPOS as $campo => [$defecto]) {
            $preferencias[$campo] = isset($fila[$campo]) ? (int) $fila[$campo] : $defecto;
        }
        return $preferencias;
    }

    // $datos ya validados (enteros dentro de rango)
    public function guardar($idUsuario, $datos) {
        try {
            include "conexion.php";
            $columnas = array_keys(self::CAMPOS);
            $sql = "INSERT INTO preferencias (id_usuario, " . implode(", ", $columnas) . ") VALUES (?" . str_repeat(", ?", count($columnas)) . ")
                    ON DUPLICATE KEY UPDATE " . implode(", ", array_map(fn($c) => "$c = VALUES($c)", $columnas));
            $sentencia = $conexion->prepare($sql);
            $sentencia->execute(array_merge([$idUsuario], array_map(fn($c) => $datos[$c], $columnas)));
            return true;
        } catch (Exception $e) {
            return $e;
        }
    }
}
