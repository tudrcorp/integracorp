@php
    $isAuthenticated = $isAuthenticated ?? (bool) ($authenticated ?? false);
    $idleTimeoutSeconds = (int) ($idleTimeoutSeconds ?? \App\Support\PresentationHubGate::idleTimeoutSeconds());
@endphp

@if ($isAuthenticated)
<script>
(() => {
    const timeoutMs = {{ $idleTimeoutSeconds }} * 1000;
    const heartbeatUrl = @json(url('/dpto-tecnologia-sistemas/heartbeat'));
    const logoutUrl = @json(url('/dpto-tecnologia-sistemas/logout?reason=idle'));
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());

    let idleTimer = null;
    let lastHeartbeatAt = 0;
    let loggingOut = false;

    const logoutForIdle = () => {
        if (loggingOut) {
            return;
        }
        loggingOut = true;
        window.location.href = logoutUrl;
    };

    const sendHeartbeat = async () => {
        const now = Date.now();
        if (now - lastHeartbeatAt < 30000) {
            return;
        }
        lastHeartbeatAt = now;

        try {
            const response = await fetch(heartbeatUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
            });

            if (response.status === 401) {
                logoutForIdle();
            }
        } catch (e) {
            // Silencioso: el cierre forzado lo hará el timer o la próxima navegación.
        }
    };

    const resetIdleTimer = () => {
        if (idleTimer) {
            clearTimeout(idleTimer);
        }
        idleTimer = window.setTimeout(logoutForIdle, timeoutMs);
        void sendHeartbeat();
    };

    ['mousemove', 'mousedown', 'keydown', 'touchstart', 'touchend', 'scroll', 'visibilitychange'].forEach((eventName) => {
        document.addEventListener(eventName, () => {
            if (eventName === 'visibilitychange' && document.visibilityState !== 'visible') {
                return;
            }
            resetIdleTimer();
        }, { passive: true });
    });

    resetIdleTimer();
})();
</script>
@endif
