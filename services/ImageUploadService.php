<?php
/**
 * Validates and stores uploaded images. Items photos go to uploads/items (public),
 * claim evidence goes to uploads/claim-evidence (private, served only through actions/serve-evidence.php).
 * File names are random and the extension comes from the detected image type, never from the user.
 */
final class ImageUploadService
{
    /** Flat list of upload entries for <input name="x"> or <input name="x[]" multiple>. Empty slots are skipped. */
    public static function uploadedFiles($key)
    {
        $out = array();
        if (!isset($_FILES[$key]) || !is_array($_FILES[$key]) || !isset($_FILES[$key]['name'])) {
            return $out;
        }
        $f = $_FILES[$key];
        if (is_array($f['name'])) {
            $n = count($f['name']);
            for ($i = 0; $i < $n; $i++) {
                $entry = array(
                    'name' => $f['name'][$i],
                    'tmp_name' => isset($f['tmp_name'][$i]) ? $f['tmp_name'][$i] : '',
                    'error' => isset($f['error'][$i]) ? $f['error'][$i] : UPLOAD_ERR_NO_FILE,
                    'size' => isset($f['size'][$i]) ? $f['size'][$i] : 0,
                );
                if ((int) $entry['error'] !== UPLOAD_ERR_NO_FILE) {
                    $out[] = $entry;
                }
            }
        } elseif ((int) $f['error'] !== UPLOAD_ERR_NO_FILE) {
            $out[] = $f;
        }
        return $out;
    }

    private static function errorMessage($code)
    {
        if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
            return 'The image is too large (maximum ' . (int) (UPLOAD_MAX_SIZE / 1048576) . ' MB).';
        }
        if ($code === UPLOAD_ERR_PARTIAL) {
            return 'The upload was interrupted. Please try again.';
        }
        return 'The server could not receive the upload. Please try again or contact an administrator.';
    }

    /** Checks one uploaded file. Returns array('ok'=>true, 'tmp_name', 'mime', 'ext', 'size') or array('ok'=>false, 'error'=>msg). */
    public static function inspect($file)
    {
        $code = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;
        if ($code !== UPLOAD_ERR_OK) {
            return array('ok' => false, 'error' => self::errorMessage($code));
        }
        $tmp = isset($file['tmp_name']) ? (string) $file['tmp_name'] : '';
        if ($tmp === '' || !is_file($tmp)) {
            return array('ok' => false, 'error' => 'The uploaded file could not be read. Please try again.');
        }
        if (PHP_SAPI !== 'cli' && !is_uploaded_file($tmp)) {
            return array('ok' => false, 'error' => 'The upload was not accepted.');
        }
        $size = (int) filesize($tmp);
        if ($size < 1) {
            return array('ok' => false, 'error' => 'The uploaded file is empty.');
        }
        if ($size > UPLOAD_MAX_SIZE) {
            return array('ok' => false, 'error' => 'The image is too large (maximum ' . (int) (UPLOAD_MAX_SIZE / 1048576) . ' MB).');
        }
        $name = isset($file['name']) ? (string) $file['name'] : '';
        $dot = strrpos($name, '.');
        $ext = ($dot === false) ? '' : strtolower(substr($name, $dot + 1));
        if (!in_array($ext, ALLOWED_IMAGE_EXTENSIONS, true)) {
            return array('ok' => false, 'error' => 'Only JPG, PNG or WebP images are allowed.');
        }
        $info = getimagesize($tmp);
        if ($info === false || !isset($info['mime']) || !array_key_exists($info['mime'], ALLOWED_IMAGE_MIMES)) {
            return array('ok' => false, 'error' => 'That file is not a valid JPG, PNG or WebP image.');
        }
        $mime = $info['mime'];
        $canonical = ALLOWED_IMAGE_MIMES[$mime];
        if (!($canonical === $ext || ($canonical === 'jpg' && $ext === 'jpeg'))) {
            return array('ok' => false, 'error' => 'The file extension does not match the image content.');
        }
        if (function_exists('finfo_open')) {
            $fi = finfo_open(FILEINFO_MIME_TYPE);
            if ($fi !== false) {
                $real = finfo_file($fi, $tmp);
                finfo_close($fi);
                if ($real !== false && $real !== $mime && $real !== 'application/octet-stream') {
                    return array('ok' => false, 'error' => 'That file is not a valid JPG, PNG or WebP image.');
                }
            }
        }
        $w = isset($info[0]) ? (int) $info[0] : 0;
        $h = isset($info[1]) ? (int) $info[1] : 0;
        if ($w < 1 || $h < 1 || $w > 12000 || $h > 12000) {
            return array('ok' => false, 'error' => 'The image dimensions are not supported.');
        }
        return array('ok' => true, 'tmp_name' => $tmp, 'mime' => $mime, 'ext' => $canonical, 'size' => $size);
    }

    /** Moves an inspected file into place and returns its relative path, e.g. "items/ab12....jpg". */
    public static function store($inspected, $folder)
    {
        if ($folder !== 'items' && $folder !== 'claim-evidence') {
            throw new InvalidArgumentException('Unknown upload folder.');
        }
        $dir = APP_ROOT . '/uploads/' . $folder;
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('The upload folder could not be created.');
        }
        if (!is_writable($dir)) {
            throw new RuntimeException('The upload folder is not writable.');
        }
        $name = bin2hex(random_bytes(16)) . '.' . $inspected['ext'];
        $dest = $dir . '/' . $name;
        $moved = move_uploaded_file($inspected['tmp_name'], $dest);
        if (!$moved && PHP_SAPI === 'cli') {
            $moved = rename($inspected['tmp_name'], $dest);    // command-line tests have no real HTTP upload
        }
        if (!$moved) {
            throw new RuntimeException('The uploaded file could not be saved.');
        }
        chmod($dest, 0644);
        return $folder . '/' . $name;
    }

    /** Absolute path of a stored upload, or null if the value is not a server-generated path inside uploads/. */
    public static function absolutePath($relative)
    {
        if (!is_string($relative) || preg_match('/^(items|claim-evidence)\/[a-f0-9]{32}\.(jpg|png|webp)$/', $relative) !== 1) {
            return null;
        }
        $real = realpath(APP_ROOT . '/uploads/' . $relative);
        if ($real === false) {
            return null;
        }
        $real = str_replace('\\', '/', $real);
        return (strpos($real, APP_ROOT . '/uploads/') === 0) ? $real : null;
    }

    public static function delete($relative)
    {
        $abs = self::absolutePath($relative);
        if ($abs !== null && is_file($abs)) {
            unlink($abs);
        }
    }
}
