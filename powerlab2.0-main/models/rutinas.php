<?php
// Rutinas del deportista y plantillas de ejemplo (es_plantilla = 1, sin dueño).
// Cada rutina guarda sus ejercicios por id: si el administrador desactiva uno,
// la rutina lo conserva y la vista lo marca como "no disponible".
include_once __DIR__ . "/ejercicios.php";

class rutinas {

    const MAX_RUTINAS = 50;     // por deportista
    const MAX_EJERCICIOS = 30;  // por rutina
    const SEG_POR_REPETICION = 4; // para estimar la duración

    // Rutinas del usuario (o las plantillas) con resumen: nº de ejercicios y series,
    // ejercicios no disponibles, duración estimada y último entrenamiento.
    public function listar($idUsuario, $plantillas, $descansoDefecto) {
        $where = $plantillas ? "r.es_plantilla = 1" : "r.es_plantilla = 0 AND r.id_usuario = ?";
        $parametros = [(int) $descansoDefecto, self::SEG_POR_REPETICION];
        if (!$plantillas) {
            $parametros[] = (int) $idUsuario;
        }
        $filas = $this->consultar(
            "SELECT r.*, COUNT(re.id) AS num_ejercicios, COALESCE(SUM(re.series), 0) AS num_series,
                    COALESCE(SUM(e.activo = 0), 0) AS no_disponibles,
                    COALESCE(SUM(re.series * (COALESCE(re.descanso_seg, ?) + COALESCE(re.duracion_seg, re.repeticiones * ?))), 0) AS segundos,
                    (SELECT MAX(en.inicio) FROM entrenamientos en WHERE en.id_rutina = r.id) AS ultimo_entreno,
                    (SELECT COUNT(*) FROM entrenamientos en WHERE en.id_rutina = r.id) AS veces
             FROM rutinas r
             LEFT JOIN rutina_ejercicios re ON re.id_rutina = r.id
             LEFT JOIN ejercicios e ON e.id = re.id_ejercicio
             WHERE $where
             GROUP BY r.id
             ORDER BY " . ($plantillas ? "r.nombre" : "COALESCE(r.actualizado_en, r.creado_en) DESC, r.id DESC"),
            $parametros
        );
        if (!$filas) {
            return [];
        }

        // Grupos musculares de cada rutina (para las etiquetas de color)
        $ids = array_map(fn($r) => (int) $r["id"], $filas);
        $grupos = [];
        foreach ($this->consultar(
            "SELECT re.id_rutina, g.nombre, g.tono, COUNT(*) AS n
             FROM rutina_ejercicios re
             JOIN ejercicios e ON e.id = re.id_ejercicio
             JOIN grupos_musculares g ON g.id = e.id_grupo
             WHERE re.id_rutina IN (" . implode(",", array_fill(0, count($ids), "?")) . ")
             GROUP BY re.id_rutina, g.id
             ORDER BY n DESC, g.orden",
            $ids
        ) as $g) {
            $grupos[(int) $g["id_rutina"]][] = ["nombre" => $g["nombre"], "tono" => (int) $g["tono"]];
        }
        foreach ($filas as &$fila) {
            $fila["grupos"] = $grupos[(int) $fila["id"]] ?? [];
        }
        return $filas;
    }

    public function contar($idUsuario) {
        return (int) ($this->consultar("SELECT COUNT(*) AS n FROM rutinas WHERE id_usuario = ? AND es_plantilla = 0", [$idUsuario])[0]["n"] ?? 0);
    }

    // Una rutina con sus ejercicios, solo si es del usuario (o si es plantilla y se permite).
    public function obtener($id, $idUsuario, $permitirPlantilla = false) {
        $rutina = $this->consultar(
            "SELECT * FROM rutinas WHERE id = ? AND ((es_plantilla = 0 AND id_usuario = ?)" . ($permitirPlantilla ? " OR es_plantilla = 1" : "") . ")",
            [$id, $idUsuario]
        )[0] ?? null;
        if (!$rutina) {
            return null;
        }
        $rutina["items"] = self::completarItems($this->consultar(
            "SELECT id_ejercicio, series, repeticiones, duracion_seg, peso_kg, descanso_seg, notas
             FROM rutina_ejercicios WHERE id_rutina = ? ORDER BY orden, id",
            [$id]
        ));
        return $rutina;
    }

    // Agrega a cada ítem (id_ejercicio + meta) los datos del ejercicio para mostrarlo.
    // Los ejercicios que ya no existen se descartan; los inactivos se marcan con disponible = false.
    public static function completarItems($items) {
        $ejercicios = (new ejercicios())->porIds(array_column($items, "id_ejercicio"));
        $completos = [];
        foreach ($items as $item) {
            $ej = $ejercicios[(int) $item["id_ejercicio"]] ?? null;
            if (!$ej) {
                continue;
            }
            $completos[] = [
                "id_ejercicio" => (int) $ej["id"],
                "series" => (int) $item["series"],
                "repeticiones" => $item["repeticiones"] !== null && $item["repeticiones"] !== "" ? (int) $item["repeticiones"] : null,
                "duracion_seg" => $item["duracion_seg"] !== null && $item["duracion_seg"] !== "" ? (int) $item["duracion_seg"] : null,
                "peso_kg" => $item["peso_kg"] !== null && $item["peso_kg"] !== "" ? (float) $item["peso_kg"] : null,
                "descanso_seg" => $item["descanso_seg"] !== null && $item["descanso_seg"] !== "" ? (int) $item["descanso_seg"] : null,
                "notas" => (string) ($item["notas"] ?? ""),
                "disponible" => (bool) $ej["activo"],
                "ejercicio" => ejercicios::tarjeta($ej),
                "pasos" => ejercicios::pasos($ej),
            ];
        }
        return $completos;
    }

