@php
    use App\Support\Affiliations\Certificates\CarnetDigitalIcons as Icon;

    $c = $carnet;
    $v = $verification;
    $tone = match (true) {
        $status === 'empty' => 'neutral',
        $status !== 'found', ! $v['active'] => 'bad',
        ! $v['paid'] => 'warn',
        default => 'ok',
    };
    // [fondo del chip, color del título]
    $toneColors = [
        'neutral' => ['#17335E', '#0F2233'],
        'ok' => ['#00A859', '#007A40'],
        'warn' => ['#E39B17', '#8A5300'],
        'bad' => ['#E03A3A', '#A11B1B'],
    ][$tone];
    $single = count($c['actions']) === 1;
    $stepsForScript = array_map(static fn (array $step): array => ['title' => $step['title'], 'text' => $step['text'], 'color' => $step['color']], $c['steps']);
    $verifyIcon = match ($tone) {
        'neutral' => 'search',
        'ok' => 'shield-check',
        default => 'triangle-alert',
    };
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F6F6F6">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $c['title'] }}</title>
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}">
    <style>
        :root {
            --ink: #0F2233; --soft: #35657D; --muted: #64748B; --brand: #17335E; --blue: #2D89CA;
            --font-display: Helvetica, "Helvetica Neue", Arial, sans-serif;
            --font-body: Verdana, Tahoma, sans-serif;
            --ease-out: cubic-bezier(0.16, 1, 0.3, 1);
            --ease-standard: cubic-bezier(0.2, 0, 0.2, 1);
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; background: #F6F6F6; -webkit-tap-highlight-color: transparent; }
        body { color: var(--ink); font-family: var(--font-body); -webkit-font-smoothing: antialiased; }
        a { color: #2D89CA; text-decoration: none; }
        button { font: inherit; color: inherit; }
        svg { display: block; flex: 0 0 auto; }
        .display { font-family: var(--font-display); font-weight: 700; }
        .press { transition: transform 120ms var(--ease-standard); }
        .press:active { transform: scale(0.98); }
        .sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

        .page { position: relative; min-height: 100vh; overflow: hidden; }
        .glow { position: absolute; top: 0; left: 0; right: 0; height: 480px; pointer-events: none; overflow: hidden; }
        .glow span { position: absolute; border-radius: 999px; filter: blur(70px); }
        .wrap { position: relative; max-width: 480px; margin: 0 auto; padding: 16px 16px calc(110px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 14px; }

        .hero { position: relative; overflow: hidden; height: 420px; border-radius: 28px; box-shadow: 0 18px 40px rgba(15,34,51,.18); background: #12293A; }
        .hero .photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; display: block; }
        .hero .top-shade { position: absolute; inset: 0 0 auto 0; height: 120px; background: linear-gradient(180deg, rgba(15,34,51,.45), rgba(15,34,51,0)); pointer-events: none; }
        .hero .logo { position: absolute; top: 18px; left: 18px; height: 42px; width: auto; min-width: 120px; filter: brightness(0) invert(1); }
        .hero .copy { position: absolute; left: 12px; right: 12px; bottom: 12px; padding: 16px 18px; border-radius: 20px; background: rgba(15,34,51,.38); -webkit-backdrop-filter: blur(20px) saturate(160%); backdrop-filter: blur(20px) saturate(160%); border: 1px solid rgba(255,255,255,.35); box-shadow: inset 0 1px 0 rgba(255,255,255,.35), 0 8px 24px rgba(15,34,51,.2); display: flex; flex-direction: column; gap: 4px; }
        .hero h1 { margin: 0; font-size: 28px; line-height: 1.1; letter-spacing: -0.02em; color: #fff; text-wrap: balance; }
        .hero p { margin: 0; font-size: 14px; line-height: 1.45; color: #fff; text-wrap: pretty; }

        .actions { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 10px; }
        .action { min-height: 124px; padding: 14px; border-radius: 22px; background: #fff; border: 2px solid; display: flex; flex-direction: column; justify-content: space-between; gap: 12px; }
        .action-icon { width: 48px; height: 48px; border-radius: 999px; display: grid; place-items: center; }
        .action-text { display: flex; flex-direction: column; gap: 3px; }
        .action-title { font-size: clamp(13px, 3.6vw, 15px); line-height: 1.2; color: var(--ink); overflow-wrap: anywhere; }
        .action-hint { font-size: 12px; font-weight: 700; }
        .action.is-wide { min-height: 96px; padding: 16px 18px; flex-direction: row; align-items: center; justify-content: flex-start; gap: 14px; }
        .action.is-wide .action-icons { display: flex; gap: 6px; flex: 0 0 auto; }
        .action.is-wide .action-text { flex: 1; }
        .action.is-wide .action-title { font-size: 20px; line-height: 1.15; }
        .action.is-wide .action-hint { font-size: 13px; }

        .note { display: flex; align-items: center; justify-content: center; gap: 8px; padding: 2px 0 6px; font-size: 13px; color: var(--soft); text-align: center; }
        .mail { align-self: center; min-height: 44px; display: inline-flex; align-items: center; gap: 8px; margin-top: -8px; font-size: 14px; color: var(--brand); }

        .glass { padding: 16px 18px; border-radius: 22px; background: rgba(255,255,255,.7); border: 1px solid rgba(255,255,255,.95); box-shadow: 0 8px 24px rgba(15,34,51,.07), inset 0 1px 0 rgba(255,255,255,.95); }
        .verify-head { display: flex; align-items: center; gap: 12px; }
        .verify-title { font-size: 16px; line-height: 1.25; }
        .verify-key { margin-top: 2px; font-size: 12px; color: var(--muted); overflow-wrap: anywhere; }
        .kv { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 12px 16px; margin: 14px 0 0; }
        .kv dt { font-family: var(--font-display); font-weight: 700; font-size: 10px; letter-spacing: .12em; text-transform: uppercase; color: var(--muted); }
        .kv dd { margin: 3px 0 0; font-size: 13px; line-height: 1.35; color: var(--ink); overflow-wrap: anywhere; }
        .verify-text { margin: 12px 0 0; font-size: 13px; line-height: 1.5; color: var(--soft); }
        .key-input { width: 100%; min-height: 48px; margin-top: 12px; border: 1px solid #D7DBDE; border-radius: 14px; padding: 0 14px; background: #fff; color: var(--ink); font-size: 16px; letter-spacing: .05em; text-transform: uppercase; }
        .key-input:focus { outline: 2px solid var(--blue); outline-offset: 1px; }
        .key-submit { margin-top: 10px; width: 100%; min-height: 48px; border: 0; border-radius: 14px; background: var(--brand); color: #fff; font-size: 15px; cursor: pointer; }

        .chip { width: 42px; height: 42px; border-radius: 999px; display: grid; place-items: center; flex: 0 0 auto; }

        .steps { position: relative; overflow: hidden; padding: 18px; border-radius: 24px; background: rgba(23,51,94,.82); -webkit-backdrop-filter: blur(22px) saturate(180%); backdrop-filter: blur(22px) saturate(180%); border: 1px solid rgba(255,255,255,.28); box-shadow: 0 14px 30px rgba(23,51,94,.28), inset 0 1px 0 rgba(255,255,255,.35); display: flex; flex-direction: column; gap: 14px; }
        .steps .shine { position: absolute; inset: 0 0 55% 0; background: linear-gradient(180deg, rgba(255,255,255,.18), rgba(255,255,255,0)); pointer-events: none; }
        .steps .orb { position: absolute; border-radius: 999px; filter: blur(40px); pointer-events: none; }
        .steps-head { position: relative; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .steps-head .title { font-size: 17px; color: #fff; }
        .steps-head .count { font-size: 13px; color: #fff; padding: 5px 10px; border-radius: 999px; background: rgba(255,255,255,.14); }
        .segs { position: relative; display: grid; grid-template-columns: repeat(3, minmax(0,1fr)); gap: 6px; }
        .seg { height: 20px; padding: 8px 0; border: none; background: transparent; cursor: pointer; display: block; }
        .seg span { display: block; height: 4px; border-radius: 999px; background: rgba(255,255,255,.2); overflow: hidden; }
        .seg i { display: block; height: 100%; width: 0; background: #fff; border-radius: 999px; }
        .stage { position: relative; display: flex; align-items: center; gap: 14px; min-height: 64px; transition: opacity 200ms var(--ease-out), transform 200ms var(--ease-out); }
        .stage.is-out { opacity: 0; transform: translateY(6px); }
        .stage-icon { position: relative; width: 56px; height: 56px; flex: 0 0 auto; border-radius: 999px; display: grid; place-items: center; box-shadow: 0 0 0 0 rgba(255,255,255,.14); transition: box-shadow 600ms var(--ease-out), background 200ms var(--ease-standard); }
        .stage-icon.is-ring { box-shadow: 0 0 0 8px rgba(255,255,255,.14); }
        .stage-icon svg { position: absolute; inset: 0; margin: auto; }
        .stage-icon > span:not(.is-on) svg { opacity: 0; }
        .stage-text { display: flex; flex-direction: column; gap: 3px; }
        .stage-title { font-size: 16px; line-height: 1.25; color: #fff; }
        .stage-desc { font-size: 13px; line-height: 1.45; color: #D7DBDE; text-wrap: pretty; }

        .row-link { width: 100%; text-align: left; cursor: pointer; display: flex; align-items: center; gap: 14px; }
        .row-link .t { flex: 1; display: flex; flex-direction: column; gap: 2px; }
        .row-link .t b { font-size: 15px; color: var(--ink); }
        .row-link .t span { font-size: 13px; line-height: 1.4; color: var(--soft); }

        .pharmacy { position: relative; overflow: hidden; display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 22px; background: #0A4348; box-shadow: 0 12px 26px rgba(10,67,72,.26), inset 0 1px 0 rgba(255,255,255,.2); }
        .pharmacy .orb { position: absolute; top: -60px; right: -40px; width: 160px; height: 160px; border-radius: 999px; background: rgba(255,255,255,.12); filter: blur(28px); pointer-events: none; }
        .pharmacy .body { position: relative; flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 6px; }
        .pharmacy .eyebrow { font-size: 11px; letter-spacing: .16em; text-transform: uppercase; color: #E1E9E4; }
        .pharmacy .brand { height: 52px; width: auto; min-width: 120px; max-width: 100%; object-fit: contain; object-position: left center; align-self: flex-start; }
        .pharmacy .phone { display: inline-flex; align-items: center; gap: 6px; font-size: 14px; color: #fff; }
        .pharmacy .go { position: relative; width: 44px; height: 44px; flex: 0 0 auto; border-radius: 999px; background: #00A859; display: grid; place-items: center; box-shadow: 0 6px 14px rgba(0,168,89,.3); }

        footer { padding: 18px 4px 0; display: flex; flex-direction: column; align-items: center; gap: 6px; text-align: center; font-size: 12px; line-height: 1.55; color: var(--muted); }

        .bar { position: fixed; left: 50%; bottom: 0; z-index: 30; width: 100%; max-width: 480px; transform: translate(-50%, 110%); transition: transform 200ms var(--ease-out); padding: 10px 12px calc(10px + env(safe-area-inset-bottom)); background: rgba(255,255,255,.72); -webkit-backdrop-filter: blur(20px) saturate(180%); backdrop-filter: blur(20px) saturate(180%); border-top: 1px solid rgba(255,255,255,.9); box-shadow: 0 -8px 24px rgba(15,34,51,.08); display: grid; grid-template-columns: repeat(var(--bar-cols), minmax(0,1fr)); gap: 8px; }
        .bar.is-on { transform: translate(-50%, 0); }
        .bar a { min-height: 54px; padding: 0 8px; border-radius: 16px; background: #fff; border: 2px solid; color: var(--ink); display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; line-height: 1.15; text-align: center; }
        .bar.is-single a { gap: 10px; font-size: 16px; }

        .overlay { position: fixed; inset: 0; z-index: 40; background: rgba(23,51,94,.45); opacity: 0; pointer-events: none; transition: opacity 200ms var(--ease-standard); }
        .overlay.is-on { opacity: 1; pointer-events: auto; }
        .sheet { position: fixed; left: 50%; bottom: 0; z-index: 41; width: 100%; max-width: 480px; max-height: 88vh; overflow-y: auto; transform: translate(-50%, 105%); visibility: hidden; transition: transform 360ms var(--ease-out), visibility 0s linear 360ms; background: rgba(246,246,246,.92); -webkit-backdrop-filter: blur(24px) saturate(180%); backdrop-filter: blur(24px) saturate(180%); border-radius: 26px 26px 0 0; border-top: 1px solid rgba(255,255,255,.9); box-shadow: 0 -16px 40px rgba(15,34,51,.18); padding: 10px 16px calc(20px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 10px; }
        .sheet.is-on { transform: translate(-50%, 0); visibility: visible; transition: transform 360ms var(--ease-out), visibility 0s; }
        .sheet .grabber { width: 40px; height: 5px; border-radius: 999px; background: #D7DBDE; margin: 0 auto 4px; flex: 0 0 auto; }
        .sheet-head { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 0 4px 4px; }
        .sheet-head h2 { margin: 0; font-size: 20px; line-height: 1.2; color: var(--ink); }
        .sheet-close { width: 44px; height: 44px; flex: 0 0 auto; border-radius: 999px; border: none; background: #fff; display: grid; place-items: center; cursor: pointer; }
        .group { display: flex; flex-direction: column; gap: 12px; }
        .group-head { display: flex; align-items: center; gap: 12px; }
        .group-head span { font-size: 16px; line-height: 1.25; color: var(--ink); }
        .phones { display: grid; grid-template-columns: minmax(0,1fr) minmax(0,1fr); gap: 8px; }
        .phones a { min-height: 48px; padding: 0 10px; border-radius: 14px; background: #2D89CA; color: #fff; display: flex; align-items: center; justify-content: center; gap: 8px; font-size: 14px; white-space: nowrap; }
        .group .email { min-height: 44px; padding: 0 12px; border-radius: 14px; background: rgba(45,137,202,.08); color: var(--brand); display: flex; align-items: center; gap: 8px; font-size: 14px; overflow-wrap: anywhere; }

        a:focus-visible, button:focus-visible { outline: 2px solid var(--blue); outline-offset: 2px; }
        @media (prefers-reduced-motion: reduce) { * { transition: none !important; } }
    </style>
</head>
<body>
<div class="page">
    <div class="glow" aria-hidden="true">
        <span style="top:-150px; left:-90px; width:380px; height:380px; background:#26B4E8; opacity:.35;"></span>
        <span style="top:20px; right:-130px; width:340px; height:340px; background:#2D89CA; opacity:.25;"></span>
    </div>

    <main class="wrap">
        <section class="hero">
            <img class="photo" src="{{ asset($c['photo']) }}" alt="{{ $c['photo_alt'] }}" style="object-position: {{ $c['photo_position'] }};">
            <div class="top-shade"></div>
            <img class="logo" src="{{ asset('image/certificado-afiliacion/carnet-digital-logo.png') }}" alt="Tu Doctor en Casa">
            <div class="copy">
                <h1 class="display">Estamos contigo, 24/7</h1>
                <p>{{ $c['subtitle'] }}</p>
            </div>
        </section>

        @if ($single)
            @foreach ($c['actions'] as $action)
                <a class="action is-wide press" href="{{ $action['href'] }}" target="_blank" rel="noopener" style="border-color: {{ $action['color'] }}; box-shadow: 0 12px 26px {{ $action['shadow'] }};">
                    <span class="action-icons">
                        @foreach ($action['icons'] as $icon)
                            <span class="action-icon" style="background: {{ $action['color'] }};">{{ Icon::svg($icon, 22, $action['icon_color']) }}</span>
                        @endforeach
                    </span>
                    <span class="action-text">
                        <span class="action-title display">{{ $action['label'] }}</span>
                        <span class="action-hint" style="color: {{ $action['hint_color'] }};">{{ $action['hint'] }}</span>
                    </span>
                    {{ Icon::svg('chevron-right', 22, $action['color']) }}
                </a>
            @endforeach
        @else
            <div class="actions">
                @foreach ($c['actions'] as $action)
                    <a class="action press" href="{{ $action['href'] }}" target="_blank" rel="noopener" style="border-color: {{ $action['color'] }}; box-shadow: 0 12px 26px {{ $action['shadow'] }};">
                        <span class="action-icon" style="background: {{ $action['color'] }};">{{ Icon::svg($action['icons'][0], 24, $action['icon_color']) }}</span>
                        <span class="action-text">
                            <span class="action-title display">{{ $action['label'] }}</span>
                            <span class="action-hint" style="color: {{ $action['hint_color'] }};">{{ $action['hint'] }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="note">{{ Icon::svg('id-card', 16, '#35657D') }}Ten tu cédula a mano al contactarnos</div>
        <a class="mail" href="mailto:24H@tudrencasa.com">
            {{ Icon::svg('mail', 16, '#FF0F0F') }}
            <span>Emergencias por correo:</span>
            <strong>24H@tudrencasa.com</strong>
        </a>

        <section class="glass" aria-live="polite" aria-labelledby="verify-title">
            <div class="verify-head">
                <span class="chip" style="background: {{ $toneColors[0] }};">
                    {{ Icon::svg($verifyIcon, 18, '#FFFFFF') }}
                </span>
                <div style="min-width: 0;">
                    <div id="verify-title" class="verify-title display" style="color: {{ $toneColors[1] }};">
                        @if ($status === 'empty')
                            Verifica un certificado
                        @elseif ($status === 'not_found')
                            No encontramos este certificado
                        @else
                            {{ $v['status_label'] }}
                        @endif
                    </div>
                    @if ($status !== 'empty')
                        <div class="verify-key">Clave {{ $key }}</div>
                    @endif
                </div>
            </div>

            @if ($status === 'empty')
                <p class="verify-text">Escribe la clave impresa en el pie del certificado (empieza por CER-).</p>
                <form method="get" action="{{ route('affiliation-certificate.verify') }}">
                    <label for="llave" class="sr-only">Clave de verificación</label>
                    <input id="llave" class="key-input" name="llave" placeholder="CER-XXXX-XXXX-XXXX-XXXX" autocomplete="off" autocapitalize="characters" spellcheck="false" required>
                    <button class="key-submit display press" type="submit">Verificar</button>
                </form>
            @elseif ($status === 'found')
                <dl class="kv">
                    <div><dt>Afiliación</dt><dd>{{ $v['code'] }}</dd></div>
                    <div><dt>Plan</dt><dd>{{ $v['plan'] }}</dd></div>
                    <div><dt>{{ $v['corporate'] ? 'Contratante' : 'Titular' }}</dt><dd>{{ $v['holder'] }}</dd></div>
                    <div><dt>Vigencia</dt><dd>{{ $v['valid_from'] === null && $v['valid_until'] === null ? '—' : ($v['valid_from'] ?? '—').' – '.($v['valid_until'] ?? '—') }}</dd></div>
                    <div><dt>Emitido</dt><dd>{{ $v['issued_at'] }}</dd></div>
                    <div><dt>Estado de pago</dt><dd>{{ $v['paid'] ? 'Al día' : 'Pendiente' }}</dd></div>
                </dl>
            @else
                <p class="verify-text">Revisa que la clave esté completa. Si el problema continúa, escríbenos por InfoChat para confirmar tu afiliación.</p>
            @endif
        </section>

        <section class="steps" aria-label="Activa el servicio en 3 pasos" data-steps>
            <div class="shine" aria-hidden="true"></div>
            <div class="orb" aria-hidden="true" style="top:-70px; right:-50px; width:200px; height:200px; background:rgba(38,180,232,.35);"></div>
            <div class="orb" aria-hidden="true" style="bottom:-80px; left:-60px; width:180px; height:180px; background:rgba(45,137,202,.3);"></div>
            <div class="steps-head">
                <span class="title display">Activa el servicio en 3 pasos</span>
                <span class="count display"><span data-step-num>1</span>/3</span>
            </div>
            <div class="segs">
                @foreach ($c['steps'] as $i => $step)
                    <button type="button" class="seg" data-seg="{{ $i }}" aria-label="Paso {{ $i + 1 }}"><span><i></i></span></button>
                @endforeach
            </div>
            <div class="stage" data-stage>
                <span class="stage-icon" data-stage-icon style="background: {{ $c['steps'][0]['color'] }};">
                    @foreach ($c['steps'] as $i => $step)
                        <span data-step-icon="{{ $i }}" @class(['is-on' => $i === 0])>{{ Icon::svg($step['icon'], 24, '#FFFFFF') }}</span>
                    @endforeach
                </span>
                <span class="stage-text">
                    <span class="stage-title display" data-stage-title>{{ $c['steps'][0]['title'] }}</span>
                    <span class="stage-desc" data-stage-desc>{{ $c['steps'][0]['text'] }}</span>
                </span>
            </div>
        </section>

        <a class="glass row-link press" href="{{ $c['info_href'] }}" target="_blank" rel="noopener">
            <span class="chip" style="background: #17335E;">{{ Icon::svg('sparkles', 18, '#FFFFFF') }}</span>
            <span class="t"><b class="display">¿Qué incluye tu plan?</b><span>Consulta tus beneficios extra por InfoChat</span></span>
            {{ Icon::svg('chevron-right', 20, '#17335E') }}
        </a>

        <button type="button" class="glass row-link press" data-open-contacts aria-haspopup="dialog" aria-controls="contacts-sheet">
            <span class="chip" style="background: #2D89CA;">{{ Icon::svg('contact', 18, '#FFFFFF') }}</span>
            <span class="t"><b class="display">Más contactos</b><span>Servicios médicos, planes, tarifas y pagos</span></span>
            {{ Icon::svg('chevron-right', 20, '#2D89CA') }}
        </button>

        <a class="pharmacy press" href="{{ $c['pharmacy_href'] }}" target="_blank" rel="noopener">
            <div class="orb" aria-hidden="true"></div>
            <span class="body">
                <span class="eyebrow display">Recibe descuentos en</span>
                <img class="brand" src="{{ asset('image/certificado-afiliacion/logo-farmadoc.png') }}" alt="Farmadoc">
                <span class="phone display">{{ Icon::svg('message-circle', 15, '#FFFFFF') }}{{ $c['pharmacy_phone'] }}</span>
            </span>
            <span class="go">{{ Icon::svg('arrow-up-right', 18, '#FFFFFF') }}</span>
        </a>

        <footer><span>tudrencasa.com · RIF J-50358368-1</span></footer>
    </main>

    <nav @class(['bar', 'is-single' => $single]) data-bar aria-label="Contacto rápido" style="--bar-cols: {{ count($c['actions']) }};">
        @foreach ($c['actions'] as $action)
            <a class="display press" href="{{ $action['href'] }}" target="_blank" rel="noopener" tabindex="-1" style="border-color: {{ $action['color'] }};">
                @foreach ($action['icons'] as $icon)
                    {{ Icon::svg($icon, 20, $action['bar_icon_color']) }}
                @endforeach
                {{ $action['bar_label'] }}
            </a>
        @endforeach
    </nav>

    <div class="overlay" data-overlay></div>
    <div class="sheet" id="contacts-sheet" role="dialog" aria-modal="true" aria-labelledby="contacts-title" data-sheet>
        <div class="grabber" aria-hidden="true"></div>
        <div class="sheet-head">
            <h2 id="contacts-title" class="display">Más contactos</h2>
            <button type="button" class="sheet-close" data-close-contacts aria-label="Cerrar">{{ Icon::svg('x', 18, '#17335E') }}</button>
        </div>
        @foreach ($c['contact_groups'] as $group)
            <div class="glass group">
                <div class="group-head">
                    <span class="chip" style="background: #17335E;">{{ Icon::svg($group['icon'], 18, '#FFFFFF') }}</span>
                    <span class="display">{{ $group['title'] }}</span>
                </div>
                <div class="phones">
                    @foreach ($group['phones'] as $phone)
                        <a class="display press" href="{{ $phone['href'] }}">{{ Icon::svg('phone', 16, '#FFFFFF') }}{{ $phone['label'] }}</a>
                    @endforeach
                </div>
                @foreach ($group['emails'] as $email)
                    <a class="email press" href="mailto:{{ $email }}">{{ Icon::svg('mail', 16, '#2D89CA') }}{{ $email }}</a>
                @endforeach
            </div>
        @endforeach
    </div>
</div>

<script>
    (function () {
        var steps = @json($stepsForScript);
        var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        var root = document.querySelector('[data-steps]');
        var stage = root.querySelector('[data-stage]');
        var stageIcon = root.querySelector('[data-stage-icon]');
        var segs = Array.prototype.slice.call(root.querySelectorAll('[data-seg] i'));
        var current = 0, t1 = null, t2 = null, raf = null;

        function paint(i, fill) {
            segs.forEach(function (bar, k) {
                var done = k < i, now = k === i;
                bar.style.transition = now && fill && !reduce ? 'width 5000ms linear' : 'none';
                bar.style.width = done || (now && (fill || reduce)) ? '100%' : '0%';
            });
            stageIcon.classList.toggle('is-ring', fill);
        }

        function swap(i) {
            current = i;
            root.querySelector('[data-step-num]').textContent = i + 1;
            root.querySelector('[data-stage-title]').textContent = steps[i].title;
            root.querySelector('[data-stage-desc]').textContent = steps[i].text;
            stageIcon.style.background = steps[i].color;
            root.querySelectorAll('[data-step-icon]').forEach(function (el) {
                el.classList.toggle('is-on', Number(el.dataset.stepIcon) === i);
            });
            stage.classList.remove('is-out');
            paint(i, false);
            raf = requestAnimationFrame(function () { raf = requestAnimationFrame(function () { paint(i, true); }); });
            if (!reduce) { t2 = setTimeout(function () { go((i + 1) % steps.length); }, 5200); }
        }

        function go(i) {
            clearTimeout(t1); clearTimeout(t2); cancelAnimationFrame(raf);
            if (i === current && !stage.classList.contains('is-out')) { return swap(i); }
            stage.classList.add('is-out');
            t1 = setTimeout(function () { swap(i); }, 200);
        }

        root.querySelectorAll('[data-seg]').forEach(function (button) {
            button.addEventListener('click', function () { go(Number(button.dataset.seg)); });
        });
        swap(0);

        var bar = document.querySelector('[data-bar]');
        var sheet = document.querySelector('[data-sheet]');
        var overlay = document.querySelector('[data-overlay]');
        var opener = document.querySelector('[data-open-contacts]');
        var sheetOpen = false;

        function syncBar() {
            var on = window.scrollY > 260 && !sheetOpen;
            bar.classList.toggle('is-on', on);
            bar.querySelectorAll('a').forEach(function (a) { a.tabIndex = on ? 0 : -1; });
        }

        function setSheet(open) {
            sheetOpen = open;
            sheet.classList.toggle('is-on', open);
            overlay.classList.toggle('is-on', open);
            document.body.style.overflow = open ? 'hidden' : '';
            syncBar();
            if (open) { sheet.querySelector('[data-close-contacts]').focus(); } else { opener.focus(); }
        }

        window.addEventListener('scroll', syncBar, { passive: true });
        opener.addEventListener('click', function () { setSheet(true); });
        overlay.addEventListener('click', function () { setSheet(false); });
        sheet.querySelector('[data-close-contacts]').addEventListener('click', function () { setSheet(false); });
        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && sheetOpen) { setSheet(false); }
        });
        syncBar();
    })();
</script>
</body>
</html>
