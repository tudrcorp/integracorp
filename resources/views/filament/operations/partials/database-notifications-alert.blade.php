@php
    $broadcastChannel = auth()->check()
        ? str_replace('\\', '.', auth()->user()::class).'.'.auth()->id()
        : null;
@endphp

{{--
    Campanita de Operaciones: cuando llega una notificación nueva (por ejemplo,
    «Expediente listo») la campana se sacude, brilla, el contador late y, en los
    dispositivos que lo permiten, vibra. Mismo lenguaje que Negocios y Telemedicina.
--}}
<style>
    @keyframes fi-operations-db-bell-ring {
        0%, 100% { transform: rotate(0deg); }
        12% { transform: rotate(18deg); }
        24% { transform: rotate(-16deg); }
        36% { transform: rotate(14deg); }
        48% { transform: rotate(-12deg); }
        60% { transform: rotate(10deg); }
        72% { transform: rotate(-8deg); }
        84% { transform: rotate(4deg); }
    }

    @keyframes fi-operations-db-bell-glow {
        0%, 100% {
            box-shadow: 0 0 0 0 rgb(251 191 36 / 0), 0 0 0 0 rgb(239 68 68 / 0);
        }

        50% {
            box-shadow: 0 0 0 8px rgb(251 191 36 / 0.4), 0 0 28px 6px rgb(249 115 22 / 0.7);
        }
    }

    @keyframes fi-operations-db-badge-pop {
        0%, 100% { transform: scale(1); }
        40% { transform: scale(1.35); }
    }

    body.fi-panel-operations .fi-topbar-database-notifications-btn.fi-db-notifications-alert,
    body.fi-panel-operations .fi-sidebar-database-notifications-btn.fi-db-notifications-alert {
        animation:
            fi-operations-db-bell-ring 0.7s ease-in-out 6,
            fi-operations-db-bell-glow 1s ease-in-out infinite;
        background: rgb(245 158 11 / 0.28) !important;
        border-radius: 9999px;
        color: #fbbf24 !important;
        transform-origin: top center;
        z-index: 5;
    }

    body.fi-panel-operations .fi-topbar-database-notifications-btn.fi-db-notifications-alert .fi-icon,
    body.fi-panel-operations .fi-sidebar-database-notifications-btn.fi-db-notifications-alert .fi-icon {
        color: #fbbf24 !important;
        filter: drop-shadow(0 0 12px rgb(251 191 36 / 1));
    }

    body.fi-panel-operations .fi-topbar-database-notifications-btn.fi-db-notifications-alert .fi-badge,
    body.fi-panel-operations .fi-sidebar-database-notifications-btn.fi-db-notifications-alert .fi-sidebar-database-notifications-btn-badge-ctn {
        animation: fi-operations-db-badge-pop 0.45s ease-out 8;
        background: #ef4444 !important;
        border: 1px solid #fecaca !important;
        box-shadow: 0 0 16px rgb(239 68 68 / 0.8);
        color: #fff !important;
    }

    /** Quien pidió menos movimiento conserva el brillo y el color, sin el balanceo. */
    @media (prefers-reduced-motion: reduce) {
        body.fi-panel-operations .fi-topbar-database-notifications-btn.fi-db-notifications-alert,
        body.fi-panel-operations .fi-sidebar-database-notifications-btn.fi-db-notifications-alert {
            animation: fi-operations-db-bell-glow 1s ease-in-out 6;
        }

        body.fi-panel-operations .fi-topbar-database-notifications-btn.fi-db-notifications-alert .fi-badge,
        body.fi-panel-operations .fi-sidebar-database-notifications-btn.fi-db-notifications-alert .fi-sidebar-database-notifications-btn-badge-ctn {
            animation: none;
        }
    }
</style>

