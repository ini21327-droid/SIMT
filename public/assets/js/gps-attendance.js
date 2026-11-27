/**
 * Engine Peta Google Maps + Sistem Pencari & Deteksi Perangkat Real-Time (gps-attendance.js v3.0)
 * - Mendukung Google Maps API Key & Google Maps Tile Layers (Satelit Hybrid, Streets, Terrain) + OpenStreetMap
 * - Sistem Deteksi Perangkat Canggih (OS, Tipe Smartphone/Laptop, Browser, Fingerprint Unik, Jaringan, Baterai, Sensor Akurasi GPS)
 * - Live Radar Tracking menggunakan navigator.geolocation.watchPosition() + getCurrentPosition()
 * - Lokasi Default: Kampus Utama Universitas Malikussaleh (Unimal) Reuleut (5.23405600, 96.98813900)
 */

const AATMS_GPS = {
    UNIMAL_LAT: 5.23405600,
    UNIMAL_LNG: 96.98813900,
    watchId: null,

    /**
     * Ambil Google Maps API Key dari meta tag
     */
    getGoogleMapsKey() {
        return document.querySelector('meta[name="gmaps-key"]')?.getAttribute('content')?.trim() || '';
    },

    getDefaultLayerName() {
        return document.querySelector('meta[name="default-map-layer"]')?.getAttribute('content')?.trim() || 'gmaps_hybrid';
    },

    /**
     * Pasang Base Layers Google Maps (Hybrid Satelit, Streets, Terrain) & OpenStreetMap pada instance Leaflet Map
     */
    attachGoogleMapLayers(map) {
        const apiKey = AATMS_GPS.getGoogleMapsKey();
        const keyParam = apiKey ? `&key=${encodeURIComponent(apiKey)}` : '';
        const defaultLayer = AATMS_GPS.getDefaultLayerName();

        const gmapsHybrid = L.tileLayer(`https://{s}.google.com/vt/lyrs=y&hl=id&x={x}&y={y}&z={z}${keyParam}`, {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Maps Satellite Hybrid (Unimal Reuleut)'
        });

        const gmapsStreets = L.tileLayer(`https://{s}.google.com/vt/lyrs=m&hl=id&x={x}&y={y}&z={z}${keyParam}`, {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Maps Roadmap'
        });

        const gmapsTerrain = L.tileLayer(`https://{s}.google.com/vt/lyrs=p&hl=id&x={x}&y={y}&z={z}${keyParam}`, {
            maxZoom: 20,
            subdomains: ['mt0', 'mt1', 'mt2', 'mt3'],
            attribution: '&copy; Google Maps Terrain'
        });

        const osmLayer = L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        });

        const layerMap = {
            gmaps_hybrid: gmapsHybrid,
            gmaps_streets: gmapsStreets,
            gmaps_terrain: gmapsTerrain,
            osm: osmLayer
        };

        const activeBase = layerMap[defaultLayer] || gmapsHybrid;
        activeBase.addTo(map);

        L.control.layers({
            'Google Maps Satelit (Hybrid)': gmapsHybrid,
            'Google Maps Peta Jalan': gmapsStreets,
            'Google Maps Terrain': gmapsTerrain,
            'OpenStreetMap': osmLayer
        }, null, { position: 'topright' }).addTo(map);

        return map;
    },

    /**
     * Reverse Geocoding via Google Maps Geocoding API (jika API Key diisi)
     */
    async reverseGeocodeGoogle(lat, lng, targetElId) {
        const apiKey = AATMS_GPS.getGoogleMapsKey();
        const el = targetElId ? document.getElementById(targetElId) : null;
        if (!apiKey) {
            if (el) {
                el.innerHTML = `<i class="fa-solid fa-location-dot text-sky me-1"></i>Area Kampus Universitas Malikussaleh Reuleut, Muara Batu, Aceh Utara (${Number(lat).toFixed(5)}, ${Number(lng).toFixed(5)})`;
            }
            return;
        }
        try {
            const res = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?latlng=${lat},${lng}&key=${encodeURIComponent(apiKey)}&language=id`);
            const data = await res.json();
            if (data.status === 'OK' && data.results && data.results[0] && el) {
                el.innerHTML = `<i class="fa-brands fa-google text-sky me-1"></i>${escapeHtml(data.results[0].formatted_address)}`;
            }
        } catch (e) {
            // Abaikan jika offline
        }
    },

    /**
     * Rumus Haversine (Jari-jari bumi R = 6371000 meter)
     */
    haversineMeters(lat1, lon1, lat2, lon2) {
        const R = 6371000;
        const toRad = deg => (deg * Math.PI) / 180;
        const dLat = toRad(lat2 - lat1);
        const dLon = toRad(lon2 - lon1);
        const a =
            Math.sin(dLat / 2) * Math.sin(dLat / 2) +
            Math.cos(toRad(lat1)) * Math.cos(toRad(lat2)) *
            Math.sin(dLon / 2) * Math.sin(dLon / 2);
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(Math.max(0, 1 - a)));
        return Math.round(R * c * 100) / 100;
    },

    /**
     * Sistem Pencari & Deteksi Perangkat Lengkap (Device Scanner & Fingerprinting)
     */
    async detectDeviceTelemetry() {
        const ua = navigator.userAgent || '';
        let deviceType = 'Laptop / Desktop PC';
        let deviceIcon = 'fa-laptop';
        let osName = 'Unknown OS';
        let browserName = 'Browser';

        // Deteksi Tipe Perangkat & OS
        if (/iPad|Tablet/i.test(ua)) {
            deviceType = 'Tablet';
            deviceIcon = 'fa-tablet-screen-button';
        } else if (/Mobile|Android|iPhone|iPod/i.test(ua)) {
            deviceType = 'Smartphone';
            deviceIcon = 'fa-mobile-screen-button';
        }

        if (/Android\s+([\d.]+)/i.test(ua)) {
            osName = 'Android ' + RegExp.$1;
            const modelMatch = ua.match(/;\s*([^;)]+)\s+Build\//i);
            if (modelMatch && modelMatch[1]) {
                deviceType = `Smartphone (${modelMatch[1].trim()})`;
            }
        } else if (/iPhone OS\s+([\d_]+)/i.test(ua)) {
            osName = 'iOS ' + RegExp.$1.replace(/_/g, '.');
            deviceType = 'Apple iPhone';
        } else if (/iPad.*OS\s+([\d_]+)/i.test(ua)) {
            osName = 'iPadOS ' + RegExp.$1.replace(/_/g, '.');
            deviceType = 'Apple iPad';
        } else if (/Windows NT 10\.0/i.test(ua)) {
            osName = 'Windows 10/11';
        } else if (/Mac OS X\s+([\d_]+)/i.test(ua)) {
            osName = 'macOS ' + RegExp.$1.replace(/_/g, '.');
        } else if (/Linux/i.test(ua)) {
            osName = 'Linux';
        }

        // Deteksi Browser
        if (/Edg\/([\d.]+)/i.test(ua)) {
            browserName = 'Microsoft Edge ' + RegExp.$1.split('.')[0];
        } else if (/OPR\/([\d.]+)/i.test(ua)) {
            browserName = 'Opera ' + RegExp.$1.split('.')[0];
        } else if (/Chrome\/([\d.]+)/i.test(ua)) {
            browserName = 'Google Chrome ' + RegExp.$1.split('.')[0];
        } else if (/Firefox\/([\d.]+)/i.test(ua)) {
            browserName = 'Mozilla Firefox ' + RegExp.$1.split('.')[0];
        } else if (/Version\/([\d.]+).*Safari/i.test(ua)) {
            browserName = 'Apple Safari ' + RegExp.$1.split('.')[0];
        }

        // Client Hints API (modern Chromium browsers)
        if (navigator.userAgentData) {
            try {
                const hints = await navigator.userAgentData.getHighEntropyValues(['model', 'platformVersion']);
                if (hints.model) {
                    deviceType = `Smartphone (${hints.model})`;
                }
            } catch (e) {}
        }

        // Informasi Layar, CPU, dan Jaringan
        const screenRes = `${window.screen.width}x${window.screen.height}`;
        const cpuCores = navigator.hardwareConcurrency ? `${navigator.hardwareConcurrency} Core` : 'Multi-Core';
        const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
        const networkInfo = conn && conn.effectiveType ? conn.effectiveType.toUpperCase() : 'WiFi / Broadband';

        // Informasi Baterai (jika didukung browser)
        let batteryInfo = null;
        if (typeof navigator.getBattery === 'function') {
            try {
                const bat = await navigator.getBattery();
                batteryInfo = `${Math.round(bat.level * 100)}%${bat.charging ? ' (Charging)' : ''}`;
            } catch (e) {}
        }

        // Generate Device Fingerprint Hash Unik (Deterministic per perangkat)
        const rawFingerprint = [
            ua,
            screenRes,
            window.screen.colorDepth,
            Intl.DateTimeFormat().resolvedOptions().timeZone,
            navigator.language,
            navigator.hardwareConcurrency || 4
        ].join('|');

        let hash = 2166136261;
        for (let i = 0; i < rawFingerprint.length; i++) {
            hash ^= rawFingerprint.charCodeAt(i);
            hash = Math.imul(hash, 16777619);
        }
        const fingerprintId = 'DEV-' + (hash >>> 0).toString(16).toUpperCase().padStart(8, '0');

        return {
            deviceType,
            deviceIcon,
            osName,
            browserName,
            screenRes,
            cpuCores,
            networkInfo,
            batteryInfo,
            fingerprintId,
            summaryString: `${deviceType} • ${osName} • ${browserName}`
        };
    },

    /**
     * 1. Peta Pemilih Titik Kelas untuk Dosen (Kampus Unimal Reuleut + Google Maps Hybrid)
     */
    initLecturerPicker({ mapId, collapseId, latInputId, lngInputId, radiusInputId, btnGpsId, btnSimCampusId, addressLabelId }) {
        const mapEl = document.getElementById(mapId);
        if (!mapEl || typeof L === 'undefined') return;

        const latInput = document.getElementById(latInputId);
        const lngInput = document.getElementById(lngInputId);
        const radiusInput = document.getElementById(radiusInputId);

        let initLat = parseFloat(latInput.value) || AATMS_GPS.UNIMAL_LAT;
        let initLng = parseFloat(lngInput.value) || AATMS_GPS.UNIMAL_LNG;
        let initRadius = parseInt(radiusInput.value, 10) || 100;

        const map = L.map(mapId).setView([initLat, initLng], 18);
        AATMS_GPS.attachGoogleMapLayers(map);

        const marker = L.marker([initLat, initLng], { draggable: true }).addTo(map)
            .bindPopup('<b>Titik Pusat Kelas (Kampus Unimal Reuleut)</b><br>Geser pin atau klik peta Google Maps untuk mengubah koordinat.');

        const circle = L.circle([initLat, initLng], {
            color: '#0EA5E9',
            fillColor: '#0EA5E9',
            fillOpacity: 0.22,
            radius: initRadius
        }).addTo(map);

        AATMS_GPS.reverseGeocodeGoogle(initLat, initLng, addressLabelId);

        if (collapseId) {
            const collapseEl = document.getElementById(collapseId);
            collapseEl?.addEventListener('shown.bs.collapse', () => {
                map.invalidateSize();
                const cLat = parseFloat(latInput.value) || initLat;
                const cLng = parseFloat(lngInput.value) || initLng;
                map.setView([cLat, cLng], 18);
                marker.openPopup();
            });
        }

        function updateCoords(lat, lng) {
            const fixedLat = Number(lat).toFixed(8);
            const fixedLng = Number(lng).toFixed(8);
            latInput.value = fixedLat;
            lngInput.value = fixedLng;
            marker.setLatLng([lat, lng]);
            circle.setLatLng([lat, lng]);
            AATMS_GPS.reverseGeocodeGoogle(lat, lng, addressLabelId);
        }

        marker.on('dragend', () => {
            const pos = marker.getLatLng();
            updateCoords(pos.lat, pos.lng);
        });

        map.on('click', (e) => {
            updateCoords(e.latlng.lat, e.latlng.lng);
        });

        radiusInput?.addEventListener('input', () => {
            const r = parseInt(radiusInput.value, 10) || 100;
            circle.setRadius(r);
        });

        const btnGps = document.getElementById(btnGpsId);
        btnGps?.addEventListener('click', () => {
            if (!navigator.geolocation) {
                alert('Perangkat Anda tidak mendukung HTML5 Geolocation API.');
                return;
            }
            btnGps.disabled = true;
            btnGps.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Memindai GPS Perangkat...';
            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    updateCoords(pos.coords.latitude, pos.coords.longitude);
                    map.setView([pos.coords.latitude, pos.coords.longitude], 18);
                    btnGps.disabled = false;
                    btnGps.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i>Deteksi Lokasi Perangkat Saya';
                    if (typeof AATMS_UI !== 'undefined') {
                        AATMS_UI.toast(`Lokasi perangkat terdeteksi (Akurasi ±${Math.round(pos.coords.accuracy)}m)!`, 'success');
                    }
                },
                (err) => {
                    alert('Gagal mendeteksi lokasi perangkat: ' + err.message);
                    btnGps.disabled = false;
                    btnGps.innerHTML = '<i class="fa-solid fa-location-crosshairs me-1"></i>Deteksi Lokasi Perangkat Saya';
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        });

        const btnSim = document.getElementById(btnSimCampusId);
        btnSim?.addEventListener('click', () => {
            updateCoords(AATMS_GPS.UNIMAL_LAT, AATMS_GPS.UNIMAL_LNG);
            map.setView([AATMS_GPS.UNIMAL_LAT, AATMS_GPS.UNIMAL_LNG], 18);
            if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast('Titik peta diarahkan ke Kampus Unimal Reuleut!', 'info');
        });
    },

    /**
     * 2. Peta Monitoring Kehadiran & Perangkat Mahasiswa untuk Dosen
     */
    initLecturerMonitorMap({ mapId, targetLat, targetLng, radiusMeters, attendances }) {
        const mapEl = document.getElementById(mapId);
        if (!mapEl || typeof L === 'undefined') return;

        const map = L.map(mapId).setView([targetLat, targetLng], 18);
        AATMS_GPS.attachGoogleMapLayers(map);

        L.circle([targetLat, targetLng], {
            color: '#0EA5E9',
            fillColor: '#0EA5E9',
            fillOpacity: 0.2,
            radius: radiusMeters
        }).addTo(map);

        L.marker([targetLat, targetLng]).addTo(map)
            .bindPopup(`<b>Titik Pusat Kelas (Unimal Reuleut)</b><br>Radius Toleransi: <b>${radiusMeters} meter</b>`);

        const bounds = [[targetLat, targetLng]];

        if (Array.isArray(attendances)) {
            attendances.forEach(item => {
                const sLat = parseFloat(item.student_lat);
                const sLng = parseFloat(item.student_lng);
                if (isNaN(sLat) || isNaN(sLng)) return;

                bounds.push([sLat, sLng]);
                const pinColor = Number(item.location_valid) === 1 ? '#16A34A' : '#DC2626';
                const circleMarker = L.circleMarker([sLat, sLng], {
                    radius: 9,
                    fillColor: pinColor,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.95
                }).addTo(map);

                const devText = item.device_info ? escapeHtml(item.device_info) : 'Perangkat Tidak Diketahui';
                const accText = item.gps_accuracy ? ` (±${escapeHtml(String(item.gps_accuracy))}m)` : '';

                circleMarker.bindPopup(
                    `<div class="small">` +
                    `<b>${escapeHtml(item.full_name)}</b> (<code>${escapeHtml(item.nim_nidn || '-')}</code>)<br>` +
                    `Status: <span class="text-uppercase fw-bold text-success">${escapeHtml(item.status)}</span><br>` +
                    `Jarak Haversine: <b>${escapeHtml(String(item.distance_meters))} m</b>${accText}<br>` +
                    `Perangkat: <b>${devText}</b><br>` +
                    `ID Perangkat: <code>${escapeHtml(item.device_fingerprint || '-')}</code><br>` +
                    `Waktu: ${escapeHtml(item.check_in_time || '-')}` +
                    `</div>`
                );
            });
        }

        if (bounds.length > 1) {
            map.fitBounds(L.latLngBounds(bounds).pad(0.35));
        }
    },

    /**
     * 3. Peta Kampus Multi-Sesi untuk Kaprodi & Admin (Google Maps Hybrid)
     */
    initCampusOverviewMap({ mapId, sessions }) {
        const mapEl = document.getElementById(mapId);
        if (!mapEl || typeof L === 'undefined') return;

        const firstLat = (Array.isArray(sessions) && sessions[0]) ? parseFloat(sessions[0].target_lat) : AATMS_GPS.UNIMAL_LAT;
        const firstLng = (Array.isArray(sessions) && sessions[0]) ? parseFloat(sessions[0].target_lng) : AATMS_GPS.UNIMAL_LNG;

        const map = L.map(mapId).setView([firstLat, firstLng], 17);
        AATMS_GPS.attachGoogleMapLayers(map);

        const bounds = [];
        if (Array.isArray(sessions)) {
            sessions.forEach(s => {
                const lat = parseFloat(s.target_lat);
                const lng = parseFloat(s.target_lng);
                const rad = parseInt(s.radius_meters, 10) || 100;
                if (isNaN(lat) || isNaN(lng)) return;

                bounds.push([lat, lng]);
                const isOpen = s.status === 'open';
                const color = isOpen ? '#10B981' : '#0EA5E9';

                L.circle([lat, lng], {
                    color,
                    fillColor: color,
                    fillOpacity: 0.2,
                    radius: rad
                }).addTo(map);

                L.marker([lat, lng]).addTo(map).bindPopup(
                    `<b>${escapeHtml(s.course_code)} - ${escapeHtml(s.course_name)}</b> (${escapeHtml(s.class_name)})<br>` +
                    `Kode: <code>${escapeHtml(s.session_code)}</code> &bull; Status: <b>${escapeHtml(s.status.toUpperCase())}</b><br>` +
                    `Dosen: ${escapeHtml(s.dosen_name)}<br>` +
                    `Radius: ${rad}m &bull; Hadir: <b>${s.present_count}/${s.total_students} Mhs</b>`
                );
            });
        }

        if (bounds.length > 1) {
            map.fitBounds(L.latLngBounds(bounds).pad(0.3));
        }
    },

    /**
     * 4. Peta Google Maps, Pemindai Perangkat & Radar GPS Real-Time Mahasiswa
     */
    async initStudentCheckIn({
        mapId,
        sessionId,
        targetLat,
        targetLng,
        radiusMeters,
        statusBoxId,
        distanceLabelId,
        coordsLabelId,
        deviceBoxId,
        accuracyLabelId,
        btnLocateId,
        btnLiveTrackId,
        btnSimulateInsideId,
        btnSubmitId
    }) {
        const mapEl = document.getElementById(mapId);
        if (!mapEl || typeof L === 'undefined') return;

        const statusBox = document.getElementById(statusBoxId);
        const distanceLabel = document.getElementById(distanceLabelId);
        const coordsLabel = document.getElementById(coordsLabelId);
        const deviceBox = document.getElementById(deviceBoxId);
        const accuracyLabel = document.getElementById(accuracyLabelId);
        const btnLocate = document.getElementById(btnLocateId);
        const btnLiveTrack = document.getElementById(btnLiveTrackId);
        const btnSimInside = document.getElementById(btnSimulateInsideId);
        const btnSubmit = document.getElementById(btnSubmitId);

        let currentStudentLat = null;
        let currentStudentLng = null;
        let currentAccuracy = null;

        // 1. Jalankan Pemindai Perangkat Otomatis
        const telemetry = await AATMS_GPS.detectDeviceTelemetry();
        if (deviceBox) {
            deviceBox.innerHTML = `
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <span class="stat-icon bg-sky-soft" style="width:38px;height:38px;font-size:1rem;">
                            <i class="fa-solid ${telemetry.deviceIcon}"></i>
                        </span>
                        <div>
                            <div class="fw-bold small">${escapeHtml(telemetry.deviceType)} &bull; ${escapeHtml(telemetry.osName)}</div>
                            <div class="text-muted" style="font-size:0.75rem;">
                                ${escapeHtml(telemetry.browserName)} &bull; Layar ${escapeHtml(telemetry.screenRes)} &bull; Jaringan ${escapeHtml(telemetry.networkInfo)}
                                ${telemetry.batteryInfo ? ' &bull; Baterai ' + escapeHtml(telemetry.batteryInfo) : ''}
                            </div>
                        </div>
                    </div>
                    <div class="text-end">
                        <span class="badge bg-navy"><i class="fa-solid fa-fingerprint me-1 text-sky"></i>${escapeHtml(telemetry.fingerprintId)}</span>
                        <div class="text-success fw-semibold" style="font-size:0.7rem;"><i class="fa-solid fa-shield-check me-1"></i>Perangkat Terdeteksi</div>
                    </div>
                </div>
            `;
        }

        // 2. Inisialisasi Peta Google Maps Hybrid di Titik Kelas Unimal Reuleut
        const map = L.map(mapId).setView([targetLat, targetLng], 18);
        AATMS_GPS.attachGoogleMapLayers(map);

        L.circle([targetLat, targetLng], {
            color: '#0EA5E9',
            fillColor: '#0EA5E9',
            fillOpacity: 0.2,
            radius: radiusMeters
        }).addTo(map);

        L.marker([targetLat, targetLng]).addTo(map)
            .bindPopup(`<b>Titik Lokasi Kelas (Unimal Reuleut)</b><br>Batas Radius: <b>${radiusMeters} meter</b>`).openPopup();

        let studentMarker = null;
        let accuracyCircle = null;
        let lineConnector = null;

        function setStudentPosition(lat, lng, accuracyMeters = 8.5, isSimulated = false) {
            currentStudentLat = lat;
            currentStudentLng = lng;
            currentAccuracy = Math.round(accuracyMeters * 100) / 100;

            const dist = AATMS_GPS.haversineMeters(targetLat, targetLng, lat, lng);
            const within = dist <= radiusMeters;

            if (coordsLabel) {
                coordsLabel.innerHTML = `<code>${lat.toFixed(6)}, ${lng.toFixed(6)}</code>` +
                    (isSimulated ? ' <span class="badge bg-warning text-dark">Simulasi Unimal</span>' : ' <span class="badge badge-status-green">Live GPS</span>');
            }
            if (accuracyLabel) {
                const sigQuality = currentAccuracy <= 20 ? 'Sinyal GPS Kuat' : (currentAccuracy <= 60 ? 'Sinyal Sedang' : 'Sinyal Lemah');
                accuracyLabel.innerHTML = `<span class="fw-bold">±${currentAccuracy} m</span> <small class="text-muted">(${sigQuality})</small>`;
            }
            if (distanceLabel) {
                distanceLabel.innerHTML = `<span class="fw-bold ${within ? 'text-success' : 'text-danger'}">${dist.toFixed(2)} meter</span> dari titik kelas (Batas: ${radiusMeters} m)`;
            }

            if (studentMarker) {
                studentMarker.setLatLng([lat, lng]);
            } else {
                studentMarker = L.circleMarker([lat, lng], {
                    radius: 9,
                    fillColor: within ? '#16A34A' : '#DC2626',
                    color: '#fff',
                    weight: 2.5,
                    fillOpacity: 0.95
                }).addTo(map);
            }
            studentMarker.setStyle({ fillColor: within ? '#16A34A' : '#DC2626' });
            studentMarker.bindPopup(
                `<b>Perangkat Anda: ${escapeHtml(telemetry.deviceType)}</b><br>` +
                `ID: <code>${escapeHtml(telemetry.fingerprintId)}</code><br>` +
                `Jarak ke Kelas: <b>${dist.toFixed(2)} m</b> (±${currentAccuracy}m)`
            ).openPopup();

            // Lingkaran akurasi sensor GPS perangkat
            if (accuracyCircle) {
                accuracyCircle.setLatLng([lat, lng]);
                accuracyCircle.setRadius(Math.min(currentAccuracy, 120));
            } else {
                accuracyCircle = L.circle([lat, lng], {
                    color: within ? '#22C55E' : '#EF4444',
                    weight: 1,
                    dashArray: '3, 4',
                    fillOpacity: 0.08,
                    radius: Math.min(currentAccuracy, 120)
                }).addTo(map);
            }

            if (lineConnector) {
                lineConnector.setLatLngs([[targetLat, targetLng], [lat, lng]]);
            } else {
                lineConnector = L.polyline([[targetLat, targetLng], [lat, lng]], {
                    color: within ? '#16A34A' : '#DC2626',
                    dashArray: '5, 6',
                    weight: 2.5
                }).addTo(map);
            }
            lineConnector.setStyle({ color: within ? '#16A34A' : '#DC2626' });

            map.fitBounds(L.latLngBounds([[targetLat, targetLng], [lat, lng]]).pad(0.35));

            if (statusBox) {
                if (within) {
                    statusBox.className = 'alert alert-success modern-alert py-2 small mb-3';
                    statusBox.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i><strong>Perangkat &amp; Lokasi Tervalidasi!</strong> Perangkat <strong>${escapeHtml(telemetry.deviceType)}</strong> berada di dalam radius kelas (${dist.toFixed(2)} m &le; ${radiusMeters} m). Silakan klik <strong>Absen Sekarang</strong>.`;
                } else {
                    statusBox.className = 'alert alert-warning modern-alert py-2 small mb-3';
                    statusBox.innerHTML = `<i class="fa-solid fa-triangle-exclamation me-2"></i><strong>Di Luar Radius Kampus!</strong> Perangkat Anda (<strong>${escapeHtml(telemetry.deviceType)}</strong>) berjarak <strong>${dist.toFixed(2)} m</strong> dari titik kelas di Unimal Reuleut (Batas: ${radiusMeters} m). Gunakan tombol <em>Simulasi Posisi di Kampus Unimal</em> jika Anda sedang menguji dari luar kampus.`;
                }
            }

            if (btnSubmit) {
                btnSubmit.disabled = false;
            }
        }

        function scanDeviceGps(continuous = false) {
            if (!navigator.geolocation) {
                if (statusBox) {
                    statusBox.className = 'alert alert-danger py-2 small mb-3';
                    statusBox.textContent = 'Perangkat Anda tidak memiliki sensor Geolocation GPS.';
                }
                return;
            }

            if (statusBox) {
                statusBox.className = 'alert alert-info modern-alert py-2 small mb-3';
                statusBox.innerHTML = `<i class="fa-solid fa-satellite-dish fa-fade me-2"></i>Memindai sensor GPS perangkat <strong>${escapeHtml(telemetry.deviceType)}</strong> (${escapeHtml(telemetry.fingerprintId)})...`;
            }

            if (continuous) {
                if (AATMS_GPS.watchId !== null) {
                    navigator.geolocation.clearWatch(AATMS_GPS.watchId);
                    AATMS_GPS.watchId = null;
                    if (btnLiveTrack) {
                        btnLiveTrack.className = 'btn btn-sm btn-outline-success';
                        btnLiveTrack.innerHTML = '<i class="fa-solid fa-satellite me-1"></i>Radar Live GPS: OFF';
                    }
                    if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast('Radar pelacakan perangkat real-time dihentikan.', 'info');
                    return;
                }

                if (btnLiveTrack) {
                    btnLiveTrack.className = 'btn btn-sm btn-success';
                    btnLiveTrack.innerHTML = '<i class="fa-solid fa-satellite-dish fa-spin me-1"></i>Radar Live GPS: ON';
                }
                if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast('Radar pelacakan perangkat real-time diaktifkan!', 'success');

                AATMS_GPS.watchId = navigator.geolocation.watchPosition(
                    (pos) => setStudentPosition(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy || 10, false),
                    (err) => {
                        if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast('Sinyal GPS perangkat terputus: ' + err.message, 'danger');
                    },
                    { enableHighAccuracy: true, timeout: 15000, maximumAge: 0 }
                );
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (pos) => {
                    setStudentPosition(pos.coords.latitude, pos.coords.longitude, pos.coords.accuracy || 10, false);
                    if (typeof AATMS_UI !== 'undefined') {
                        AATMS_UI.toast(`Perangkat ${telemetry.deviceType} berhasil ditemukan!`, 'success');
                    }
                },
                (err) => {
                    if (statusBox) {
                        statusBox.className = 'alert alert-warning modern-alert py-2 small mb-3';
                        statusBox.innerHTML = `<i class="fa-solid fa-circle-info me-2"></i>Sensor GPS perangkat belum mengunci lokasi (${escapeHtml(err.message)}). Tekan tombol <strong>Simulasi Posisi di Kampus Unimal</strong> untuk menguji validasi di localhost.`;
                    }
                },
                { enableHighAccuracy: true, timeout: 12000, maximumAge: 0 }
            );
        }

        btnLocate?.addEventListener('click', () => scanDeviceGps(false));
        btnLiveTrack?.addEventListener('click', () => scanDeviceGps(true));

        btnSimInside?.addEventListener('click', () => {
            if (AATMS_GPS.watchId !== null) {
                navigator.geolocation.clearWatch(AATMS_GPS.watchId);
                AATMS_GPS.watchId = null;
            }
            setStudentPosition(targetLat + 0.000075, targetLng + 0.000075, 5.4, true);
            if (typeof AATMS_UI !== 'undefined') {
                AATMS_UI.toast('Simulasi GPS perangkat di Kampus Unimal Reuleut aktif!', 'info');
            }
        });

        btnSubmit?.addEventListener('click', async () => {
            if (currentStudentLat === null || currentStudentLng === null) {
                alert('Silakan pindai lokasi GPS perangkat terlebih dahulu.');
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const apiBase = document.querySelector('meta[name="api-url"]')?.getAttribute('content') || '../api';

            btnSubmit.disabled = true;
            const origText = btnSubmit.innerHTML;
            btnSubmit.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i>Memvalidasi Perangkat &amp; Haversine...';

            try {
                const response = await fetch(`${apiBase}/submit_attendance.php`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        session_id: sessionId,
                        lat: currentStudentLat,
                        lng: currentStudentLng,
                        accuracy: currentAccuracy,
                        device_info: telemetry.summaryString,
                        device_fingerprint: telemetry.fingerprintId
                    })
                });
                const res = await response.json();

                if (res.success) {
                    statusBox.className = 'alert alert-success modern-alert py-3 mb-3';
                    statusBox.innerHTML = `<i class="fa-solid fa-circle-check me-2"></i><strong>${escapeHtml(res.message)}</strong>`;
                    if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast('Presensi & Perangkat berhasil divalidasi!', 'success');
                    btnSubmit.innerHTML = '<i class="fa-solid fa-check me-1"></i>Kehadiran Tercatat';
                    setTimeout(() => window.location.reload(), 1300);
                } else {
                    statusBox.className = 'alert alert-danger modern-alert py-2 small mb-3';
                    statusBox.innerHTML = `<i class="fa-solid fa-circle-xmark me-2"></i>${escapeHtml(res.message)}`;
                    if (typeof AATMS_UI !== 'undefined') AATMS_UI.toast(res.message, 'danger');
                    btnSubmit.disabled = false;
                    btnSubmit.innerHTML = origText;
                }
            } catch (e) {
                statusBox.className = 'alert alert-danger py-2 small mb-3';
                statusBox.textContent = 'Gagal mengirim data absensi ke server.';
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = origText;
            }
        });

        scanDeviceGps(false);
    }
};