    // Ejercicios que existen, como [id => activo]
    public function estadoEjercicios($ids) {
        return array_map(fn($e) => (bool) $e["activo"], (new ejercicios())->porIds($ids));
    }

    // ----- Escritura (solo el dueño) -----

    // Crea ($id null) o reemplaza una rutina completa con sus ejercicios. Devuelve el id o la excepción.
    public function guardar($id, $idUsuario, $nombre, $descripcion, $items) {
        try {
            include "conexion.php";
            $conexion->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $conexion->beginTransaction();
            if ($id) {
                // Solo el dueño: se comprueba (y se bloquea la fila) antes de reemplazar los ejercicios
                $dueno = $conexion->prepare("SELECT id FROM rutinas WHERE id = ? AND id_usuario = ? AND es_plantilla = 0 FOR UPDATE");
                $dueno->execute([$id, $idUsuario]);
                if (!$dueno->fetch()) {
                    throw new Exception("No encontramos esa rutina.");
                }
                $conexion->prepare("UPDATE rutinas SET nombre = ?, descripcion = ?, actualizado_en = NOW() WHERE id = ?")->execute([$nombre, $descripcion, $id]);
                $conexion->prepare("DELETE FROM rutina_ejercicios WHERE id_rutina = ?")->execute([$id]);
            } else {
                $conexion->prepare("INSERT INTO rutinas (id_usuario, nombre, descripcion) VALUES (?, ?, ?)")->execute([$idUsuario, $nombre, $descripcion]);
                $id = (int) $conexion->lastInsertId();
            }
            $insertar = $conexion->prepare(
                "INSERT INTO rutina_ejercicios (id_rutina, id_ejercicio, orden, series, repeticiones, duracion_seg, peso_kg, descanso_seg, notas)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            foreach (array_values($items) as $orden => $item) {
                $insertar->execute([$id, $item["id_ejercicio"], $orden + 1, $item["series"], $item["repeticiones"], $item["duracion_seg"],
                                    $item["peso_kg"], $item["descanso_seg"], $item["notas"] !== "" ? $item["notas"] : null]);
            }
            $conexion->commit();
            return (int) $id;
        } catch (Exception $e) {
            if (isset($conexion) && $conexion->inTransaction()) {
                $conexion->rollBack();
            }
            return $e;
        }
    }

    // Copia una plantilla o una rutina propia a las rutinas del usuario (solo ejercicios disponibles).
    // Devuelve ["id" => nuevo, "omitidos" => N] o la excepción.
    public function copiar($id, $idUsuario) {
        $origen = $this->obtener($id, $idUsuario, true);
        if (!$origen) {
            return new Exception("No encontramos esa rutina.");
        }
        $disponibles = array_values(array_filter($origen["items"], fn($i) => $i["disponible"]));
        $nombre = $origen["es_plantilla"] ? $origen["nombre"] : mb_substr($origen["nombre"], 0, 72) . " (copia)";
        $nuevo = $this->guardar(null, $idUsuario, $nombre, $origen["descripcion"], $disponibles);
        return $nuevo instanceof Exception ? $nuevo : ["id" => $nuevo, "omitidos" => count($origen["items"]) - count($disponibles)];
    }

    // Borra una rutina propia (sus ejercicios se borran en cascada; el historial de
    // entrenamientos se conserva con el nombre copiado).
    public function eliminar($id, $idUsuario) {
        try {
            include "conexion.php";
            $sentencia = $conexion->prepare("DELETE FROM rutinas WHERE id = ? AND id_usuario = ? AND es_plantilla = 0");
            $sentencia->execute([$id, $idUsuario]);
            return $sentencia->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // ----- Presentación -----

    // Meta de una serie: "12 reps", "30 s" o "1:30 min"
    public static function meta($item) {
        if ($item["duracion_seg"] !== null) {
            return self::tiempo($item["duracion_seg"]);
        }
        return (int) $item["repeticiones"] . " reps";
    }

    public static function tiempo($segundos) {
        $segundos = (int) $segundos;
        if ($segundos < 60) {
            return $segundos . " s";
        }
        return intdiv($segundos, 60) . ":" . str_pad((string) ($segundos % 60), 2, "0", STR_PAD_LEFT) . " min";
    }

    // Duración aproximada en minutos (mínimo 1)
    public static function minutos($segundos) {
        return max(1, (int) round($segundos / 60));
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
