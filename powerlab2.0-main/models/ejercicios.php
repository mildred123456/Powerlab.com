<?php
// Biblioteca de ejercicios. Todo el contenido vive en la base de datos;
// este modelo solo consulta, filtra, pagina y guarda.
class ejercicios {

    const SELECT_BASE = "SELECT e.*, g.nombre AS grupo, g.icono AS grupo_icono, g.tono AS grupo_tono,
                                g.imagen_respaldo AS grupo_imagen, t.nombre AS tipo, eq.nombre AS equipamiento,
                                eq.imagen AS equipo_imagen,
                                n.nombre AS nivel, n.clave AS nivel_clave
                         FROM ejercicios e
                         JOIN grupos_musculares g ON g.id = e.id_grupo
                         JOIN tipos_ejercicio  t ON t.id = e.id_tipo
                         JOIN equipamiento    eq ON eq.id = e.id_equipamiento
                         JOIN niveles          n ON n.id = e.id_nivel";

    const COLUMNAS_GUARDAR = ["nombre", "descripcion", "id_grupo", "musculo_principal", "musculos_secundarios",
                              "id_tipo", "id_equipamiento", "id_nivel", "imagen", "video_url", "instrucciones", "activo"];

    // Búsqueda con filtros y paginación.
    // $filtros: q (texto), grupo, equipo, nivel (ids), estado (activos | inactivos | todos)
    // Devuelve ["items" => [...], "total" => N, "pagina" => P, "paginas" => T]
    public function buscar($filtros, $pagina = 1, $porPagina = 24) {
        [$where, $parametros] = $this->condiciones($filtros);
        $pagina = max(1, (int) $pagina);
        $porPagina = max(1, min(100, (int) $porPagina));
        try {
            include "conexion.php";
            $conteo = $conexion->prepare("SELECT COUNT(*) FROM ejercicios e
                                          JOIN grupos_musculares g ON g.id = e.id_grupo
                                          JOIN tipos_ejercicio  t ON t.id = e.id_tipo
                                          JOIN equipamiento    eq ON eq.id = e.id_equipamiento
                                          $where");
            $conteo->execute($parametros);
            $total = (int) $conteo->fetchColumn();
            $paginas = max(1, (int) ceil($total / $porPagina));
            $pagina = min($pagina, $paginas);
            $desde = ($pagina - 1) * $porPagina;

            $consulta = $conexion->prepare(self::SELECT_BASE . " $where ORDER BY g.orden, g.nombre, e.nombre LIMIT $porPagina OFFSET $desde");
            $consulta->execute($parametros);
            return ["items" => $consulta->fetchAll(PDO::FETCH_ASSOC), "total" => $total, "pagina" => $pagina, "paginas" => $paginas];
        } catch (Exception $e) {
            return ["items" => [], "total" => 0, "pagina" => 1, "paginas" => 1];
        }
    }

    public function ConsultaEspecifica($id, $soloActivos = true) {
        try {
            include "conexion.php";
            $consulta = $conexion->prepare(self::SELECT_BASE . " WHERE e.id = ?" . ($soloActivos ? " AND e.activo = 1" : ""));
            $consulta->execute([$id]);
            return $consulta->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (Exception $e) {
            return null;
        }
    }

    // Varios ejercicios por id (activos o no), como [id => fila]. Lo usan las rutinas.
    public function porIds($ids) {
        $ids = array_values(array_unique(array_filter(array_map("intval", (array) $ids))));
        if (!$ids) {
            return [];
        }
        try {
            include "conexion.php";
            $consulta = $conexion->prepare(self::SELECT_BASE . " WHERE e.id IN (" . implode(",", array_fill(0, count($ids), "?")) . ")");
            $consulta->execute($ids);
            $filas = [];
            foreach ($consulta->fetchAll(PDO::FETCH_ASSOC) as $fila) {
                $filas[(int) $fila["id"]] = $fila;
            }
            return $filas;
        } catch (Exception $e) {
            return [];
        }
    }

    // Otros ejercicios activos del mismo grupo muscular
    public function Relacionados($idGrupo, $idExcluir, $limite = 4) {
        try {
            include "conexion.php";
            $consulta = $conexion->prepare(self::SELECT_BASE . " WHERE e.id_grupo = ? AND e.id <> ? AND e.activo = 1 ORDER BY RAND() LIMIT " . (int) $limite);
            $consulta->execute([$idGrupo, $idExcluir]);
            return $consulta->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }

    public function existeNombre($nombre, $idExcluir = 0) {
        try {
            include "conexion.php";
            $consulta = $conexion->prepare("SELECT id FROM ejercicios WHERE nombre = ? AND id <> ?");
            $consulta->execute([$nombre, (int) $idExcluir]);
            return (bool) $consulta->fetch();
        } catch (Exception $e) {
            return false;
        }
    }

    // ----- Escritura (solo administrador) -----

    // Devuelve el id nuevo o la excepción
    public function REGISTRAR($datos) {
        try {
            include "conexion.php";
            $columnas = self::COLUMNAS_GUARDAR;
            $sql = "INSERT INTO ejercicios (" . implode(", ", $columnas) . ") VALUES (" . implode(", ", array_fill(0, count($columnas), "?")) . ")";
            $insertar = $conexion->prepare($sql);
            $insertar->execute(array_map(fn($c) => $datos[$c] ?? null, $columnas));
            return (int) $conexion->lastInsertId();
        } catch (Exception $e) {
            return $e;
        }
    }

    public function ACTUALIZAR($id, $datos) {
        try {
            include "conexion.php";
            $columnas = self::COLUMNAS_GUARDAR;
            $sql = "UPDATE ejercicios SET " . implode(", ", array_map(fn($c) => "$c = ?", $columnas)) . " WHERE id = ?";
            $actualizar = $conexion->prepare($sql);
            $valores = array_map(fn($c) => $datos[$c] ?? null, $columnas);
            $valores[] = $id;
            $actualizar->execute($valores);
            return true;
        } catch (Exception $e) {
            return $e;
        }
    }

    // Desactivar en lugar de borrar (no rompe rutinas que usan el ejercicio)
    public function CAMBIAR_ESTADO($id, $activo) {
        try {
            include "conexion.php";
            $sentencia = $conexion->prepare("UPDATE ejercicios SET activo = ? WHERE id = ?");
            $sentencia->execute([$activo ? 1 : 0, $id]);
            return $sentencia->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    // ----- Presentación -----

    public static function secundarios($ejercicio) {
        return array_values(array_filter(array_map("trim", explode(",", (string) ($ejercicio["musculos_secundarios"] ?? "")))));
    }

    public static function pasos($ejercicio) {
        return array_values(array_filter(array_map("trim", preg_split("/\r\n|\n|\r/", (string) ($ejercicio["instrucciones"] ?? "")))));
    }

    // Ruta pública (desde views/) de una imagen guardada, solo si está dentro de imagenes/ejercicios/
    public static function imagenSrc($ruta) {
        $ruta = (string) $ruta;
        if ($ruta === "" || str_contains($ruta, "..") || !preg_match('~^imagenes/ejercicios/[A-Za-z0-9/_-]+\.(?:jpe?g|png|webp)$~', $ruta)) {
            return null;
        }
        return "../" . $ruta;
    }

    // URL de video válida: solo YouTube. Devuelve el ID de 11 caracteres o null.
    public static function idYoutube($url) {
        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{11})(?:[?&#].*)?$~', trim((string) $url), $m)) {
            return $m[1];
        }
        return null;
    }

    public static function videoEmbed($url) {
        $id = self::idYoutube($url);
        return $id ? "https://www.youtube-nocookie.com/embed/" . $id : null;
    }

    // Imagen que se muestra si el ejercicio no tiene una propia: la del equipo que usa,
    // si no la de respaldo del grupo (si no hay ninguna, la vista pone el color e ícono del grupo)
    public static function imagenRespaldo($equipoImagen, $grupoImagen) {
        return self::imagenSrc($equipoImagen) ?? self::imagenSrc($grupoImagen);
    }

    // Datos de la tarjeta (los usa la vista y el JSON del scroll infinito)
    public static function tarjeta($e) {
        return [
            "id" => (int) $e["id"],
            "nombre" => $e["nombre"],
            "grupo" => $e["grupo"],
            "grupo_icono" => $e["grupo_icono"],
            "grupo_tono" => (int) $e["grupo_tono"],
            "musculo" => $e["musculo_principal"],
            "equipamiento" => $e["equipamiento"],
            "nivel" => $e["nivel"],
            "nivel_clave" => $e["nivel_clave"],
            "tipo" => $e["tipo"],
            "imagen" => self::imagenSrc($e["imagen"]),
            "respaldo" => self::imagenRespaldo($e["equipo_imagen"] ?? null, $e["grupo_imagen"]),
            "video" => (bool) self::idYoutube($e["video_url"]),
            "activo" => (bool) $e["activo"],
        ];
    }

    // ----- Auxiliares -----

    private function condiciones($f) {
        $where = [];
        $parametros = [];

        $estado = $f["estado"] ?? "activos";
        if ($estado === "activos") {
            $where[] = "e.activo = 1";
        } elseif ($estado === "inactivos") {
            $where[] = "e.activo = 0";
        }
        foreach (["grupo" => "e.id_grupo", "equipo" => "e.id_equipamiento", "nivel" => "e.id_nivel"] as $clave => $columna) {
            if (!empty($f[$clave])) {
                $where[] = "$columna = ?";
                $parametros[] = (int) $f[$clave];
            }
        }
        // Texto: cada palabra debe aparecer en algún campo. La colación utf8mb4_unicode_ci
        // ignora mayúsculas y tildes ("gluteo" encuentra "Glúteo").
        $palabras = array_slice(preg_split('/\s+/', trim((string) ($f["q"] ?? "")), -1, PREG_SPLIT_NO_EMPTY), 0, 6);
        foreach ($palabras as $palabra) {
            $like = "%" . addcslashes(mb_substr($palabra, 0, 40), "%_\\") . "%";
            $campos = ["e.nombre", "e.musculo_principal", "e.musculos_secundarios", "g.nombre", "g.palabras_clave", "eq.nombre", "t.nombre"];
            $where[] = "(" . implode(" OR ", array_map(fn($c) => "$c LIKE ?", $campos)) . ")";
            array_push($parametros, ...array_fill(0, count($campos), $like));
        }
        return [$where ? "WHERE " . implode(" AND ", $where) : "", $parametros];
    }
}
