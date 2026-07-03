@php
    $broadcastChannel = auth()->check()
        ? str_replace('\\', '.', auth()->user()::class).'.'.auth()->id()
        : null;
    $bellAlertUrl = route('business.notifications.bell-alert');
@endphp

<style>
    @keyframes fi-db-bell-ring {
        0%,
        100% {
            transform: rotate(0deg);
        }

        12% {
            transform: rotate(18deg);
        }

        24% {
            transform: rotate(-16deg);
        }

        36% {
            transform: rotate(14deg);
        }

        48% {
            transform: rotate(-12deg);
        }

        60% {
            transform: rotate(10deg);
        }

        72% {
            transform: rotate(-8deg);
        }

        84% {
            transform: rotate(4deg);
        }
    }

    @keyframes fi-db-bell-glow {
        0%,
        100% {
            box-shadow:
                0 0 0 0 rgb(251 191 36 / 0),
                0 0 0 0 rgb(239 68 68 / 0);
        }

        50% {
            box-shadow:
                0 0 0 8px rgb(251 191 36 / 0.4),
                0 0 28px 6px rgb(249 115 22 / 0.7);
        }
    }

    @keyframes fi-db-badge-pop {
        0%,
        100% {
            transform: scale(1);
        }

        40% {
            transform: scale(1.35);
        }
    }

    body.fi-panel-business .fi-topbar-database-notifications-btn.fi-db-notifications-alert,
    body.fi-panel-business .fi-sidebar-database-notifications-btn.fi-db-notifications-alert {
        animation:
            fi-db-bell-ring 0.7s ease-in-out 6,
            fi-db-bell-glow 1s ease-in-out infinite;
        background: rgb(245 158 11 / 0.28) !important;
        border-radius: 9999px;
        color: #fbbf24 !important;
        transform-origin: top center;
        z-index: 5;
    }

    body.fi-panel-business .fi-topbar-database-notifications-btn.fi-db-notifications-alert .fi-icon,
    body.fi-panel-business .fi-sidebar-database-notifications-btn.fi-db-notifications-alert .fi-icon {
        color: #fbbf24 !important;
        filter: drop-shadow(0 0 12px rgb(251 191 36 / 1));
    }

    body.fi-panel-business .fi-topbar-database-notifications-btn.fi-db-notifications-alert .fi-badge,
    body.fi-panel-business .fi-sidebar-database-notifications-btn.fi-db-notifications-alert .fi-sidebar-database-notifications-btn-badge-ctn {
        animation: fi-db-badge-pop 0.45s ease-out 8;
        background: #ef4444 !important;
        border: 1px solid #fecaca !important;
        box-shadow: 0 0 16px rgb(239 68 68 / 0.8);
        color: #fff !important;
    }
</style>

<script>
    (() => {
        const panelBodyClass = 'fi-panel-business';
        const buttonSelector = 'body.fi-panel-business .fi-topbar-database-notifications-btn, body.fi-panel-business .fi-sidebar-database-notifications-btn';
        const alertClass = 'fi-db-notifications-alert';
        const alertDurationMs = 12000;
        const bellAlertUrl = @js($bellAlertUrl);
        const broadcastChannel = @js($broadcastChannel);

        let lastUnreadCount = null;
        let alertTimeout = null;
        let echoRegistered = false;
        let pollIntervalId = null;
        let observer = null;

        function isBusinessPanel() {
            return document.body.classList.contains(panelBodyClass);
        }

        function notificationButtons() {
            return document.querySelectorAll(buttonSelector);
        }

        function parseUnreadCount(button) {
            const topbarBadge = button.querySelector('.fi-icon-btn-badge-ctn .fi-badge');
            const sidebarBadge = button.querySelector('.fi-sidebar-database-notifications-btn-badge-ctn');

            const raw = (topbarBadge ?? sidebarBadge)?.textContent?.trim() ?? '0';
            const count = Number.parseInt(raw, 10);

            return Number.isFinite(count) ? count : 0;
        }

        function currentUnreadCount() {
            const buttons = notificationButtons();

            if (! buttons.length) {
                return 0;
            }

            let maxCount = 0;

            buttons.forEach((button) => {
                maxCount = Math.max(maxCount, parseUnreadCount(button));
            });

            return maxCount;
        }

        function triggerBellAlert() {
            if (! isBusinessPanel()) {
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

            window.clearTimeout(alertTimeout);

            alertTimeout = window.setTimeout(() => {
                notificationButtons().forEach((button) => {
                    button.classList.remove(alertClass);
                });
            }, alertDurationMs);
        }

        function checkForIncreasedUnreadCount() {
            const count = currentUnreadCount();

            if (lastUnreadCount !== null && count > lastUnreadCount) {
                triggerBellAlert();
            }

            lastUnreadCount = count;
        }

        function pollBellAlertSignal() {
            if (! isBusinessPanel()) {
                return;
            }

            window.fetch(bellAlertUrl, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            })
                .then((response) => response.json())
                .then((payload) => {
                    if (payload?.alert === true) {
                        triggerBellAlert();
                    }
                })
                .catch(() => {});
        }

        function registerEchoListeners() {
            if (echoRegistered || ! broadcastChannel || ! window.Echo) {
                return;
            }

            echoRegistered = true;

            const channel = window.Echo.private(broadcastChannel);

            channel.listen('.database-notifications.sent', () => {
                window.setTimeout(() => {
                    checkForIncreasedUnreadCount();
                    triggerBellAlert();
                }, 250);
            });

            channel.notification((notification) => {
                if ((notification?.format ?? null) !== 'filament') {
                    return;
                }

                triggerBellAlert();
            });
        }

        function observeBadgeChanges() {
            if (observer !== null) {
                return;
            }

            const topbarEnd = document.querySelector('body.fi-panel-business .fi-topbar-end');

            if (! topbarEnd) {
                return;
            }

            observer = new MutationObserver(() => {
                checkForIncreasedUnreadCount();
            });

            observer.observe(topbarEnd, {
                childList: true,
                subtree: true,
                characterData: true,
            });
        }

        function bootBellAlertWatcher() {
            if (! isBusinessPanel()) {
                return;
            }

            window.setTimeout(() => {
                lastUnreadCount = currentUnreadCount();
            }, 300);

            observeBadgeChanges();
            registerEchoListeners();
            pollBellAlertSignal();
        }

        function startPolling() {
            if (pollIntervalId !== null) {
                return;
            }

            pollIntervalId = window.setInterval(pollBellAlertSignal, 2000);
        }

        document.addEventListener('livewire:init', () => {
            window.Livewire.hook('message.processed', () => {
                window.setTimeout(checkForIncreasedUnreadCount, 150);
            });

            window.Livewire.hook('morph.updated', () => {
                window.setTimeout(checkForIncreasedUnreadCount, 150);
            });
        });

        window.addEventListener('EchoLoaded', registerEchoListeners);

        if (window.Echo) {
            registerEchoListeners();
        }

        document.addEventListener('DOMContentLoaded', () => {
            bootBellAlertWatcher();
            startPolling();
        });

        document.addEventListener('livewire:navigated', () => {
            bootBellAlertWatcher();
            startPolling();
        });
    })();
</script>
