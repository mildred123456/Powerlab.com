-- =====================================================================
--  PowerLab · Script completo de base de datos
--  Dos roles: deportista (registro público) y administrador (solo por
--  este script; gestiona la biblioteca de ejercicios).
--
--  Ejecutar sobre un servidor MySQL/MariaDB:
--      mysql -u root < "script sql/powerlab_completo.sql"
--  o en phpMyAdmin: pestaña "Importar" -> seleccionar este archivo.
--
--  Está pensado para crear la base desde cero, y también es seguro
--  re-ejecutarlo: usa CREATE ... IF NOT EXISTS e inserta los datos
--  iniciales sin duplicarlos. No contiene DROP ni DELETE.
--
--  Tablas (en orden de creación):
--   1. usuario              · cuentas (rol deportista | administrador)
--   2. medidas_corporales   · historial de medidas (Mis medidas)
--   3. grupos_musculares    · catálogo editable por el administrador
--   4. equipamiento         · catálogo editable por el administrador
--   5. tipos_ejercicio      · catálogo fijo
--   6. niveles              · catálogo fijo
--   7. ejercicios           · biblioteca (FK a los cuatro catálogos)
--   8. rutinas              · rutinas del deportista y plantillas de ejemplo
--   9. rutina_ejercicios    · ejercicios de cada rutina (series, reps/tiempo, peso)
--  10. entrenamientos       · historial de sesiones (Modo entrenamiento)
--  11. entrenamiento_series · series realmente hechas en cada sesión
--  12. preferencias         · descanso, hidratación, sonido… (Configuración)
--
--  Este script NO inserta ejercicios: la biblioteca la llena el
--  administrador. Las plantillas de rutina solo se crean si ya existen
--  en la biblioteca los ejercicios que usan (se buscan por nombre).
-- =====================================================================

CREATE DATABASE IF NOT EXISTS powerlab
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE powerlab;

SET NAMES utf8mb4;

