@php
    $shouldLoad = request()->is('business/helpdesks*');
@endphp

@if ($shouldLoad)
    <style>
        .helpdesk-tour-overlay {
            position: fixed;
            inset: 0;
            background: radial-gradient(circle at 20% 15%, rgba(59, 130, 246, 0.2), transparent 35%),
                radial-gradient(circle at 80% 80%, rgba(14, 165, 233, 0.15), transparent 30%),
                rgba(15, 23, 42, 0.62);
            backdrop-filter: blur(2px);
            z-index: 2147483000;
        }

        .helpdesk-tour-highlight {
            position: relative;
            z-index: 2147483001 !important;
            outline: 3px solid rgba(96, 165, 250, 0.95);
            outline-offset: 3px;
            border-radius: 12px;
            box-shadow: 0 0 0 6px rgba(96, 165, 250, 0.22), 0 8px 26px rgba(15, 23, 42, 0.22);
            pointer-events: none;
            transition: box-shadow 220ms ease, outline-color 220ms ease, outline-offset 220ms ease;
        }

        /* Animación solo al cambiar de paso (no en re-sync por Livewire) */
        .helpdesk-tour-highlight.is-animating {
            animation: helpdesk-tour-focus-in 240ms ease;
        }

        @keyframes helpdesk-tour-focus-in {
            from {
                box-shadow: 0 0 0 2px rgba(96, 165, 250, 0.1), 0 2px 12px rgba(15, 23, 42, 0.12);
                outline-offset: 0;
            }
            to {
                box-shadow: 0 0 0 6px rgba(96, 165, 250, 0.22), 0 8px 26px rgba(15, 23, 42, 0.22);
                outline-offset: 3px;
            }
        }

        /* Botones tipo “pill” (como Crear ticket) se ven mejor con ring */
        .helpdesk-tour-highlight[data-tour-shape='pill'] {
            outline: 0;
            outline-offset: 0;
            border-radius: 9999px;
            box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.35), 0 10px 30px rgba(15, 23, 42, 0.25);
        }

        .helpdesk-tour-tooltip {
            position: fixed;
            z-index: 2147483002;
            width: min(400px, calc(100vw - 28px));
            background: linear-gradient(180deg, rgba(255, 255, 255, 0.98) 0%, rgba(248, 250, 252, 0.98) 100%);
            color: #0f172a;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 16px;
            box-shadow: 0 20px 56px rgba(15, 23, 42, 0.28);
            padding: 16px;
            font-size: 13px;
            opacity: 0;
            transform: translateY(8px) scale(0.985);
            transition: opacity 220ms ease, transform 220ms ease, left 280ms cubic-bezier(0.22, 1, 0.36, 1),
                top 280ms cubic-bezier(0.22, 1, 0.36, 1);
        }

        .helpdesk-tour-tooltip.is-visible {
            opacity: 1;
            transform: translateY(0) scale(1);
        }

        .helpdesk-tour-tooltip.is-switching {
            opacity: 0.4;
            transform: translateY(4px) scale(0.992);
        }

        .dark .helpdesk-tour-tooltip {
            background: linear-gradient(180deg, rgba(2, 6, 23, 0.98) 0%, rgba(15, 23, 42, 0.98) 100%);
            color: #e2e8f0;
            border-color: rgba(100, 116, 139, 0.45);
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.5);
        }

        .helpdesk-tour-tooltip-title {
            font-weight: 800;
            letter-spacing: -0.01em;
            margin: 0 0 8px 0;
            font-size: 15px;
        }

        .helpdesk-tour-tooltip-body {
            margin: 0 0 12px 0;
            font-size: 13px;
            line-height: 1.45;
            opacity: 0.95;
        }

        .helpdesk-tour-tooltip-actions {
            display: flex;
            gap: 8px;
            justify-content: flex-end;
            align-items: center;
        }

        .helpdesk-tour-btn {
            border: 1px solid rgba(148, 163, 184, 0.45);
            background: rgba(255, 255, 255, 0.5);
            color: inherit;
            padding: 8px 12px;
            border-radius: 9999px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
            transition: transform 120ms ease, background 120ms ease, border-color 120ms ease;
        }

        .helpdesk-tour-btn:hover {
            border-color: rgba(100, 116, 139, 0.6);
            background: rgba(148, 163, 184, 0.12);
        }

        .helpdesk-tour-btn:active {
            transform: scale(0.98);
        }

        .helpdesk-tour-btn-primary {
            border-color: rgba(59, 130, 246, 0.55);
            background: linear-gradient(180deg, rgba(59, 130, 246, 0.17), rgba(59, 130, 246, 0.1));
        }

        .helpdesk-tour-btn-primary:hover {
            border-color: rgba(59, 130, 246, 0.72);
            background: rgba(59, 130, 246, 0.24);
        }

        .helpdesk-tour-btn[disabled] {
            opacity: 0.45;
            cursor: default;
            pointer-events: none;
        }
    </style>

    <script>
        (function () {
            if (window.__helpdeskTourInitialized === true) {
                return;
            }
            window.__helpdeskTourInitialized = true;

            const state = {
                steps: [],
                index: -1,
                overlayEl: null,
                tooltipEl: null,
                highlightedEl: null,
                syncTimer: null,
                transitionToken: 0,
            };

            const defaultSteps = [
                {
                    selector: '#helpdesk-create-ticket-btn',
                    title: '1) Crear ticket de soporte',
                    body: 'Inicia el proceso aquí. Describe claramente el problema, define la prioridad (BAJA, MEDIA o ALTA) y, si aplica, adjunta evidencias para acelerar la atención.',
                    placement: 'bottom',
                },
                {
                    selector: 'input[placeholder="Buscar"]',
                    title: '2) Ubicar tickets rápidamente',
                    body: 'Este buscador filtra por contenido visible del ticket (descripción, creador, estado, etc.). Úsalo para seguimiento diario y control de pendientes.',
                    placement: 'bottom',
                },
                {
                    selector: '.fi-ta',
                    title: '3) Tabla de control operativo',
                    body: 'La tabla consolida los tickets creados por ti o asignados a ti. Se prioriza por estado y te muestra prioridad, responsables, fecha de creación y última actualización.',
                    placement: 'top',
                },
                {
                    selector: '.fi-ta-table thead',
                    title: '4) Cómo leer las columnas',
                    body: 'Prioridad y Estado usan colores para facilitar lectura rápida. "Fecha de actualización" y su valor relativo (ej. hace 1 día) ayudan a detectar tickets estancados.',
                    placement: 'top',
                },
                {
                    selector: '.fi-ta-table tbody tr:first-child',
                    title: '5) Acciones por ticket',
                    body: 'Desde cada fila puedes: editar (solo creador), revisar documentos, agregar notas internas y actualizar estado. Este es el flujo de seguimiento hasta su cierre.',
                    placement: 'top',
                },
                {
                    selector: '#helpdesk-tour-btn',
                    title: '6) Reabrir guía cuando quieras',
                    body: 'Este botón te permite repetir la explicación para capacitación o para nuevos usuarios del equipo.',
                    placement: 'top',
                },
            ];

            function isElementVisible(el) {
                if (!el) return false;
                const rect = el.getBoundingClientRect();
                return rect.width > 0 && rect.height > 0;
            }

            function resolveTarget(step) {
                if (!step?.selector) return null;
                const el = document.querySelector(step.selector);
                if (!el) return null;
                if (!isElementVisible(el)) return null;
                return el;
            }

            function cleanupHighlight() {
                document.querySelectorAll('.helpdesk-tour-highlight').forEach((el) => {
                    el.classList.remove('helpdesk-tour-highlight');
                    el.classList.remove('is-animating');
                });
                state.highlightedEl = null;
            }

            function cleanupUI() {
                cleanupHighlight();
                document.querySelectorAll('.helpdesk-tour-tooltip').forEach((el) => el.remove());
                document.querySelectorAll('.helpdesk-tour-overlay').forEach((el) => el.remove());
                state.tooltipEl = null;
                state.overlayEl = null;
                if (state.syncTimer) {
                    clearInterval(state.syncTimer);
                    state.syncTimer = null;
                }
                state.index = -1;
            }

            function isActive() {
                return state.index >= 0;
            }

            function clamp(value, min, max) {
                return Math.min(max, Math.max(min, value));
            }

            function placeTooltip(target, placement) {
                const tooltip = state.tooltipEl;
                if (!tooltip || !target) return;

                const rect = target.getBoundingClientRect();
                const tipRect = tooltip.getBoundingClientRect();

                const margin = 10;
                const gap = 12;

                let top = rect.bottom + gap;
                let left = rect.left;

                if (placement === 'top') {
                    top = rect.top - tipRect.height - gap;
                }

                if (placement === 'right') {
                    top = rect.top;
                    left = rect.right + gap;
                }

                if (placement === 'left') {
                    top = rect.top;
                    left = rect.left - tipRect.width - gap;
                }

                const maxLeft = window.innerWidth - tipRect.width - margin;
                const maxTop = window.innerHeight - tipRect.height - margin;

                tooltip.style.left = clamp(left, margin, maxLeft) + 'px';
                tooltip.style.top = clamp(top, margin, maxTop) + 'px';
            }

            function placeTooltipCentered() {
                const tooltip = state.tooltipEl;
                if (!tooltip) return;

                const tipRect = tooltip.getBoundingClientRect();
                const margin = 10;
                const maxLeft = window.innerWidth - tipRect.width - margin;
                const maxTop = window.innerHeight - tipRect.height - margin;
                const left = clamp((window.innerWidth - tipRect.width) / 2, margin, maxLeft);
                const top = clamp((window.innerHeight - tipRect.height) / 2, margin, maxTop);

                tooltip.style.left = left + 'px';
                tooltip.style.top = top + 'px';
            }

            function syncCurrentStep() {
                if (!isActive()) return;

                const step = state.steps[state.index];
                const target = resolveTarget(step);

                if (!target) {
                    cleanupHighlight();
                    if (state.tooltipEl) {
                        placeTooltipCentered();
                    }
                    return;
                }

                if (state.highlightedEl !== target) {
                    cleanupHighlight();
                    target.classList.add('helpdesk-tour-highlight');
                    state.highlightedEl = target;
                } else if (!target.classList.contains('helpdesk-tour-highlight')) {
                    target.classList.add('helpdesk-tour-highlight');
                }

                if (state.tooltipEl) {
                    placeTooltip(target, step?.placement ?? 'bottom');
                }
            }

            function renderTooltipContent(step, isFirst, isLast) {
                if (!state.tooltipEl) return;

                state.tooltipEl.innerHTML = `
                    <div class="helpdesk-tour-tooltip-title">${step.title ?? ''}</div>
                    <div class="helpdesk-tour-tooltip-body">${step.body ?? ''}</div>
                    <div class="helpdesk-tour-tooltip-actions">
                        <button type="button" class="helpdesk-tour-btn" data-tour-action="close">Cerrar</button>
                        <button type="button" class="helpdesk-tour-btn" data-tour-action="prev" ${isFirst ? 'disabled' : ''}>Atrás</button>
                        <button type="button" class="helpdesk-tour-btn helpdesk-tour-btn-primary" data-tour-action="next">${isLast ? 'Finalizar' : 'Siguiente'}</button>
                    </div>
                `;

                state.tooltipEl.querySelector('[data-tour-action="close"]')?.addEventListener('click', close);
                state.tooltipEl.querySelector('[data-tour-action="prev"]')?.addEventListener('click', prev);
                state.tooltipEl.querySelector('[data-tour-action="next"]')?.addEventListener('click', next);
            }

            function renderStep() {
                const step = state.steps[state.index];
                if (!step) {
                    cleanupUI();
                    return;
                }

                const target = resolveTarget(step);
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'center', inline: 'center' });
                }

                cleanupHighlight();
                if (target) {
                    target.classList.add('helpdesk-tour-highlight');
                    target.classList.add('is-animating');
                    setTimeout(() => {
                        target.classList.remove('is-animating');
                    }, 260);
                    state.highlightedEl = target;
                }

                if (!state.overlayEl) {
                    const overlay = document.createElement('div');
                    overlay.className = 'helpdesk-tour-overlay';
                    // Importante: el tour NO debe cerrarse por clic afuera.
                    // Solo se controla con "Siguiente" o "Cerrar".
                    overlay.addEventListener('click', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                    });
                    document.body.appendChild(overlay);
                    state.overlayEl = overlay;
                }

                if (!state.tooltipEl) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'helpdesk-tour-tooltip';
                    document.body.appendChild(tooltip);
                    state.tooltipEl = tooltip;
                }

                const isFirst = state.index === 0;
                const isLast = state.index === state.steps.length - 1;
                const token = ++state.transitionToken;
                const tooltip = state.tooltipEl;
                const hasContent = tooltip.childElementCount > 0;

                const paintStep = () => {
                    if (token !== state.transitionToken || !state.tooltipEl) return;

                    renderTooltipContent(step, isFirst, isLast);

                    requestAnimationFrame(() => {
                        if (token !== state.transitionToken || !state.tooltipEl) return;

                        if (target) {
                            placeTooltip(target, step.placement ?? 'bottom');
                        } else {
                            placeTooltipCentered();
                        }

                        state.tooltipEl.classList.remove('is-switching');
                        state.tooltipEl.classList.add('is-visible');
                    });
                };

                if (hasContent) {
                    tooltip.classList.add('is-switching');
                    setTimeout(paintStep, 120);
                } else {
                    paintStep();
                }
            }

            function start(customSteps) {
                cleanupUI();
                state.steps = Array.isArray(customSteps) && customSteps.length ? customSteps : defaultSteps;
                state.index = 0;
                renderStep();
                state.syncTimer = setInterval(syncCurrentStep, 200);
            }

            function next() {
                if (state.index < 0) return;
                if (state.index >= state.steps.length - 1) {
                    close();
                    return;
                }
                state.index++;
                renderStep();
            }

            function prev() {
                if (state.index <= 0) return;
                state.index--;
                renderStep();
            }

            function close() {
                cleanupUI();
            }

            function wireButton() {
                const btn = document.querySelector('#helpdesk-tour-btn');
                if (!btn) return;
                btn.onclick = () => {
                    start();
                };
            }

            window.HelpdeskTour = { start, next, prev, close };

            // Bloquear interacción con la UI durante el tour (excepto dentro del tooltip).
            document.addEventListener(
                'click',
                (e) => {
                    if (!isActive()) return;
                    const tooltip = document.querySelector('.helpdesk-tour-tooltip');
                    if (tooltip && tooltip.contains(e.target)) {
                        return;
                    }
                    e.preventDefault();
                    e.stopPropagation();
                },
                true
            );

            window.addEventListener('resize', () => {
                const step = state.steps[state.index];
                const target = resolveTarget(step);
                if (state.tooltipEl && target) {
                    placeTooltip(target, step?.placement ?? 'bottom');
                }
            });

            document.addEventListener('keydown', (e) => {
                if (state.index < 0) return;
                if (e.key === 'Escape') close();
                if (e.key === 'ArrowRight') next();
                if (e.key === 'ArrowLeft') prev();
            });

            // Filament SPA (Livewire v3): re-enganchar al navegar sin recargar.
            document.addEventListener('livewire:navigated', () => {
                close();
                wireButton();
            });

            document.addEventListener('DOMContentLoaded', wireButton);
            wireButton();
        })();
    </script>
@endif

