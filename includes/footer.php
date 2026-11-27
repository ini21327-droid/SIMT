<?php
/**
 * Layout Footer, Modal Command Palette Lookup Kode, dan Script Global
 */
?>
        </main>

        <?php if (isLoggedIn()): ?>
        <footer class="app-footer d-print-none">
            <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2 text-muted small">
                <div>
                    <strong class="text-slate-800"><?= e(APP_NAME) ?></strong> &mdash; <?= e(APP_FULL_NAME) ?>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <span class="badge bg-sky-soft"><i class="fa-solid fa-earth-asia me-1"></i>Asia/Jakarta (WIB)</span>
                    <span class="badge bg-navy-soft"><i class="fa-solid fa-shield-halved me-1"></i>PDO + Haversine Verified</span>
                </div>
            </div>
        </footer>
        <?php endif; ?>
    </div>
</div>

<?php if (isLoggedIn()): ?>
<!-- Command Palette: Pemanggilan Data Otomatis Berbasis Kode (Spesifikasi 6.1) -->
<div class="modal fade d-print-none" id="quickLookupModal" tabindex="-1" aria-labelledby="quickLookupModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content modern-modal border-0 shadow-lg overflow-hidden">
            <div class="modal-header bg-navy text-white border-0 py-3">
                <h6 class="modal-title fw-bold d-flex align-items-center gap-2" id="quickLookupModalLabel">
                    <span class="brand-icon" style="width: 30px; height: 30px; font-size: 0.85rem;">
                        <i class="fa-solid fa-bolt"></i>
                    </span>
                    <span>Smart Code Lookup</span>
                </h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <p class="small text-muted mb-3">
                    Ketik atau klik contoh kode di bawah untuk memanggil detail <strong>Mata Kuliah</strong> atau <strong>Tugas</strong> secara instan dari database:
                </p>

                <div class="d-flex flex-wrap gap-1 mb-3">
                    <span class="small text-muted me-1">Contoh cepat:</span>
                    <button type="button" class="btn btn-xs-chip lookup-sample-chip" data-type="course" data-code="IF201">IF201</button>
                    <button type="button" class="btn btn-xs-chip lookup-sample-chip" data-type="course" data-code="IF202">IF202</button>
                    <button type="button" class="btn btn-xs-chip lookup-sample-chip" data-type="course" data-code="IF203">IF203</button>
                    <button type="button" class="btn btn-xs-chip lookup-sample-chip" data-type="task" data-code="TGS-IF201-01">TGS-IF201-01</button>
                    <button type="button" class="btn btn-xs-chip lookup-sample-chip" data-type="task" data-code="TGS-IF202-01">TGS-IF202-01</button>
                </div>

                <div class="row g-2 mb-3">
                    <div class="col-4">
                        <select id="lookupType" class="form-select">
                            <option value="course">Mata Kuliah</option>
                            <option value="task">Kode Tugas</option>
                        </select>
                    </div>
                    <div class="col-8">
                        <div class="input-group">
                            <input type="text" id="lookupCodeInput" class="form-control text-uppercase fw-semibold" placeholder="Misal: IF201 / TGS-IF201-01">
                            <button class="btn btn-sky px-3" type="button" id="btnRunLookup">
                                <i class="fa-solid fa-magnifying-glass me-1"></i>Cari
                            </button>
                        </div>
                    </div>
                </div>
                <div id="lookupResultBox" class="d-none"></div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Bootstrap 5.3 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Leaflet.js -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<!-- Chart.js 4 via CDN -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>

<!-- Global AATMS JS -->
<script src="<?= e(BASE_URL) ?>/assets/js/app.js"></script>
<?php if (!empty($extraScripts) && is_array($extraScripts)): ?>
    <?php foreach ($extraScripts as $src): ?>
        <script src="<?= e($src) ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>
</html>