-- ---------------------------------------------------------------------
-- 1. Usuarios
--    rol: 'deportista' (lo crea el registro público) o 'administrador'
--    (solo se crea desde este script).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS usuario (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    nombre           VARCHAR(100) NOT NULL,
    apellido         VARCHAR(100) NOT NULL,
    correo           VARCHAR(100) NOT NULL,
    fecha_nacimiento DATE         NOT NULL,
    genero           VARCHAR(10)  NOT NULL,
    rol              VARCHAR(20)  NOT NULL DEFAULT 'deportista',
    estado           CHAR(1)      NOT NULL DEFAULT 'A', -- 'A' = Activo, 'I' = Inactivo
    contrasenia      VARCHAR(255) NOT NULL,
    creado_en        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_usuario_correo (correo),
    CONSTRAINT chk_usuario_rol CHECK (rol IN ('deportista', 'administrador'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 2. Medidas corporales
--    Cada medición es un registro NUEVO (se conserva el historial).
--    Todas las medidas son opcionales, pero cada registro debe traer al
--    menos una (lo valida el controlador). Perímetros en cm.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS medidas_corporales (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario          INT          NOT NULL,
    fecha               DATE         NOT NULL,
    peso_kg             DECIMAL(5,2) NULL,
    altura_cm           DECIMAL(5,1) NULL,
    grasa_pct           DECIMAL(4,1) NULL,
    pecho_cm            DECIMAL(5,1) NULL,
    cintura_cm          DECIMAL(5,1) NULL,
    abdomen_cm          DECIMAL(5,1) NULL,
    cadera_cm           DECIMAL(5,1) NULL,
    brazo_der_cm        DECIMAL(5,1) NULL,
    brazo_izq_cm        DECIMAL(5,1) NULL,
    muslo_der_cm        DECIMAL(5,1) NULL,
    muslo_izq_cm        DECIMAL(5,1) NULL,
    pantorrilla_der_cm  DECIMAL(5,1) NULL,
    pantorrilla_izq_cm  DECIMAL(5,1) NULL,
    creado_en           DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en      DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_medidas_usuario_fecha (id_usuario, fecha, id),
    CONSTRAINT fk_medidas_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT chk_medidas_peso   CHECK (peso_kg   IS NULL OR peso_kg   BETWEEN 20 AND 400),
    CONSTRAINT chk_medidas_altura CHECK (altura_cm IS NULL OR altura_cm BETWEEN 80 AND 250),
    CONSTRAINT chk_medidas_grasa  CHECK (grasa_pct IS NULL OR grasa_pct BETWEEN 2 AND 75)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 3. Grupos musculares (editable por el administrador)
--    icono: clase de Bootstrap Icons para el respaldo visual.
--    tono: 1..9, color del grupo (clase CSS pl-tono-N).
--    palabras_clave: términos extra para el buscador (p. ej. "pierna"
--    también encuentra ejercicios de glúteo).
--    imagen_respaldo: imagen subida por el administrador (opcional).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS grupos_musculares (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nombre          VARCHAR(40)  NOT NULL,
    icono           VARCHAR(40)  NOT NULL DEFAULT 'bi-collection',
    tono            TINYINT      NOT NULL DEFAULT 1,
    palabras_clave  VARCHAR(255) NULL,
    imagen_respaldo VARCHAR(255) NULL,
    orden           SMALLINT     NOT NULL DEFAULT 0,
    activo          TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_grupos_nombre (nombre),
    CONSTRAINT chk_grupos_tono CHECK (tono BETWEEN 1 AND 9)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 4. Equipamiento (editable por el administrador)
--    imagen: foto del equipo o máquina (opcional, en imagenes/ejercicios/equipos/).
--    Es la imagen que ven los ejercicios que no tienen una propia.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS equipamiento (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(40)  NOT NULL,
    imagen VARCHAR(255) NULL,
    orden  SMALLINT     NOT NULL DEFAULT 0,
    activo TINYINT(1)   NOT NULL DEFAULT 1,
    UNIQUE KEY uq_equipamiento_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Para bases creadas antes de añadir la imagen (no hace nada si ya existe; no borra datos)
ALTER TABLE equipamiento ADD COLUMN IF NOT EXISTS imagen VARCHAR(255) NULL AFTER nombre;

-- ---------------------------------------------------------------------
-- 5. Tipos de ejercicio (fijo)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tipos_ejercicio (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL,
    orden  SMALLINT    NOT NULL DEFAULT 0,
    UNIQUE KEY uq_tipos_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 6. Niveles de dificultad (fijo). clave: nombre técnico para el color.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS niveles (
    id     INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(30) NOT NULL,
    clave  VARCHAR(20) NOT NULL,
    orden  SMALLINT    NOT NULL DEFAULT 0,
    UNIQUE KEY uq_niveles_nombre (nombre),
    UNIQUE KEY uq_niveles_clave (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 7. Ejercicios
--    Se desactivan (activo = 0) en lugar de borrarse, para no romper las
--    rutinas que los usan. El deportista solo ve los activos.
--    imagen: ruta relativa dentro de imagenes/ejercicios/ (subida o IA).
--    video_url: URL de YouTube (opcional).
--    instrucciones: un paso por línea.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ejercicios (
    id                   INT AUTO_INCREMENT PRIMARY KEY,
    nombre               VARCHAR(120) NOT NULL,
    descripcion          VARCHAR(500) NOT NULL,
    id_grupo             INT          NOT NULL,
    musculo_principal    VARCHAR(80)  NOT NULL,
    musculos_secundarios VARCHAR(255) NULL,      -- separados por coma
    id_tipo              INT          NOT NULL,
    id_equipamiento      INT          NOT NULL,
    id_nivel             INT          NOT NULL,
    imagen               VARCHAR(255) NULL,      -- opcional; si no hay, se usa la del equipamiento
    video_url            VARCHAR(255) NULL,
    instrucciones        TEXT         NOT NULL,
    activo               TINYINT(1)   NOT NULL DEFAULT 1,
    creado_en            DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en       DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_ejercicios_nombre (nombre),
    KEY idx_ejercicios_activo_grupo (activo, id_grupo),
    KEY idx_ejercicios_equipamiento (id_equipamiento),
    KEY idx_ejercicios_nivel (id_nivel),
    CONSTRAINT fk_ejercicios_grupo        FOREIGN KEY (id_grupo)        REFERENCES grupos_musculares (id),
    CONSTRAINT fk_ejercicios_tipo         FOREIGN KEY (id_tipo)         REFERENCES tipos_ejercicio (id),
    CONSTRAINT fk_ejercicios_equipamiento FOREIGN KEY (id_equipamiento) REFERENCES equipamiento (id),
    CONSTRAINT fk_ejercicios_nivel        FOREIGN KEY (id_nivel)        REFERENCES niveles (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 8. Rutinas
--    Del deportista (id_usuario) o plantillas de ejemplo (es_plantilla = 1,
--    sin dueño) que el deportista copia a sus rutinas.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rutinas (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario     INT          NULL,
    nombre         VARCHAR(80)  NOT NULL,
    descripcion    VARCHAR(255) NULL,
    es_plantilla   TINYINT(1)   NOT NULL DEFAULT 0,
    creado_en      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME     NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_rutinas_usuario (id_usuario, es_plantilla),
    CONSTRAINT fk_rutinas_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT chk_rutinas_duenio CHECK ((es_plantilla = 1 AND id_usuario IS NULL) OR (es_plantilla = 0 AND id_usuario IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 9. Ejercicios de cada rutina (en orden)
--    Meta por serie: repeticiones O duración en segundos (una de las dos).
--    peso_kg y descanso_seg son opcionales (sin descanso se usa el de
--    Configuración). Referencia el ejercicio por id: si el administrador
--    lo desactiva, la rutina lo conserva y lo marca como no disponible.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS rutina_ejercicios (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    id_rutina     INT          NOT NULL,
    id_ejercicio  INT          NOT NULL,
    orden         SMALLINT     NOT NULL DEFAULT 0,
    series        TINYINT      NOT NULL DEFAULT 3,
    repeticiones  SMALLINT     NULL,
    duracion_seg  SMALLINT     NULL,
    peso_kg       DECIMAL(6,2) NULL,
    descanso_seg  SMALLINT     NULL,
    notas         VARCHAR(255) NULL,
    KEY idx_rutina_ejercicios_orden (id_rutina, orden),
    CONSTRAINT fk_rutina_ejercicios_rutina    FOREIGN KEY (id_rutina)    REFERENCES rutinas (id) ON DELETE CASCADE,
    CONSTRAINT fk_rutina_ejercicios_ejercicio FOREIGN KEY (id_ejercicio) REFERENCES ejercicios (id),
    CONSTRAINT chk_rutina_series   CHECK (series BETWEEN 1 AND 20),
    CONSTRAINT chk_rutina_reps     CHECK (repeticiones IS NULL OR repeticiones BETWEEN 1 AND 200),
    CONSTRAINT chk_rutina_duracion CHECK (duracion_seg IS NULL OR duracion_seg BETWEEN 5 AND 3600),
    CONSTRAINT chk_rutina_meta     CHECK ((repeticiones IS NULL) <> (duracion_seg IS NULL)),
    CONSTRAINT chk_rutina_peso     CHECK (peso_kg IS NULL OR peso_kg BETWEEN 0 AND 500),
    CONSTRAINT chk_rutina_descanso CHECK (descanso_seg IS NULL OR descanso_seg BETWEEN 0 AND 600)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 10. Entrenamientos (historial del Modo entrenamiento)
--     nombre_rutina se copia para que el historial siga legible aunque la
--     rutina se edite o se borre (id_rutina queda en NULL).
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entrenamientos (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    id_usuario         INT           NOT NULL,
    id_rutina          INT           NULL,
    nombre_rutina      VARCHAR(80)   NOT NULL,
    inicio             DATETIME      NOT NULL,
    fin                DATETIME      NOT NULL,
    duracion_seg       INT           NOT NULL DEFAULT 0,
    series_completadas SMALLINT      NOT NULL DEFAULT 0,
    volumen_kg         DECIMAL(10,2) NOT NULL DEFAULT 0, -- suma de repeticiones x peso
    creado_en          DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entrenamientos_usuario_inicio (id_usuario, inicio),
    KEY idx_entrenamientos_rutina (id_rutina),
    CONSTRAINT fk_entrenamientos_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT fk_entrenamientos_rutina  FOREIGN KEY (id_rutina)  REFERENCES rutinas (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 11. Series hechas en cada entrenamiento
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS entrenamiento_series (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    id_entrenamiento INT          NOT NULL,
    id_ejercicio     INT          NOT NULL,
    nombre_ejercicio VARCHAR(120) NOT NULL,
    numero_serie     TINYINT      NOT NULL,
    repeticiones     SMALLINT     NULL,
    duracion_seg     SMALLINT     NULL,
    peso_kg          DECIMAL(6,2) NULL,
    KEY idx_series_entrenamiento (id_entrenamiento),
    KEY idx_series_ejercicio (id_ejercicio),
    CONSTRAINT fk_series_entrenamiento FOREIGN KEY (id_entrenamiento) REFERENCES entrenamientos (id) ON DELETE CASCADE,
    CONSTRAINT fk_series_ejercicio     FOREIGN KEY (id_ejercicio)     REFERENCES ejercicios (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------------------------------------------------------------------
-- 12. Preferencias del deportista (Configuración). Sin fila = valores por defecto.
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS preferencias (
    id_usuario         INT        NOT NULL PRIMARY KEY,
    descanso_seg       SMALLINT   NOT NULL DEFAULT 90,  -- descanso entre series por defecto
    hidratacion_activa TINYINT(1) NOT NULL DEFAULT 1,   -- recordatorio de beber agua al entrenar
    hidratacion_min    SMALLINT   NOT NULL DEFAULT 20,  -- cada cuántos minutos
    sonido             TINYINT(1) NOT NULL DEFAULT 1,   -- pitido al terminar el descanso
    vibracion          TINYINT(1) NOT NULL DEFAULT 1,   -- vibración (móviles compatibles)
    meta_semanal       TINYINT    NOT NULL DEFAULT 3,   -- entrenamientos por semana
    actualizado_en     DATETIME   NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_preferencias_usuario FOREIGN KEY (id_usuario) REFERENCES usuario (id) ON DELETE CASCADE,
    CONSTRAINT chk_pref_descanso    CHECK (descanso_seg BETWEEN 15 AND 600),
    CONSTRAINT chk_pref_hidratacion CHECK (hidratacion_min BETWEEN 5 AND 120),
    CONSTRAINT chk_pref_meta        CHECK (meta_semanal BETWEEN 1 AND 7)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  DATOS INICIALES
-- =====================================================================

-- Usuarios de prueba. Nota: el login compara la contraseña en texto plano.
INSERT IGNORE INTO usuario (nombre, apellido, correo, fecha_nacimiento, genero, rol, estado, contrasenia) VALUES
    ('Laura',   'Gómez', 'deportista@powerlab.com', '1998-09-22', 'femenino', 'deportista',    'A', 'deportista123'),
    ('Mildred', 'Callejas', 'admin@powerlab.com',   '1990-01-01', 'femenino', 'administrador', 'A', 'admin123');

-- Catálogos
INSERT IGNORE INTO grupos_musculares (nombre, icono, tono, palabras_clave, orden) VALUES
    ('Pecho',   'bi-heart-pulse',         1, 'pecho pectoral pectorales',                         1),
    ('Espalda', 'bi-arrows-angle-expand', 2, 'espalda dorsal dorsales lumbar',                    2),
    ('Pierna',  'bi-person-walking',      3, 'pierna piernas tren inferior cuadriceps femoral',   3),
    ('Glúteo',  'bi-chevron-double-up',   4, 'gluteo gluteos pierna piernas tren inferior cadera',4),
    ('Hombro',  'bi-triangle',            5, 'hombro hombros deltoides',                          5),
    ('Bíceps',  'bi-lightning-charge',    6, 'biceps brazo brazos',                               6),
    ('Tríceps', 'bi-lightning',           7, 'triceps brazo brazos',                              7),
    ('Abdomen', 'bi-grid-3x2-gap',        8, 'abdomen abdominales core',                          8),
    ('Cardio',  'bi-activity',            9, 'cardio resistencia aerobico',                       9);

INSERT IGNORE INTO equipamiento (nombre, orden) VALUES
    ('Barra', 1), ('Mancuernas', 2), ('Máquina', 3), ('Polea', 4), ('Peso corporal', 5),
    ('Kettlebell', 6), ('Banda elástica', 7), ('Banco', 8), ('Cuerda', 9), ('Rueda abdominal', 10);

INSERT IGNORE INTO tipos_ejercicio (nombre, orden) VALUES
    ('Compuesto', 1), ('Aislamiento', 2), ('Isométrico', 3), ('Cardio', 4);

INSERT IGNORE INTO niveles (nombre, clave, orden) VALUES
    ('Principiante', 'principiante', 1), ('Intermedio', 'intermedio', 2), ('Avanzado', 'avanzado', 3);

-- Historial de medidas de ejemplo (para ver gráficas y diferencias).
-- Solo se insertan si el usuario de prueba todavía no tiene mediciones.
INSERT INTO medidas_corporales
    (id_usuario, fecha, peso_kg, altura_cm, grasa_pct, pecho_cm, cintura_cm, abdomen_cm, cadera_cm,
     brazo_der_cm, brazo_izq_cm, muslo_der_cm, muslo_izq_cm, pantorrilla_der_cm, pantorrilla_izq_cm)
SELECT u.id, m.fecha, m.peso, 165.0, m.grasa, m.pecho, m.cintura, m.abdomen, m.cadera,
       m.brazo_d, m.brazo_i, m.muslo_d, m.muslo_i, m.pant_d, m.pant_i
FROM usuario u
JOIN (
    SELECT DATE_SUB(CURDATE(), INTERVAL 150 DAY) fecha, 68.40 peso, 29.5 grasa, 92.0 pecho, 78.0 cintura, 84.0 abdomen, 101.0 cadera, 28.0 brazo_d, 27.5 brazo_i, 58.0 muslo_d, 57.5 muslo_i, 36.0 pant_d, 35.5 pant_i
    UNION ALL SELECT DATE_SUB(CURDATE(), INTERVAL 120 DAY), 67.60, 28.8, 91.5, 77.0, 83.0, 100.5, 28.0, 27.6, 57.8, 57.2, 36.0, 35.6
    UNION ALL SELECT DATE_SUB(CURDATE(), INTERVAL 90 DAY),  66.90, 27.9, 91.0, 75.5, 81.5, 100.0, 28.3, 27.9, 57.5, 57.0, 36.2, 35.8
    UNION ALL SELECT DATE_SUB(CURDATE(), INTERVAL 60 DAY),  66.20, 27.1, 90.5, 74.5, 80.0,  99.0, 28.5, 28.1, 57.0, 56.8, 36.3, 36.0
    UNION ALL SELECT DATE_SUB(CURDATE(), INTERVAL 30 DAY),  65.70, 26.4, 90.5, 73.0, 79.0,  98.5, 28.8, 28.4, 56.8, 56.5, 36.5, 36.1
    UNION ALL SELECT DATE_SUB(CURDATE(), INTERVAL 5 DAY),   65.10, 25.8, 90.0, 72.0, 77.5,  98.0, 29.0, 28.7, 56.5, 56.2, 36.6, 36.3
) m
WHERE u.correo = 'deportista@powerlab.com'
  AND NOT EXISTS (SELECT 1 FROM medidas_corporales mc WHERE mc.id_usuario = u.id);

-- Los ejercicios NO se cargan aquí: la biblioteca empieza vacía y la llena
-- el administrador.

-- ---------------------------------------------------------------------
-- Plantillas de rutina (el deportista las copia a "Mis rutinas").
-- Usan ejercicios de la biblioteca buscándolos por NOMBRE: una plantilla
-- solo se crea si encuentra al menos 3 de sus ejercicios activos, y solo
-- incluye los que existan. Re-ejecutar no duplica (se busca por nombre de
-- plantilla). La tabla temporal desaparece sola al terminar la conexión.
-- ---------------------------------------------------------------------
CREATE TEMPORARY TABLE IF NOT EXISTS tmp_plantillas (
    plantilla    VARCHAR(80)  NOT NULL,
    descripcion  VARCHAR(255) NOT NULL,
    orden        SMALLINT     NOT NULL,
    ejercicio    VARCHAR(120) NOT NULL,
    series       TINYINT      NOT NULL,
    repeticiones SMALLINT     NULL,
    duracion_seg SMALLINT     NULL,
    descanso_seg SMALLINT     NULL,
    PRIMARY KEY (plantilla, orden)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO tmp_plantillas (plantilla, descripcion, orden, ejercicio, series, repeticiones, duracion_seg, descanso_seg) VALUES
    ('Cuerpo completo · Principiante', 'Todo el cuerpo con poco equipo. Ideal para empezar, 2-3 días por semana.', 1, 'Sentadilla con peso corporal', 3, 12, NULL, 60),
    ('Cuerpo completo · Principiante', 'Todo el cuerpo con poco equipo. Ideal para empezar, 2-3 días por semana.', 2, 'Flexiones de pecho',           3, 10, NULL, 60),
    ('Cuerpo completo · Principiante', 'Todo el cuerpo con poco equipo. Ideal para empezar, 2-3 días por semana.', 3, 'Remo con mancuerna',           3, 12, NULL, 60),
    ('Cuerpo completo · Principiante', 'Todo el cuerpo con poco equipo. Ideal para empezar, 2-3 días por semana.', 4, 'Puente de glúteos',            3, 15, NULL, 45),
    ('Cuerpo completo · Principiante', 'Todo el cuerpo con poco equipo. Ideal para empezar, 2-3 días por semana.', 5, 'Elevaciones laterales',        3, 12, NULL, 45),
    ('Cuerpo completo · Principiante', 'Todo el cuerpo con poco equipo. Ideal para empezar, 2-3 días por semana.', 6, 'Plancha abdominal',            3, NULL, 30, 45),

    ('Torso · Fuerza', 'Pecho, espalda, hombros y brazos con cargas moderadas.', 1, 'Press de banca con barra',      4, 8,  NULL, 120),
    ('Torso · Fuerza', 'Pecho, espalda, hombros y brazos con cargas moderadas.', 2, 'Remo con barra',                4, 8,  NULL, 120),
    ('Torso · Fuerza', 'Pecho, espalda, hombros y brazos con cargas moderadas.', 3, 'Press militar con mancuernas',  3, 10, NULL, 90),
    ('Torso · Fuerza', 'Pecho, espalda, hombros y brazos con cargas moderadas.', 4, 'Jalón en polea al pecho',       3, 10, NULL, 90),
    ('Torso · Fuerza', 'Pecho, espalda, hombros y brazos con cargas moderadas.', 5, 'Curl de bíceps con barra',      3, 12, NULL, 60),
    ('Torso · Fuerza', 'Pecho, espalda, hombros y brazos con cargas moderadas.', 6, 'Extensión de tríceps en polea', 3, 12, NULL, 60),

    ('Pierna y glúteo', 'Tren inferior completo: cuádriceps, femorales y glúteo.', 1, 'Sentadilla con barra',          4, 8,  NULL, 120),
    ('Pierna y glúteo', 'Tren inferior completo: cuádriceps, femorales y glúteo.', 2, 'Peso muerto rumano',            3, 10, NULL, 90),
    ('Pierna y glúteo', 'Tren inferior completo: cuádriceps, femorales y glúteo.', 3, 'Hip thrust',                    4, 10, NULL, 90),
    ('Pierna y glúteo', 'Tren inferior completo: cuádriceps, femorales y glúteo.', 4, 'Zancadas',                      3, 12, NULL, 60),
    ('Pierna y glúteo', 'Tren inferior completo: cuádriceps, femorales y glúteo.', 5, 'Abducción de cadera con banda', 3, 15, NULL, 45),
    ('Pierna y glúteo', 'Tren inferior completo: cuádriceps, femorales y glúteo.', 6, 'Plancha lateral',               3, NULL, 30, 45),

    ('Cardio y abdomen en casa', 'Circuito sin máquinas para sudar y fortalecer el core.', 1, 'Salto de cuerda',      3, NULL, 60, 30),
    ('Cardio y abdomen en casa', 'Circuito sin máquinas para sudar y fortalecer el core.', 2, 'Burpees',              3, 10,  NULL, 45),
    ('Cardio y abdomen en casa', 'Circuito sin máquinas para sudar y fortalecer el core.', 3, 'Mountain climbers',    3, NULL, 30, 30),
    ('Cardio y abdomen en casa', 'Circuito sin máquinas para sudar y fortalecer el core.', 4, 'Crunch abdominal',     3, 15,  NULL, 30),
    ('Cardio y abdomen en casa', 'Circuito sin máquinas para sudar y fortalecer el core.', 5, 'Abdominal bicicleta',  3, 20,  NULL, 30),
    ('Cardio y abdomen en casa', 'Circuito sin máquinas para sudar y fortalecer el core.', 6, 'Plancha abdominal',    3, NULL, 40, 30);

-- Crea cada plantilla que aún no existe y que tenga al menos 3 ejercicios disponibles
INSERT INTO rutinas (id_usuario, nombre, descripcion, es_plantilla)
SELECT NULL, t.plantilla, MIN(t.descripcion), 1
FROM tmp_plantillas t
JOIN ejercicios e ON e.nombre = t.ejercicio AND e.activo = 1
WHERE NOT EXISTS (SELECT 1 FROM rutinas r WHERE r.es_plantilla = 1 AND r.nombre = t.plantilla)
GROUP BY t.plantilla
HAVING COUNT(*) >= 3;

-- Sus ejercicios (solo en plantillas que todavía no tienen ninguno)
INSERT INTO rutina_ejercicios (id_rutina, id_ejercicio, orden, series, repeticiones, duracion_seg, descanso_seg)
SELECT r.id, e.id, t.orden, t.series, t.repeticiones, t.duracion_seg, t.descanso_seg
FROM tmp_plantillas t
JOIN rutinas r    ON r.es_plantilla = 1 AND r.nombre = t.plantilla
JOIN ejercicios e ON e.nombre = t.ejercicio AND e.activo = 1
WHERE NOT EXISTS (SELECT 1 FROM rutina_ejercicios x WHERE x.id_rutina = r.id);
