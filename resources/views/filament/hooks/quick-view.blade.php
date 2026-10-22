@php
    use App\Support\Filament\QuickViewRoutes;
    use Filament\Facades\Filament;

    $quickViewConfig = [
        'patterns' => QuickViewRoutes::patterns((string) Filament::getCurrentPanel()?->getId()),
    ];
@endphp

{{--
    Vista rápida (lado anfitrión y lado incrustado).

    - Solo se abre con Alt + clic (PC) o ⌥ Option + clic (Mac; el navegador lo reporta como Alt).
      Sin la tecla, todo navega como siempre. Ctrl/⌘/Shift + clic quedan para el navegador.
    - Global en el panel: menú lateral, menú de usuario, tablas, pestañas y acciones que navegan.
      Solo pantallas de Filament (lista blanca de QuickViewRoutes): nunca descargas, PDF ni exportes.
    - Acciones que abren una modal o ejecutan algo no tienen pantalla propia: se comportan como siempre.
    - Botones: cerrar (Esc), pantalla completa y al lado (pantallas anchas).
    - Si dentro se hizo algo, al cerrar se refresca la página de atrás.
    - El botón «atrás» del navegador no la cierra a propósito: Livewire repintaría la página desde
      su caché y se perderían los filtros de la tabla.
--}}
<script type="application/json" id="ic-quick-view-config">@json($quickViewConfig)</script>

