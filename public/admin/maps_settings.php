<?php
/**
 * Modul Admin: Konfigurasi Google Maps API Key & Uji Pemindai Perangkat (Unimal Reuleut)
 */
require_once __DIR__ . '/../../includes/auth.php';
requireRole('admin');

$pageTitle = 'Konfigurasi Google Maps & Perangkat';
$mapsConfigFile = ROOT_PATH . '/config/maps_key.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireCsrf();
    $apiKey = trim($_POST['google_maps_api_key'] ?? '');
    $defaultLayer = trim($_POST['default_map_layer'] ?? 'gmaps_hybrid');

    if (!in_array($defaultLayer, ['gmaps_hybrid', 'gmaps_streets', 'gmaps_terrain', 'osm'], true)) {
        $defaultLayer = 'gmaps_hybrid';
    }

    $cfgData = [
        'google_maps_api_key' => $apiKey,
        'default_map_layer'   => $defaultLayer,
        'updated_at'          => date('Y-m-d H:i:s'),
        'updated_by'          => currentUser()['username'] ?? 'admin'
    ];

    file_put_contents($mapsConfigFile, json_encode($cfgData, JSON_PRETTY_PRINT));
    setFlash('success', 'Konfigurasi Google Maps API Key & Layer Default berhasil disimpan!');
    header('Location: ' . BASE_URL . '/admin/maps_settings.php');
    exit;
}

