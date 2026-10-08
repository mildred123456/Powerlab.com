<?php
// Guardado seguro de las imágenes que sube el administrador en imagenes/ejercicios/
// (ejercicios en la raíz, grupos musculares en grupos/, equipamiento en equipos/).
// - Tipos permitidos: JPG, PNG y WEBP (se comprueba el contenido real, no la extensión)
// - Máximo 2 MB, dimensiones entre 300x200 y 6000x6000
// - El archivo se renombra con un nombre aleatorio

const IMG_MAX_BYTES = 2 * 1024 * 1024;
const IMG_TIPOS = ["image/jpeg" => "jpg", "image/png" => "png", "image/webp" => "webp"];

function carpetaImagenes($subcarpeta = "") {
    $carpeta = dirname(__DIR__) . "/imagenes/ejercicios" . ($subcarpeta ? "/" . $subcarpeta : "");
    if (!is_dir($carpeta)) {
        mkdir($carpeta, 0755, true);
    }
    return $carpeta;
}

// Comprueba que un archivo del disco sea una imagen válida. Devuelve [extension, null] o [null, error].
function validarArchivoImagen($ruta, $maxBytes = IMG_MAX_BYTES) {
    if (!is_file($ruta)) {
        return [null, "No se recibió la imagen."];
    }
    if (filesize($ruta) > $maxBytes) {
        return [null, "La imagen pesa más de " . round($maxBytes / 1048576) . " MB."];
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($ruta);
    if (!isset(IMG_TIPOS[$mime])) {
        return [null, "Formato no permitido. Usa JPG, PNG o WEBP."];
    }
    $info = @getimagesize($ruta);
    if (!$info || $info[0] < 300 || $info[1] < 200 || $info[0] > 6000 || $info[1] > 6000) {
        return [null, "La imagen debe medir entre 300×200 y 6000×6000 píxeles."];
    }
    return [IMG_TIPOS[$mime], null];
}

// Procesa un archivo de $_FILES. Devuelve ["ruta" => ...], ["error" => ...] o null si no se envió nada.
function guardarImagenSubida($archivo, $subcarpeta = "") {
    if (!$archivo || ($archivo["error"] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($archivo["error"] === UPLOAD_ERR_INI_SIZE || $archivo["error"] === UPLOAD_ERR_FORM_SIZE) {
        return ["error" => "La imagen pesa más de 2 MB."];
    }
    if ($archivo["error"] !== UPLOAD_ERR_OK || !is_uploaded_file($archivo["tmp_name"])) {
        return ["error" => "No se pudo subir la imagen. Inténtalo de nuevo."];
    }
    [$extension, $error] = validarArchivoImagen($archivo["tmp_name"]);
    if ($error) {
        return ["error" => $error];
    }
    $nombre = bin2hex(random_bytes(16)) . "." . $extension;
    if (!move_uploaded_file($archivo["tmp_name"], carpetaImagenes($subcarpeta) . "/" . $nombre)) {
        return ["error" => "No se pudo guardar la imagen en el servidor."];
    }
    return ["ruta" => "imagenes/ejercicios/" . ($subcarpeta ? $subcarpeta . "/" : "") . $nombre];
}

// Borra una imagen SOLO si fue creada por el sistema (nombre aleatorio dentro de imagenes/ejercicios/)
function borrarImagenSubida($ruta) {
    if (!preg_match('~^imagenes/ejercicios/(?:grupos/|equipos/)?[a-f0-9]{32}\.(?:jpg|png|webp)$~', (string) $ruta)) {
        return;
    }
    $archivo = dirname(__DIR__) . "/" . $ruta;
    if (is_file($archivo)) {
        unlink($archivo);
    }
}
