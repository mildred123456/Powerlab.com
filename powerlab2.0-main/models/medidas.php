<?php
class medidas {

    // Campos medibles: columna => [etiqueta, unidad, mínimo, máximo, grupo]
    // Es la única lista de columnas permitidas (se usa para armar el SQL de forma segura).
    const CAMPOS = [
        "peso_kg"            => ["Peso",                  "kg", 20, 400, "Generales"],
        "altura_cm"          => ["Altura",                "cm", 80, 250, "Generales"],
        "grasa_pct"          => ["% grasa corporal",      "%",  2,  75,  "Generales"],
        "pecho_cm"           => ["Pecho",                 "cm", 40, 200, "Tronco"],
        "cintura_cm"         => ["Cintura",               "cm", 40, 250, "Tronco"],
        "abdomen_cm"         => ["Abdomen",               "cm", 40, 250, "Tronco"],
        "cadera_cm"          => ["Cadera",                "cm", 40, 250, "Tronco"],
        "brazo_der_cm"       => ["Brazo derecho",         "cm", 10, 80,  "Brazos"],
        "brazo_izq_cm"       => ["Brazo izquierdo",       "cm", 10, 80,  "Brazos"],
        "muslo_der_cm"       => ["Muslo derecho",         "cm", 20, 120, "Piernas"],
        "muslo_izq_cm"       => ["Muslo izquierdo",       "cm", 20, 120, "Piernas"],
        "pantorrilla_der_cm" => ["Pantorrilla derecha",   "cm", 15, 70,  "Piernas"],
        "pantorrilla_izq_cm" => ["Pantorrilla izquierda", "cm", 15, 70,  "Piernas"],
    ];

    public function REGISTRAR($id_usuario, $fecha, $valores) {
        try {
            include "conexion.php";
            $columnas = array_keys(self::CAMPOS);
            $sql = "INSERT INTO medidas_corporales (id_usuario, fecha, " . implode(", ", $columnas) . ")
                    VALUES (?, ?" . str_repeat(", ?", count($columnas)) . ")";
            $parametros = [$id_usuario, $fecha];
            foreach ($columnas as $columna) {
                $parametros[] = $valores[$columna] ?? null;
            }
            $insertar = $conexion->prepare($sql);
            $insertar->execute($parametros);
            return true;
        } catch (Exception $e) {
            return $e;
        }
    }

    // Historial del usuario, del más antiguo al más reciente
    public function ConsultaPorUsuario($id_usuario) {
        try {
            include "conexion.php";
            $consulta = $conexion->prepare("SELECT * FROM medidas_corporales WHERE id_usuario = ? ORDER BY fecha ASC, id ASC");
            $consulta->execute([$id_usuario]);
            return $consulta->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    // Una medición, solo si pertenece al usuario
    public function ConsultaEspecifica($id, $id_usuario) {
        try {
            include "conexion.php";
            $consulta = $conexion->prepare("SELECT * FROM medidas_corporales WHERE id = ? AND id_usuario = ?");
            $consulta->execute([$id, $id_usuario]);
            return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    // Edita una medición existente (corrige errores). Solo afecta registros del propio usuario.
    public function ACTUALIZAR($id, $id_usuario, $fecha, $valores) {
        try {
            include "conexion.php";
            $columnas = array_keys(self::CAMPOS);
            $asignaciones = implode(", ", array_map(fn($c) => "$c = ?", $columnas));
            $sql = "UPDATE medidas_corporales SET fecha = ?, $asignaciones WHERE id = ? AND id_usuario = ?";
            $parametros = [$fecha];
            foreach ($columnas as $columna) {
                $parametros[] = $valores[$columna] ?? null;
            }
            $parametros[] = $id;
            $parametros[] = $id_usuario;
            $actualizar = $conexion->prepare($sql);
            $actualizar->execute($parametros);
            return true;
        } catch (Exception $e) {
            return $e;
        }
    }

    // Recorre el historial (ascendente) y agrega a cada registro la diferencia de cada
    // campo respecto al último valor registrado de ese mismo campo.
    public static function conDiferencias($historial) {
        $ultimo = [];
        foreach ($historial as $i => $fila) {
            $diferencias = [];
            foreach (self::CAMPOS as $campo => $info) {
                if ($fila[$campo] === null) {
                    continue;
                }
                $valor = (float) $fila[$campo];
                if (isset($ultimo[$campo])) {
                    $diferencias[$campo] = round($valor - $ultimo[$campo], 2);
                }
                $ultimo[$campo] = $valor;
            }
            $historial[$i]["diferencias"] = $diferencias;
        }
        return $historial;
    }

    // Último valor conocido de cada campo (aunque venga de registros distintos)
    public static function ultimosValores($historial) {
        $ultimos = [];
        foreach ($historial as $fila) {
            foreach (self::CAMPOS as $campo => $info) {
                if ($fila[$campo] !== null) {
                    $ultimos[$campo] = ["valor" => (float) $fila[$campo], "fecha" => $fila["fecha"], "diferencia" => $fila["diferencias"][$campo] ?? null];
                }
            }
        }
        return $ultimos;
    }
}
?>