$extraScripts = [BASE_URL . '/assets/js/gps-attendance.js'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center mb-4 gap-2">
    <div>
        <h4 class="fw-bold mb-1">Konfigurasi Google Maps API &amp; Deteksi Perangkat</h4>
        <p class="text-muted small mb-0">Kelola API Key Google Maps Platform, mode tampilan peta satelit, dan uji pemindai perangkat di area <strong><?= e(CAMPUS_NAME) ?></strong>.</p>
    </div>
    <span class="badge bg-sky-soft px-3 py-2">
        <i class="fa-brands fa-google me-1"></i>Koordinat Pusat Unimal Reuleut: <?= e((string)CAMPUS_DEFAULT_LAT) ?>, <?= e((string)CAMPUS_DEFAULT_LNG) ?>
    </span>
</div>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card-custom p-4 mb-4">
            <h6 class="fw-bold mb-3"><i class="fa-solid fa-key text-sky me-2"></i>Pengaturan Google Maps API Key</h6>
            <form method="POST" action="">
                <?= csrfInput() ?>
                <div class="mb-3">
                    <label class="form-label small fw-semibold">Google Maps API Key</label>
                    <input type="text" name="google_maps_api_key" class="form-control font-monospace"
                           value="<?= e(GOOGLE_MAPS_API_KEY) ?>"
                           placeholder="AIzaSy... (Kosongkan untuk Google Hybrid Tile gratis)">
                    <div class="form-text small">
                        Dengan memasukkan API Key dari <strong>Google Cloud Console (Maps JavaScript &amp; Geocoding API)</strong>, sistem akan mengautentikasi tile satelit resolusi tinggi dan mengaktifkan pencarian alamat gedung otomatis (<em>Reverse Geocoding</em>). Tanpa API Key pun, layer <strong>Google Maps Satelit Hybrid</strong> tetap aktif.
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-semibold">Layer Peta Default</label>
                    <select name="default_map_layer" class="form-select">
                        <option value="gmaps_hybrid" <?= DEFAULT_MAP_LAYER === 'gmaps_hybrid' ? 'selected' : '' ?>>Google Maps Satelit + Nama Gedung (Hybrid)</option>
                        <option value="gmaps_streets" <?= DEFAULT_MAP_LAYER === 'gmaps_streets' ? 'selected' : '' ?>>Google Maps Peta Jalan (Streets)</option>
                        <option value="gmaps_terrain" <?= DEFAULT_MAP_LAYER === 'gmaps_terrain' ? 'selected' : '' ?>>Google Maps Kontur (Terrain)</option>
                        <option value="osm" <?= DEFAULT_MAP_LAYER === 'osm' ? 'selected' : '' ?>>OpenStreetMap</option>
                    </select>
                </div>

                <button type="submit" class="btn btn-sky w-100 fw-semibold">
                    <i class="fa-solid fa-floppy-disk me-2"></i>Simpan Konfigurasi Peta
                </button>
            </form>
        </div>

        <!-- Panel Diagnostik Pemindai Perangkat Saat Ini -->
        <div class="card-custom p-4">
            <h6 class="fw-bold mb-2"><i class="fa-solid fa-microchip text-sky me-2"></i>Diagnostik Pemindai Perangkat Anda</h6>
            <p class="small text-muted mb-3">Hasil deteksi otomatis perangkat yang sedang Anda gunakan saat ini:</p>
            <div id="adminDeviceDiagnosticBox" class="p-3 rounded-3 bg-light border small">
                <i class="fa-solid fa-spinner fa-spin me-2 text-sky"></i>Memindai perangkat...
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card-custom p-4 h-100">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h6 class="fw-bold mb-0"><i class="fa-solid fa-earth-asia text-sky me-2"></i>Preview Peta Satelit Kampus Unimal Reuleut</h6>
                    <small class="text-muted">Gunakan tombol layer di pojok kanan atas peta untuk beralih antara Satelit, Jalan, dan Terrain.</small>
                </div>
            </div>
            <div id="adminPreviewUnimalMap" class="leaflet-map-box" style="height: 430px;"></div>
            <div id="adminGeocodeAddress" class="small text-muted mt-2"></div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const map = L.map('adminPreviewUnimalMap').setView([AATMS_GPS.UNIMAL_LAT, AATMS_GPS.UNIMAL_LNG], 18);
    AATMS_GPS.attachGoogleMapLayers(map);

    L.circle([AATMS_GPS.UNIMAL_LAT, AATMS_GPS.UNIMAL_LNG], {
        color: '#0EA5E9',
        fillColor: '#0EA5E9',
        fillOpacity: 0.2,
        radius: 150
    }).addTo(map);

    L.marker([AATMS_GPS.UNIMAL_LAT, AATMS_GPS.UNIMAL_LNG]).addTo(map)
        .bindPopup('<b>Kampus Utama Universitas Malikussaleh (Unimal) Reuleut</b><br>Kec. Muara Batu, Kab. Aceh Utara').openPopup();

    AATMS_GPS.reverseGeocodeGoogle(AATMS_GPS.UNIMAL_LAT, AATMS_GPS.UNIMAL_LNG, 'adminGeocodeAddress');

    const dev = await AATMS_GPS.detectDeviceTelemetry();
    const box = document.getElementById('adminDeviceDiagnosticBox');
    if (box) {
        box.innerHTML = `
            <div class="d-flex flex-column gap-2">
                <div class="d-flex justify-content-between"><span>Tipe Perangkat:</span><strong><i class="fa-solid ${dev.deviceIcon} text-sky me-1"></i>${escapeHtml(dev.deviceType)}</strong></div>
                <div class="d-flex justify-content-between"><span>Sistem Operasi:</span><strong>${escapeHtml(dev.osName)}</strong></div>
                <div class="d-flex justify-content-between"><span>Browser:</span><strong>${escapeHtml(dev.browserName)}</strong></div>
                <div class="d-flex justify-content-between"><span>Resolusi &amp; CPU:</span><strong>${escapeHtml(dev.screenRes)} (${escapeHtml(dev.cpuCores)})</strong></div>
                <div class="d-flex justify-content-between"><span>Jaringan &amp; Baterai:</span><strong>${escapeHtml(dev.networkInfo)}${dev.batteryInfo ? ' • ' + escapeHtml(dev.batteryInfo) : ''}</strong></div>
                <div class="d-flex justify-content-between border-top pt-2"><span>Fingerprint ID Unik:</span><code class="fw-bold text-sky">${escapeHtml(dev.fingerprintId)}</code></div>
            </div>
        `;
    }
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

