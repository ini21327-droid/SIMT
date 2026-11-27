/**
 * AATMS v2.0 Global UI, Theme Manager, Command Palette, Live Clock & Helpers (app.js)
 */

const AATMS_UI = {
    toast(message, type = 'info') {
        const container = document.getElementById('aatmsToastContainer');
        if (!container) return;

        const el = document.createElement('div');
        el.className = `aatms-toast toast-${type}`;
        const icon = type === 'success'
            ? 'fa-circle-check text-success'
            : (type === 'danger' ? 'fa-circle-xmark text-danger' : 'fa-bolt text-sky');

        el.innerHTML = `<i class="fa-solid ${icon} fs-6"></i><div>${escapeHtml(message)}</div>`;
        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(20px)';
            el.style.transition = 'all 0.25s ease';
            setTimeout(() => el.remove(), 260);
        }, 3200);
    },

    copyCode(code, label = 'Kode') {
        if (!navigator.clipboard) return;
        navigator.clipboard.writeText(code).then(() => {
            AATMS_UI.toast(`${label} "${code}" berhasil disalin ke clipboard!`, 'success');
        });
    }
};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar & Backdrop Toggle
    const sidebar = document.getElementById('appSidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const toggleBtn = document.getElementById('sidebarToggle');
    const closeBtn = document.getElementById('sidebarClose');

    function setSidebarOpen(open) {
        if (!sidebar) return;
        sidebar.classList.toggle('show', open);
        if (backdrop) backdrop.classList.toggle('show', open);
    }

    toggleBtn?.addEventListener('click', () => setSidebarOpen(!sidebar.classList.contains('show')));
    closeBtn?.addEventListener('click', () => setSidebarOpen(false));
    backdrop?.addEventListener('click', () => setSidebarOpen(false));

    // 2. Dark / Light Theme Switcher
    const themeBtn = document.getElementById('themeToggleBtn');
    const themeIcon = document.getElementById('themeToggleIcon');

    function syncThemeIcon() {
        if (!themeIcon) return;
        const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
        themeIcon.className = isDark ? 'fa-solid fa-sun text-warning' : 'fa-solid fa-moon';
    }
    syncThemeIcon();

    themeBtn?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-theme') || 'light';
        const next = current === 'dark' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-theme', next);
        localStorage.setItem('aatms_theme', next);
        syncThemeIcon();
    });

    // 3. Live Ticking WIB Clock in Topbar
    const liveClockEl = document.getElementById('liveWibClock');
    if (liveClockEl) {
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];
        setInterval(() => {
            const now = new Date();
            const wib = new Date(now.toLocaleString('en-US', { timeZone: 'Asia/Jakarta' }));
            const d = String(wib.getDate()).padStart(2, '0');
            const m = months[wib.getMonth()];
            const y = wib.getFullYear();
            const hh = String(wib.getHours()).padStart(2, '0');
            const mm = String(wib.getMinutes()).padStart(2, '0');
            const ss = String(wib.getSeconds()).padStart(2, '0');
            liveClockEl.textContent = `${d} ${m} ${y}, ${hh}:${mm}:${ss} WIB`;
        }, 1000);
    }

    // 4. Live Countdown Timers ([data-countdown="YYYY-MM-DD HH:MM:SS"])
    const countdownEls = document.querySelectorAll('[data-countdown]');
    if (countdownEls.length > 0) {
        const updateCountdowns = () => {
            const nowMs = Date.now();
            countdownEls.forEach(el => {
                const raw = el.getAttribute('data-countdown');
                if (!raw) return;
                const targetMs = new Date(raw.replace(' ', 'T') + '+07:00').getTime();
                if (isNaN(targetMs)) return;

                const diffSec = Math.floor((targetMs - nowMs) / 1000);
                if (diffSec <= 0) {
                    el.textContent = 'Waktu Habis';
                    el.classList.remove('badge-status-green', 'badge-status-yellow');
                    el.classList.add('badge-status-red');
                    return;
                }
                const d = Math.floor(diffSec / 86400);
                const h = Math.floor((diffSec % 86400) / 3600);
                const m = Math.floor((diffSec % 3600) / 60);
                const s = diffSec % 60;
                if (d > 0) {
                    el.textContent = `Sisa ${d}h ${h}j ${m}m`;
                } else {
                    el.textContent = `Sisa ${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
                }
            });
        };
        updateCountdowns();
        setInterval(updateCountdowns, 1000);
    }

    // 5. Instant Client-Side Table Search ([data-table-search="#targetTable"])
    document.querySelectorAll('[data-table-search]').forEach(input => {
        const selector = input.getAttribute('data-table-search');
        const table = document.querySelector(selector);
        if (!table) return;
        input.addEventListener('input', () => {
            const q = input.value.trim().toLowerCase();
            table.querySelectorAll('tbody tr').forEach(row => {
                if (row.querySelector('td[colspan]')) return;
                const match = row.textContent.toLowerCase().includes(q);
                row.style.display = match ? '' : 'none';
            });
        });
    });

    // 6. Copy Code Buttons ([data-copy-code])
    document.addEventListener('click', (e) => {
        const copyBtn = e.target.closest('[data-copy-code]');
        if (copyBtn) {
            e.preventDefault();
            AATMS_UI.copyCode(
                copyBtn.getAttribute('data-copy-code'),
                copyBtn.getAttribute('data-copy-label') || 'Kode'
            );
        }
    });

    // 7. Drag & Drop File Upload Enhancement
    document.querySelectorAll('.upload-dropzone').forEach(zone => {
        const fileInput = zone.querySelector('input[type="file"]');
        const labelEl = zone.querySelector('.dropzone-filename');
        if (!fileInput) return;

        zone.addEventListener('click', (e) => {
            if (e.target !== fileInput) fileInput.click();
        });
        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', () => zone.classList.remove('dragover'));
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('dragover');
            if (e.dataTransfer.files && e.dataTransfer.files.length > 0) {
                fileInput.files = e.dataTransfer.files;
                fileInput.dispatchEvent(new Event('change'));
            }
        });
        fileInput.addEventListener('change', () => {
            if (fileInput.files && fileInput.files[0] && labelEl) {
                const f = fileInput.files[0];
                const sizeMb = (f.size / (1024 * 1024)).toFixed(2);
                labelEl.innerHTML = `<span class="badge badge-status-green mt-2"><i class="fa-solid fa-file-circle-check me-1"></i>${escapeHtml(f.name)} (${sizeMb} MB)</span>`;
            }
        });
    });

    // 8. Command Palette Shortcut (Ctrl + K) & Smart Code Lookup
    const lookupModalEl = document.getElementById('quickLookupModal');
    const btnRunLookup = document.getElementById('btnRunLookup');
    const lookupCodeInput = document.getElementById('lookupCodeInput');
    const lookupType = document.getElementById('lookupType');
    const lookupResultBox = document.getElementById('lookupResultBox');

    document.addEventListener('keydown', (e) => {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            if (lookupModalEl && typeof bootstrap !== 'undefined') {
                e.preventDefault();
                const modal = bootstrap.Modal.getOrCreateInstance(lookupModalEl);
                modal.show();
            }
        }
    });

    lookupModalEl?.addEventListener('shown.bs.modal', () => {
        lookupCodeInput?.focus();
    });

    const apiUrlMeta = document.querySelector('meta[name="api-url"]');
    const baseUrlMeta = document.querySelector('meta[name="base-url"]');
    const apiBase = apiUrlMeta ? apiUrlMeta.getAttribute('content') : '../api';
    const baseUrl = baseUrlMeta ? baseUrlMeta.getAttribute('content') : '';

    async function executeCodeLookup() {
        if (!lookupCodeInput || !lookupResultBox) return;
        const code = lookupCodeInput.value.trim().toUpperCase();
        let type = lookupType ? lookupType.value : 'course';

        if (!code) {
            lookupResultBox.classList.remove('d-none');
            lookupResultBox.innerHTML = `<div class="alert alert-warning py-2 small mb-0">Silakan masukkan kode mata kuliah atau kode tugas terlebih dahulu.</div>`;
            return;
        }

        if (code.startsWith('TGS-') && lookupType) {
            type = 'task';
            lookupType.value = 'task';
        } else if (!code.startsWith('TGS-') && lookupType && type === 'task' && /^[A-Z]{2,4}\d{2,4}$/.test(code)) {
            type = 'course';
            lookupType.value = 'course';
        }

        lookupResultBox.classList.remove('d-none');
        lookupResultBox.innerHTML = `<div class="text-center py-3 text-muted small"><i class="fa-solid fa-spinner fa-spin me-2 text-sky"></i>Memuat data kode <strong>${escapeHtml(code)}</strong>...</div>`;

        try {
            const res = await fetch(`${apiBase}/lookup_code.php?type=${encodeURIComponent(type)}&code=${encodeURIComponent(code)}`);
            const data = await res.json();

            if (!data.success) {
                lookupResultBox.innerHTML = `<div class="alert alert-danger py-2 small mb-0"><i class="fa-solid fa-circle-xmark me-1"></i>${escapeHtml(data.message || 'Kode tidak ditemukan.')}</div>`;
                return;
            }

            if (data.type === 'course') {
                const c = data.data;
                let classesHtml = '';
                if (Array.isArray(c.classes) && c.classes.length > 0) {
                    classesHtml = c.classes.map(cl =>
                        `<li class="list-group-item d-flex justify-content-between align-items-center small">
                            <span><strong>${escapeHtml(cl.class_name)}</strong> &bull; ${escapeHtml(cl.schedule_day)}, ${escapeHtml(cl.start_time.substring(0, 5))}–${escapeHtml(cl.end_time.substring(0, 5))}</span>
                            <span class="badge bg-sky-soft"><i class="fa-solid fa-door-open me-1"></i>${escapeHtml(cl.room)}</span>
                        </li>`
                    ).join('');
                } else {
                    classesHtml = `<li class="list-group-item small text-muted">Belum ada jadwal kelas terdaftar.</li>`;
                }

                lookupResultBox.innerHTML = `
                    <div class="card-custom p-3 border-sky">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <div>
                                <span class="badge bg-sky mb-1">${escapeHtml(c.course_code)}</span>
                                <h6 class="fw-bold mb-0">${escapeHtml(c.course_name)}</h6>
                            </div>
                            <span class="badge bg-navy">${escapeHtml(String(c.sks))} SKS</span>
                        </div>
                        <div class="small text-muted mb-2">
                            <div><i class="fa-solid fa-user-tie me-1 text-sky"></i>Dosen: <strong>${escapeHtml(c.dosen_name)}</strong></div>
                            <div><i class="fa-solid fa-calendar me-1 text-sky"></i>Semester: ${escapeHtml(c.semester_name)}</div>
                        </div>
                        <div class="small fw-semibold mb-1">Kelas Terdaftar:</div>
                        <ul class="list-group list-group-flush border rounded overflow-hidden">${classesHtml}</ul>
                    </div>`;
            } else if (data.type === 'task') {
                const t = data.data;
                const badgeClass = t.is_expired ? 'badge-status-red' : 'badge-status-green';
                lookupResultBox.innerHTML = `
                    <div class="card-custom p-3 border-sky">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge bg-navy">${escapeHtml(t.task_code)}</span>
                            <span class="badge ${badgeClass}">${escapeHtml(t.countdown_label)}</span>
                        </div>
                        <h6 class="fw-bold mb-1">${escapeHtml(t.title)}</h6>
                        <p class="small text-muted mb-2">${escapeHtml(t.description)}</p>
                        <div class="small border-top pt-2 d-flex flex-column gap-1">
                            <div><strong>Mata Kuliah:</strong> ${escapeHtml(t.course_code)} - ${escapeHtml(t.course_name)} (Kelas ${escapeHtml(t.class_name)})</div>
                            <div><strong>Dosen:</strong> ${escapeHtml(t.dosen_name)}</div>
                            <div><strong>Deadline:</strong> <span class="text-danger fw-medium">${escapeHtml(t.deadline_formatted)}</span></div>
                        </div>
                    </div>`;
            }
        } catch (err) {
            lookupResultBox.innerHTML = `<div class="alert alert-danger py-2 small mb-0">Gagal menghubungi server lookup.</div>`;
        }
    }

    btnRunLookup?.addEventListener('click', executeCodeLookup);
    lookupCodeInput?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            executeCodeLookup();
        }
    });

    document.querySelectorAll('.lookup-sample-chip').forEach(chip => {
        chip.addEventListener('click', () => {
            if (lookupType) lookupType.value = chip.dataset.type;
            if (lookupCodeInput) lookupCodeInput.value = chip.dataset.code;
            executeCodeLookup();
        });
    });
});

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}
