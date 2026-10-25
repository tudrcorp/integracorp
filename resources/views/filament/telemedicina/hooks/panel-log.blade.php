{{--
    Bitácora del panel de Telemedicina, lado del navegador. Reporta lo que el
    servidor nunca ve: una petición de Livewire que falló (error, sesión
    vencida, sin conexión) y los errores de JavaScript de la página, que dejan
    botones sin efecto. Nunca envía el contenido de la respuesta ni del
    formulario. Una instancia por pestaña (sobrevive a la navegación SPA),
    sin repetir el mismo fallo en un minuto y con tope por carga.
--}}
@auth
<script data-navigate-once>
(() => {
    if (window.__tmPanelLog) {
        return;
    }

    window.__tmPanelLog = true;

    const cfg = {
        url: @js(route('telemedicine.panel-log.client')),
        token: @js(csrf_token()),
        maxPerLoad: 25,
        dedupeMs: 60000,
    };

    let sent = 0;
    const recent = new Map();

    const send = (data) => {
        if (sent >= cfg.maxPerLoad) return;

        const key = [data.event, data.status ?? '', data.message ?? '', (data.methods || []).join(',')].join('|');
        const now = Date.now();
        if (recent.has(key) && now - recent.get(key) < cfg.dedupeMs) return;
        recent.set(key, now);
        sent++;

        const token = document.querySelector('meta[name="csrf-token"]')?.content || cfg.token;

        try {
            fetch(cfg.url, {
                method: 'POST',
                keepalive: true,
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ path: window.location.pathname, online: navigator.onLine, ...data }),
            }).catch(() => {});
        } catch (error) {
            // La bitácora nunca puede romper la página.
        }
    };

    const describePayload = (payload) => {
        const out = { components: [], methods: [] };

        try {
            const parsed = typeof payload === 'string' ? JSON.parse(payload) : payload;

            (parsed?.components || []).forEach((component) => {
                try {
                    const snapshot = typeof component.snapshot === 'string' ? JSON.parse(component.snapshot) : component.snapshot;
                    if (snapshot?.memo?.name) out.components.push(String(snapshot.memo.name).slice(0, 190));
                } catch (error) {}

                (component.calls || []).forEach((call) => {
                    if (call?.method && !String(call.method).startsWith('$') && call.method !== '__dispatch') {
                        out.methods.push(String(call.method).slice(0, 120));
                    }
                });
            });
        } catch (error) {}

        out.components = [...new Set(out.components)].slice(0, 10);
        out.methods = [...new Set(out.methods)].slice(0, 10);

        return out;
    };

    const watchLivewire = () => {
        if (!window.Livewire?.hook) return;

        window.Livewire.hook('request', ({ payload, fail }) => {
            fail(({ status }) => {
                const info = describePayload(payload);
                const code = Number(status) || 0;

                send({
                    event: code === 419 ? 'sesion_vencida' : (code === 0 || !navigator.onLine ? 'sin_conexion' : 'peticion_fallida'),
                    status: code,
                    components: info.components,
                    methods: info.methods,
                });
            });
        });
    };

    if (window.Livewire?.hook) {
        watchLivewire();
    } else {
        document.addEventListener('livewire:init', watchLivewire, { once: true });
    }

    const sameOrigin = (source) => !source || source.startsWith(window.location.origin);
    const ignored = /ResizeObserver loop|Script error\.?$/i;

    window.addEventListener('error', (event) => {
        const message = String(event?.message || '');
        if (!message || ignored.test(message) || !sameOrigin(event?.filename || '')) return;

        const file = String(event.filename || '').replace(window.location.origin, '').split('?')[0];
        send({
            event: 'error_navegador',
            message: message.slice(0, 900),
            source: file ? (file + ':' + (event.lineno || 0)).slice(0, 300) : null,
        });
    });

    window.addEventListener('unhandledrejection', (event) => {
        const reason = event?.reason;
        const message = String(reason?.message || reason || '');
        if (!message || ignored.test(message)) return;

        send({ event: 'error_navegador', message: ('Promesa sin manejar: ' + message).slice(0, 900) });
    });
})();
</script>
@endauth
