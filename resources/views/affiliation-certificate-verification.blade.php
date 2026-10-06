@php
    $wa = static fn (string $number, string $text): string => 'https://wa.me/'.$number.'?text='.rawurlencode($text);
    $mediHref = $wa('584142009229', 'Hola, soy afiliado de Tu Dr en Casa y necesito orientación médica.');
    $infoHref = $wa('584242271498', 'Hola, soy afiliado de Tu Dr en Casa y quiero consultar los beneficios extra de mi plan. Mi cédula es: ');
    $groups = [
        ['title' => 'Coordinación de servicios médicos', 'phones' => [['0414 901 0352', '+584149010352'], ['0414 136 2847', '+584141362847']], 'emails' => ['operaciones@tudrencasa.com']],
        ['title' => 'Información sobre planes y coberturas', 'phones' => [['0424 222 0056', '+584242220056'], ['0424 227 1498', '+584242271498']], 'emails' => ['cotizaciones@tudrencasa.com', 'comercial@tudrencasa.com']],
        ['title' => 'Información sobre tarifas y pagos', 'phones' => [['0424 222 0056', '+584242220056'], ['0414 924 5606', '+584149245606']], 'emails' => ['administracion@tudrencasa.com']],
    ];
    $v = $verification;
    $tone = match (true) {
        $status !== 'found' => 'bad',
        ! $v['active'] => 'bad',
        ! $v['paid'] => 'warn',
        default => 'ok',
    };
    $toneColors = ['ok' => ['#E6F6EE', '#00703C', '#00A859'], 'warn' => ['#FFF4E0', '#8A5300', '#E39B17'], 'bad' => ['#FDECEC', '#A11B1B', '#E03A3A']][$tone];
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#F6F6F6">
    <meta name="robots" content="noindex, nofollow">
    <title>Carnet digital · Tu Dr en Casa</title>
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}">
    <style>
        :root { --ink: #0F2233; --soft: #35657D; --brand: #17335E; --sky: #26B4E8; --blue: #2D89CA; }
        * { box-sizing: border-box; }
        body { margin: 0; background: #F6F6F6; color: var(--ink); font-family: Verdana, Tahoma, -apple-system, BlinkMacSystemFont, sans-serif; -webkit-font-smoothing: antialiased; }
        a { color: inherit; }
        .display { font-family: Helvetica, "Helvetica Neue", Arial, sans-serif; font-weight: 700; }
        .wrap { position: relative; max-width: 480px; margin: 0 auto; padding: 16px 16px calc(32px + env(safe-area-inset-bottom)); display: flex; flex-direction: column; gap: 14px; }
        .glow { position: absolute; inset: 0 0 auto 0; height: 480px; pointer-events: none; overflow: hidden; }
        .glow span { position: absolute; border-radius: 999px; filter: blur(70px); }
        .hero { position: relative; overflow: hidden; height: 400px; border-radius: 28px; box-shadow: 0 18px 40px rgba(15,34,51,.18); background: #12293A; }
        .hero img.photo { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: 66% center; }
        .hero .shade { position: absolute; inset: 0; background: linear-gradient(180deg, rgba(15,34,51,.5), rgba(15,34,51,0) 35%, rgba(15,34,51,.75)); }
        .hero img.logo { position: absolute; top: 18px; left: 18px; height: 40px; }
        .hero .copy { position: absolute; left: 20px; right: 20px; bottom: 20px; color: #fff; }
        .hero h1 { margin: 0; font-size: 28px; line-height: 1.1; letter-spacing: -0.02em; }
        .hero p { margin: 6px 0 0; font-size: 14px; line-height: 1.45; }
        .card { background: #fff; border-radius: 22px; padding: 16px 18px; box-shadow: 0 6px 18px rgba(15,34,51,.06); }
        .verify-head { display: flex; align-items: center; gap: 10px; }
        .dot { width: 34px; height: 34px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-weight: 700; flex: none; }
        .kv { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 16px; margin-top: 14px; }
        .kv span { display: block; font-size: 10px; letter-spacing: .1em; text-transform: uppercase; color: #64748B; }
        .kv strong { display: block; margin-top: 3px; font-size: 13px; }
        .actions { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
        .action { min-height: 92px; border-radius: 22px; padding: 14px; display: flex; flex-direction: column; justify-content: space-between; color: #fff; text-decoration: none; }
        .action small { font-size: 13px; }
        .row-link { display: flex; align-items: center; gap: 14px; padding: 14px 16px; border-radius: 20px; background: #fff; text-decoration: none; box-shadow: 0 6px 18px rgba(15,34,51,.06); }
        .chip { width: 42px; height: 42px; border-radius: 999px; display: grid; place-items: center; color: #fff; font-weight: 700; flex: none; }
        .row-link .t { flex: 1; display: flex; flex-direction: column; gap: 2px; }
        .row-link .t b { font-size: 15px; }
        .row-link .t i { font-style: normal; font-size: 13px; line-height: 1.4; color: var(--soft); }
        details.contacts > summary { list-style: none; cursor: pointer; }
        details.contacts > summary::-webkit-details-marker { display: none; }
        .group { padding: 12px 0; border-top: 1px solid #EEF2F4; }
        .group h3 { margin: 0 0 6px; font-size: 14px; }
        .group a { display: inline-flex; min-height: 36px; align-items: center; margin-right: 12px; font-size: 14px; color: var(--brand); }
        .pharmacy { position: relative; overflow: hidden; border-radius: 24px; padding: 18px; background: linear-gradient(135deg, #0A7A82, #0A4348); color: #fff; display: flex; flex-direction: column; gap: 12px; }
        .pill { padding: 6px 11px; border-radius: 999px; background: #fff; color: #0A4348; font-size: 12px; }
        input.key { width: 100%; min-height: 46px; border: 1px solid #DCE5EA; border-radius: 12px; padding: 0 14px; font-size: 15px; letter-spacing: .05em; text-transform: uppercase; }
        button.go { margin-top: 10px; width: 100%; min-height: 46px; border: 0; border-radius: 12px; background: var(--brand); color: #fff; font-size: 15px; font-weight: 700; }
        footer { padding: 14px 4px 0; text-align: center; font-size: 12px; line-height: 1.55; color: #64748B; }
    </style>
</head>
<body>
<div class="glow" aria-hidden="true">
    <span style="top:-150px; left:-90px; width:380px; height:380px; background:#26B4E8; opacity:.35;"></span>
    <span style="top:20px; right:-130px; width:340px; height:340px; background:#2D89CA; opacity:.25;"></span>
</div>

<main class="wrap">
    <section class="hero">
        <img class="photo" src="{{ asset('image/certificado-afiliacion/carnet-digital-portada.jpg') }}" alt="Médica de Tu Doctor en Casa atendiendo a un niño en casa junto a su familia">
        <div class="shade"></div>
        <img class="logo" src="{{ asset('image/certificado-afiliacion/logo-tu-doctor-en-casa-blanco.png') }}" alt="Tu Doctor en Casa">
        <div class="copy">
            <h1 class="display">Estamos contigo, 24/7</h1>
            <p>¿Te sientes mal? Elige una opción y un médico te atiende.</p>
        </div>
    </section>

    <section class="card" aria-live="polite">
        @if ($status === 'empty')
            <div class="display" style="font-size:16px;">Verifica un certificado</div>
            <p style="margin:6px 0 12px; font-size:13px; line-height:1.5; color:var(--soft);">Escribe la clave impresa en el pie del certificado (empieza por CER-).</p>
            <form method="get" action="{{ route('affiliation-certificate.verify') }}">
                <label for="llave" class="sr-only" style="position:absolute; left:-9999px;">Clave de verificación</label>
                <input id="llave" class="key" name="llave" placeholder="CER-XXXX-XXXX-XXXX-XXXX" autocomplete="off" required>
                <button class="go" type="submit">Verificar</button>
            </form>
        @else
            <div class="verify-head">
                <span class="dot" style="background:{{ $toneColors[2] }};">{{ $tone === 'ok' ? '✓' : '!' }}</span>
                <div>
                    <div class="display" style="font-size:16px; color:{{ $toneColors[1] }};">
                        @if ($status === 'not_found')
                            No encontramos este certificado
                        @else
                            {{ $v['status_label'] }}
                        @endif
                    </div>
                    <div style="font-size:12px; color:#64748B; margin-top:2px;">Clave {{ $key }}</div>
                </div>
            </div>

            @if ($status === 'found')
                <div class="kv">
                    <div><span>Afiliación</span><strong>{{ $v['code'] }}</strong></div>
                    <div><span>Plan</span><strong>{{ $v['plan'] }}</strong></div>
                    <div><span>{{ $v['corporate'] ? 'Contratante' : 'Titular' }}</span><strong>{{ $v['holder'] }}</strong></div>
                    <div><span>Vigencia</span><strong>{{ $v['valid_from'] ?? '—' }} – {{ $v['valid_until'] ?? '—' }}</strong></div>
                    <div><span>Emitido</span><strong>{{ $v['issued_at'] }}</strong></div>
                    <div><span>Estado de pago</span><strong>{{ $v['paid'] ? 'Al día' : 'Pendiente' }}</strong></div>
                </div>
            @else
                <p style="margin:12px 0 0; font-size:13px; line-height:1.5; color:var(--soft);">Revisa que la clave esté completa. Si el problema continúa, escríbenos por InfoChat para confirmar tu afiliación.</p>
            @endif
        @endif
    </section>

    <div class="actions">
        <a class="action" href="tel:+584142009229" style="background:linear-gradient(135deg,#FF4D4D,#D61F1F);">
            <span class="display" style="font-size:18px;">Emergencia</span><small>Llamar ahora</small>
        </a>
        <a class="action" href="{{ $mediHref }}" target="_blank" rel="noopener" style="background:linear-gradient(135deg,#26B4E8,#2D89CA);">
            <span class="display" style="font-size:18px;">MediChat</span><small>WhatsApp</small>
        </a>
    </div>

    <div style="text-align:center; font-size:13px; color:var(--soft);">Ten tu cédula a mano al contactarnos</div>
    <a href="mailto:24H@tudrencasa.com" style="align-self:center; min-height:44px; display:inline-flex; align-items:center; gap:6px; margin-top:-8px; font-size:14px; color:var(--brand); text-decoration:none;">
        <span style="color:#FF0F0F;">✉</span> Emergencias por correo: <strong>24H@tudrencasa.com</strong>
    </a>

    <a class="row-link" href="{{ $infoHref }}" target="_blank" rel="noopener">
        <span class="chip" style="background:var(--brand);">✦</span>
        <span class="t"><b class="display">¿Qué incluye tu plan?</b><i>Consulta tus beneficios extra por InfoChat</i></span>
        <span style="color:var(--brand); font-size:20px;">›</span>
    </a>

    <details class="contacts card" style="padding:0;">
        <summary class="row-link" style="box-shadow:none;">
            <span class="chip" style="background:var(--blue);">☎</span>
            <span class="t"><b class="display">Más contactos</b><i>Servicios médicos, planes, tarifas y pagos</i></span>
            <span style="color:var(--blue); font-size:20px;">›</span>
        </summary>
        <div style="padding:0 18px 8px;">
            @foreach ($groups as $group)
                <div class="group">
                    <h3 class="display">{{ $group['title'] }}</h3>
                    @foreach ($group['phones'] as [$label, $tel])
                        <a href="tel:{{ $tel }}">{{ $label }}</a>
                    @endforeach
                    <div>
                        @foreach ($group['emails'] as $email)
                            <a href="mailto:{{ $email }}">{{ $email }}</a>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </details>

    <section class="pharmacy">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
            <span class="display" style="font-size:12px; letter-spacing:.16em; text-transform:uppercase;">Farmacia aliada</span>
            <span class="pill display">Con tu carnet</span>
        </div>
        <img src="{{ asset('image/certificado-afiliacion/logo-farmadoc.png') }}" alt="Farmadoc" style="height:40px; width:auto; max-width:85%; object-fit:contain; align-self:flex-start;">
        <p style="margin:0; font-size:14px; line-height:1.5;">Muestra este carnet en caja y recibe tu descuento en medicamentos.</p>
    </section>

    <footer>tudrencasa.com · RIF J-50358368-1</footer>
</main>
</body>
</html>
