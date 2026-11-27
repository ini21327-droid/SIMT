<?php
/**
 * Layout Header & Glassmorphism Topbar AATMS v2.0
 */
require_once __DIR__ . '/auth.php';
$user = currentUser();
$pageTitle = $pageTitle ?? APP_FULL_NAME;
$flash = getFlash();
?>
<!DOCTYPE html>
<html lang="id" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= e(generateCsrfToken()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <meta name="api-url" content="<?= e(API_URL) ?>">
    <meta name="gmaps-key" content="<?= e(GOOGLE_MAPS_API_KEY) ?>">
    <meta name="default-map-layer" content="<?= e(DEFAULT_MAP_LAYER) ?>">
    <title><?= e($pageTitle) ?> — <?= e(APP_NAME) ?></title>

    <script>
        (function() {
            const savedTheme = localStorage.getItem('aatms_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <!-- Google Fonts: Plus Jakarta Sans & Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5.3 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">

    <!-- Leaflet.js CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

    <!-- Custom Modern AATMS Stylesheet -->
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/css/style.css">
</head>
<body>
<!-- Toast Container Global -->
<div id="aatmsToastContainer" class="aatms-toast-container d-print-none"></div>

<div class="app-wrapper">
    <?php if (isLoggedIn()): ?>
        <div class="sidebar-backdrop d-lg-none" id="sidebarBackdrop"></div>
        <?php include __DIR__ . '/sidebar.php'; ?>
    <?php endif; ?>

    <div class="main-panel <?= isLoggedIn() ? '' : 'w-100 ms-0' ?>">
        <?php if (isLoggedIn()): ?>
        <header class="top-navbar d-print-none">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-icon-soft d-lg-none" id="sidebarToggle" type="button" aria-label="Toggle Menu">
                    <i class="fa-solid fa-bars-staggered"></i>
                </button>
                <div>
                    <h5 class="mb-0 fw-bold page-heading-title"><?= e($pageTitle) ?></h5>
                    <div class="d-none d-sm-flex align-items-center gap-2 text-muted" style="font-size: 0.76rem;">
                        <span><i class="fa-regular fa-clock me-1 text-sky"></i><span id="liveWibClock"><?= e(formatDateTime(date('Y-m-d H:i:s'))) ?></span></span>
                    </div>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <!-- Smart Lookup Command Trigger (Ctrl + K) -->
                <button type="button" class="btn btn-command-search d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#quickLookupModal" title="Pencarian Cepat Kode Matkul / Tugas (Ctrl+K)">
                    <i class="fa-solid fa-magnifying-glass text-sky"></i>
                    <span class="d-none d-md-inline">Cek Kode Cepat...</span>
                    <kbd class="d-none d-lg-inline-block">Ctrl K</kbd>
                </button>

                <!-- Dark / Light Mode Toggle -->
                <button type="button" class="btn btn-icon-soft" id="themeToggleBtn" title="Ganti Tema Terang / Gelap">
                    <i class="fa-solid fa-moon" id="themeToggleIcon"></i>
                </button>

                <!-- User Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn user-pill-btn d-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <span class="avatar-circle bg-gradient-sky text-white fw-bold">
                            <?= e(strtoupper(substr($user['full_name'] ?? 'U', 0, 1))) ?>
                        </span>
                        <div class="text-start d-none d-md-block">
                            <div class="fw-semibold small lh-1 user-pill-name"><?= e($user['full_name'] ?? '') ?></div>
                            <span class="role-micro-tag text-uppercase"><?= e($user['role'] ?? '') ?></span>
                        </div>
                        <i class="fa-solid fa-chevron-down small text-muted ms-1 d-none d-sm-inline"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end modern-dropdown shadow-lg border-0 mt-2">
                        <li class="px-3 py-2 border-bottom">
                            <div class="fw-bold small"><?= e($user['full_name'] ?? '') ?></div>
                            <div class="text-muted" style="font-size: 0.75rem;"><?= e($user['email'] ?? '') ?></div>
                            <?php if (!empty($user['nim_nidn'])): ?>
                                <div class="badge bg-sky-soft mt-1">ID: <?= e($user['nim_nidn']) ?></div>
                            <?php endif; ?>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 small" href="<?= e(BASE_URL) ?>/profile.php">
                                <i class="fa-solid fa-user-gear me-2 text-sky"></i>Profil & Keamanan Akun
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item py-2 small" href="<?= e(BASE_URL) ?>/schedule.php">
                                <i class="fa-solid fa-calendar-week me-2 text-sky"></i>Jadwal Perkuliahan
                            </a>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <a class="dropdown-item text-danger py-2 small fw-semibold" href="<?= e(BASE_URL) ?>/logout.php">
                                <i class="fa-solid fa-right-from-bracket me-2"></i>Keluar Sistem
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </header>
        <?php endif; ?>

        <main class="content-container">
            <?php if ($flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?> modern-alert alert-dismissible fade show d-flex align-items-center gap-3" role="alert">
                    <div class="alert-icon-circle">
                        <i class="fa-solid <?= $flash['type'] === 'success' ? 'fa-circle-check' : ($flash['type'] === 'danger' ? 'fa-triangle-exclamation' : 'fa-circle-info') ?>"></i>
                    </div>
                    <div class="flex-grow-1 small fw-medium"><?= e($flash['message']) ?></div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
