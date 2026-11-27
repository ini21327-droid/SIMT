<?php
/**
 * Konfigurasi Global Aplikasi AATMS
 * (Academic Attendance & Task Monitoring System)
 * Mendukung akses baik dari root folder proyek (XAMPP/Laragon subfolder)
 * maupun DocumentRoot yang diarahkan langsung ke folder /public.
 */

date_default_timezone_set('Asia/Jakarta');

define('APP_NAME', 'AATMS');
define('APP_FULL_NAME', 'Academic Attendance & Task Monitoring System');
define('APP_VERSION', '2.0.0');

// Deteksi Base URL & API URL secara akurat di semua skenario deployment
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');

if (($pos = strpos($scriptName, '/public/')) !== false) {
    // Skenario 1: Diakses via http://localhost/aatms/public/...
    $rootUrlPath   = substr($scriptName, 0, $pos);
    $publicUrlPath = $rootUrlPath . '/public';
    $apiUrlPath    = $rootUrlPath . '/api';
} elseif (($pos = strpos($scriptName, '/api/')) !== false) {
    // Skenario 2: Diakses via endpoint /api/... (atau /public/api/...)
    $beforeApi = substr($scriptName, 0, $pos);
    if (substr($beforeApi, -7) === '/public') {
        $rootUrlPath   = substr($beforeApi, 0, -7);
        $publicUrlPath = $beforeApi;
        $apiUrlPath    = $beforeApi . '/api';
    } else {
        $rootUrlPath   = $beforeApi;
        $publicUrlPath = $rootUrlPath . '/public';
        $apiUrlPath    = $rootUrlPath . '/api';
    }
} else {
    // Skenario 3: DocumentRoot langsung mengarah ke folder public/ (misal php -S localhost:8000 -t public atau VirtualHost)
    if (preg_match('#^(.*?)/(admin|dosen|mahasiswa|kaprodi)/[^/]+$#', $scriptName, $matches)) {
        $publicUrlPath = $matches[1];
    } else {
        $dir = rtrim(dirname($scriptName), '/');
        $publicUrlPath = ($dir === '.' || $dir === '\\') ? '' : $dir;
    }
    $rootUrlPath = $publicUrlPath;
    $apiUrlPath  = $publicUrlPath . '/api';
}

define('ROOT_URL', $protocol . '://' . $host . $rootUrlPath);
define('BASE_URL', $protocol . '://' . $host . $publicUrlPath);
define('API_URL', $protocol . '://' . $host . $apiUrlPath);

// Path Direktori Sistem
define('ROOT_PATH', dirname(__DIR__));
define('UPLOAD_TASK_DIR', ROOT_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'tasks' . DIRECTORY_SEPARATOR);
define('MAX_UPLOAD_SIZE', 10 * 1024 * 1024); // 10 MB

// Konfigurasi Google Maps API Key & Koordinat Default Kampus Unimal Reuleut
$mapsConfigFile = __DIR__ . '/maps_key.json';
$savedMapsKey = '';
$savedDefaultLayer = 'gmaps_hybrid';
if (file_exists($mapsConfigFile)) {
    $mapsCfg = json_decode((string)file_get_contents($mapsConfigFile), true);
    if (is_array($mapsCfg)) {
        $savedMapsKey = trim($mapsCfg['google_maps_api_key'] ?? '');
        $savedDefaultLayer = trim($mapsCfg['default_map_layer'] ?? 'gmaps_hybrid');
    }
}
define('GOOGLE_MAPS_API_KEY', getenv('GOOGLE_MAPS_API_KEY') ?: $savedMapsKey);
define('DEFAULT_MAP_LAYER', $savedDefaultLayer ?: 'gmaps_hybrid');
define('CAMPUS_NAME', 'Kampus Universitas Malikussaleh (Unimal) Reuleut');
define('CAMPUS_DEFAULT_LAT', 5.23405600);
define('CAMPUS_DEFAULT_LNG', 96.98813900);

// Allowed extensions & MIME types untuk tugas
define('ALLOWED_EXTENSIONS', ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png']);
define('ALLOWED_MIMES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'image/jpeg',
    'image/png',
]);
