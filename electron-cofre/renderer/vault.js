    (function () {
        'use strict';

        /* ---- DOM ---- */
        var safe = document.getElementById('safe');
        var digitBoxes = document.querySelectorAll('.digit-box');
        var submitBtn = document.getElementById('submit-btn');
        var statusMsg = document.getElementById('status-msg');
        var attemptsMsg = document.getElementById('attempts-msg');
        var digitArea = document.getElementById('digit-area');
        var safeInteriorValue = document.getElementById('safe-interior-value');
        var blockedBanner = document.getElementById('blocked-banner');
        var blockedCountdown = document.getElementById('blocked-countdown');
        var notConfigured = document.getElementById('not-configured');
        var confirmOverlay = document.getElementById('confirm-overlay');
        var confirmCancel = document.getElementById('confirm-cancel');
        var confirmOk = document.getElementById('confirm-ok');
        var toastEl = document.getElementById('toast');

        /* Geofence DOM */
        var geofenceOutOfRange = document.getElementById('geofence-out-of-range');
        var geofenceNoLocation = document.getElementById('geofence-no-location');
        var geofenceNotReleased = document.getElementById('geofence-not-released');
        var geofenceLoading = document.getElementById('geofence-loading');
        var geoRadiusEl = document.getElementById('geo-radius');
        var geoDistanceEl = document.getElementById('geo-distance-msg');
        var geoRetryBtn = document.getElementById('geo-retry-btn');
        var geoAllowBtn = document.getElementById('geo-allow-btn');

        var currentPassword = '';
        var isSubmitting = false;
        var blockedUntilTs = null;  /* timestamp of block end */
        var countdownTimer = null;

        /* Geofence state */
        var userLat = null;
        var userLng = null;
        var hasCoordinate = null;  /* from API: does vault have coords? */

        /* ============================================================
           GEOFENCE — GEOLOCATION
           ============================================================ */
        function requestLocation(callback) {
            if (!navigator.geolocation) {
                /* Geolocation not supported — treat as no permission */
                callback(null, null);
                return;
            }
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    callback(pos.coords.latitude, pos.coords.longitude);
                },
                function (err) {
                    callback(null, null);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        }

        function hideAllGeofence() {
            geofenceOutOfRange.classList.remove('visible');
            geofenceNoLocation.classList.remove('visible');
            if (geofenceNotReleased) geofenceNotReleased.classList.remove('visible');
            geofenceLoading.classList.remove('visible');
        }

        /* O cofre ainda não foi liberado (a organização não registrou o local) */
        function showGeofenceNotReleased() {
            hideAllGeofence();
            if (geofenceNotReleased) geofenceNotReleased.classList.add('visible');
            digitArea.style.display = 'none';
            submitBtn.style.display = 'none';
            statusMsg.textContent = '';
            attemptsMsg.textContent = '';
        }

        function showGeofenceOutOfRange(radius, distance) {
            hideAllGeofence();
            geofenceOutOfRange.classList.add('visible');
            geoRadiusEl.textContent = radius;
            if (distance !== null && distance !== undefined) {
                geoDistanceEl.innerHTML = 'Distância atual: <strong>~' + formatDistance(distance) + '</strong>';
            } else {
                geoDistanceEl.textContent = '';
            }
            /* Hide the vault UI */
            digitArea.style.display = 'none';
            submitBtn.style.display = 'none';
            statusMsg.textContent = '';
            attemptsMsg.textContent = '';
        }

        function showGeofenceNoLocation() {
            hideAllGeofence();
            geofenceNoLocation.classList.add('visible');
            /* Hide the vault UI */
            digitArea.style.display = 'none';
            submitBtn.style.display = 'none';
            statusMsg.textContent = '';
            attemptsMsg.textContent = '';
        }

        function showGeofenceLoading() {
            hideAllGeofence();
            geofenceLoading.classList.add('visible');
        }

        function formatDistance(meters) {
            if (meters >= 1000) {
                return (meters / 1000).toFixed(1).replace('.', ',') + ' km';
            }
            return Math.round(meters) + ' m';
        }

        /* Retry / allow buttons */
        geoRetryBtn.addEventListener('click', function () {
            initGeofence();
        });
        geoAllowBtn.addEventListener('click', function () {
            initGeofence();
        });

        /**
         * Main geofence init: request location, then loadStatus with coords.
         */
        function initGeofence() {
            hideAllGeofence();
            showGeofenceLoading();

            requestLocation(function (lat, lng) {
                userLat = lat;
                userLng = lng;
                loadStatus();
            });
        }

        /* ============================================================
           DIGIT INPUT
           ============================================================ */
        var boxes = Array.prototype.slice.call(digitBoxes);

        boxes.forEach(function (box, idx) {
            box.addEventListener('input', function () {
                var val = box.value.replace(/[^0-9]/g, '');
                box.value = val;
                box.classList.toggle('filled', !!val);

                if (val && idx < boxes.length - 1) {
                    boxes[idx + 1].focus();
                }
                updateSubmitState();
            });

            box.addEventListener('keydown', function (e) {
                if (e.key === 'Backspace' && !box.value && idx > 0) {
                    e.preventDefault();
                    boxes[idx - 1].focus();
                    boxes[idx - 1].value = '';
                    boxes[idx - 1].classList.remove('filled');
                    updateSubmitState();
                }
                if (e.key === 'Enter' && !submitBtn.disabled) {
                    submitBtn.click();
                }
            });

            box.addEventListener('paste', function (e) {
                e.preventDefault();
                var text = (e.clipboardData || window.clipboardData).getData('text').replace(/[^0-9]/g, '');
                if (!text) return;
                for (var j = 0; j < text.length && (idx + j) < boxes.length; j++) {
                    boxes[idx + j].value = text[j];
                    boxes[idx + j].classList.add('filled');
                }
                var nextIdx = Math.min(idx + text.length, boxes.length - 1);
                boxes[nextIdx].focus();
                updateSubmitState();
            });

            box.addEventListener('focus', function () {
                box.select();
            });
        });

        function getCode() {
            var code = '';
            boxes.forEach(function (b) { code += b.value; });
            return code;
        }

        function updateSubmitState() {
            submitBtn.disabled = getCode().length !== 9 || isSubmitting;
        }

        /* ============================================================
           TOAST
           ============================================================ */
        function showToast(msg, type) {
            toastEl.textContent = msg;
            toastEl.className = 'toast ' + (type || 'error');
            requestAnimationFrame(function () { toastEl.classList.add('visible'); });
            setTimeout(function () {
                toastEl.classList.remove('visible');
            }, 3500);
        }

        /* ============================================================
           FETCH STATUS ON LOAD (with geolocation params)
           ============================================================ */
        function loadStatus() {
            window.cofreApi.status(userLat, userLng)
                .then(function (data) {
                    hideAllGeofence();

                    if (!data.success) {
                        showNotConfigured();
                        return;
                    }

                    if (!data.configured) {
                        showNotConfigured();
                        return;
                    }

                    /* Store whether vault has coordinates */
                    hasCoordinate = data.has_coordinate;

                    /* Check geofence */
                    if (!data.in_range) {
                        if (data.range_reason === 'sem_coordenada') {
                            showGeofenceNotReleased();
                            return;
                        }
                        if (data.range_reason === 'fora_do_raio') {
                            showGeofenceOutOfRange(data.radius, data.distance);
                            return;
                        }
                        if (data.range_reason === 'sem_localizacao') {
                            showGeofenceNoLocation();
                            return;
                        }
                    }

                    /* All good — show vault */
                    hideAllGeofence();
                    if (data.blocked) {
                        showBlocked(data.blocked_until);
                    } else {
                        hideBlocked();
                        showNormal();
                    }
                })
                .catch(function () {
                    hideAllGeofence();
                    showToast('Erro ao verificar o cofre. Verifique sua conexão.', 'error');
                });
        }

        function showNotConfigured() {
            notConfigured.classList.add('visible');
            digitArea.style.display = 'none';
            submitBtn.style.display = 'none';
            statusMsg.textContent = '';
            attemptsMsg.textContent = '';
        }

        function showNormal() {
            notConfigured.classList.remove('visible');
            digitArea.style.display = '';
            submitBtn.style.display = '';
            blockedBanner.classList.remove('visible');
            hideAllGeofence();
            updateSubmitState();
        }

        function showBlocked(blockedUntil) {
            if (!blockedUntil) {
                hideBlocked();
                return;
            }
            blockedBanner.classList.add('visible');
            digitArea.style.display = 'none';
            submitBtn.style.display = 'none';
            notConfigured.classList.remove('visible');
            statusMsg.textContent = '';
            attemptsMsg.textContent = '';
            hideAllGeofence();

            /* Parse blocked_until as local time */
            var parts = blockedUntil.split(/[- :]/);
            blockedUntilTs = new Date(
                parseInt(parts[0], 10),
                parseInt(parts[1], 10) - 1,
                parseInt(parts[2], 10),
                parseInt(parts[3], 10),
                parseInt(parts[4], 10),
                parseInt(parts[5], 10)
            ).getTime();

            if (countdownTimer) clearInterval(countdownTimer);
            updateCountdown();
            countdownTimer = setInterval(updateCountdown, 1000);
        }

        function hideBlocked() {
            blockedBanner.classList.remove('visible');
            blockedUntilTs = null;
            if (countdownTimer) {
                clearInterval(countdownTimer);
                countdownTimer = null;
            }
        }

        function updateCountdown() {
            if (!blockedUntilTs) return;
            var now = Date.now();
            var diff = blockedUntilTs - now;

            if (diff <= 0) {
                hideBlocked();
                showNormal();
                loadStatus();
                return;
            }

            var totalSec = Math.floor(diff / 1000);
            var min = Math.floor(totalSec / 60);
            var sec = totalSec % 60;
            blockedCountdown.textContent =
                String(min).padStart(2, '0') + ':' + String(sec).padStart(2, '0');
        }

        /* ============================================================
           SUBMIT — shows confirm modal first
           ============================================================ */
        submitBtn.addEventListener('click', function () {
            var code = getCode();
            if (code.length !== 9) return;
            openConfirmModal();
        });

        /* ============================================================
           CONFIRM MODAL
           ============================================================ */
        function openConfirmModal() {
            confirmOverlay.classList.add('open');
        }

        function closeConfirmModal() {
            confirmOverlay.classList.remove('open');
        }

        confirmCancel.addEventListener('click', closeConfirmModal);

        confirmOverlay.addEventListener('click', function (e) {
            if (e.target === confirmOverlay) closeConfirmModal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && confirmOverlay.classList.contains('open')) {
                closeConfirmModal();
            }
        });

        confirmOk.addEventListener('click', function () {
            closeConfirmModal();
            sendCode();
        });

        /* ============================================================
           SEND CODE TO API (with lat/lng)
           ============================================================ */
        function sendCode() {
            if (isSubmitting) return;
            isSubmitting = true;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner"></span> Verificando...';

            window.cofreApi.check(getCode(), userLat, userLng)
            .then(function (result) {
                isSubmitting = false;
                submitBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Abrir o cofre';
                updateSubmitState();

                var data = result.data;
                var status = result.status;

                /* 403 = out of range (geofence) */
                if (status === 403 && data.out_of_range) {
                    if (data.range_reason === 'fora_do_raio') {
                        showGeofenceOutOfRange(data.radius, data.distance);
                    } else if (data.range_reason === 'sem_localizacao') {
                        showGeofenceNoLocation();
                    }
                    showToast(data.error || 'Você precisa estar no local do cofre.', 'error');
                    return;
                }

                /* 423 = blocked */
                if (status === 423) {
                    showBlocked(data.blocked_until);
                    showToast(data.error || 'O cofre está bloqueado.', 'error');
                    return;
                }

                /* 400 = validation error */
                if (status === 400) {
                    showToast(data.error || 'Erro de validação.', 'error');
                    return;
                }

                /* success */
                if (data.success && data.correct) {
                    /* CORRECT — open the safe! */
                    currentPassword = data.password || '';
                    openSafe();
                } else if (data.success && !data.correct) {
                    /* WRONG */
                    triggerError();
                    var attempts = data.attempts_left;
                    statusMsg.textContent = 'Código incorreto';
                    statusMsg.className = 'vault-status-msg error';
                    if (attempts !== undefined) {
                        attemptsMsg.textContent = 'Tentativas restantes: ' + attempts;
                    }
                    if (data.blocked) {
                        showBlocked(data.blocked_until);
                        showToast('Número de tentativas esgotado. Cofre bloqueado.', 'error');
                    }
                }
            })
            .catch(function () {
                isSubmitting = false;
                submitBtn.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Abrir o cofre';
                updateSubmitState();
                showToast('Erro de conexão. Verifique sua rede e tente novamente.', 'error');
            });
        }

        /* ============================================================
           ERROR ANIMATION
           ============================================================ */
        function triggerError() {
            safe.classList.remove('opened');
            safe.classList.add('error-shake');
            setTimeout(function () {
                safe.classList.remove('error-shake');
            }, 600);
            clearDigits();
        }

        function clearDigits() {
            boxes.forEach(function (b) {
                b.value = '';
                b.classList.remove('filled');
            });
            boxes[0].focus();
        }

        /* ============================================================
           OPEN SAFE (correct!) + SLOW PASSWORD REVEAL
           ============================================================ */
        function openSafe() {
            safe.classList.remove('locked');
            safe.classList.add('opened');
            document.body.classList.add('opened');

            /* Hide input, status */
            digitArea.style.display = 'none';
            submitBtn.style.display = 'none';
            statusMsg.textContent = '';
            attemptsMsg.textContent = '';
            blockedBanner.classList.remove('visible');
            notConfigured.classList.remove('visible');
            hideAllGeofence();

            /* Build password characters inside the safe */
            var pwd = currentPassword;
            safeInteriorValue.innerHTML = '';

            for (var i = 0; i < pwd.length; i++) {
                var span = document.createElement('span');
                span.className = 'char masked';
                span.textContent = pwd[i];
                safeInteriorValue.appendChild(span);
            }

            /* Reveal one character at a time with delay */
            var chars = safeInteriorValue.querySelectorAll('.char');
            chars.forEach(function (ch, idx) {
                setTimeout(function () {
                    ch.classList.remove('masked');
                    ch.classList.add('revealed');
                }, 800 + idx * 600);
            });
        }

        /* ============================================================
           INIT — start with geolocation flow
           ============================================================ */
        initGeofence();

    })();
