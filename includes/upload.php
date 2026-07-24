<?php
declare(strict_types=1);

function uploads_dir(): string
{
    $dir = __DIR__ . '/../uploads';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    foreach (['barbeiros', 'servicos', 'planos', 'logo'] as $sub) {
        $p = $dir . '/' . $sub;
        if (!is_dir($p)) {
            mkdir($p, 0775, true);
        }
    }
    return $dir;
}

/**
 * Detecta MIME de imagem sem depender só da extensão fileinfo.
 */
function detect_image_mime(string $path): string
{
    if (class_exists('finfo')) {
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($path);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }

    if (function_exists('mime_content_type')) {
        $mime = @mime_content_type($path);
        if (is_string($mime) && $mime !== '') {
            return $mime;
        }
    }

    $info = @getimagesize($path);
    if (is_array($info) && !empty($info['mime'])) {
        return (string)$info['mime'];
    }

    return '';
}

/**
 * Faz upload de imagem. Retorna path relativo (uploads/...) ou null.
 */
function upload_image(array $file, string $folder): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        return null;
    }

    $tmp = $file['tmp_name'] ?? '';
    if (!$tmp || !is_uploaded_file($tmp)) {
        return null;
    }

    $mime = detect_image_mime($tmp);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];
    if (!isset($allowed[$mime])) {
        return null;
    }

    uploads_dir();
    $name = $folder . '/' . date('YmdHis') . '_' . bin2hex(random_bytes(4)) . '.' . $allowed[$mime];
    $dest = uploads_dir() . '/' . $name;
    if (!move_uploaded_file($tmp, $dest)) {
        return null;
    }
    return 'uploads/' . $name;
}
