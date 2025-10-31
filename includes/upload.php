<?php
// includes/upload.php
// -------------------------------------------------------------
// Utilidades de subida de archivos para Sign-Up (EPA photo)
// - Tamaño máximo: 2 MB (2 * 1024 * 1024 = 2,097,152 bytes)
// - Tipos permitidos: JPG / JPEG / PNG / PDF
// - Renombrado: EPA_[LICENSE]_[YYYYMMDDhhmmss].[ext]
// - Devuelve arreglo con metadata listo para guardar en BD
// -------------------------------------------------------------
declare(strict_types=1);

/**
 * Convierte un tamaño estilo php.ini (e.g. "8M") a bytes.
 */
function bytes_from_ini(?string $val): ?int {
    if (!$val) return null;
    $val = trim($val);
    $last = strtolower(substr($val, -1));
    $num = (int)$val;
    return match ($last) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => (int)$val,
    };
}

/**
 * Valida y mueve el archivo EPA al directorio de uploads.
 *
 * @param string $field_name Nombre del campo en $_FILES (ej: 'epa_photo')
 * @param string $epa_certification_number Para renombrar el archivo
 * @param string $uploads_dir Ruta absoluta al dir de uploads (ej: __DIR__.'/../storage/uploads')
 * @return array{
 *   epa_photo_url:string,
 *   epa_photo_filename:string,
 *   epa_photo_mime:string,
 *   epa_photo_size:int,
 *   epa_photo_checksum:string
 * }
 * @throws RuntimeException si hay cualquier error de validación
 */
function validate_and_store_epa_photo(string $field_name, string $epa_certification_number, string $uploads_dir): array
{
    if (!isset($_FILES[$field_name])) {
        throw new RuntimeException('EPA photo is required.');
    }

    $file = $_FILES[$field_name];

    // Errores nativos de PHP primero
    if (!is_array($file) || !isset($file['error'])) {
        throw new RuntimeException('Invalid file upload.');
    }
    if ($file['error'] === UPLOAD_ERR_INI_SIZE) {
        // Excedió upload_max_filesize del php.ini
        throw new RuntimeException('EPA photo exceeds server size limit.');
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Failed to upload EPA photo (code '.$file['error'].').');
    }

    // Tamaño en BYTES (correcto)
    $size = (int)$file['size'];
    if ($size <= 0) {
        throw new RuntimeException('EPA photo is empty.');
    }
    // 2 MB exactos en bytes
    $max_bytes = 2.5 * 1024 * 1024; // 2,097,152
    if ($size > $max_bytes) {
        throw new RuntimeException('Invalid EPA photo size (must be ≤ 2 MB).');
    }

    // Comprobar también límites de php.ini (por seguridad)
    $post_max = bytes_from_ini(ini_get('post_max_size')) ?? null;
    $upl_max  = bytes_from_ini(ini_get('upload_max_filesize')) ?? null;
    if (($post_max !== null && $size > $post_max) || ($upl_max !== null && $size > $upl_max)) {
        throw new RuntimeException('EPA photo exceeds server configuration limits.');
    }

    // Detectar MIME real (finfo), no solo por extensión
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']) ?: 'application/octet-stream';

    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'application/pdf' => 'pdf',
    ];
    if (!array_key_exists($mime, $allowed)) {
        throw new RuntimeException('EPA photo must be JPG, PNG or PDF.');
    }

    // Preparar destino
    if (!is_dir($uploads_dir)) {
        if (!mkdir($uploads_dir, 0755, true)) {
            throw new RuntimeException('Cannot create uploads directory.');
        }
    }
    if (!is_writable($uploads_dir)) {
        throw new RuntimeException('Uploads directory is not writable.');
    }

    $safe_license = preg_replace('/[^A-Z0-9_\-]/i', '_', $epa_certification_number) ?: 'EPA';
    $ts = date('YmdHis');
    $ext = $allowed[$mime];
    $filename = "EPA_{$safe_license}_{$ts}.{$ext}";
    $dest = rtrim($uploads_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        throw new RuntimeException('Failed to save EPA photo.');
    }

    // Calcular checksum
    $checksum = hash_file('sha256', $dest);

    // Construir URL pública si sirves /storage via /public … ajusta si usas otra ruta
    $base = rtrim(base_url('/'), '/'); // helper existente
    // Asumimos que /storage/uploads está mapeado públicamente como /storage/uploads
    $public_path = '/storage/uploads/' . $filename;
    $url = $base . $public_path;

    return [
        'epa_photo_url'      => $url,
        'epa_photo_filename' => $filename,
        'epa_photo_mime'     => $mime,
        'epa_photo_size'     => $size,
        'epa_photo_checksum' => $checksum,
    ];
}
