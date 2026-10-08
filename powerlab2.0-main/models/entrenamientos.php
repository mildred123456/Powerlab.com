<?php
// Historial del Modo entrenamiento: cada sesión terminada y las series que se hicieron.
class entrenamientos {

    // Guarda una sesión con sus series. $series: [id_ejercicio, nombre_ejercicio, numero_serie, repeticiones, duracion_seg, peso_kg]
    // Devuelve el id o la excepción.
    public function registrar($idUsuario, $idRutina, $nombreRutina, $inicio, $fin, $series) {
        $volumen = 0;
        foreach ($series as $s) {
            $volumen += ($s["repeticiones"] ?? 0) * ($s["peso_kg"] ?? 0);
        }
        try {
            include "conexion.php";
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conexion->beginTransaction();
            $conexion->prepare(
                "INSERT INTO entrenamientos (id_usuario, id_rutina, nombre_rutina, inicio, fin, duracion_seg, series_completadas, volumen_kg)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
            )->execute([$idUsuario, $idRutina, $nombreRutina, date("Y-m-d H:i:s", $inicio), date("Y-m-d H:i:s", $fin),
                        $fin - $inicio, count($series), round($volumen, 2)]);
            $id = (int) $conexion->lastInsertId();
            $insertar = $conexion->prepare(
                "INSERT INTO entrenamiento_series (id_entrenamiento, id_ejercicio, nombre_ejercicio, numero_serie, repeticiones, duracion_seg, peso_kg)
                 VALUES (?, ?, ?, ?, ?, ?, ?)"
            );
            foreach ($series as $s) {
                $insertar->execute([$id, $s["id_ejercicio"], $s["nombre_ejercicio"], $s["numero_serie"], $s["repeticiones"], $s["duracion_seg"], $s["peso_kg"]]);
            }
            $conexion->commit();
            return $id;
        } catch (Exception $e) {
            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            return $e;
        }
    }

    public function recientes($idUsuario, $limite = 6) {
        return $this->consultar("SELECT * FROM entrenamientos WHERE id_usuario = ? ORDER BY inicio DESC LIMIT " . (int) $limite, [$idUsuario]);
    }

    // Una sesión del usuario con sus series
    public function obtener($id, $idUsuario) {
        $sesion = $this->consultar("SELECT * FROM entrenamientos WHERE id = ? AND id_usuario = ?", [$id, $idUsuario])[0] ?? null;
        if ($sesion) {
            $sesion["series"] = $this->consultar("SELECT * FROM entrenamiento_series WHERE id_entrenamiento = ? ORDER BY id", [$id]);
        }
        return $sesion;
    }

    // Entrenamientos desde el lunes de esta semana
    public function deEstaSemana($idUsuario) {
        $lunes = date("Y-m-d 00:00:00", strtotime("monday this week"));
        return (int) ($this->consultar("SELECT COUNT(*) AS n FROM entrenamientos WHERE id_usuario = ? AND inicio >= ?", [$idUsuario, $lunes])[0]["n"] ?? 0);
    }

    public function totales($idUsuario) {
        $fila = $this->consultar("SELECT COUNT(*) AS sesiones, COALESCE(SUM(duracion_seg), 0) AS segundos, COALESCE(SUM(series_completadas), 0) AS series
                                  FROM entrenamientos WHERE id_usuario = ?", [$idUsuario])[0] ?? [];
        return ["sesiones" => (int) ($fila["sesiones"] ?? 0), "segundos" => (int) ($fila["segundos"] ?? 0), "series" => (int) ($fila["series"] ?? 0)];
    }

    // Lo último que hizo el usuario en cada ejercicio (para sugerir peso y repeticiones): [id_ejercicio => serie]
    public function ultimasSeries($idUsuario, $idsEjercicios) {
        $ids = array_values(array_unique(array_map("intval", $idsEjercicios)));
        if (!$ids) {
            return [];
        }
        $filas = $this->consultar(
            "SELECT es.id_ejercicio, es.repeticiones, es.duracion_seg, es.peso_kg, en.inicio
             FROM entrenamiento_series es
             JOIN entrenamientos en ON en.id = es.id_entrenamiento
             WHERE en.id_usuario = ? AND es.id_ejercicio IN (" . implode(",", array_fill(0, count($ids), "?")) . ")
             ORDER BY en.inicio DESC, es.numero_serie DESC",
            array_merge([$idUsuario], $ids)
        );
        $ultimas = [];
        foreach ($filas as $f) {
            $ultimas[(int) $f["id_ejercicio"]] ??= [
                "repeticiones" => $f["repeticiones"] !== null ? (int) $f["repeticiones"] : null,
                "duracion_seg" => $f["duracion_seg"] !== null ? (int) $f["duracion_seg"] : null,
                "peso_kg" => $f["peso_kg"] !== null ? (float) $f["peso_kg"] : null,
                "fecha" => $f["inicio"],
            ];
        }
        return $ultimas;
    }

    private function consultar($sql, $parametros = []) {
        try {
            include "conexion.php";
            $consulta = $conexion->prepare($sql);
            $consulta->execute($parametros);
            return $consulta->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
