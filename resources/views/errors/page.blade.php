{{--
    Página de error única para todos los códigos (las vistas 401…5xx solo la incluyen).
    Autónoma a propósito: CSS e íconos en línea, sin Vite, Livewire ni consultas,
    para que se muestre aunque fallen los assets o la base.
--}}
@php
    use App\Support\Errors\ErrorPage;

    $exception ??= null;
    $status = ErrorPage::status($exception);
    $content = ErrorPage::content($status);
    $detail = ErrorPage::detail($exception, $status);
    $home = ErrorPage::home(request());
    $back = ErrorPage::backUrl(request(), $home['url']);
    $retry = ErrorPage::retryUrl(request(), $status, $back);
    $occurredAt = now()->format('d/m/Y h:i:s a');
    $path = '/'.ltrim(request()->path(), '/');

    /*
     * «Reportar a Sistemas»: solo con sesión iniciada y en errores que son fallas.
     * Sin sesión, sin ruta o sin CSRF (un 404 de URL inexistente no pasa por la
     * sesión) la página se muestra igual, solo que sin el botón.
     */
    $report = null;

    if (ErrorPage::reportable($status)) {
        try {
            $reporter = auth()->user();

            if ($reporter instanceof \App\Models\User) {
                $report = [
                    'endpoint' => route('error-report.store'),
                    'csrf' => csrf_token(),
                    'context' => \App\Support\Errors\ErrorReportContext::seal(
                        \App\Support\Errors\ErrorReportContext::build(request(), $exception, $status, $reporter)
                    ),
                    'max' => \App\Support\Errors\ErrorReportTicket::MAX_NOTE_LENGTH,
                ];
            }
        } catch (\Throwable) {
            $report = null;
        }

        if ($report !== null && blank($report['csrf'])) {
            $report = null;
        }
    }

    $icons = [
        'lock' => 'M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z',
        'search' => 'm21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z',
        'clock' => 'M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z',
        'user' => 'M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z',
        'wrench' => 'M11.42 15.17 17.25 21A2.652 2.652 0 0 0 21 17.25l-5.877-5.877M11.42 15.17l2.496-3.03c.317-.384.74-.626 1.208-.766M11.42 15.17l-4.655 5.653a2.548 2.548 0 1 1-3.586-3.586l6.837-5.63m5.108-.233c.55-.164 1.163-.188 1.743-.14a4.5 4.5 0 0 0 4.486-6.336l-3.276 3.277a3.004 3.004 0 0 1-2.25-2.25l3.276-3.276a4.5 4.5 0 0 0-6.336 4.486c.091 1.076-.071 2.264-.904 2.95l-.102.085',
        'alert' => 'M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z',
    ];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $status }} · {{ $content['title'] }} · IntegraCorp</title>
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}">
    <script>
        {{-- Mismo tema que eligió en Filament (localStorage «theme»); si no hay, el del sistema. --}}
        (function () {
            var theme = 'system';
            try { theme = localStorage.getItem('theme') || 'system'; } catch (e) {}
            var dark = theme === 'dark' || (theme === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);
            document.documentElement.classList.toggle('dark', dark);
        })();
    </script>
    <style>
        :root {
            --bg: #f3f6fa; --bg-accent: rgba(5, 47, 96, .10); --card: #ffffff; --border: #e3e8ef;
            --text: #0f172a; --muted: #5b6474; --soft: #f6f8fb; --brand: #052F60; --brand-hover: #0a417f;
            --focus: rgba(5, 47, 96, .35);
            --info: #2d89ca; --info-bg: rgba(45, 137, 202, .12);
            --warning: #b45309; --warning-bg: rgba(245, 158, 11, .15);
            --danger: #c2410c; --danger-bg: rgba(239, 68, 68, .12);
        }
        html.dark {
            --bg: #0b1220; --bg-accent: rgba(45, 137, 202, .14); --card: #111a2b; --border: #1f2a3d;
            --text: #e6ebf3; --muted: #9aa6b8; --soft: #0d1626; --brand: #2d89ca; --brand-hover: #4aa0dc;
            --focus: rgba(45, 137, 202, .45);
            --info: #5fb0e8; --info-bg: rgba(45, 137, 202, .18);
            --warning: #fbbf24; --warning-bg: rgba(245, 158, 11, .16);
            --danger: #fb923c; --danger-bg: rgba(239, 68, 68, .16);
        }
        * { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px 16px; color: var(--text);
            font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: radial-gradient(1100px 500px at 50% -10%, var(--bg-accent), transparent 70%), var(--bg);
        }
        .card {
            width: 100%; max-width: 560px; background: var(--card); border: 1px solid var(--border);
            border-radius: 20px; padding: 36px 32px 28px; box-shadow: 0 20px 50px -24px rgba(15, 23, 42, .35);
            animation: rise .35s ease-out both;
        }
        @keyframes rise { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: none; } }
        @media (prefers-reduced-motion: reduce) { .card { animation: none; } }
        .logo { display: block; height: 40px; width: auto; margin: 0 auto 28px; }
        .logo-dark, html.dark .logo-light { display: none; }
        html.dark .logo-dark { display: block; }
        .icon {
            width: 64px; height: 64px; margin: 0 auto 18px; border-radius: 18px;
            display: flex; align-items: center; justify-content: center;
        }
        .icon svg { width: 32px; height: 32px; }
        .tone-info { color: var(--info); background: var(--info-bg); }
        .tone-warning { color: var(--warning); background: var(--warning-bg); }
        .tone-danger { color: var(--danger); background: var(--danger-bg); }
        .code {
            display: block; width: max-content; margin: 0 auto 10px; padding: 3px 10px; border-radius: 999px;
            font-size: 12px; font-weight: 600; letter-spacing: .06em; color: var(--muted); background: var(--soft);
            border: 1px solid var(--border);
        }
        h1 { margin: 0 0 10px; font-size: 24px; line-height: 1.25; text-align: center; font-weight: 700; }
        .message { margin: 0 0 8px; font-size: 15px; line-height: 1.55; text-align: center; }
        .hint { margin: 0; font-size: 14px; line-height: 1.55; text-align: center; color: var(--muted); }
        .detail {
            margin: 18px 0 0; padding: 12px 14px; border-radius: 12px; font-size: 14px; line-height: 1.5;
            background: var(--soft); border: 1px solid var(--border);
        }
        .detail strong { display: block; font-size: 12px; color: var(--muted); font-weight: 600; margin-bottom: 2px; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 26px; }
        .btn {
            display: inline-flex; align-items: center; justify-content: center; gap: 8px; min-height: 42px;
            padding: 0 18px; border-radius: 12px; font: inherit; font-size: 14px; font-weight: 600;
            text-decoration: none; cursor: pointer; border: 1px solid var(--border); color: var(--text);
            background: var(--card); transition: background-color .15s, border-color .15s, transform .05s;
        }
        .btn:hover { background: var(--soft); }
        .btn:active { transform: translateY(1px); }
        .btn:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--focus); }
        .btn svg { width: 18px; height: 18px; flex: none; }
        .btn-primary { background: var(--brand); border-color: var(--brand); color: #fff; }
        .btn-primary:hover { background: var(--brand-hover); border-color: var(--brand-hover); }
        .report {
            margin-top: 22px; padding: 16px; border-radius: 14px; border: 1px dashed var(--border);
            background: var(--soft); text-align: center;
        }
        .report-title { margin: 0 0 4px; font-size: 14px; font-weight: 600; }
        .report-text { margin: 0 0 12px; font-size: 13px; line-height: 1.5; color: var(--muted); }
        .report form { text-align: left; }
        .report label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        .report label span { font-weight: 400; color: var(--muted); }
        .report textarea {
            width: 100%; min-height: 84px; resize: vertical; padding: 10px 12px; border-radius: 10px;
            border: 1px solid var(--border); background: var(--card); color: var(--text);
            font: inherit; font-size: 14px; line-height: 1.5;
        }
        .report textarea:focus { outline: none; border-color: var(--brand); box-shadow: 0 0 0 3px var(--focus); }
        .report-meta { display: flex; justify-content: space-between; gap: 8px; margin-top: 4px; font-size: 12px; color: var(--muted); }
        .report-error {
            margin: 10px 0 0; padding: 10px 12px; border-radius: 10px; font-size: 13px; line-height: 1.45;
            color: var(--danger); background: var(--danger-bg);
        }
        .report-buttons { display: flex; flex-wrap: wrap; gap: 8px; justify-content: flex-end; margin-top: 12px; }
        .report-done { display: flex; flex-direction: column; align-items: center; gap: 6px; }
        .report-done .icon { width: 44px; height: 44px; margin: 0 0 4px; border-radius: 12px; }
        .report-done .icon svg { width: 22px; height: 22px; }
        .tone-success { color: #15803d; background: rgba(34, 197, 94, .14); }
        html.dark .tone-success { color: #4ade80; background: rgba(34, 197, 94, .16); }
        .report-done p { margin: 0; font-size: 13px; line-height: 1.5; color: var(--muted); }
        .report-done p.report-title { font-size: 15px; color: var(--text); }
        .report-done .btn { margin-top: 8px; min-height: 36px; font-size: 13px; }
        .spinner {
            width: 16px; height: 16px; border-radius: 50%; border: 2px solid currentColor; border-right-color: transparent;
            animation: spin .7s linear infinite; display: none;
        }
        .is-sending .spinner { display: inline-block; }
        .is-sending .send-icon { display: none; }
        .btn[disabled] { opacity: .65; cursor: not-allowed; }
        @keyframes spin { to { transform: rotate(360deg); } }
        [hidden] { display: none !important; }
        .support { margin-top: 26px; border-top: 1px solid var(--border); padding-top: 16px; }
        .support summary {
            cursor: pointer; font-size: 13px; color: var(--muted); list-style: none; text-align: center;
            border-radius: 8px; padding: 4px;
        }
        .support summary::-webkit-details-marker { display: none; }
        .support summary:focus-visible { outline: none; box-shadow: 0 0 0 3px var(--focus); }
        .support dl {
            display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; margin: 14px 0 12px;
            font-size: 13px; padding: 12px 14px; border-radius: 12px; background: var(--soft);
            border: 1px solid var(--border);
        }
        .support dt { color: var(--muted); }
        .support dd { margin: 0; word-break: break-all; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .copy { display: flex; justify-content: center; }
        .copy .btn { min-height: 36px; font-size: 13px; }
        @media (max-width: 480px) {
            .card { padding: 28px 20px 22px; border-radius: 16px; }
            h1 { font-size: 21px; }
            .actions .btn { width: 100%; }
        }
    </style>
</head>
<body>
    <main class="card" role="main" aria-labelledby="error-title">
        <img class="logo logo-light" src="{{ asset('image/logoNewTDG.png') }}" alt="Tu Dr. Group">
        <img class="logo logo-dark" src="{{ asset('image/logoTDG.png') }}" alt="Tu Dr. Group">

        <div class="icon tone-{{ $content['tone'] }}" aria-hidden="true">
            <svg fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $icons[$content['icon']] }}"/></svg>
        </div>

        <span class="code">ERROR {{ $status }}</span>
        <h1 id="error-title">{{ $content['title'] }}</h1>
        <p class="message">{{ $content['message'] }}</p>
        <p class="hint">{{ $content['hint'] }}</p>

        @if ($detail)
            <div class="detail" role="note">
                <strong>Detalle</strong>
                {{ $detail }}
            </div>
        @endif

        <div class="actions">
            @if ($status !== 503)
                <a class="btn {{ $content['retry'] ? '' : 'btn-primary' }}" href="{{ $back }}" target="_top" data-error-back>
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
                    Volver atrás
                </a>
            @endif

            @if ($content['retry'])
                <a class="btn btn-primary" href="{{ $retry }}" target="_top">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99"/></svg>
                    {{ $status === 419 ? 'Recargar la página' : 'Reintentar' }}
                </a>
            @endif

            <a class="btn" href="{{ $home['url'] }}" target="_top">
                <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25"/></svg>
                {{ $home['label'] }}
            </a>
        </div>

        @if ($report)
            <section class="report" aria-labelledby="report-title" data-error-report
                     data-endpoint="{{ $report['endpoint'] }}" data-csrf="{{ $report['csrf'] }}" data-context="{{ $report['context'] }}">
                <div data-report-intro>
                    <p class="report-title" id="report-title">¿Quieres que Sistemas lo revise?</p>
                    <p class="report-text">Enviamos los detalles del error automáticamente y se crea un ticket para el equipo de Sistemas. No necesitas explicar nada técnico.</p>
                    <button type="button" class="btn" data-report-open>
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z"/></svg>
                        Reportar a Sistemas
                    </button>
                </div>

                <form data-report-form hidden novalidate>
                    <label for="report-note">¿Qué estabas haciendo? <span>(opcional)</span></label>
                    <textarea id="report-note" name="note" maxlength="{{ $report['max'] }}" rows="3"
                              placeholder="Ej.: quise abrir la orden de servicio desde el listado"></textarea>
                    <div class="report-meta">
                        <span>Ayuda a Sistemas a reproducir el problema.</span>
                        <span><span data-report-count>0</span>/{{ $report['max'] }}</span>
                    </div>
                    <p class="report-error" data-report-error role="alert" hidden></p>
                    <div class="report-buttons">
                        <button type="button" class="btn" data-report-cancel>Cancelar</button>
                        <button type="submit" class="btn btn-primary" data-report-submit>
                            <span class="spinner" aria-hidden="true"></span>
                            <svg class="send-icon" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5"/></svg>
                            <span data-report-label>Enviar reporte</span>
                        </button>
                    </div>
                </form>

                <div class="report-done" data-report-done role="status" aria-live="polite" hidden>
                    <div class="icon tone-success" aria-hidden="true">
                        <svg fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg>
                    </div>
                    <p class="report-title" data-report-done-title></p>
                    <p data-report-done-text></p>
                    <a class="btn" href="#" target="_top" data-report-link hidden>Ver ticket</a>
                </div>
            </section>
        @endif

        <details class="support">
            <summary>Datos para soporte</summary>
            <dl>
                <dt>Código</dt><dd>{{ $status }}</dd>
                <dt>Fecha</dt><dd>{{ $occurredAt }}</dd>
                <dt>Dirección</dt><dd>{{ $path }}</dd>
            </dl>
            <div class="copy">
                <button type="button" class="btn" data-error-copy
                        data-copy-text="Error {{ $status }} · {{ $occurredAt }} · {{ $path }}">
                    <svg fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184"/></svg>
                    <span>Copiar datos</span>
                </button>
            </div>
        </details>
    </main>

    <script>
        (function () {
            {{-- «Volver atrás» usa el historial real si existe; si no (pestaña nueva), sigue el enlace. --}}
            var back = document.querySelector('[data-error-back]');
            if (back) {
                back.addEventListener('click', function (event) {
                    try {
                        var target = window.top || window;
                        if (target.history.length > 1) {
                            event.preventDefault();
                            target.history.back();
                        }
                    } catch (e) {}
                });
            }

            {{-- «Reportar a Sistemas»: un envío a la vez, 20 s de límite y mensajes claros para cada falla. --}}
            var report = document.querySelector('[data-error-report]');
            if (report && window.fetch) {
                var intro = report.querySelector('[data-report-intro]');
                var form = report.querySelector('[data-report-form]');
                var note = report.querySelector('#report-note');
                var count = report.querySelector('[data-report-count]');
                var error = report.querySelector('[data-report-error]');
                var submit = report.querySelector('[data-report-submit]');
                var label = report.querySelector('[data-report-label]');
                var cancel = report.querySelector('[data-report-cancel]');
                var done = report.querySelector('[data-report-done]');
                var sending = false;

                var showError = function (message) {
                    error.textContent = message;
                    error.hidden = false;
                };
                var setSending = function (value) {
                    sending = value;
                    submit.disabled = value;
                    cancel.disabled = value;
                    note.readOnly = value;
                    submit.classList.toggle('is-sending', value);
                    label.textContent = value ? 'Enviando…' : 'Enviar reporte';
                };
                var messageFor = function (status, data) {
                    if (status === 401 || status === 419) {
                        return 'Tu sesión expiró. Recarga la página, inicia sesión y vuelve a enviar el reporte.';
                    }
                    if (status === 429) {
                        return 'Enviaste varios reportes seguidos. Espera un minuto e inténtalo de nuevo.';
                    }
                    if (data && data.message) {
                        return data.message;
                    }
                    if (data && data.errors) {
                        var first = Object.keys(data.errors)[0];
                        if (first && data.errors[first][0]) {
                            return data.errors[first][0];
                        }
                    }
                    return 'No pudimos enviar el reporte. Inténtalo de nuevo en unos minutos.';
                };

                report.querySelector('[data-report-open]').addEventListener('click', function () {
                    intro.hidden = true;
                    form.hidden = false;
                    count.textContent = note.value.length;
                    note.focus();
                });
                cancel.addEventListener('click', function () {
                    if (sending) { return; }
                    form.hidden = true;
                    error.hidden = true;
                    intro.hidden = false;
                });
                note.addEventListener('input', function () {
                    count.textContent = note.value.length;
                });

                form.addEventListener('submit', function (event) {
                    event.preventDefault();
                    if (sending) { return; }

                    error.hidden = true;
                    setSending(true);

                    var controller = window.AbortController ? new AbortController() : null;
                    var timer = controller ? setTimeout(function () { controller.abort(); }, 20000) : null;

                    fetch(report.getAttribute('data-endpoint'), {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': report.getAttribute('data-csrf')
                        },
                        body: JSON.stringify({ context: report.getAttribute('data-context'), note: note.value }),
                        signal: controller ? controller.signal : undefined
                    }).then(function (response) {
                        return response.json().catch(function () { return null; }).then(function (data) {
                            return { status: response.status, data: data };
                        });
                    }).then(function (result) {
                        if (timer) { clearTimeout(timer); }

                        if (result.status !== 200 || !result.data || !result.data.ok) {
                            setSending(false);
                            showError(messageFor(result.status, result.data));
                            return;
                        }

                        form.hidden = true;
                        done.hidden = false;
                        report.querySelector('[data-report-done-title]').textContent = result.data.created ? 'Reporte enviado' : 'Ya lo habías reportado';
                        report.querySelector('[data-report-done-text]').textContent = result.data.message;

                        var link = report.querySelector('[data-report-link]');
                        if (result.data.url) {
                            link.href = result.data.url;
                            link.hidden = false;
                        }
                    }).catch(function (failure) {
                        if (timer) { clearTimeout(timer); }
                        setSending(false);
                        showError(failure && failure.name === 'AbortError'
                            ? 'El servidor tardó demasiado en responder. Inténtalo de nuevo.'
                            : 'No hay conexión con el servidor. Revisa tu internet e inténtalo de nuevo.');
                    });
                });
            }

            var copy = document.querySelector('[data-error-copy]');
            if (copy) {
                copy.addEventListener('click', function () {
                    var label = copy.querySelector('span');
                    var text = copy.getAttribute('data-copy-text');
                    var done = function () {
                        label.textContent = '¡Copiado!';
                        setTimeout(function () { label.textContent = 'Copiar datos'; }, 2000);
                    };
                    if (navigator.clipboard && window.isSecureContext) {
                        navigator.clipboard.writeText(text).then(done, function () { label.textContent = 'Selecciona y copia el texto'; });
                    } else {
                        label.textContent = 'Selecciona y copia el texto';
                    }
                });
            }
        })();
    </script>
</body>
</html>