<style>
    .ic-qv-root { position: fixed; inset: 0; z-index: 60; }
    .ic-qv-backdrop { position: absolute; inset: 0; background: rgb(3 7 18 / 0.55); backdrop-filter: blur(2px); }
    .ic-qv-panel {
        position: absolute; top: 1.5rem; bottom: 1.5rem; left: calc(5.5rem + 2vw); right: 2vw;
        border-radius: 1rem; overflow: hidden; background: #fff;
        box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.45); transition: all 0.2s ease;
    }
    .dark .ic-qv-panel { background: rgb(3 7 18); box-shadow: 0 25px 50px -12px rgb(0 0 0 / 0.8), 0 0 0 1px rgb(255 255 255 / 0.1); }
    .ic-qv-frame { width: 100%; height: 100%; border: 0; display: block; background: transparent; }
    .ic-qv-rail { position: absolute; top: 1.5rem; left: 2vw; display: flex; flex-direction: column; gap: 0.75rem; }
    .ic-qv-btn {
        width: 2.75rem; height: 2.75rem; border-radius: 9999px; display: grid; place-items: center;
        background: #fff; color: rgb(55 65 81); border: 0; cursor: pointer;
        box-shadow: 0 4px 12px rgb(0 0 0 / 0.25); transition: transform 0.15s ease, background 0.15s ease;
    }
    .ic-qv-btn:hover { transform: scale(1.06); background: rgb(243 244 246); }
    .ic-qv-btn:focus-visible { outline: 2px solid var(--primary-500, #f59e0b); outline-offset: 2px; }
    .dark .ic-qv-btn { background: rgb(31 41 55); color: rgb(229 231 235); }
    .dark .ic-qv-btn:hover { background: rgb(55 65 81); }
    .ic-qv-btn svg { width: 1.25rem; height: 1.25rem; }
    .ic-qv-loading { position: absolute; inset: 0; display: grid; place-items: center; color: rgb(107 114 128); font-size: 0.875rem; background: inherit; }
    .ic-qv-spinner {
        width: 2rem; height: 2rem; border-radius: 9999px; border: 3px solid rgb(229 231 235);
        border-top-color: var(--primary-500, #f59e0b); animation: ic-qv-spin 0.8s linear infinite;
    }
    @keyframes ic-qv-spin { to { transform: rotate(360deg); } }

    /* Al lado: la página sigue usable a la izquierda. */
    .ic-qv-root.ic-qv-is-split { inset: 4rem 0 0 auto; width: 50vw; }
    .ic-qv-root.ic-qv-is-split .ic-qv-backdrop { display: none; }
    .ic-qv-root.ic-qv-is-split .ic-qv-panel { top: 0; bottom: 0; left: 4rem; right: 0; border-radius: 1rem 0 0 0; }
    .ic-qv-root.ic-qv-is-split .ic-qv-rail { top: 1rem; left: 0.5rem; }
    html.ic-qv-split .fi-main-ctn { margin-inline-end: 50vw; transition: margin 0.2s ease; }
</style>

<script>
    (function () {
        if (window.__icQuickViewInstalled) {
            return;
        }

        window.__icQuickViewInstalled = true;

        var embedded = document.documentElement.classList.contains('ic-qv-embedded');
        var MIN_WIDTH = 1024;
        var SPLIT_MIN_WIDTH = 1280;
        var compiled = { key: null, list: [] };

        function patterns() {
            var source = [];

            try {
                source = JSON.parse(document.getElementById('ic-quick-view-config').textContent || '{}').patterns || [];
            } catch (error) {
                source = [];
            }

            var key = source.join('|');

            if (compiled.key !== key) {
                compiled = {
                    key: key,
                    list: source.map(function (pattern) {
                        try {
                            return new RegExp(pattern);
                        } catch (error) {
                            return null;
                        }
                    }).filter(Boolean),
                };
            }

            return compiled.list;
        }

        /** URL absoluta si el enlace lleva a una pantalla de Filament de este panel; null si no. */
        function quickViewUrl(anchor) {
            if (!anchor || anchor.target === '_blank' || anchor.hasAttribute('download') || anchor.closest('[data-ic-no-quick-view]')) {
                return null;
            }

            var url;

            try {
                url = new URL(anchor.getAttribute('href'), window.location.href);
            } catch (error) {
                return null;
            }

            if (url.origin !== window.location.origin || (url.protocol !== 'https:' && url.protocol !== 'http:')) {
                return null;
            }

            var path = url.pathname.replace(/\/+$/, '') || '/';

            return patterns().some(function (pattern) { return pattern.test(path); }) ? url.href : null;
        }

        function navigate(url) {
            if (window.Livewire && typeof window.Livewire.navigate === 'function') {
                window.Livewire.navigate(url);
            } else {
                window.location.href = url;
            }
        }

        function isQuickViewClick(event) {
            return event.button === 0 && event.altKey && !event.ctrlKey && !event.metaKey && !event.shiftKey;
        }

        /* ---------- Lado incrustado. ---------- */
        if (embedded) {
            var host = null;

            try {
                host = window.parent && window.parent.icQuickView ? window.parent.icQuickView : null;
            } catch (error) {
                host = null;
            }

            /* Dentro de la vista rápida no se anidan ventanas: Alt/⌥ + clic navega aquí mismo (en Mac descargaría el enlace). */
            window.addEventListener('click', function (event) {
                if (!isQuickViewClick(event)) {
                    return;
                }

                var url = quickViewUrl(event.target && event.target.closest ? event.target.closest('a[href]') : null);

                if (url !== null) {
                    event.preventDefault();
                    event.stopImmediatePropagation();
                    navigate(url);
                }
            }, true);

            document.addEventListener('keydown', function (event) {
                /* Con una modal de Filament abierta, Esc cierra la modal y no la vista rápida. */
                if (event.key !== 'Escape' || !host || document.querySelector('.fi-modal.fi-modal-open')) {
                    return;
                }

                host.close();
            });

            /* Cualquier acción ejecutada dentro (guardar, aprobar…) marca la página de atrás para refrescarla. */
            var watchCommits = function () {
                window.Livewire.hook('commit', function (payload) {
                    if (host && payload.commit && Array.isArray(payload.commit.calls) && payload.commit.calls.length > 0) {
                        payload.succeed(function () {
                            host.markDirty();
                        });
                    }
                });
            };

            if (window.Livewire && typeof window.Livewire.hook === 'function') {
                watchCommits();
            } else {
                document.addEventListener('livewire:init', watchCommits);
            }

            return;
        }

        /* ---------- Lado anfitrión. ---------- */
        var icons = {
            close: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M6 6l12 12M18 6L6 18"/></svg>',
            expand: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/></svg>',
            split: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><path d="M12 4v16"/></svg>',
            overlay: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="3"/><rect x="7" y="8" width="10" height="8" rx="1.5"/></svg>'
        };

        var state = { root: null, frame: null, loading: null, splitButton: null, url: null, dirty: false, split: false, previousOverflow: '' };

        function button(name, label, onClick) {
            var element = document.createElement('button');
            element.type = 'button';
            element.className = 'ic-qv-btn';
            element.innerHTML = icons[name];
            element.title = label;
            element.setAttribute('aria-label', label);
            element.addEventListener('click', onClick);

            return element;
        }

        function applySplit() {
            if (!state.root) {
                return;
            }

            var wideEnough = window.innerWidth >= SPLIT_MIN_WIDTH;
            var split = state.split && wideEnough;
            state.root.classList.toggle('ic-qv-is-split', split);
            document.documentElement.classList.toggle('ic-qv-split', split);
            document.documentElement.style.overflow = split ? state.previousOverflow : 'hidden';

            var label = split ? 'Volver a ventana flotante' : 'Abrir al lado';
            state.splitButton.innerHTML = split ? icons.overlay : icons.split;
            state.splitButton.title = label;
            state.splitButton.setAttribute('aria-label', label);
            state.splitButton.style.display = wideEnough ? '' : 'none';
        }

        function open(url) {
            if (state.root) {
                state.url = url;
                state.loading.style.display = '';
                state.frame.src = url;

                return;
            }

            var root = document.createElement('div');
            root.className = 'ic-qv-root';
            root.setAttribute('role', 'dialog');
            root.setAttribute('aria-modal', 'true');
            root.setAttribute('aria-label', 'Vista rápida');

            var backdrop = document.createElement('div');
            backdrop.className = 'ic-qv-backdrop';
            backdrop.addEventListener('click', function () { close(); });

            var panel = document.createElement('div');
            panel.className = 'ic-qv-panel';

            var loading = document.createElement('div');
            loading.className = 'ic-qv-loading';
            loading.innerHTML = '<div style="display:grid;place-items:center;gap:.5rem"><div class="ic-qv-spinner"></div><span>Cargando…</span></div>';

            var frame = document.createElement('iframe');
            frame.className = 'ic-qv-frame';
            frame.setAttribute('data-ic-quick-view', '1');
            frame.setAttribute('title', 'Vista rápida');
            frame.addEventListener('load', function () { loading.style.display = 'none'; });
            frame.src = url;

            panel.appendChild(frame);
            panel.appendChild(loading);

            var rail = document.createElement('div');
            rail.className = 'ic-qv-rail';
            rail.appendChild(button('close', 'Cerrar (Esc)', function () { close(); }));
            rail.appendChild(button('expand', 'Abrir en pantalla completa', expand));
            state.splitButton = button('split', 'Abrir al lado', function () {
                state.split = !state.split;
                applySplit();
            });
            rail.appendChild(state.splitButton);

            root.appendChild(backdrop);
            root.appendChild(panel);
            root.appendChild(rail);
            document.body.appendChild(root);

            state.root = root;
            state.frame = frame;
            state.loading = loading;
            state.url = url;
            state.dirty = false;
            state.previousOverflow = document.documentElement.style.overflow;
            applySplit();
            frame.focus();
        }

        function currentFrameUrl() {
            try {
                return state.frame.contentWindow.location.href || state.url;
            } catch (error) {
                return state.url;
            }
        }

        function teardown() {
            if (!state.root) {
                return;
            }

            state.root.remove();
            document.documentElement.classList.remove('ic-qv-split');
            document.documentElement.style.overflow = state.previousOverflow;
            state.root = null;
            state.frame = null;
            state.loading = null;
            state.splitButton = null;
        }

        function close() {
            if (!state.root) {
                return;
            }

            var dirty = state.dirty;
            teardown();

            if (dirty && window.Livewire && typeof window.Livewire.all === 'function') {
                window.Livewire.all().forEach(function (component) {
                    try {
                        component.$wire.$refresh();
                    } catch (error) {
                        /* Componente ya desmontado. */
                    }
                });
            }
        }

        function expand() {
            var url = currentFrameUrl();
            teardown();
            navigate(url);
        }

        window.icQuickView = {
            open: open,
            close: close,
            markDirty: function () { state.dirty = true; }
        };

        /* Captura en window: corre antes que Livewire/Alpine y evita la descarga de ⌥ + clic en Mac. */
        window.addEventListener('click', function (event) {
            if (!isQuickViewClick(event) || window.innerWidth < MIN_WIDTH) {
                return;
            }

            var url = quickViewUrl(event.target && event.target.closest ? event.target.closest('a[href]') : null);

            if (url === null) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();
            open(url);
        }, true);

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && state.root) {
                close();
            }
        });

        window.addEventListener('resize', applySplit);
        document.addEventListener('livewire:navigating', teardown);
    })();
</script>
