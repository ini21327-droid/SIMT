<?php
/**
 * Router untuk PHP Built-in Web Server
 * Cara pakai: php -S localhost:8000 router.php
 */
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($uri !== '/' && file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    // Cegah akses langsung ke folder sensitif
    if (preg_match('#^/(config|database|includes|uploads)/#i', $uri)) {
        http_response_code(403);
        echo '403 Forbidden';
        return true;
    }
    return false;
}

if ($uri === '/' || $uri === '') {
    header('Location: /public/index.php');
    exit;
}

http_response_code(404);
echo '404 Halaman Tidak Ditemukan';

