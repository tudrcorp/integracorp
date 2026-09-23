{{--
    Latido del monitor en vivo. Una sola instancia por pestaña (sobrevive a la
    navegación SPA de Filament con data-navigate-once), late cada 15 s visible
    y cada 60 s oculta, y mide la latencia real de ida y vuelta al servidor.
    Si la sesión vence (401/419) deja de latir.
--}}
@if (config('live-presence.enabled', true) && auth()->check())
<script data-navigate-once>
(() => {
    if (window.__livePresence) {
        window.__livePresence.ping('navigate');
        return;
    }

    const cfg = {
        url: @js(route('live-presence.ping')),
        token: @js(csrf_token()),
        every: {{ (int) config('live-presence.heartbeat_seconds', 15) }} * 1000,
        hiddenEvery: {{ (int) config('live-presence.hidden_heartbeat_seconds', 60) }} * 1000,
    };

    let lastRtt = null;
    let timer = null;
    let inflight = false;
    let stopped = false;

    const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection || {};
    const standalone = () => window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;

    const timing = () => {
        const nav = performance.getEntriesByType ? performance.getEntriesByType('navigation')[0] : null;
        if (!nav) return {};
        const load = Math.round(nav.loadEventEnd || nav.domContentLoadedEventEnd || 0);
        return { load: load > 0 ? load : null, ttfb: Math.round(nav.responseStart || 0) || null };
    };

    const schedule = () => {
        clearTimeout(timer);
        if (stopped) return;
        timer = setTimeout(() => ping('heartbeat'), document.visibilityState === 'visible' ? cfg.every : cfg.hiddenEvery);
    };

    const ping = async (reason) => {
        if (stopped || inflight) return;
        inflight = true;
        const started = performance.now();

        try {
            const response = await fetch(cfg.url, {
                method: 'POST',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': cfg.token,
                },
                body: JSON.stringify({
                    reason,
                    path: window.location.pathname,
                    title: (document.title || '').slice(0, 150),
                    rtt: lastRtt,
                    visible: document.visibilityState === 'visible',
                    effective_type: conn.effectiveType || null,
                    downlink: typeof conn.downlink === 'number' ? conn.downlink : null,
                    conn_rtt: typeof conn.rtt === 'number' ? conn.rtt : null,
                    standalone: standalone(),
                    screen: `${window.screen.width}x${window.screen.height}`,
                    memory: navigator.deviceMemory || null,
                    cores: navigator.hardwareConcurrency || null,
                    lang: (navigator.language || '').slice(0, 20),
                    tz: (Intl.DateTimeFormat().resolvedOptions().timeZone || '').slice(0, 60),
                    ...timing(),
                }),
            });

            if (response.status === 401 || response.status === 419) {
                stopped = true;
            } else {
                lastRtt = Math.round(performance.now() - started);
            }
        } catch (error) {
            // Sin red: se reintenta en el próximo latido.
        } finally {
            inflight = false;
            schedule();
        }
    };

    document.addEventListener('visibilitychange', () => ping('visibility'));
    document.addEventListener('livewire:navigated', () => ping('navigate'));

    window.__livePresence = { ping };
    ping('load');
})();
</script>
@endif
