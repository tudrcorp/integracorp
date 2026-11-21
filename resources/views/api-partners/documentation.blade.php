@use('App\Http\Middleware\AuthenticateApiPartner')
@use('App\Support\ApiPartners\NonCoveredMedicationFeed')
@use('App\Support\Operations\MedicationInventoryStockStatus')
@php
    $baseUrl = url('/api/v1/aliados');
    $statuses = NonCoveredMedicationFeed::STATUSES;
    $maxPerPage = NonCoveredMedicationFeed::MAX_PER_PAGE;
    $defaultPerPage = NonCoveredMedicationFeed::DEFAULT_PER_PAGE;
    $maxFailedAttempts = AuthenticateApiPartner::MAX_FAILED_ATTEMPTS_PER_MINUTE;
    $defaultRateLimit = 60;
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">
    <title>API de Medicamentos No Cubiertos · Tu Dr. Group</title>
    <link rel="icon" href="{{ asset('image/ico_Android_IOS.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Mono:wght@400;500&family=Space+Grotesk:wght@500;600&display=swap">
    <style>
        :root {
            --bg: #f6f8fb; --surface: #ffffff; --fg: #142235; --muted: #5a6b80; --line: #d9e1ec;
            --accent: #052f60; --accent-soft: #1f6fb2; --code-bg: #0f1d2e; --code-fg: #dbe7f5;
            --ok: #1b7f4b; --warn: #a86400; --bad: #b3261e;
            --font-display: "Space Grotesk", "Segoe UI", system-ui, sans-serif;
            --font-body: "IBM Plex Sans", "Segoe UI", system-ui, sans-serif;
            --font-mono: "IBM Plex Mono", ui-monospace, SFMono-Regular, Menlo, monospace;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #0d1622; --surface: #142131; --fg: #e3ebf5; --muted: #93a4b8; --line: #263649;
                --accent: #8cc4f0; --accent-soft: #6cb3ec; --code-bg: #08111b; --code-fg: #d3e2f2;
                --ok: #5fcf92; --warn: #e8a94a; --bad: #f08a83;
            }
        }
        * { box-sizing: border-box; }
        html { scroll-behavior: smooth; }
        body {
            margin: 0; background: var(--bg); color: var(--fg); font-family: var(--font-body); font-size: 15px; line-height: 1.6;
            padding-left: max(16px, env(safe-area-inset-left, 0px)); padding-right: max(16px, env(safe-area-inset-right, 0px));
        }
        .brand { max-width: 1080px; margin: 0 auto; padding-block: 20px 0; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
        .brand img { height: 40px; width: auto; }
        .brand .tag { font-family: var(--font-mono); font-size: 12px; color: var(--muted); }
        .logo-dark { display: none; }
        @media (prefers-color-scheme: dark) { .logo-light { display: none; } .logo-dark { display: inline; } }
        .wrap { max-width: 1080px; margin: 0 auto; padding-block: 28px 64px; display: grid; grid-template-columns: 200px minmax(0, 1fr); gap: 48px; }
        nav.toc { position: sticky; top: 24px; align-self: start; display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
        nav.toc a { color: var(--muted); text-decoration: none; }
        nav.toc a:hover, nav.toc a:focus-visible { color: var(--accent-soft); }
        nav.toc .label { font-size: 11px; text-transform: uppercase; letter-spacing: 0.08em; color: var(--muted); margin-bottom: 4px; }
        main { min-width: 0; display: flex; flex-direction: column; gap: 40px; }
        header.top { display: flex; flex-direction: column; gap: 10px; border-bottom: 1px solid var(--line); padding-bottom: 28px; }
        .eyebrow { font-family: var(--font-mono); font-size: 12px; color: var(--accent-soft); letter-spacing: 0.04em; }
        h1, h2 { font-family: var(--font-display); text-wrap: balance; margin: 0; line-height: 1.2; }
        h1 { font-size: 32px; font-weight: 600; color: var(--accent); }
        h2 { font-size: 22px; font-weight: 600; }
        p { margin: 0; max-width: 72ch; }
        a { color: var(--accent-soft); }
        section { display: flex; flex-direction: column; gap: 14px; scroll-margin-top: 24px; }
        .lead { color: var(--muted); font-size: 16px; }
        code { font-family: var(--font-mono); font-size: 0.9em; background: color-mix(in srgb, var(--accent-soft) 12%, transparent); padding: 1px 5px; border-radius: 4px; }
        .codeblock { position: relative; }
        pre { margin: 0; background: var(--code-bg); color: var(--code-fg); font-family: var(--font-mono); font-size: 13px; line-height: 1.55; padding: 16px 18px; border-radius: 8px; overflow-x: auto; }
        pre code { background: none; padding: 0; font-size: inherit; }
        .copy { position: absolute; top: 8px; right: 8px; font: 500 12px var(--font-body); padding: 4px 10px; border-radius: 6px; border: 1px solid color-mix(in srgb, var(--code-fg) 30%, transparent); background: var(--code-bg); color: var(--code-fg); cursor: pointer; }
        .copy:focus-visible, a:focus-visible { outline: 2px solid var(--accent-soft); outline-offset: 2px; }
        .endpoint { display: flex; flex-wrap: wrap; align-items: center; gap: 10px; background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 12px 14px; font-family: var(--font-mono); font-size: 14px; }
        .verb { font-weight: 600; color: #fff; background: var(--ok); padding: 2px 8px; border-radius: 4px; font-size: 12px; }
        @media (prefers-color-scheme: dark) { .verb { color: #0d1622; } }
        .endpoint .path { min-width: 0; overflow-wrap: anywhere; }
        .endpoint .desc { font-family: var(--font-body); color: var(--muted); font-size: 13px; flex-basis: 100%; }
        .tablebox { overflow-x: auto; border: 1px solid var(--line); border-radius: 8px; background: var(--surface); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th, td { text-align: left; padding: 10px 14px; border-bottom: 1px solid var(--line); vertical-align: top; }
        th { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: var(--muted); font-weight: 600; }
        tr:last-child td { border-bottom: 0; }
        td code { white-space: nowrap; }
        .num { font-variant-numeric: tabular-nums; font-family: var(--font-mono); }
        .states { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 12px; }
        .state { background: var(--surface); border: 1px solid var(--line); border-radius: 8px; padding: 14px; display: flex; flex-direction: column; gap: 6px; }
        .chip { align-self: flex-start; font-family: var(--font-mono); font-size: 12px; font-weight: 500; padding: 2px 8px; border-radius: 999px; border: 1px solid currentColor; }
        .chip.ok { color: var(--ok); } .chip.warn { color: var(--warn); } .chip.bad { color: var(--bad); }
        .state p { font-size: 14px; color: var(--muted); }
        .note { border-left: 3px solid var(--warn); padding: 10px 14px; background: color-mix(in srgb, var(--warn) 8%, var(--surface)); border-radius: 0 8px 8px 0; font-size: 14px; }
        ul, ol { margin: 0; padding-left: 20px; display: flex; flex-direction: column; gap: 6px; max-width: 72ch; }
        footer { max-width: 1080px; margin: 0 auto; padding-block: 0 32px; color: var(--muted); font-size: 12px; }
        @media (max-width: 820px) {
            .wrap { grid-template-columns: minmax(0, 1fr); gap: 24px; padding-block: 20px 48px; }
            nav.toc { position: static; flex-direction: row; flex-wrap: wrap; gap: 8px 14px; }
            nav.toc .label { flex-basis: 100%; }
            .states { grid-template-columns: minmax(0, 1fr); }
            h1 { font-size: 26px; }
        }
        @media (prefers-reduced-motion: reduce) { html { scroll-behavior: auto; } }
    </style>
</head>
<body>
    <div class="brand">
        <span>
            <img class="logo-light" src="{{ asset('image/logoNewTDG.png') }}" alt="Tu Dr. Group">
            <img class="logo-dark" src="{{ asset('image/logoTDG.png') }}" alt="Tu Dr. Group">
        </span>
        <span class="tag">IntegraCorp · API de aliados · v1</span>
    </div>

    <div class="wrap">
        <nav class="toc" aria-label="Contenido">
            <span class="label">Contenido</span>
            <a href="#autenticacion">Autenticación</a>
            <a href="#endpoints">Endpoints</a>
            <a href="#filtros">Filtros</a>
            <a href="#respuesta">Respuesta</a>
            <a href="#inventario">Estado de inventario</a>
            <a href="#sincronizacion">Sincronización</a>
            <a href="#pedidos-farmadoc">Pedidos a Farmadoc</a>
            <a href="#errores">Errores</a>
            <a href="#limites">Límites y seguridad</a>
        </nav>

        <main>
            <header class="top">
                <span class="eyebrow">Documentación para aliados</span>
                <h1>API de Medicamentos No Cubiertos</h1>
                <p class="lead">Consulta de solo lectura de los medicamentos no cubiertos que Operaciones de Tu Dr. Group está gestionando: datos del paciente, número de caso, el medicamento y su estado en el inventario de IntegraCorp.</p>
            </header>

            <section id="autenticacion">
                <h2>Autenticación</h2>
                <p>Cada petición lleva el token del aliado en el encabezado <code>Authorization</code>. TDG genera el token y lo entrega una sola vez por un canal seguro. Si se pierde o se expone, se genera uno nuevo y el anterior deja de funcionar en el acto.</p>
                <div class="codeblock">
                    <button class="copy" type="button" data-copy="auth">Copiar</button>
                    <pre id="auth"><code>Authorization: Bearer icp_xxxxxxxxxxxx.&lt;secreto&gt;
Accept: application/json</code></pre>
                </div>
                <ul>
                    <li>Solo HTTPS. Las peticiones por HTTP se rechazan.</li>
                    <li>El token va solo en el encabezado, nunca en la URL ni en parámetros.</li>
                    <li>El acceso depende únicamente del token: guárdenlo en un gestor de secretos, nunca en el código ni en repositorios.</li>
                </ul>
            </section>

            <section id="endpoints">
                <h2>Endpoints</h2>
                <p>Base: <code>{{ $baseUrl }}</code></p>
                <div class="endpoint"><span class="verb">GET</span><span class="path">/medicamentos-no-cubiertos</span><span class="desc">Listado paginado, con filtros.</span></div>
                <div class="endpoint"><span class="verb">GET</span><span class="path">/medicamentos-no-cubiertos/{id}</span><span class="desc">Un medicamento por su identificador. Devuelve 404 si no existe o ya no es un medicamento no cubierto en gestión.</span></div>
                <div class="endpoint"><span class="verb">GET</span><span class="path">/perfil</span><span class="desc">Datos del propio aliado: nombre, permisos, límite por minuto y vencimiento del acceso. Úsenlo para probar la conexión.</span></div>
                <div class="codeblock">
                    <button class="copy" type="button" data-copy="curl">Copiar</button>
                    <pre id="curl"><code>curl -s "{{ $baseUrl }}/medicamentos-no-cubiertos?estatus=PENDIENTE,EN%20GESTION&amp;por_pagina=50" \
  -H "Authorization: Bearer $TOKEN_INTEGRACORP" \
  -H "Accept: application/json"</code></pre>
                </div>
            </section>

            <section id="filtros">
                <h2>Filtros del listado</h2>
                <p>Todos son opcionales. Un parámetro que no esté en esta tabla se rechaza con 422.</p>
                <div class="tablebox">
                    <table>
                        <thead><tr><th>Parámetro</th><th>Formato</th><th>Qué hace</th></tr></thead>
                        <tbody>
                            <tr><td><code>estatus</code></td><td>Lista separada por comas</td><td>Estatus de la gestión del medicamento: @foreach ($statuses as $status)<code>{{ $status }}</code>{{ $loop->last ? '.' : ', ' }}@endforeach Sin filtro, vienen todos.</td></tr>
                            <tr><td><code>desde</code></td><td><code>AAAA-MM-DD</code></td><td>Registrados desde ese día (inclusive).</td></tr>
                            <tr><td><code>hasta</code></td><td><code>AAAA-MM-DD</code></td><td>Registrados hasta ese día (inclusive). No puede ser anterior a <code>desde</code>.</td></tr>
                            <tr><td><code>actualizado_desde</code></td><td>ISO 8601</td><td>Cambiados desde ese momento. Ordena del más antiguo al más reciente; ver <a href="#sincronizacion">Sincronización</a>.</td></tr>
                            <tr><td><code>caso</code></td><td>Texto</td><td>Número exacto del caso, por ejemplo <code>25043-01757</code>.</td></tr>
                            <tr><td><code>coordinacion</code></td><td>Entero</td><td>Identificador de la coordinación de servicio.</td></tr>
                            <tr><td><code>por_pagina</code></td><td>Entero, 1–{{ $maxPerPage }}</td><td>Registros por página. Por defecto {{ $defaultPerPage }}.</td></tr>
                            <tr><td><code>pagina</code></td><td>Entero ≥ 1</td><td>Página a consultar. Por defecto 1.</td></tr>
                        </tbody>
                    </table>
                </div>
                <p>Sin <code>actualizado_desde</code>, el listado viene del más reciente al más antiguo.</p>
            </section>

            <section id="respuesta">
                <h2>Respuesta</h2>
                <p>Ejemplo con datos ficticios. Los campos sin valor llegan como <code>null</code>; las fechas, en ISO 8601 con zona horaria de Venezuela.</p>
                <div class="codeblock">
                    <button class="copy" type="button" data-copy="sample">Copiar</button>
                    <pre id="sample"><code>{
  "data": [
    {
      "id": 3716,
      "caso": {
        "numero": "25043-01757",
        "coordinacion_id": 3088,
        "coordinacion_referencia": "REF-38448",
        "coordinacion_estatus": "PENDIENTE"
      },
      "paciente": {
        "nombre": "MARÍA EJEMPLO PÉREZ",
        "cedula": "V-00000000",
        "telefono": "0414-0000000",
        "estado": "TRUJILLO",
        "ciudad": "TRUJILLO"
      },
      "medicamento": {
        "nombre": "OMEPRAZOL CAP 40MG",
        "indicaciones": "TOMAR 1 CÁPSULA EN AYUNAS DURANTE 14 DÍAS",
        "cantidad": "14",
        "duracion": "14 días",
        "prioridad": "Estándar",
        "estatus_gestion": "PENDIENTE",
        "cubierto": false
      },
      "inventario": {
        "estado": "EXISTENCIA_MINIMA",
        "codigos_barras": ["7591585173881"]
      },
      "registrado_en": "2026-11-18T04:12:43-04:00",
      "actualizado_en": "2026-11-18T04:12:46-04:00"
    }
  ],
  "meta": {
    "pagina_actual": 1,
    "por_pagina": {{ $defaultPerPage }},
    "total": 2716,
    "ultima_pagina": 109,
    "estados_inventario": {!! json_encode(MedicationInventoryStockStatus::values()) !!},
    "generado_en": "2026-11-20T10:30:15-04:00"
  }
}</code></pre>
                </div>
                <p><code>cantidad</code> y <code>duracion</code> llegan como texto, tal como los registró el médico. <code>id</code> es estable: úsenlo como clave para no duplicar registros.</p>
            </section>

            <section id="inventario">
                <h2>Estado en el inventario de IntegraCorp</h2>
                <p>Cada medicamento indica si TDG lo tiene en su inventario. No se informan cantidades, solo el estado.</p>
                <div class="states">
                    <div class="state"><span class="chip ok">{{ MedicationInventoryStockStatus::AVAILABLE }}</span><p>Hay existencia por encima del stock mínimo.</p></div>
                    <div class="state"><span class="chip warn">{{ MedicationInventoryStockStatus::MINIMUM }}</span><p>Hay existencia, pero está en el stock mínimo o por debajo.</p></div>
                    <div class="state"><span class="chip bad">{{ MedicationInventoryStockStatus::MISSING }}</span><p>No está en el inventario o su existencia es cero.</p></div>
                </div>
                <p>El estado se calcula en el momento de la consulta. Para medicamentos recetados por nombre, IntegraCorp lo busca en su catálogo por el principio o la marca principal del nombre.</p>
                <h2 style="font-size:17px;">Códigos de barras</h2>
                <p><code>inventario.codigos_barras</code> trae los códigos de barras de los productos del inventario de IntegraCorp que coinciden con el medicamento recetado.</p>
                <ul>
                    <li>Puede traer <strong>varios</strong> códigos cuando el nombre recetado coincide con varias presentaciones o marcas.</li>
                    <li>Viene vacío (<code>[]</code>) cuando ningún producto de nuestro inventario coincide, que es lo normal en medicamentos no cubiertos.</li>
                    <li>Son referencias del inventario de TDG, no el producto exacto a despachar: confirmen la presentación con el nombre, la cantidad y las indicaciones antes de despachar.</li>
                </ul>
            </section>

            <section id="sincronizacion">
                <h2>Sincronización incremental</h2>
                <ol>
                    <li>Primera carga: recorran el listado completo página por página.</li>
                    <li>Guarden la hora en que empezó esa carga.</li>
                    <li>En cada consulta siguiente pidan <code>actualizado_desde</code> con esa hora, avancen por todas las páginas y guarden la nueva hora de inicio.</li>
                    <li>Inserten o actualicen por <code>id</code>. Un medicamento puede volver a aparecer cuando cambia su estatus.</li>
                </ol>
                <p class="note">El estado de inventario cambia sin que cambie <code>actualizado_en</code>. Si lo necesitan al día, consulten el medicamento puntual con <code>/medicamentos-no-cubiertos/{id}</code> antes de actuar.</p>
            </section>

            <section id="pedidos-farmadoc">
                <h2>Pedidos a Farmadoc: recepción en inventario</h2>
                <p>Solo para Farmadoc (permiso <code>{{ \App\Enums\ApiPartnerAbility::FarmadocOrdersRead->value }}</code>). Cuando un pedido que Farmadoc envió entra al inventario de IntegraCorp, su estatus pasa a <code>{{ \App\Models\FarmadocOrder::STATUS_RECEIVED }}</code> y queda disponible la <strong>nota de recibido y aprobado</strong> en PDF.</p>
                <div class="endpoint"><span class="verb">GET</span><span class="path">/pedidos-farmadoc</span><span class="desc">Listado paginado. Filtros: <code>estatus</code> (separados por coma), <code>actualizado_desde</code> (ISO 8601), <code>por_pagina</code> (máx. {{ \App\Support\ApiPartners\FarmadocOrderFeed::MAX_PER_PAGE }}), <code>pagina</code>.</span></div>
                <div class="endpoint"><span class="verb">GET</span><span class="path">/pedidos-farmadoc/{numero}</span><span class="desc">Un pedido por su número (el mismo <code>order_number</code> que IntegraCorp les envió, por ejemplo <code>IC-3088-20261120084525-2411</code>).</span></div>
                <div class="endpoint"><span class="verb">GET</span><span class="path">/pedidos-farmadoc/{numero}/nota-de-recibido</span><span class="desc">PDF de la nota de recibido y aprobado. 404 <code>sin_nota_recibido</code> si el pedido todavía no entró al inventario.</span></div>
                <div class="codeblock">
                    <button class="copy" type="button" data-copy="farmadoc-sample">Copiar</button>
                    <pre id="farmadoc-sample"><code>{
  "data": {
    "numero": "IC-3088-20261120084525-2411",
    "pedido_farmadoc_id": 8,
    "orden_servicio": "ORD-002",
    "estatus": "{{ \App\Models\FarmadocOrder::STATUS_RECEIVED }}",
    "estatus_etiqueta": "Registrado en inventario",
    "nota_entrega": "NEP-0000001",
    "aceptado_en": "2026-11-20T18:21:15-04:00",
    "recepcion": {
      "nota_recibido": "NRA-000001",
      "nota_recibido_url": "{{ $baseUrl }}/pedidos-farmadoc/IC-3088-20261120084525-2411/nota-de-recibido",
      "registrado_en": "2026-11-21T10:05:00-04:00",
      "recibido_por": "Analista de Operaciones",
      "ubicacion": "DIAGNO-MOVIL - MENE GRANDE",
      "completo": false,
      "observaciones": "Faltó una unidad de LOREX.",
      "medicamentos": [
        { "nombre": "LOREX 1MG/ML JBE 60ML", "codigo_barras": "7592803000750", "sku": "CSV-7592803000750", "cantidad_pedida": 2, "cantidad_recibida": 1 }
      ]
    },
    "enviado_en": "2026-11-20T08:45:25-04:00",
    "actualizado_en": "2026-11-21T10:05:00-04:00"
  }
}</code></pre>
                </div>
                <ul>
                    <li><code>recepcion</code> viene en <code>null</code> mientras el pedido no haya entrado al inventario.</li>
                    <li>Para enterarse de las recepciones nuevas, consulten <code>/pedidos-farmadoc?estatus={{ \App\Models\FarmadocOrder::STATUS_RECEIVED }}&amp;actualizado_desde=…</code> con la hora de su última consulta.</li>
                    <li>No incluye datos del paciente ni montos.</li>
                </ul>
            </section>

            <section id="errores">
                <h2>Errores</h2>
                <p>Los errores llegan en JSON con un código estable y un mensaje legible: <code>{"error": "no_autenticado", "mensaje": "…"}</code>. Los de validación (422) traen <code>message</code> y <code>errors</code> por campo.</p>
                <div class="tablebox">
                    <table>
                        <thead><tr><th>HTTP</th><th>error</th><th>Qué hacer</th></tr></thead>
                        <tbody>
                            <tr><td class="num">401</td><td><code>no_autenticado</code></td><td>Falta el token o no es válido. Revisen el encabezado o pidan uno nuevo.</td></tr>
                            <tr><td class="num">403</td><td><code>https_requerido</code></td><td>Usen HTTPS.</td></tr>
                            <tr><td class="num">403</td><td><code>acceso_inactivo</code> / <code>acceso_vencido</code></td><td>El acceso fue desactivado o venció. Contacten a TDG.</td></tr>
                            <tr><td class="num">403</td><td><code>sin_permiso</code></td><td>El aliado no tiene habilitado este recurso.</td></tr>
                            <tr><td class="num">404</td><td><code>no_encontrado</code> / <code>ruta_no_encontrada</code></td><td>El medicamento, el pedido o la ruta no existen.</td></tr>
                            <tr><td class="num">422</td><td>validación</td><td>Algún filtro tiene un valor no válido. El detalle viene en <code>errors</code>.</td></tr>
                            <tr><td class="num">429</td><td><code>limite_excedido</code> / <code>demasiados_intentos</code></td><td>Esperen los segundos que indica <code>Retry-After</code>.</td></tr>
                            <tr><td class="num">503</td><td><code>servicio_no_disponible</code></td><td>Reintenten más tarde.</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="limites">
                <h2>Límites y seguridad</h2>
                <ul>
                    <li>Límite por aliado: {{ $defaultRateLimit }} peticiones por minuto, salvo que TDG acuerde otro. Cada respuesta trae <code>X-RateLimit-Limit</code> y <code>X-RateLimit-Remaining</code>.</li>
                    <li>Más de {{ $maxFailedAttempts }} intentos fallidos por minuto desde una misma IP bloquean esa IP temporalmente.</li>
                    <li>Cada consulta queda registrada: aliado, IP de origen, filtros, cantidad de registros y hora.</li>
                    <li>Si sospechan que el token se expuso, avisen a TDG para rotarlo de inmediato.</li>
                    <li>Los datos son de pacientes. Guárdenlos cifrados, con acceso restringido, y úsenlos solo para gestionar los medicamentos.</li>
                </ul>
            </section>
        </main>
    </div>

    <footer>Tu Dr. Group · IntegraCorp. Esta documentación no contiene datos de pacientes ni credenciales.</footer>

    <script>
        document.querySelectorAll('.copy').forEach(function (button) {
            button.addEventListener('click', function () {
                var target = document.getElementById(button.dataset.copy);
                var text = target ? target.innerText : '';
                var reset = function () { setTimeout(function () { button.textContent = 'Copiar'; }, 1800); };
                var fallback = function () {
                    var range = document.createRange();
                    range.selectNodeContents(target);
                    var selection = window.getSelection();
                    selection.removeAllRanges();
                    selection.addRange(range);
                    button.textContent = 'Seleccionado';
                    reset();
                };

                if (! navigator.clipboard) {
                    fallback();

                    return;
                }

                navigator.clipboard.writeText(text).then(function () { button.textContent = 'Copiado'; reset(); }, fallback);
            });
        });
    </script>
</body>
</html>
