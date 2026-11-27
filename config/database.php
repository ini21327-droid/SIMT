<?php
/**
 * Koneksi Database PDO (MySQL / MariaDB) dengan Auto-Migration & Auto-Seed Aman
 */

require_once __DIR__ . '/app.php';

define('DB_HOST', getenv('AATMS_DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('AATMS_DB_PORT') ?: '3306');
define('DB_NAME', getenv('AATMS_DB_NAME') ?: 'aatms_db');
define('DB_USER', getenv('AATMS_DB_USER') ?: 'root');
define('DB_PASS', getenv('AATMS_DB_PASS') !== false ? getenv('AATMS_DB_PASS') : '');

/**
 * Menjalankan file SQL multi-statement dengan aman pada instance PDO
 */
function executeSqlFile(PDO $pdo, string $filePath): void
{
    if (!file_exists($filePath)) {
        return;
    }
    $sql = file_get_contents($filePath);
    if ($sql === false || trim($sql) === '') {
        return;
    }

    // Hapus baris komentar SQL '-- ...'
    $lines = preg_split('/\R/', $sql);
    $cleanLines = [];
    foreach ($lines as $line) {
        $trimmed = ltrim($line);
        if (strpos($trimmed, '--') === 0 || $trimmed === '') {
            continue;
        }
        $cleanLines[] = $line;
    }
    $cleanSql = implode("\n", $cleanLines);

    // Pisahkan statement berdasarkan titik koma di akhir baris
    $statements = preg_split('/;\s*(\R|$)/', $cleanSql);
    foreach ($statements as $stmtSql) {
        $stmtSql = trim($stmtSql);
        if ($stmtSql !== '') {
            $pdo->exec($stmtSql);
        }
    }
}

/**
 * Tampilkan halaman diagnostik modern jika MySQL belum dinyalakan
 */
function renderDatabaseErrorPage(string $errorMsg): void
{
    $isApi = (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) ||
             (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false);

    if ($isApi) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Koneksi database MySQL gagal: ' . $errorMsg
        ]);
        exit;
    }

    http_response_code(500);
    $safeMsg = htmlspecialchars($errorMsg, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Koneksi Database — AATMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Plus Jakarta Sans', sans-serif; background: linear-gradient(135deg, #0F172A, #1E293B); color: #F8FAFC; min-height: 100vh; margin: 0; display: flex; align-items: center; justify-content: center; padding: 24px; }
        .card { background: rgba(30, 41, 59, 0.92); border: 1px solid rgba(148, 163, 184, 0.2); border-radius: 20px; max-width: 580px; width: 100%; padding: 32px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .badge { display: inline-block; background: rgba(239, 68, 68, 0.2); color: #FCA5A5; border: 1px solid rgba(239,68,68,0.4); padding: 6px 12px; border-radius: 999px; font-size: 12px; font-weight: 600; margin-bottom: 16px; }
        h2 { margin: 0 0 10px; font-size: 22px; }
        p { color: #CBD5E1; font-size: 14px; line-height: 1.6; }
        .code-box { background: #0F172A; border: 1px solid #334155; padding: 12px 16px; border-radius: 12px; font-family: monospace; font-size: 13px; color: #F87171; margin: 16px 0; word-break: break-word; }
        ol { color: #CBD5E1; font-size: 14px; line-height: 1.7; padding-left: 20px; }
        .btn { display: inline-block; margin-top: 12px; background: #0EA5E9; color: #fff; text-decoration: none; padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>
    <div class="card">
        <span class="badge">MySQL / MariaDB Belum Aktif</span>
        <h2>Layanan Database Belum Terhubung</h2>
        <p>Sistem <strong>AATMS</strong> siap menginisialisasi tabel dan data awal secara otomatis, namun layanan MySQL pada port <code>3306</code> belum merespons.</p>
        <div class="code-box">{$safeMsg}</div>
        <ol>
            <li>Buka aplikasi <strong>Laragon</strong> atau <strong>XAMPP Control Panel</strong> Anda.</li>
            <li>Klik tombol <strong>Start</strong> pada layanan <strong>MySQL / MariaDB</strong>.</li>
            <li>Muat ulang halaman ini — database <code>aatms_db</code> beserta akun demo akan dibuat otomatis!</li>
        </ol>
        <a href="" class="btn">Coba Hubungkan Kembali</a>
    </div>
</body>
</html>
HTML;
    exit;
}

/**
 * Mengembalikan instance PDO yang terhubung ke database AATMS.
 */
function getDB(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_TIMEOUT            => 3,
    ];

    try {
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        $pdo->exec("SET time_zone = '+07:00'");

        // Pastikan tabel sudah ada (jika user membuat database kosong aatms_db secara manual)
        $hasUsersTable = $pdo->query("SHOW TABLES LIKE 'users'")->fetchColumn();
        if (!$hasUsersTable) {
            $initPdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);
            $initPdo->exec("SET time_zone = '+07:00'");
            executeSqlFile($initPdo, ROOT_PATH . '/database/schema.sql');
            executeSqlFile($initPdo, ROOT_PATH . '/database/seed.sql');
        } else {
            // Auto-migrasi koordinat lama (-6.200000) ke Kampus Universitas Malikussaleh Reuleut (5.234056, 96.988139)
            $pdo->exec("
                UPDATE attendance_sessions
                SET target_lat = 5.23405600, target_lng = 96.98813900
                WHERE target_lat BETWEEN -6.21 AND -6.19
            ");
            $pdo->exec("
                UPDATE attendances
                SET student_lat = 5.23413000, student_lng = 96.98818000
                WHERE student_lat BETWEEN -6.21 AND -6.19
            ");

            // Auto-migrasi kolom deteksi perangkat pada tabel attendances jika belum ada
            $hasDeviceCol = $pdo->query("SHOW COLUMNS FROM `attendances` LIKE 'device_info'")->fetchColumn();
            if (!$hasDeviceCol) {
                $pdo->exec("
                    ALTER TABLE `attendances`
                    ADD COLUMN `gps_accuracy` DECIMAL(10, 2) DEFAULT NULL AFTER `distance_meters`,
                    ADD COLUMN `device_info` VARCHAR(180) DEFAULT NULL AFTER `gps_accuracy`,
                    ADD COLUMN `device_fingerprint` VARCHAR(64) DEFAULT NULL AFTER `device_info`,
                    ADD COLUMN `ip_address` VARCHAR(45) DEFAULT NULL AFTER `device_fingerprint`
                ");
                $pdo->exec("
                    UPDATE `attendances`
                    SET `gps_accuracy` = 6.50,
                        `device_info` = 'Smartphone Android 14 • Chrome Mobile',
                        `device_fingerprint` = CONCAT('DEV-', LPAD(student_id, 4, '0')),
                        `ip_address` = '10.10.12.45'
                    WHERE `device_info` IS NULL
                ");
            }
        }

        return $pdo;
    } catch (PDOException $e) {
        if ($e->getCode() == 1049 || strpos($e->getMessage(), 'Unknown database') !== false) {
            try {
                $dsnNoDb = sprintf('mysql:host=%s;port=%s;charset=utf8mb4', DB_HOST, DB_PORT);
                $initPdo = new PDO($dsnNoDb, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE          => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => true,
                ]);
                $initPdo->exec("SET time_zone = '+07:00'");

                executeSqlFile($initPdo, ROOT_PATH . '/database/schema.sql');
                executeSqlFile($initPdo, ROOT_PATH . '/database/seed.sql');

                $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                $pdo->exec("SET time_zone = '+07:00'");
                return $pdo;
            } catch (PDOException $innerEx) {
                renderDatabaseErrorPage($innerEx->getMessage());
            }
        }

        renderDatabaseErrorPage($e->getMessage());
    }
}
