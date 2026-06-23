<x-filament-panels::page>
    @push('styles')
        <style>
            .qr-generator {
                --qr-bg: #f3f7fb;
                --qr-bg-soft: #e8f0f8;
                --qr-panel: #ffffff;
                --qr-panel-soft: #f8fbff;
                --qr-line: #d7e1ee;
                --qr-text: #152334;
                --qr-muted: #5a6f86;
                --qr-accent: #0284c7;
                --qr-accent-2: #0ea5e9;
                --qr-success: #16a34a;
                --qr-shadow: 0 12px 40px rgb(2 8 23 / 9%);
            }

            .dark .qr-generator {
                --qr-bg: rgb(15 23 42 / 70%);
                --qr-bg-soft: rgb(30 41 59 / 60%);
                --qr-panel: rgb(15 23 42);
                --qr-panel-soft: rgb(30 41 59 / 45%);
                --qr-line: rgb(51 65 85);
                --qr-text: rgb(241 245 249);
                --qr-muted: rgb(148 163 184);
                --qr-accent: rgb(56 189 248);
                --qr-accent-2: rgb(14 165 233);
                --qr-success: rgb(74 222 128);
                --qr-shadow: 0 20px 42px rgb(2 6 23 / 38%);
            }

            .qr-generator * {
                box-sizing: border-box;
            }

            .qr-generator .page-shell {
                max-width: 2000px;
                margin: 0 auto;
                padding: 12px;
                border: 1px solid var(--qr-line);
                border-radius: 18px;
                background: linear-gradient(140deg, var(--qr-bg), var(--qr-bg-soft));
                box-shadow: var(--qr-shadow);
            }

            .qr-generator .hero {
                border: 1px solid var(--qr-line);
                border-radius: 14px;
                background: linear-gradient(140deg, rgb(255 255 255 / 92%), rgb(243 249 255 / 90%));
                padding: 16px 18px;
                margin-bottom: 14px;
            }

            .dark .qr-generator .hero {
                background: linear-gradient(140deg, rgb(15 23 42 / 82%), rgb(30 41 59 / 65%));
            }

            .qr-generator .hero h2 {
                margin: 0 0 8px;
                font-size: 24px;
                line-height: 1.2;
                font-weight: 800;
                letter-spacing: -0.02em;
                color: var(--qr-text);
            }

            .qr-generator .hero p {
                margin: 0;
                color: var(--qr-muted);
                font-size: 13px;
                line-height: 1.5;
            }

            .qr-generator .page {
                display: grid;
                grid-template-columns: minmax(0, 1fr) 420px;
                gap: 16px;
            }

            .qr-generator .left,
            .qr-generator .right {
                background: var(--qr-panel);
                border: 1px solid var(--qr-line);
                border-radius: 14px;
                box-shadow: inset 0 1px 0 rgb(255 255 255 / 55%);
            }

            .qr-generator .left {
                padding: 16px;
                background: linear-gradient(160deg, var(--qr-panel), var(--qr-panel-soft));
            }

            .qr-generator details {
                border: 1px solid var(--qr-line);
                border-radius: 12px;
                margin-bottom: 12px;
                background: rgb(255 255 255 / 82%);
                overflow: hidden;
                transition: border-color 160ms ease, box-shadow 160ms ease, transform 160ms ease;
            }

            .dark .qr-generator details {
                background: rgb(15 23 42 / 65%);
            }

            .qr-generator details[open] {
                border-color: rgb(2 132 199 / 35%);
                box-shadow: 0 8px 20px rgb(15 23 42 / 8%);
                transform: translateY(-1px);
            }

            .qr-generator summary {
                cursor: pointer;
                list-style: none;
                padding: 13px 14px;
                font-weight: 700;
                font-size: 13px;
                letter-spacing: 0.04em;
                color: var(--qr-text);
                user-select: none;
                display: flex;
                align-items: center;
                justify-content: space-between;
                background: rgb(244 249 255 / 90%);
                border-bottom: 1px solid var(--qr-line);
            }

            .dark .qr-generator summary {
                background: rgb(30 41 59 / 80%);
            }

            .qr-generator summary::-webkit-details-marker {
                display: none;
            }

            .qr-generator summary span {
                display: inline-flex;
                width: 20px;
                height: 20px;
                align-items: center;
                justify-content: center;
                border: 1px solid var(--qr-line);
                border-radius: 9999px;
                color: var(--qr-muted);
                font-size: 14px;
                line-height: 1;
                transition: transform 180ms ease;
            }

            .qr-generator details[open] summary span {
                transform: rotate(45deg);
            }

            .qr-generator .block {
                padding: 14px;
                background: rgb(252 254 255 / 70%);
            }

            .dark .qr-generator .block {
                background: rgb(15 23 42 / 45%);
            }

            .qr-generator .field {
                margin-bottom: 12px;
            }

            .qr-generator .field label {
                display: block;
                font-size: 13px;
                margin-bottom: 6px;
                color: var(--qr-muted);
                font-weight: 600;
            }

            .qr-generator .field label::after {
                content: "";
                display: inline-block;
                width: 4px;
                height: 4px;
                border-radius: 9999px;
                background: rgb(14 165 233 / 35%);
                margin-left: 6px;
                vertical-align: middle;
            }

            .qr-generator input[type="text"],
            .qr-generator input[type="email"],
            .qr-generator input[type="tel"],
            .qr-generator input[type="color"],
            .qr-generator input[type="range"],
            .qr-generator select {
                width: 100%;
            }

            .qr-generator input[type="text"],
            .qr-generator input[type="email"],
            .qr-generator input[type="tel"],
            .qr-generator select {
                height: 42px;
                border: 1px solid var(--qr-line);
                border-radius: 9px;
                padding: 0 12px;
                background: var(--qr-panel);
                color: var(--qr-text);
                transition: border-color 150ms ease, box-shadow 150ms ease;
            }

            .qr-generator input[type="text"]:focus,
            .qr-generator input[type="email"]:focus,
            .qr-generator input[type="tel"]:focus,
            .qr-generator select:focus,
            .qr-generator input[type="file"]:focus {
                outline: none;
                border-color: rgb(2 132 199 / 55%);
                box-shadow: 0 0 0 3px rgb(14 165 233 / 15%);
            }

            .qr-generator input[type="file"] {
                width: 100%;
                border: 1px dashed var(--qr-line);
                border-radius: 10px;
                padding: 11px;
                background: rgb(248 252 255 / 85%);
                color: var(--qr-text);
            }

            .qr-generator .inline {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 10px;
            }

            .qr-generator .inline-3 {
                display: grid;
                grid-template-columns: repeat(3, 1fr);
                gap: 10px;
            }

            .qr-generator .switch {
                display: flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                color: var(--qr-muted);
                border: 1px solid var(--qr-line);
                border-radius: 9px;
                padding: 9px 10px;
                background: rgb(243 248 255 / 75%);
            }

            .dark .qr-generator .switch {
                background: rgb(30 41 59 / 45%);
            }

            .qr-generator .right {
                padding: 16px;
                position: sticky;
                top: 16px;
                height: fit-content;
                background: linear-gradient(160deg, var(--qr-panel), var(--qr-panel-soft));
            }

            .qr-generator .preview {
                width: 100%;
                aspect-ratio: 1;
                display: grid;
                place-items: center;
                border: 1px solid var(--qr-line);
                border-radius: 12px;
                background: #fff;
                margin-bottom: 16px;
                overflow: hidden;
                box-shadow: inset 0 0 0 3px rgb(15 23 42 / 3%);
            }

            .dark .qr-generator .preview {
                background: rgb(15 23 42);
            }

            .qr-generator .preview-head {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 10px;
                gap: 10px;
            }

            .qr-generator .preview-head h3 {
                margin: 0;
                font-size: 14px;
                font-weight: 700;
                color: var(--qr-text);
            }

            .qr-generator .chip {
                font-size: 11px;
                font-weight: 700;
                color: var(--qr-accent);
                background: rgb(14 165 233 / 11%);
                border: 1px solid rgb(14 165 233 / 22%);
                border-radius: 9999px;
                padding: 5px 9px;
                letter-spacing: 0.03em;
            }

            .qr-generator .size-row {
                margin-bottom: 14px;
                border: 1px solid var(--qr-line);
                border-radius: 10px;
                padding: 12px;
                background: rgb(248 251 255 / 80%);
            }

            .dark .qr-generator .size-row {
                background: rgb(30 41 59 / 38%);
            }

            .qr-generator .size-meta {
                display: flex;
                justify-content: space-between;
                font-size: 12px;
                color: var(--qr-muted);
                margin-top: 6px;
            }

            .qr-generator .actions {
                display: grid;
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .qr-generator button {
                height: 44px;
                border: none;
                border-radius: 10px;
                font-weight: 700;
                cursor: pointer;
                color: #fff;
                transition: transform 140ms ease, box-shadow 140ms ease, filter 140ms ease;
            }

            .qr-generator button:hover {
                transform: translateY(-1px);
                box-shadow: 0 8px 20px rgb(15 23 42 / 18%);
            }

            .qr-generator button:active {
                transform: translateY(0);
            }

            .qr-generator .btn-download {
                background: linear-gradient(135deg, #0ea5e9, #0284c7);
            }

            .qr-generator .btn-alt {
                background: linear-gradient(135deg, #475569, #334155);
                width: 100%;
                margin-top: 10px;
            }

            .qr-generator .badge {
                font-size: 12px;
                color: var(--qr-muted);
                margin-top: 13px;
                line-height: 1.4;
                border: 1px dashed var(--qr-line);
                border-radius: 10px;
                padding: 10px;
                background: rgb(248 251 255 / 70%);
            }

            .dark .qr-generator .badge {
                background: rgb(30 41 59 / 38%);
            }

            .qr-generator .muted {
                color: var(--qr-muted);
                font-size: 12px;
            }

            @media (max-width: 980px) {
                .qr-generator .page-shell {
                    padding: 10px;
                    border-radius: 14px;
                }

                .qr-generator .hero {
                    padding: 14px;
                }

                .qr-generator .hero h2 {
                    font-size: 20px;
                }

                .qr-generator .page {
                    grid-template-columns: 1fr;
                }

                .qr-generator .right {
                    position: static;
                }
            }

            @media (max-width: 640px) {
                .qr-generator .inline,
                .qr-generator .inline-3,
                .qr-generator .actions {
                    grid-template-columns: 1fr;
                }

                .qr-generator .size-meta {
                    gap: 8px;
                    flex-wrap: wrap;
                }
            }
        </style>
    @endpush

    <div class="qr-generator w-full">
        <div class="page-shell">
            <header class="hero">
                <h2>Generador QR personalizado</h2>
                <p>Disena, visualiza y descarga codigos QR de alta calidad con logo, color y estilo visual. El flujo sigue siendo el mismo, solo mejoramos la experiencia visual.</p>
            </header>

            <main class="page">
                <section class="left">
                    <details open>
                    <summary>INGRESE CONTENIDO <span>+</span></summary>
                    <div class="block">
                        <div class="field">
                            <label for="qrData">Tu URL o texto</label>
                            <input id="qrData" type="text" value="https://integracorp.tudrgroup.com/" placeholder="https://tusitio.com">
                        </div>
                        <div class="inline">
                            <div class="field">
                                <label for="emailData">Correo (opcional)</label>
                                <input id="emailData" type="email" placeholder="nombre@dominio.com">
                            </div>
                            <div class="field">
                                <label for="phoneData">Telefono (opcional)</label>
                                <input id="phoneData" type="tel" placeholder="+58...">
                            </div>
                        </div>
                        <div class="switch">
                            <input id="useContactPayload" type="checkbox">
                            <label for="useContactPayload">Usar formato contacto (vCard simple)</label>
                        </div>
                    </div>
                </details>

                    <details>
                    <summary>ESTABLECER COLORES <span>+</span></summary>
                    <div class="block">
                        <div class="inline">
                            <div class="field">
                                <label for="dotColor">Color frontal</label>
                                <input id="dotColor" type="color" value="#000000">
                            </div>
                            <div class="field">
                                <label for="bgColor">Color de fondo</label>
                                <input id="bgColor" type="color" value="#ffffff">
                            </div>
                        </div>
                        <p class="muted">Tip: alto contraste = mejor lectura para camaras.</p>
                    </div>
                </details>

                    <details>
                    <summary>AGREGAR IMAGEN DE LOGOTIPO <span>+</span></summary>
                    <div class="block">
                        <div class="field">
                            <label for="logoInput">Subir logo</label>
                            <input id="logoInput" type="file" accept="image/png,image/jpeg,image/webp,image/svg+xml">
                        </div>
                        <div class="inline">
                            <div class="field">
                                <label for="logoSize">Tamano logo</label>
                                <input id="logoSize" type="range" min="0.1" max="0.5" value="0.28" step="0.01">
                            </div>
                            <div class="field">
                                <label for="logoMargin">Margen logo</label>
                                <input id="logoMargin" type="range" min="0" max="20" value="4" step="1">
                            </div>
                        </div>
                        <div class="switch">
                            <input id="hideBgDots" type="checkbox" checked>
                            <label for="hideBgDots">Eliminar puntos detras del logo</label>
                        </div>
                        <button id="removeLogoBtn" class="btn-alt" type="button">Quitar logo</button>
                    </div>
                </details>

                    <details>
                    <summary>PERSONALIZAR DISENO <span>+</span></summary>
                    <div class="block">
                        <div class="inline-3">
                            <div class="field">
                                <label for="dotStyle">Forma del cuerpo</label>
                                <select id="dotStyle">
                                    <option value="square">Square</option>
                                    <option value="dots" selected>Dots</option>
                                    <option value="rounded">Rounded</option>
                                    <option value="classy">Classy</option>
                                    <option value="classy-rounded">Classy rounded</option>
                                    <option value="extra-rounded">Extra rounded</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="cornerSquareStyle">Marco de ojo</label>
                                <select id="cornerSquareStyle">
                                    <option value="square">Square</option>
                                    <option value="dot">Dot</option>
                                    <option value="extra-rounded" selected>Extra rounded</option>
                                </select>
                            </div>
                            <div class="field">
                                <label for="cornerDotStyle">Centro de ojo</label>
                                <select id="cornerDotStyle">
                                    <option value="square">Square</option>
                                    <option value="dot" selected>Dot</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    </details>
                </section>

                <aside class="right">
                    <div class="preview-head">
                        <h3>Vista previa en vivo</h3>
                        <span class="chip">QR READY</span>
                    </div>

                    <div id="qrPreview" class="preview"></div>

                    <div class="size-row">
                        <input id="qrSize" type="range" min="220" max="1200" value="700" step="10">
                        <div class="size-meta">
                            <span>Baja calidad</span>
                            <strong id="sizeLabel">700 x 700 px</strong>
                            <span>Alta calidad</span>
                        </div>
                    </div>

                    <div class="actions">
                        <button id="downloadPngBtn" class="btn-download" type="button">Descargar PNG</button>
                    </div>
                    <button id="downloadSvgBtn" class="btn-alt" type="button">Descargar SVG</button>
                    <div class="field" style="margin-top: 12px;">
                        <label for="associationPlanIndividual">Asociar QR a tarjeta — afiliados individuales</label>
                        <select id="associationPlanIndividual">
                            <option value="">Seleccione un plan</option>
                            @foreach ($this->getIndividualQrPlanOptions() as $planId => $planLabel)
                                <option value="{{ $planId }}">{{ $planLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button id="downloadAndAssociateIndividualBtn" class="btn-download" type="button">
                        Aplicar QR a tarjetas individuales
                    </button>

                    <div class="field" style="margin-top: 16px;">
                        <label for="associationPlanCorporate">Asociar QR a tarjeta — afiliados corporativos</label>
                        <select id="associationPlanCorporate">
                            <option value="">Seleccione un plan</option>
                            @foreach ($this->getCorporateQrPlanOptions() as $planId => $planLabel)
                                <option value="{{ $planId }}">{{ $planLabel }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button id="downloadAndAssociateCorporateBtn" class="btn-download" type="button">
                        Aplicar QR a tarjetas corporativas
                    </button>

                    <p class="badge">
                        Generador local de QR. Si agregas logo o formas muy extremas, valida con dos moviles antes de usarlo en produccion.
                    </p>
                </aside>
            </main>
        </div>
    </div>

    @push('scripts')
        <script src="https://cdn.jsdelivr.net/npm/qr-code-styling@1.8.0/lib/qr-code-styling.js"></script>
        <script>
            (function () {
                function bootQrGenerator() {
                    const qrPreview = document.getElementById('qrPreview');

                    if (!qrPreview || typeof QRCodeStyling === 'undefined') {
                        return;
                    }

                    if (qrPreview.dataset.qrBooted === '1') {
                        return;
                    }

                    qrPreview.dataset.qrBooted = '1';
                    qrPreview.innerHTML = '';

                const elements = {
                    qrData: document.getElementById('qrData'),
                    emailData: document.getElementById('emailData'),
                    phoneData: document.getElementById('phoneData'),
                    useContactPayload: document.getElementById('useContactPayload'),
                    dotColor: document.getElementById('dotColor'),
                    bgColor: document.getElementById('bgColor'),
                    logoInput: document.getElementById('logoInput'),
                    logoSize: document.getElementById('logoSize'),
                    logoMargin: document.getElementById('logoMargin'),
                    hideBgDots: document.getElementById('hideBgDots'),
                    removeLogoBtn: document.getElementById('removeLogoBtn'),
                    dotStyle: document.getElementById('dotStyle'),
                    cornerSquareStyle: document.getElementById('cornerSquareStyle'),
                    cornerDotStyle: document.getElementById('cornerDotStyle'),
                    qrSize: document.getElementById('qrSize'),
                    sizeLabel: document.getElementById('sizeLabel'),
                    qrPreview: document.getElementById('qrPreview'),
                    downloadPngBtn: document.getElementById('downloadPngBtn'),
                    downloadSvgBtn: document.getElementById('downloadSvgBtn'),
                    associationPlanIndividual: document.getElementById('associationPlanIndividual'),
                    associationPlanCorporate: document.getElementById('associationPlanCorporate'),
                    downloadAndAssociateIndividualBtn: document.getElementById('downloadAndAssociateIndividualBtn'),
                    downloadAndAssociateCorporateBtn: document.getElementById('downloadAndAssociateCorporateBtn'),
                };

                let logoDataUrl = null;
                const associateIndividualRoute = @js(route('business.affiliation-tarjeta-qr.associate-plan'));
                const associateCorporateRoute = @js(route('business.affiliation-corporate-tarjeta-qr.associate-plan'));
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @js(csrf_token());

                function buildPayload() {
                    if (!elements.useContactPayload.checked) {
                        return elements.qrData.value.trim() || 'https://integracorp.tudrgroup.com/';
                    }

                    const name = elements.qrData.value.trim() || 'Integracorp';
                    const email = elements.emailData.value.trim();
                    const phone = elements.phoneData.value.trim();

                    return [
                        'BEGIN:VCARD',
                        'VERSION:3.0',
                        'N:;' + name + ';;;',
                        email ? 'EMAIL:' + email : '',
                        phone ? 'TEL:' + phone : '',
                        'END:VCARD',
                    ].filter(Boolean).join('\n');
                }

                function buildQrOptions(renderType) {
                    const size = Number(elements.qrSize.value);

                    return {
                        width: size,
                        height: size,
                        type: renderType,
                        data: buildPayload(),
                        image: logoDataUrl,
                        dotsOptions: {
                            color: elements.dotColor.value,
                            type: elements.dotStyle.value,
                        },
                        backgroundOptions: {
                            color: elements.bgColor.value,
                        },
                        cornersSquareOptions: {
                            type: elements.cornerSquareStyle.value,
                        },
                        cornersDotOptions: {
                            type: elements.cornerDotStyle.value,
                        },
                        imageOptions: {
                            crossOrigin: 'anonymous',
                            margin: Number(elements.logoMargin.value),
                            imageSize: Number(elements.logoSize.value),
                            hideBackgroundDots: elements.hideBgDots.checked,
                        },
                    };
                }

                function waitForNextPaint() {
                    return new Promise((resolve) => {
                        requestAnimationFrame(() => requestAnimationFrame(resolve));
                    });
                }

                const qrCode = new QRCodeStyling(buildQrOptions('canvas'));

                qrCode.append(elements.qrPreview);

                function updateSizeLabel() {
                    const size = Number(elements.qrSize.value);
                    elements.sizeLabel.textContent = size + ' x ' + size + ' px';
                }

                async function renderQr() {
                    updateSizeLabel();
                    qrCode.update(buildQrOptions('canvas'));
                    await waitForNextPaint();
                }

                function readImage(file) {
                    return new Promise((resolve, reject) => {
                        const reader = new FileReader();
                        reader.onload = () => resolve(String(reader.result));
                        reader.onerror = reject;
                        reader.readAsDataURL(file);
                    });
                }

                elements.logoInput.addEventListener('change', async (event) => {
                    const [file] = event.target.files || [];
                    if (!file) {
                        return;
                    }

                    logoDataUrl = await readImage(file);
                    renderQr();
                });

                elements.removeLogoBtn.addEventListener('click', () => {
                    logoDataUrl = null;
                    elements.logoInput.value = '';
                    renderQr();
                });

                [
                    elements.qrData,
                    elements.emailData,
                    elements.phoneData,
                    elements.useContactPayload,
                    elements.dotColor,
                    elements.bgColor,
                    elements.logoSize,
                    elements.logoMargin,
                    elements.hideBgDots,
                    elements.dotStyle,
                    elements.cornerSquareStyle,
                    elements.cornerDotStyle,
                    elements.qrSize,
                ].forEach((el) => {
                    el.addEventListener('input', renderQr);
                    el.addEventListener('change', renderQr);
                });

                async function downloadQr(extension) {
                    const blob = await buildQrBlob(extension);
                    const payloadSlug = buildPayload()
                        .replace(/[^a-z0-9]+/gi, '-')
                        .replace(/^-+|-+$/g, '')
                        .slice(0, 48) || 'mi-qr';

                    triggerDownload(blob, payloadSlug + '.' + extension);
                }

                async function buildQrBlob(extension) {
                    await renderQr();

                    const renderType = extension === 'svg' ? 'svg' : 'canvas';
                    const qrForDownload = new QRCodeStyling(buildQrOptions(renderType));
                    const qrRawData = await qrForDownload.getRawData(extension);

                    if (!qrRawData) {
                        throw new Error('No fue posible generar el archivo.');
                    }

                    return qrRawData instanceof Blob
                        ? qrRawData
                        : new Blob([qrRawData], {
                            type: extension === 'svg' ? 'image/svg+xml;charset=utf-8' : 'image/png',
                        });
                }

                function triggerDownload(blob, fileName) {
                    const url = URL.createObjectURL(blob);
                    const anchor = document.createElement('a');
                    anchor.href = url;
                    anchor.download = fileName;
                    document.body.appendChild(anchor);
                    anchor.click();
                    document.body.removeChild(anchor);
                    URL.revokeObjectURL(url);
                }

                async function associatePlanQr(planId, pngBlob, associateRoute) {
                    const formData = new FormData();
                    formData.append('plan_id', planId);
                    formData.append('qr_image', pngBlob, 'qr-plan-' + planId + '.png');

                    const response = await fetch(associateRoute, {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': csrfToken,
                            'Accept': 'application/json',
                        },
                        body: formData,
                        credentials: 'same-origin',
                    });

                    if (!response.ok) {
                        throw new Error('No se pudo asociar el QR al plan seleccionado.');
                    }

                    return response.json();
                }

                elements.downloadPngBtn.addEventListener('click', async () => {
                    try {
                        await downloadQr('png');
                    } catch (error) {
                        // eslint-disable-next-line no-alert
                        alert('No se pudo descargar el QR en PNG. Intenta nuevamente.');
                        console.error(error);
                    }
                });

                elements.downloadSvgBtn.addEventListener('click', async () => {
                    try {
                        await downloadQr('svg');
                    } catch (error) {
                        // eslint-disable-next-line no-alert
                        alert('No se pudo descargar el QR en SVG. Intenta nuevamente.');
                        console.error(error);
                    }
                });
                elements.downloadAndAssociateIndividualBtn.addEventListener('click', async () => {
                    try {
                        const pngBlob = await buildQrBlob('png');
                        const selectedPlanId = elements.associationPlanIndividual.value.trim();
                        if (selectedPlanId === '') {
                            // eslint-disable-next-line no-alert
                            alert('Selecciona un plan para asociar el QR a afiliados individuales.');
                            return;
                        }
                        const selectedLabel = elements.associationPlanIndividual.options[elements.associationPlanIndividual.selectedIndex]?.text || selectedPlanId;
                        await associatePlanQr(selectedPlanId, pngBlob, associateIndividualRoute);
                        // eslint-disable-next-line no-alert
                        alert('Listo: el QR fue asociado correctamente al plan ' + selectedLabel + ' para afiliados individuales.');
                    } catch (error) {
                        // eslint-disable-next-line no-alert
                        alert('No se pudo completar la asociacion del QR para afiliados individuales. Intenta nuevamente.');
                        console.error(error);
                    }
                });

                elements.downloadAndAssociateCorporateBtn.addEventListener('click', async () => {
                    try {
                        const pngBlob = await buildQrBlob('png');
                        const selectedPlanId = elements.associationPlanCorporate.value.trim();
                        if (selectedPlanId === '') {
                            // eslint-disable-next-line no-alert
                            alert('Selecciona un plan para asociar el QR a afiliados corporativos.');
                            return;
                        }
                        const selectedLabel = elements.associationPlanCorporate.options[elements.associationPlanCorporate.selectedIndex]?.text || selectedPlanId;
                        await associatePlanQr(selectedPlanId, pngBlob, associateCorporateRoute);
                        // eslint-disable-next-line no-alert
                        alert('Listo: el QR fue asociado correctamente al plan ' + selectedLabel + ' para afiliados corporativos.');
                    } catch (error) {
                        // eslint-disable-next-line no-alert
                        alert('No se pudo completar la asociacion del QR para afiliados corporativos. Intenta nuevamente.');
                        console.error(error);
                    }
                });

                void renderQr();
                }

                function scheduleBoot() {
                    bootQrGenerator();
                    setTimeout(bootQrGenerator, 50);
                    setTimeout(bootQrGenerator, 200);
                }

                document.addEventListener('livewire:navigated', scheduleBoot);

                if (document.readyState === 'loading') {
                    document.addEventListener('DOMContentLoaded', scheduleBoot);
                } else {
                    scheduleBoot();
                }
            })();
        </script>
    @endpush
</x-filament-panels::page>