{{-- data-navigate-once: el panel es SPA; sin esto cada navegación sumaba otra copia de los oyentes y del observador. --}}
<script data-navigate-once>
    (() => {
        if (window.__tdgOperationsBellAlert) {
            return;
        }

        window.__tdgOperationsBellAlert = true;

        const panelBodyClass = 'fi-panel-operations';
        const buttonSelector = 'body.fi-panel-operations .fi-topbar-database-notifications-btn, body.fi-panel-operations .fi-sidebar-database-notifications-btn';
        const alertClass = 'fi-db-notifications-alert';
        const alertDurationMs = 12000;
        const vibrationPattern = [180, 90, 180, 90, 260];
        const broadcastChannel = @js($broadcastChannel);

        let lastUnreadCount = null;
        let alertTimeout = null;
        let echoRegistered = false;
        let observer = null;
        let observedNode = null;
        let checkTimeout = null;

        function isOperationsPanel() {
            return document.body.classList.contains(panelBodyClass);
        }

        function notificationButtons() {
            return document.querySelectorAll(buttonSelector);
        }

        function parseUnreadCount(button) {
            const topbarBadge = button.querySelector('.fi-icon-btn-badge-ctn .fi-badge');
            const sidebarBadge = button.querySelector('.fi-sidebar-database-notifications-btn-badge-ctn');
            const count = Number.parseInt((topbarBadge ?? sidebarBadge)?.textContent?.trim() ?? '0', 10);

            return Number.isFinite(count) ? count : 0;
        }

        function currentUnreadCount() {
            let maxCount = 0;

            notificationButtons().forEach((button) => {
                maxCount = Math.max(maxCount, parseUnreadCount(button));
            });

            return maxCount;
        }

        /** El navegador solo deja vibrar si la página ya recibió un toque o clic; si no, se ignora en silencio. */
        function vibrate() {
            try {
                if (typeof navigator.vibrate === 'function' && ! window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    navigator.vibrate(vibrationPattern);
                }
            } catch (error) {
            }
        }

        function triggerBellAlert() {
            if (! isOperationsPanel()) {
                return;
            }

            const buttons = notificationButtons();

            if (! buttons.length) {
                return;
            }

            buttons.forEach((button) => {
                button.classList.remove(alertClass);
                void button.offsetWidth;
                button.classList.add(alertClass);
            });

            vibrate();

            window.clearTimeout(alertTimeout);

            alertTimeout = window.setTimeout(() => {
                notificationButtons().forEach((button) => button.classList.remove(alertClass));
            }, alertDurationMs);
        }

        function checkForIncreasedUnreadCount() {
            const count = currentUnreadCount();

            if (lastUnreadCount !== null && count > lastUnreadCount) {
                triggerBellAlert();
            }

            lastUnreadCount = count;
        }

        /** Agrupa ráfagas de cambios (una tabla grande dispara cientos) en una sola revisión del contador. */
        function scheduleCheck(delayMs = 150) {
            window.clearTimeout(checkTimeout);
            checkTimeout = window.setTimeout(checkForIncreasedUnreadCount, delayMs);
        }

        function registerEchoListeners() {
            if (echoRegistered || ! broadcastChannel || ! window.Echo) {
                return;
            }

            echoRegistered = true;

            const channel = window.Echo.private(broadcastChannel);

            channel.listen('.database-notifications.sent', () => {
                scheduleCheck(250);
            });
        }

        function observeBadgeChanges() {
            const topbarEnd = document.querySelector('body.fi-panel-operations .fi-topbar-end');

            /** Tras una navegación SPA la barra superior puede ser otro nodo: se vuelve a enganchar. */
            if (! topbarEnd || topbarEnd === observedNode) {
                return;
            }

            observer?.disconnect();
            observedNode = topbarEnd;
            observer = new MutationObserver(() => scheduleCheck());
            observer.observe(topbarEnd, { childList: true, subtree: true, characterData: true });
        }

        function bootBellAlertWatcher() {
            if (! isOperationsPanel()) {
                return;
            }

            /** Al entrar no suena: solo cuando el contador sube después de cargar la página. */
            window.setTimeout(() => {
                lastUnreadCount = currentUnreadCount();
            }, 300);

            observeBadgeChanges();
            registerEchoListeners();
        }

        document.addEventListener('livewire:init', () => {
            window.Livewire.hook('morph.updated', () => scheduleCheck());
        });

        window.addEventListener('EchoLoaded', registerEchoListeners);

        document.addEventListener('DOMContentLoaded', bootBellAlertWatcher);
        document.addEventListener('livewire:navigated', bootBellAlertWatcher);
    })();
</script>
