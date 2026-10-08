<?php
// Catálogos de la biblioteca: grupos musculares y equipamiento (editables por el administrador),
// tipos de ejercicio y niveles (fijos, se cargan por SQL).
class catalogos {

    // Íconos disponibles para un grupo muscular (opciones de interfaz, no datos de ejercicios)
    const ICONOS = [
        "bi-heart-pulse", "bi-arrows-angle-expand", "bi-person-walking", "bi-chevron-double-up",
        "bi-triangle", "bi-lightning-charge", "bi-lightning", "bi-grid-3x2-gap", "bi-activity",
        "bi-fire", "bi-trophy", "bi-person-arms-up", "bi-bicycle", "bi-droplet", "bi-collection",
    ];
    const TONOS = 9; // clases CSS pl-tono-1 … pl-tono-9

    // ----- Consultas -----

    // Grupos con el número de ejercicios activos y totales que los usan
    public function grupos($soloActivos = false) {
        return $this->listar("grupos_musculares", "id_grupo", $soloActivos);
    }

    public function equipamiento($soloActivos = false) {
        return $this->listar("equipamiento", "id_equipamiento", $soloActivos);
    }

    public function tipos() {
        return $this->consultar("SELECT * FROM tipos_ejercicio ORDER BY orden, nombre");
    }

    public function niveles() {
        return $this->consultar("SELECT * FROM niveles ORDER BY orden, nombre");
    }

    public function grupo($id) {
        return $this->consultar("SELECT * FROM grupos_musculares WHERE id = ?", [$id])[0] ?? null;
    }

    public function equipo($id) {
        return $this->consultar("SELECT * FROM equipamiento WHERE id = ?", [$id])[0] ?? null;
    }

    // ¿Existe el id en el catálogo? (para validar claves foráneas en el servidor)
    public function existe($catalogo, $id, $soloActivos = false) {
        $tablas = ["grupo" => "grupos_musculares", "equipo" => "equipamiento", "tipo" => "tipos_ejercicio", "nivel" => "niveles"];
        if (!isset($tablas[$catalogo])) {
            return false;
        }
        $condicion = $soloActivos && in_array($catalogo, ["grupo", "equipo"], true) ? " AND activo = 1" : "";
        return (bool) $this->consultar("SELECT id FROM {$tablas[$catalogo]} WHERE id = ?$condicion", [$id]);
    }

    // ----- Escritura (solo administrador) -----

    // Crea (id null) o edita un grupo muscular. Devuelve el id o una excepción.
    public function guardarGrupo($id, $datos) {
        $valores = [$datos["nombre"], $datos["icono"], $datos["tono"], $datos["palabras_clave"], $datos["orden"]];
        if ($id) {
            $sql = "UPDATE grupos_musculares SET nombre = ?, icono = ?, tono = ?, palabras_clave = ?, orden = ? WHERE id = ?";
            $valores[] = $id;
        } else {
            $sql = "INSERT INTO grupos_musculares (nombre, icono, tono, palabras_clave, orden) VALUES (?, ?, ?, ?, ?)";
        }
        return $this->escribir($sql, $valores, $id);
    }

    // Imagen opcional: respaldo del grupo o foto del equipo/máquina ($ruta null la quita)
    public function guardarImagen($catalogo, $id, $ruta) {
        $sql = $catalogo === "grupo"
            ? "UPDATE grupos_musculares SET imagen_respaldo = ? WHERE id = ?"
            : "UPDATE equipamiento SET imagen = ? WHERE id = ?";
        return $this->escribir($sql, [$ruta, $id], $id);
    }

    public function guardarEquipo($id, $datos) {
        if ($id) {
            return $this->escribir("UPDATE equipamiento SET nombre = ?, orden = ? WHERE id = ?", [$datos["nombre"], $datos["orden"], $id], $id);
        }
        return $this->escribir("INSERT INTO equipamiento (nombre, orden) VALUES (?, ?)", [$datos["nombre"], $datos["orden"]], null);
    }

    // Activa o desactiva. No permite desactivar si hay ejercicios activos que lo usan.
    // Devuelve true, o ["bloqueado" => N] con el número de ejercicios activos.
    public function cambiarEstado($catalogo, $id, $activo) {
        $config = ["grupo" => ["grupos_musculares", "id_grupo"], "equipo" => ["equipamiento", "id_equipamiento"]];
        if (!isset($config[$catalogo])) {
            return false;
        }
        [$tabla, $columna] = $config[$catalogo];
        if (!$activo) {
            $enUso = (int) ($this->consultar("SELECT COUNT(*) n FROM ejercicios WHERE $columna = ? AND activo = 1", [$id])[0]["n"] ?? 0);
            if ($enUso > 0) {
                return ["bloqueado" => $enUso];
            }
        }
        return !($this->escribir("UPDATE $tabla SET activo = ? WHERE id = ?", [$activo ? 1 : 0, $id], $id) instanceof Exception);
    }

    // ----- Auxiliares -----

    private function listar($tabla, $columnaFk, $soloActivos) {
        $where = $soloActivos ? "WHERE c.activo = 1" : "";
        return $this->consultar(
            "SELECT c.*,
                    (SELECT COUNT(*) FROM ejercicios e WHERE e.$columnaFk = c.id AND e.activo = 1) AS ejercicios_activos,
                    (SELECT COUNT(*) FROM ejercicios e WHERE e.$columnaFk = c.id) AS ejercicios_total
             FROM $tabla c $where
             ORDER BY c.orden, c.nombre"
        );
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

    private function escribir($sql, $parametros, $id) {
        try {
            include "conexion.php";
            $sentencia = $conexion->prepare($sql);
            $sentencia->execute($parametros);
            return $id ?: (int) $conexion->lastInsertId();
        } catch (Exception $e) {
            return $e;
        }
    }
}
