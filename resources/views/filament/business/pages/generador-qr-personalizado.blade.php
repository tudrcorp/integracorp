<x-filament-panels::page>
    @php
        $studioConfig = [
            'libUrl' => asset('vendor/qr-code-styling/1.8.0/qr-code-styling.js'),
            'brandLogoUrl' => $brandLogoUrl,
            'logoMax' => $logoMax,
            'minContrast' => $minContrast,
        ];
    @endphp

    <style>
        .qrc { --qrc-bg: #f3f4f6; --qrc-panel: #ffffff; --qrc-soft: #f9fafb; --qrc-line: #e5e7eb; --qrc-line-strong: #d1d5db; --qrc-text: #111827; --qrc-muted: #4b5563; --qrc-faint: #6b7280; --qrc-primary: #3b4252; --qrc-primary-text: #ffffff; --qrc-info-bg: #eff6ff; --qrc-info-line: #bfdbfe; --qrc-info-text: #1e3a8a; --qrc-warn-bg: #fff7ed; --qrc-warn-line: #fed7aa; --qrc-warn-text: #7c2d12; --qrc-ok: #16a34a; --qrc-warn: #d97706; --qrc-fail: #dc2626; --qrc-shadow: 0 24px 48px rgb(17 24 39 / 18%); color: var(--qrc-text); display: flex; flex-direction: column; gap: 20px; }
        .dark .qrc { --qrc-bg: rgb(15 23 42); --qrc-panel: rgb(17 24 39); --qrc-soft: rgb(30 41 59 / 60%); --qrc-line: rgb(51 65 85); --qrc-line-strong: rgb(71 85 105); --qrc-text: rgb(241 245 249); --qrc-muted: rgb(203 213 225); --qrc-faint: rgb(148 163 184); --qrc-primary: rgb(226 232 240); --qrc-primary-text: rgb(15 23 42); --qrc-info-bg: rgb(30 58 138 / 25%); --qrc-info-line: rgb(59 130 246 / 40%); --qrc-info-text: rgb(191 219 254); --qrc-warn-bg: rgb(124 45 18 / 25%); --qrc-warn-line: rgb(251 146 60 / 40%); --qrc-warn-text: rgb(254 215 170); --qrc-shadow: 0 24px 48px rgb(0 0 0 / 45%); }
        .qrc * { box-sizing: border-box; }
        .qrc-lead { margin: 0; font-size: 14px; line-height: 1.5; color: var(--qrc-muted); max-width: 760px; }
        .qrc-tabs { display: inline-flex; flex-wrap: wrap; gap: 4px; padding: 4px; border-radius: 14px; background: var(--qrc-line); align-self: flex-start; }
        .qrc-tab { min-height: 40px; padding: 0 16px; border: 0; border-radius: 10px; background: transparent; font-size: 14px; font-weight: 600; color: var(--qrc-muted); cursor: pointer; }
        .qrc-tab[aria-selected="true"] { background: var(--qrc-panel); color: var(--qrc-text); box-shadow: 0 1px 2px rgb(17 24 39 / 8%); }
        .qrc-stats { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
        .qrc-stat { background: var(--qrc-panel); border: 1px solid var(--qrc-line); border-radius: 16px; padding: 14px 16px; display: flex; flex-direction: column; gap: 4px; }
        .qrc-stat span { font-size: 13px; color: var(--qrc-faint); }
        .qrc-stat strong { font-size: 24px; font-weight: 700; font-variant-numeric: tabular-nums; }
        .qrc-note { display: flex; gap: 10px; align-items: flex-start; padding: 12px 14px; border-radius: 14px; font-size: 14px; line-height: 1.5; }
        .qrc-note svg { flex: 0 0 auto; margin-top: 2px; }
        .qrc-note.is-info { background: var(--qrc-info-bg); border: 1px solid var(--qrc-info-line); color: var(--qrc-info-text); }
        .qrc-note.is-warn { background: var(--qrc-warn-bg); border: 1px solid var(--qrc-warn-line); color: var(--qrc-warn-text); }
        .qrc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .qrc-card { background: var(--qrc-panel); border: 1px solid var(--qrc-line); border-radius: 18px; }
        .qrc-table-wrap { overflow-x: auto; }
        .qrc-table { width: 100%; min-width: 1080px; border-collapse: collapse; }
        .qrc-table th { padding: 10px 16px; background: var(--qrc-soft); font-size: 12px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: var(--qrc-faint); text-align: left; }
        .qrc-table td { padding: 14px 16px; border-top: 1px solid var(--qrc-line); vertical-align: middle; font-size: 14px; }
        .qrc-thumb { width: 56px; height: 56px; border-radius: 10px; border: 1px solid var(--qrc-line); background: #fff; display: grid; place-items: center; overflow: hidden; }
        .qrc-thumb img { width: 100%; height: 100%; object-fit: contain; }
        .qrc-thumb.is-empty { background: var(--qrc-soft); color: var(--qrc-faint); font-size: 11px; text-align: center; padding: 4px; }
        .qrc-chip { display: inline-flex; align-items: center; font-size: 12px; font-weight: 600; padding: 3px 9px; border-radius: 999px; background: var(--qrc-line); color: var(--qrc-muted); white-space: nowrap; }
        .qrc-chip.is-page { background: #ddf3ef; color: #115e59; }
        .qrc-chip.is-url { background: #e0ecff; color: #1e40af; }
        .qrc-chip.is-doc { background: #ede9fe; color: #5b21b6; }
        .qrc-chip.is-legacy { background: #fef3c7; color: #92400e; }
        .dark .qrc-chip.is-page { background: rgb(17 94 89 / 35%); color: rgb(153 246 228); }
        .dark .qrc-chip.is-url { background: rgb(30 64 175 / 35%); color: rgb(191 219 254); }
        .dark .qrc-chip.is-doc { background: rgb(91 33 182 / 35%); color: rgb(221 214 254); }
        .dark .qrc-chip.is-legacy { background: rgb(146 64 14 / 35%); color: rgb(253 230 138); }
        .qrc-cell-stack { display: flex; flex-direction: column; gap: 5px; min-width: 0; }
        .qrc-ellipsis { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 340px; }
        .qrc-sub { font-size: 12px; color: var(--qrc-faint); }
        .qrc-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
        .qrc-btn { min-height: 40px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; padding: 0 14px; border-radius: 10px; border: 1px solid var(--qrc-line-strong); background: var(--qrc-panel); color: var(--qrc-text); font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; white-space: nowrap; }
        .qrc-btn:hover { background: var(--qrc-soft); }
        .qrc-btn:disabled { opacity: .55; cursor: not-allowed; }
        .qrc-btn.is-primary { background: var(--qrc-primary); border-color: var(--qrc-primary); color: var(--qrc-primary-text); }
        .qrc-btn.is-primary:hover { filter: brightness(1.08); }
        .qrc-btn.is-danger { color: var(--qrc-fail); border-color: color-mix(in srgb, var(--qrc-fail) 40%, transparent); }
        .qrc-btn.is-lg { min-height: 44px; padding: 0 18px; border-radius: 12px; }
        .qrc-btn:focus-visible, .qrc-tab:focus-visible, .qrc-option:focus-visible, .qrc-pill:focus-visible { outline: 2px solid #2563eb; outline-offset: 2px; }
        .qrc-field { display: flex; flex-direction: column; gap: 6px; }
        .qrc-field label, .qrc-label { font-size: 13px; font-weight: 600; color: var(--qrc-muted); }
        .qrc-input, .qrc-select { min-height: 44px; width: 100%; padding: 0 12px; border: 1px solid var(--qrc-line-strong); border-radius: 10px; background: var(--qrc-panel); color: var(--qrc-text); font-size: 14px; }
        .qrc-input:focus, .qrc-select:focus { outline: 2px solid #2563eb; outline-offset: 0; border-color: transparent; }
        .qrc-error { font-size: 13px; color: var(--qrc-fail); }
        .qrc-hint { font-size: 12px; color: var(--qrc-faint); line-height: 1.4; }
        .qrc-two { display: flex; flex-wrap: wrap; gap: 20px; align-items: flex-start; }
        .qrc-two > * { flex: 1 1 460px; min-width: 0; }
        .qrc-pad { padding: 20px; display: flex; flex-direction: column; gap: 14px; }
        .qrc-h2 { margin: 0; font-size: 17px; font-weight: 700; }
        .qrc-h3 { margin: 0; font-size: 15px; font-weight: 700; }
        .qrc-drop { display: flex; flex-direction: column; align-items: center; gap: 8px; padding: 24px 16px; border: 2px dashed var(--qrc-line-strong); border-radius: 14px; background: var(--qrc-soft); text-align: center; cursor: pointer; }
        .qrc-drop strong { font-size: 15px; }
        .qrc-review { display: flex; flex-direction: column; gap: 8px; padding: 14px; border-radius: 12px; background: var(--qrc-soft); border: 1px solid var(--qrc-line); }
        .qrc-check { display: flex; gap: 10px; align-items: flex-start; font-size: 14px; line-height: 1.4; }
        .qrc-dot { width: 20px; height: 20px; flex: 0 0 auto; border-radius: 999px; display: grid; place-items: center; color: #fff; font-size: 12px; font-weight: 700; }
        .qrc-dot.is-ok { background: var(--qrc-ok); }
        .qrc-dot.is-warn { background: var(--qrc-warn); }
        .qrc-dot.is-error, .qrc-dot.is-fail { background: var(--qrc-fail); }
        .qrc-dot.is-pending, .qrc-dot.is-unavailable { background: var(--qrc-faint); }
        .qrc-page { display: grid; grid-template-columns: minmax(0, 1fr); gap: 8px; padding: 16px; border-top: 1px solid var(--qrc-line); }
        .qrc-page:first-child { border-top: 0; }
        .qrc-slug { display: flex; align-items: stretch; border: 1px solid var(--qrc-line-strong); border-radius: 10px; overflow: hidden; }
        .qrc-slug span { display: flex; align-items: center; padding: 0 10px; background: var(--qrc-soft); border-right: 1px solid var(--qrc-line-strong); font-size: 13px; color: var(--qrc-faint); white-space: nowrap; }
        .qrc-slug input { flex: 1; min-width: 0; min-height: 44px; padding: 0 12px; border: 0; background: var(--qrc-panel); color: var(--qrc-text); font-size: 14px; }
        .qrc-empty { padding: 28px 16px; text-align: center; color: var(--qrc-faint); font-size: 14px; }

        .qrc-scrim { position: fixed; inset: 0; z-index: 40; background: rgb(17 24 39 / 45%); }
        .qrc-drawer { position: fixed; top: 0; right: 0; bottom: 0; z-index: 41; width: 640px; max-width: 100%; background: var(--qrc-panel); color: var(--qrc-text); box-shadow: var(--qrc-shadow); display: flex; flex-direction: column; }
        .qrc-drawer-head { display: flex; align-items: flex-start; justify-content: space-between; gap: 12px; padding: 20px 24px 14px; border-bottom: 1px solid var(--qrc-line); }
        .qrc-drawer-body { flex: 1; overflow-y: auto; padding: 20px 24px; display: flex; flex-direction: column; gap: 22px; }
        .qrc-drawer-foot { padding: 14px 24px 18px; border-top: 1px solid var(--qrc-line); display: flex; flex-direction: column; gap: 12px; }
        .qrc-icon-btn { width: 44px; height: 44px; flex: 0 0 auto; border-radius: 12px; border: 1px solid var(--qrc-line); background: var(--qrc-panel); color: var(--qrc-text); display: grid; place-items: center; cursor: pointer; }
        .qrc-options { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 8px; }
        .qrc-option { min-height: 64px; text-align: left; padding: 10px 12px; border-radius: 12px; border: 1px solid var(--qrc-line-strong); background: var(--qrc-panel); color: var(--qrc-text); cursor: pointer; display: flex; flex-direction: column; gap: 2px; }
        .qrc-option[aria-checked="true"] { border: 2px solid var(--qrc-primary); background: var(--qrc-soft); padding: 9px 11px; }
        .qrc-option:disabled { opacity: .5; cursor: not-allowed; }
        .qrc-option span:first-child { font-size: 14px; font-weight: 600; }
        .qrc-option span:last-child { font-size: 12px; color: var(--qrc-faint); }
        .qrc-pills { display: flex; gap: 8px; flex-wrap: wrap; }
        .qrc-pill { min-height: 40px; padding: 0 14px; border-radius: 999px; border: 1px solid var(--qrc-line-strong); background: var(--qrc-panel); color: var(--qrc-text); font-size: 14px; font-weight: 600; cursor: pointer; }
        .qrc-pill[aria-pressed="true"] { border: 2px solid var(--qrc-primary); background: var(--qrc-soft); padding: 0 13px; }
        .qrc-flow { display: grid; grid-template-columns: minmax(0, 1fr) auto minmax(0, 1fr); gap: 10px; align-items: center; padding: 12px; border-radius: 12px; background: var(--qrc-soft); border: 1px solid var(--qrc-line); }
        .qrc-preview-row { display: flex; gap: 18px; align-items: flex-start; flex-wrap: wrap; }
        .qrc-preview { width: 200px; height: 200px; flex: 0 0 auto; border-radius: 16px; border: 1px solid var(--qrc-line); background: #fff; display: grid; place-items: center; overflow: hidden; padding: 6px; }
        .qrc-preview canvas, .qrc-preview svg { width: 100% !important; height: 100% !important; display: block; }
        .qrc-adv { border: 1px solid var(--qrc-line); border-radius: 12px; padding: 0 14px; }
        .qrc-adv > summary { min-height: 44px; display: flex; align-items: center; font-size: 14px; font-weight: 600; color: var(--qrc-muted); cursor: pointer; list-style: none; }
        .qrc-adv > summary::-webkit-details-marker { display: none; }
        .qrc-adv-body { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; padding: 4px 0 14px; }
        .qrc-color { display: flex; align-items: center; gap: 8px; }
        .qrc-color input[type="color"] { width: 44px; height: 44px; padding: 2px; border: 1px solid var(--qrc-line-strong); border-radius: 10px; background: var(--qrc-panel); }
        .qrc-versions { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
        .qrc-version { display: grid; grid-template-columns: 64px minmax(0, 1fr) auto; gap: 14px; align-items: center; padding: 12px 14px; border: 1px solid var(--qrc-line); border-radius: 14px; }
        .qrc-version.is-current { border: 2px solid var(--qrc-ok); padding: 11px 13px; }
        .qrc-bars { display: flex; align-items: flex-end; gap: 2px; height: 64px; padding: 6px 0 0; border-bottom: 1px solid var(--qrc-line); }
        .qrc-bars span { flex: 1; min-width: 2px; border-radius: 3px 3px 0 0; background: #2d89ca; }
        .qrc-dialog { position: fixed; inset: 0; z-index: 60; display: grid; place-items: center; padding: 16px; background: rgb(17 24 39 / 50%); }
        .qrc-dialog-card { width: 100%; max-width: 440px; background: var(--qrc-panel); color: var(--qrc-text); border-radius: 18px; padding: 22px; display: flex; flex-direction: column; gap: 12px; box-shadow: var(--qrc-shadow); }
        .qrc-spin { width: 16px; height: 16px; border-radius: 999px; border: 2px solid currentColor; border-right-color: transparent; animation: qrc-spin .7s linear infinite; }
        @keyframes qrc-spin { to { transform: rotate(360deg); } }
        .qrc-sr { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }
        @media (max-width: 900px) { .qrc-stats { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 560px) { .qrc-options, .qrc-adv-body { grid-template-columns: minmax(0, 1fr); } .qrc-version { grid-template-columns: 56px minmax(0, 1fr); } .qrc-version .qrc-actions { grid-column: 1 / -1; justify-content: flex-start; } }
        @media (prefers-reduced-motion: reduce) { .qrc-spin { animation: none; } }
    </style>

    <div
        class="qrc"
        x-data="{
            config: @js($studioConfig),
            libPromise: null,
            brandLogo: null,
            editor: { open: false, loading: false, saving: false, key: null, title: '', scopeLabel: '', stableUrl: '', documentLabel: '', documentUrl: null, currentLabel: '', targetType: 'hosted_page', targetUrl: '', hostedPageId: '', pages: [] },
            history: { open: false, loading: false, key: null, title: '', scopeLabel: '', stableUrl: null, daily: [], versions: [], restoring: null },
            confirm: { open: false, title: '', body: '', label: '', danger: false, run: null },
            free: { mode: 'link', text: '', name: '', phone: '', email: '', size: 1000 },
            style: { preset: 'marca', dotColor: '#17335e', bgColor: '#ffffff', dotStyle: 'rounded', cornerSquareStyle: 'extra-rounded', cornerDotStyle: 'dot', logo: true, logoSize: 0.24, customLogo: null },
            decode: 'pending',
            qr: null,
            renderTimer: null,
            presets: {
                marca: { dotColor: '#17335e', bgColor: '#ffffff', dotStyle: 'rounded', cornerSquareStyle: 'extra-rounded', cornerDotStyle: 'dot', logo: true, logoSize: 0.24 },
                clasico: { dotColor: '#000000', bgColor: '#ffffff', dotStyle: 'square', cornerSquareStyle: 'square', cornerDotStyle: 'square', logo: false, logoSize: 0.24 },
                contraste: { dotColor: '#000000', bgColor: '#ffffff', dotStyle: 'square', cornerSquareStyle: 'square', cornerDotStyle: 'square', logo: true, logoSize: 0.2 },
            },
            init() {
                this.$watch('style', () => this.scheduleRender(), { deep: true });
                this.$watch('free', () => this.scheduleRender(), { deep: true });
                this.$watch('editor.open', (open) => { document.body.style.overflow = open || this.history.open ? 'hidden' : ''; });
                this.$watch('history.open', (open) => { document.body.style.overflow = open || this.editor.open ? 'hidden' : ''; });
            },
            destroy() {
                clearTimeout(this.renderTimer);
                document.body.style.overflow = '';
            },
            loadLib() {
                if (window.QRCodeStyling) return Promise.resolve();
                if (window.__qrcStylingLoad) return window.__qrcStylingLoad;
                window.__qrcStylingLoad = new Promise((resolve, reject) => {
                    const script = document.createElement('script');
                    script.src = this.config.libUrl;
                    script.onload = () => resolve();
                    script.onerror = () => { window.__qrcStylingLoad = null; reject(new Error('lib')); };
                    document.head.appendChild(script);
                });
                return window.__qrcStylingLoad;
            },
            async loadBrandLogo() {
                if (this.brandLogo !== null || !this.config.brandLogoUrl) return;
                try {
                    const response = await fetch(this.config.brandLogoUrl, { credentials: 'same-origin' });
                    if (!response.ok) throw new Error('logo');
                    const blob = await response.blob();
                    this.brandLogo = await new Promise((resolve) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result)); reader.readAsDataURL(blob); });
                } catch (error) {
                    this.brandLogo = '';
                }
            },
            applyPreset(name) {
                this.style = { ...this.style, ...this.presets[name], preset: name };
            },
            payloadText() {
                if (this.editor.open) return this.editor.stableUrl;
                if (this.free.mode === 'contact') {
                    const esc = (value) => String(value || '').replace(/\\/g, '\\\\').replace(/([;,])/g, '\\$1').replace(/\r?\n/g, ' ').trim();
                    const name = esc(this.free.name) || 'Tu Doctor en Casa';
                    return ['BEGIN:VCARD', 'VERSION:3.0', 'N:' + name + ';;;;', 'FN:' + name, this.free.phone ? 'TEL;TYPE=CELL:' + esc(this.free.phone) : '', this.free.email ? 'EMAIL:' + esc(this.free.email) : '', 'END:VCARD'].filter(Boolean).join('\n');
                }
                return this.free.text.trim() || 'https://tudrencasa.com';
            },
            logoImage() {
                if (!this.style.logo) return undefined;
                return this.style.customLogo || this.brandLogo || undefined;
            },
            options(size) {
                return {
                    width: size, height: size, type: 'canvas', margin: Math.round(size * 0.04),
                    data: this.payloadText(),
                    image: this.logoImage(),
                    qrOptions: { errorCorrectionLevel: 'H' },
                    dotsOptions: { color: this.style.dotColor, type: this.style.dotStyle },
                    backgroundOptions: { color: this.style.bgColor },
                    cornersSquareOptions: { color: this.style.dotColor, type: this.style.cornerSquareStyle },
                    cornersDotOptions: { color: this.style.dotColor, type: this.style.cornerDotStyle },
                    imageOptions: { crossOrigin: 'anonymous', margin: Math.round(size * 0.008), imageSize: Number(this.style.logoSize), hideBackgroundDots: true },
                };
            },
            async mountQr(container) {
                try {
                    await this.loadLib();
                    await this.loadBrandLogo();
                } catch (error) {
                    container.innerHTML = '<p class=\'qrc-hint\' style=\'padding:12px;text-align:center\'>No se pudo cargar el generador de QR. Recarga la página.</p>';
                    return;
                }
                container.innerHTML = '';
                this.qr = new window.QRCodeStyling(this.options(1000));
                this.qr.append(container);
                this.verify();
            },
            scheduleRender() {
                clearTimeout(this.renderTimer);
                this.renderTimer = setTimeout(() => {
                    if (!this.qr) return;
                    this.qr.update(this.options(1000));
                    this.verify();
                }, 150);
            },
            luminance(hex) {
                const channel = (i) => { const v = parseInt(hex.slice(i, i + 2), 16) / 255; return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4); };
                return 0.2126 * channel(1) + 0.7152 * channel(3) + 0.0722 * channel(5);
            },
            contrast() {
                const a = this.luminance(this.style.dotColor), b = this.luminance(this.style.bgColor);
                return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
            },
            checks() {
                const ratio = this.contrast();
                const darker = this.luminance(this.style.dotColor) < this.luminance(this.style.bgColor);
                const logoOk = !this.style.logo || Number(this.style.logoSize) <= this.config.logoMax;
                return [
                    { state: darker && ratio >= this.config.minContrast ? 'ok' : 'fail', title: darker ? 'Contraste ' + ratio.toFixed(1) + ':1' : 'Puntos más claros que el fondo', detail: darker ? (ratio >= this.config.minContrast ? 'suficiente para cualquier móvil' : 'mínimo ' + this.config.minContrast + ':1') : 'muchos lectores no leen QR invertidos' },
                    { state: logoOk ? 'ok' : 'fail', title: this.style.logo ? 'Logo al ' + Math.round(this.style.logoSize * 100) + ' %' : 'Sin logo', detail: logoOk ? 'con corrección de errores alta' : 'el máximo es ' + Math.round(this.config.logoMax * 100) + ' %' },
                    { state: this.decode, title: { ok: 'Se lee correctamente', fail: 'El navegador no pudo leerlo', pending: 'Comprobando lectura…', unavailable: 'Escanéalo con un móvil' }[this.decode], detail: { ok: 'el código leído coincide con el enlace', fail: 'prueba con otro estilo o un logo más pequeño', pending: '', unavailable: 'este navegador no trae lector de QR integrado' }[this.decode] },
                ];
            },
            canApply() {
                return this.checks().every((check) => check.state !== 'fail') && !this.editor.saving && !this.editor.loading;
            },
            async verify() {
                if (!('BarcodeDetector' in window)) { this.decode = 'unavailable'; return; }
                this.decode = 'pending';
                try {
                    await new Promise((resolve) => setTimeout(resolve, 60));
                    const blob = await this.qr.getRawData('png');
                    const bitmap = await createImageBitmap(blob);
                    const found = await new window.BarcodeDetector({ formats: ['qr_code'] }).detect(bitmap);
                    this.decode = found.some((code) => code.rawValue === this.payloadText()) ? 'ok' : 'fail';
                } catch (error) {
                    this.decode = 'unavailable';
                }
            },
            async readLogo(event) {
                const [file] = event.target.files || [];
                if (!file) return;
                if (file.size > 1024 * 1024) { this.notify('El logo pesa más de 1 MB. Usa uno más liviano.'); event.target.value = ''; return; }
                this.style.customLogo = await new Promise((resolve) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result)); reader.readAsDataURL(file); });
                this.style.logo = true;
            },
            notify(message) {
                new FilamentNotification().title(message).warning().send();
            },
            async openEditor(key) {
                this.editor = { ...this.editor, open: true, loading: true, saving: false, key };
                try {
                    const data = await this.$wire.openEditor(key);
                    this.editor = { ...this.editor, ...data, hostedPageId: data.hostedPageId ?? (data.pages[0]?.id ?? ''), targetType: data.pages.length === 0 && data.targetType === 'hosted_page' ? (data.documentUrl ? 'document' : 'url') : data.targetType, loading: false };
                    if (data.style) { this.style = { ...this.style, ...data.style, customLogo: null }; } else { this.applyPreset('marca'); }
                    await this.$nextTick();
                    await this.mountQr(this.$refs.editorPreview);
                    this.$refs.editorClose?.focus();
                } catch (error) {
                    this.editor.open = false;
                }
            },
            closeEditor() {
                this.editor.open = false;
                this.qr = null;
                if (this.$refs.freePreview) this.$nextTick(() => this.mountQr(this.$refs.freePreview));
            },
            selectedPageName() {
                return (this.editor.pages.find((page) => String(page.id) === String(this.editor.hostedPageId)) || {}).name || 'una página alojada';
            },
            futureLabel() {
                return { hosted_page: this.selectedPageName(), url: this.editor.targetUrl || 'el enlace que escribas', document: this.editor.documentLabel }[this.editor.targetType];
            },
            askApply() {
                if (!this.canApply()) return;
                this.confirm = {
                    open: true, danger: false, label: 'Aplicar a las tarjetas',
                    title: '¿Aplicar este QR a ' + this.editor.title + '?',
                    body: 'Las tarjetas de ' + this.editor.title + ' (' + this.editor.scopeLabel.toLowerCase() + ') que se generen desde ahora llevarán este QR, que abrirá ' + this.futureLabel() + '. Las ya emitidas no cambian y la versión anterior queda en el historial.',
                    run: () => this.apply(),
                };
            },
            async apply() {
                this.editor.saving = true;
                try {
                    await this.verify();
                    if (this.decode === 'fail') { this.editor.saving = false; return; }
                    const blob = await this.qr.getRawData('png');
                    const dataUrl = await new Promise((resolve) => { const reader = new FileReader(); reader.onload = () => resolve(String(reader.result)); reader.readAsDataURL(blob); });
                    const style = { preset: this.style.preset, dotColor: this.style.dotColor, bgColor: this.style.bgColor, dotStyle: this.style.dotStyle, cornerSquareStyle: this.style.cornerSquareStyle, cornerDotStyle: this.style.cornerDotStyle, logo: this.style.logo && !!this.logoImage(), logoSize: Number(this.style.logoSize) };
                    const ok = await this.$wire.saveDestination(this.editor.key, { targetType: this.editor.targetType, targetUrl: this.editor.targetUrl, hostedPageId: this.editor.hostedPageId, style }, dataUrl);
                    if (ok) this.closeEditor();
                } finally {
                    this.editor.saving = false;
                }
            },
            askReset(key, name) {
                this.confirm = {
                    open: true, danger: true, label: 'Volver al automático',
                    title: '¿Volver al QR automático en ' + name + '?',
                    body: 'Las tarjetas nuevas imprimirán el QR de siempre. Las que ya llevan el enlace estable seguirán funcionando y abrirán el documento del plan. Puedes restaurar la versión actual desde el historial.',
                    run: () => this.$wire.resetDestination(key),
                };
            },
            async openHistory(key) {
                this.history = { ...this.history, open: true, loading: true, key, versions: [], daily: [] };
                try {
                    const data = await this.$wire.history(key);
                    this.history = { ...this.history, ...data, loading: false };
                    this.$refs.historyClose?.focus();
                } catch (error) {
                    this.history.open = false;
                }
            },
            maxDaily() {
                return Math.max(1, ...this.history.daily.map((day) => day.scans));
            },
            totalDaily() {
                return this.history.daily.reduce((sum, day) => sum + day.scans, 0);
            },
            askRestore(version) {
                this.confirm = {
                    open: true, danger: false, label: 'Restaurar',
                    title: '¿Restaurar la versión ' + version.version + '?',
                    body: 'Vuelve el QR y el destino de esa versión (' + version.target + '). Se guarda como una versión nueva; nada se borra.',
                    run: async () => { this.history.restoring = version.id; const ok = await this.$wire.restoreVersion(version.id); this.history.restoring = null; if (ok) this.openHistory(this.history.key); },
                };
            },
            async runConfirm() {
                const run = this.confirm.run;
                this.confirm.open = false;
                if (typeof run === 'function') await run();
            },
            async download(format) {
                if (!this.qr) return;
                const size = Number(this.free.size) || 1000;
                const exporter = new window.QRCodeStyling({ ...this.options(size), type: format === 'svg' ? 'svg' : 'canvas' });
                const blob = await exporter.getRawData(format);
                const url = URL.createObjectURL(blob);
                const anchor = document.createElement('a');
                anchor.href = url;
                anchor.download = (this.editor.open ? 'qr-' + this.editor.key.replace(':', '-') : 'qr-tu-doctor-en-casa') + '.' + format;
                document.body.appendChild(anchor);
                anchor.click();
                anchor.remove();
                setTimeout(() => URL.revokeObjectURL(url), 1000);
            },
            async copy(text) {
                try { await navigator.clipboard.writeText(text); new FilamentNotification().title('Enlace copiado').success().send(); } catch (error) { this.notify('No se pudo copiar. Selecciona el enlace y cópialo a mano.'); }
            },
        }"
        x-on:keydown.escape.window="if (confirm.open) { confirm.open = false } else if (editor.open && !editor.saving) { closeEditor() } else if (history.open) { history.open = false }"
    >
        <p class="qrc-lead">Define a dónde lleva el QR impreso en cada tarjeta. Si un destino no tiene QR personalizado, la tarjeta usa el automático del plan. Solo lo administra Superadmin.</p>

        <div class="qrc-tabs" role="tablist" aria-label="Secciones del centro de QR">
            <button type="button" role="tab" class="qrc-tab" aria-selected="{{ $tab === 'destinos' ? 'true' : 'false' }}" wire:click="setTab('destinos')">Destinos de tarjetas</button>
            <button type="button" role="tab" class="qrc-tab" aria-selected="{{ $tab === 'paginas' ? 'true' : 'false' }}" wire:click="setTab('paginas')">Páginas alojadas · {{ count($pages) }}</button>
            <button type="button" role="tab" class="qrc-tab" aria-selected="{{ $tab === 'libre' ? 'true' : 'false' }}" wire:click="setTab('libre')">QR libre (solo descargar)</button>
        </div>

        @if ($tab === 'destinos')
            <div class="qrc-stats">
                <div class="qrc-stat"><span>Destinos</span><strong>{{ number_format($totals['destinations'], 0, ',', '.') }}</strong></div>
                <div class="qrc-stat"><span>Con QR personalizado</span><strong>{{ number_format($totals['customized'], 0, ',', '.') }}</strong></div>
                <div class="qrc-stat"><span>Lecturas del QR · {{ $statsDays }} días</span><strong>{{ number_format($totals['scans'], 0, ',', '.') }}</strong></div>
                <div class="qrc-stat"><span>Visitas a páginas · {{ $statsDays }} días</span><strong>{{ number_format($totals['pageViews'], 0, ',', '.') }}</strong></div>
            </div>

            <div class="qrc-note is-info">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>
                <span><strong>QR estable:</strong> un QR personalizado lleva a un enlace fijo de IntegraCorp (<span class="qrc-mono">/q/…</span>). Puedes cambiar a dónde lleva sin reimprimir las tarjetas. Las lecturas no cuentan robots ni vistas previas de WhatsApp.</span>
            </div>

            <div class="qrc-card">
                <div class="qrc-table-wrap">
                    <table class="qrc-table">
                        <thead>
                            <tr>
                                <th scope="col">Destino</th>
                                <th scope="col">QR actual</th>
                                <th scope="col">A dónde lleva</th>
                                <th scope="col">Lecturas · {{ $statsDays }} días</th>
                                <th scope="col">Actualizado</th>
                                <th scope="col"><span class="qrc-sr">Acciones</span></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($rows as $row)
                                <tr wire:key="qr-row-{{ $row['key'] }}">
                                    <td>
                                        <div class="qrc-cell-stack">
                                            <strong>{{ $row['name'] }}</strong>
                                            <span><span class="qrc-chip">{{ $row['scope'] }}</span></span>
                                        </div>
                                    </td>
                                    <td>
                                        @if ($row['thumb'])
                                            <span class="qrc-thumb"><img src="{{ $row['thumb'] }}" alt="QR actual de {{ $row['name'] }}" loading="lazy"></span>
                                        @else
                                            <span class="qrc-thumb is-empty">Automático</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="qrc-cell-stack">
                                            <span><span class="qrc-chip {{ $row['kind'][1] }}">{{ $row['kind'][0] }}</span></span>
                                            <span class="qrc-ellipsis" title="{{ $row['target'] }}">{{ $row['target'] }}</span>
                                            @if ($row['link'] !== '')
                                                <span class="qrc-sub {{ $row['customized'] ? 'qrc-mono' : '' }}">{{ $row['link'] }}</span>
                                            @endif
                                        </div>
                                    </td>
                                    <td>
                                        @if ($row['customized'] || $row['scans'] > 0)
                                            <div class="qrc-cell-stack">
                                                <strong style="font-variant-numeric: tabular-nums;">{{ number_format($row['scans'], 0, ',', '.') }}</strong>
                                                <span class="qrc-sub">{{ number_format($row['visitors'], 0, ',', '.') }} {{ $row['visitors'] === 1 ? 'persona' : 'personas' }}</span>
                                            </div>
                                        @else
                                            <span class="qrc-sub">Sin enlace estable</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="qrc-cell-stack">
                                            <span>{{ $row['updated'] }}</span>
                                            <span class="qrc-sub">{{ $row['by'] }}</span>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="qrc-actions">
                                            @if ($row['hasHistory'])
                                                <button type="button" class="qrc-btn" x-on:click="openHistory(@js($row['key']))">Historial</button>
                                            @endif
                                            @if ($row['customized'])
                                                <button type="button" class="qrc-btn is-danger" x-on:click="askReset(@js($row['key']), @js($row['name'].' · '.$row['scope']))">Automático</button>
                                            @endif
                                            <button type="button" class="qrc-btn is-primary" x-on:click="openEditor(@js($row['key']))">{{ $row['customized'] ? 'Reemplazar' : 'Personalizar' }}</button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
            <p class="qrc-hint">Las tarjetas ya generadas conservan el QR con el que se imprimieron. Las de empresas aliadas mantienen su QR automático.</p>
        @endif

        @if ($tab === 'paginas')
            <div class="qrc-two">
                <section class="qrc-card qrc-pad" aria-labelledby="qrc-upload-title">
                    <div style="display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;">
                        <h2 id="qrc-upload-title" class="qrc-h2">{{ $pageVersionOf ? 'Nueva versión de «'.$pageName.'»' : 'Subir página' }}</h2>
                        @if ($pageVersionOf)
                            <button type="button" class="qrc-btn" wire:click="resetPageForm">Cancelar</button>
                        @endif
                    </div>

                    @unless ($pageVersionOf)
                        <div class="qrc-field">
                            <label for="qrc-page-name">Nombre interno</label>
                            <input id="qrc-page-name" class="qrc-input" type="text" maxlength="120" wire:model.blur="pageName" placeholder="Carnet a la medida (Tailor Made)">
                            @error('pageName') <span class="qrc-error">{{ $message }}</span> @enderror
                        </div>
                        <div class="qrc-field">
                            <label for="qrc-page-slug">Enlace</label>
                            <div class="qrc-slug">
                                <span class="qrc-mono">{{ rtrim(route('qr.page', ['slug' => 'x']), 'x') }}</span>
                                <input id="qrc-page-slug" class="qrc-mono" type="text" maxlength="60" wire:model.blur="pageSlug" placeholder="carnet-a-la-medida" autocomplete="off" spellcheck="false">
                            </div>
                            <span class="qrc-hint">Se propone a partir del nombre. Solo letras, números y guiones; no se puede repetir ni cambiar después.</span>
                            @error('pageSlug') <span class="qrc-error">{{ $message }}</span> @enderror
                        </div>
                    @else
                        <p class="qrc-hint" style="font-size: 13px;">La dirección no cambia: quienes ya tienen el enlace verán la versión nueva.</p>
                    @endunless

                    <label for="qrc-page-file" class="qrc-drop">
                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 3v12M7 8l5-5 5 5"/><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/></svg>
                        <strong wire:loading.remove wire:target="pageFile">{{ $pageFile ? $pageFile->getClientOriginalName() : 'Elige el archivo .html' }}</strong>
                        <strong wire:loading wire:target="pageFile">Subiendo y revisando…</strong>
                        <span class="qrc-hint">Un solo archivo con sus imágenes y estilos incluidos · hasta {{ $maxPageSize }}</span>
                    </label>
                    <input id="qrc-page-file" class="qrc-sr" type="file" accept=".html,.htm,text/html" wire:model="pageFile">
                    @error('pageFile') <span class="qrc-error">{{ $message }}</span> @enderror

                    @if ($pageReview !== [])
                        <div class="qrc-review" aria-live="polite">
                            <strong style="font-size: 14px;">Revisión del archivo</strong>
                            @foreach ($pageReview as $check)
                                <div class="qrc-check">
                                    <span class="qrc-dot is-{{ $check['level'] }}" aria-hidden="true">{{ $check['level'] === 'ok' ? '✓' : '!' }}</span>
                                    <span><strong style="font-weight: 600;">{{ $check['title'] }}</strong> <span class="qrc-sub" style="font-size: 13px;">{{ $check['detail'] }}</span></span>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <div class="qrc-note is-info">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10"/></svg>
                        <span>La página se publica aislada del sistema: puede ejecutar su propio código, pero no puede leer sesiones, cookies ni datos de IntegraCorp.</span>
                    </div>

                    <div style="display: flex; justify-content: flex-end;">
                        <button type="button" class="qrc-btn is-primary is-lg" wire:click="publishPage" wire:loading.attr="disabled" wire:target="publishPage,pageFile" @disabled(! $pageFile)>
                            <span wire:loading wire:target="publishPage" class="qrc-spin" aria-hidden="true"></span>
                            {{ $pageVersionOf ? 'Publicar versión nueva' : 'Publicar página' }}
                        </button>
                    </div>
                </section>

                <section class="qrc-card" aria-labelledby="qrc-pages-title">
                    <div class="qrc-pad" style="padding-bottom: 8px;"><h2 id="qrc-pages-title" class="qrc-h2">Publicadas</h2></div>
                    @forelse ($pages as $page)
                        <article class="qrc-page" wire:key="qr-page-{{ $page['id'] }}">
                            <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                <strong style="font-size: 15px;">{{ $page['name'] }}</strong>
                                <span class="qrc-chip">Versión {{ $page['version'] }}</span>
                                <span class="qrc-chip">{{ $page['size'] }}</span>
                            </div>
                            <a class="qrc-mono" href="{{ $page['url'] }}" target="_blank" rel="noopener" style="font-size: 13px; color: #2563eb; overflow-wrap: anywhere;">{{ $page['url'] }}</a>
                            <span class="qrc-sub" style="font-size: 13px;">
                                {{ $page['usage'] === 0 ? 'Ningún QR lleva a esta página' : ($page['usage'] === 1 ? 'Usada por 1 destino' : 'Usada por '.$page['usage'].' destinos') }}
                                · {{ number_format($page['views'], 0, ',', '.') }} visitas y {{ number_format($page['visitors'], 0, ',', '.') }} personas en {{ $statsDays }} días
                            </span>
                            @if ($page['warnings'] !== [])
                                <span class="qrc-sub" style="color: var(--qrc-warn);">Aviso: {{ implode(' · ', $page['warnings']) }}</span>
                            @endif
                            <span class="qrc-sub">Publicada {{ $page['published'] }}</span>
                            <div class="qrc-actions" style="justify-content: flex-start; margin-top: 4px;">
                                <a class="qrc-btn" href="{{ $page['url'] }}" target="_blank" rel="noopener">Abrir</a>
                                <button type="button" class="qrc-btn" x-on:click="copy(@js($page['url']))">Copiar enlace</button>
                                <button type="button" class="qrc-btn" wire:click="startPageVersion({{ $page['id'] }})">Subir nueva versión</button>
                            </div>
                        </article>
                    @empty
                        <p class="qrc-empty">Todavía no hay páginas. Sube el HTML de un carnet digital para que un QR pueda llevar a él.</p>
                    @endforelse
                    <p class="qrc-hint" style="padding: 0 16px 16px;">Una página no se borra: algún QR impreso puede llevar a ella. Para cambiarla, sube una versión nueva.</p>
                </section>
            </div>
        @endif

        @if ($tab === 'libre')
            <section class="qrc-card qrc-pad" aria-labelledby="qrc-free-title" x-init="$nextTick(() => mountQr($refs.freePreview))">
                <h2 id="qrc-free-title" class="qrc-h2">QR libre</h2>
                <p class="qrc-hint" style="font-size: 13px; margin: 0;">Genera un QR para descargar. No cambia ninguna tarjeta ni queda registrado.</p>
                <div class="qrc-preview-row">
                    <div class="qrc-preview" x-ref="freePreview" wire:ignore aria-label="Vista previa del QR" role="img"></div>
                    <div style="flex: 1; min-width: 260px; display: flex; flex-direction: column; gap: 12px;">
                        <div class="qrc-pills" role="radiogroup" aria-label="Contenido">
                            <button type="button" class="qrc-pill" role="radio" x-bind:aria-pressed="free.mode === 'link'" x-bind:aria-checked="free.mode === 'link'" x-on:click="free.mode = 'link'">Enlace o texto</button>
                            <button type="button" class="qrc-pill" role="radio" x-bind:aria-pressed="free.mode === 'contact'" x-bind:aria-checked="free.mode === 'contact'" x-on:click="free.mode = 'contact'">Contacto</button>
                        </div>
                        <div class="qrc-field" x-show="free.mode === 'link'">
                            <label for="qrc-free-text">Enlace o texto</label>
                            <input id="qrc-free-text" class="qrc-input" type="text" x-model.debounce.300ms="free.text" placeholder="https://tudrencasa.com">
                        </div>
                        <template x-if="free.mode === 'contact'">
                            <div style="display: flex; flex-direction: column; gap: 10px;">
                                <div class="qrc-field"><label for="qrc-free-name">Nombre</label><input id="qrc-free-name" class="qrc-input" type="text" x-model.debounce.300ms="free.name"></div>
                                <div class="qrc-field"><label for="qrc-free-phone">Teléfono</label><input id="qrc-free-phone" class="qrc-input" type="tel" x-model.debounce.300ms="free.phone" placeholder="+58 424 000 0000"></div>
                                <div class="qrc-field"><label for="qrc-free-email">Correo</label><input id="qrc-free-email" class="qrc-input" type="email" x-model.debounce.300ms="free.email"></div>
                            </div>
                        </template>
                        @include('filament.business.pages.partials.qr-center-style-controls', ['prefix' => 'free'])
                        <div class="qrc-field">
                            <label for="qrc-free-size">Tamaño de descarga</label>
                            <select id="qrc-free-size" class="qrc-select" x-model.number="free.size">
                                <option value="600">600 × 600 px · pantalla</option>
                                <option value="1000">1000 × 1000 px · impresión</option>
                                <option value="2000">2000 × 2000 px · gran formato</option>
                            </select>
                        </div>
                        <div class="qrc-actions" style="justify-content: flex-start;">
                            <button type="button" class="qrc-btn is-primary" x-on:click="download('png')">Descargar PNG</button>
                            <button type="button" class="qrc-btn" x-on:click="download('svg')">Descargar SVG</button>
                        </div>
                    </div>
                </div>
            </section>
        @endif

        <div wire:ignore>

            <template x-teleport="body">
                <div>
                    <div class="qrc-scrim" x-cloak x-show="editor.open" x-transition.opacity x-on:click="if (!editor.saving) closeEditor()"></div>
                    <aside class="qrc-drawer qrc" role="dialog" aria-modal="true" aria-labelledby="qrc-editor-title" x-cloak x-show="editor.open" x-trap.noscroll="editor.open" style="gap: 0;">
                        <div class="qrc-drawer-head">
                            <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span class="qrc-sub" style="font-size: 13px;" x-text="editor.title + ' · ' + editor.scopeLabel"></span>
                                <h2 id="qrc-editor-title" class="qrc-h2" style="font-size: 21px;">QR de la tarjeta</h2>
                            </div>
                            <button type="button" class="qrc-icon-btn" x-ref="editorClose" aria-label="Cerrar" x-on:click="closeEditor()" x-bind:disabled="editor.saving">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="qrc-drawer-body">
                            <div x-show="editor.loading" class="qrc-empty" style="display: flex; gap: 10px; justify-content: center; align-items: center;"><span class="qrc-spin" aria-hidden="true"></span>Preparando el editor…</div>

                            <section x-show="!editor.loading" style="display: flex; flex-direction: column; gap: 12px;">
                                <h3 class="qrc-h3">1 · ¿A dónde debe llevar?</h3>
                                <div class="qrc-options" role="radiogroup" aria-label="Tipo de destino">
                                    <button type="button" class="qrc-option" role="radio" x-bind:aria-checked="editor.targetType === 'hosted_page'" x-bind:disabled="editor.pages.length === 0" x-on:click="editor.targetType = 'hosted_page'">
                                        <span>Página alojada</span><span x-text="editor.pages.length === 0 ? 'Primero sube una en «Páginas alojadas»' : 'HTML publicado en IntegraCorp'"></span>
                                    </button>
                                    <button type="button" class="qrc-option" role="radio" x-bind:aria-checked="editor.targetType === 'url'" x-on:click="editor.targetType = 'url'">
                                        <span>Enlace externo</span><span>Una dirección https://</span>
                                    </button>
                                    <button type="button" class="qrc-option" role="radio" x-bind:aria-checked="editor.targetType === 'document'" x-bind:disabled="!editor.documentUrl" x-on:click="editor.targetType = 'document'">
                                        <span x-text="editor.documentLabel"></span><span x-text="editor.documentUrl ? 'Con enlace estable y lecturas' : 'No hay documento publicado'"></span>
                                    </button>
                                </div>

                                <div class="qrc-field" x-show="editor.targetType === 'hosted_page'">
                                    <label for="qrc-editor-page">Página alojada</label>
                                    <select id="qrc-editor-page" class="qrc-select" x-model="editor.hostedPageId">
                                        <template x-for="page in editor.pages" x-bind:key="page.id">
                                            <option x-bind:value="page.id" x-text="page.name"></option>
                                        </template>
                                    </select>
                                </div>
                                <div class="qrc-field" x-show="editor.targetType === 'url'">
                                    <label for="qrc-editor-url">Dirección</label>
                                    <input id="qrc-editor-url" class="qrc-input" type="url" inputmode="url" maxlength="2000" x-model="editor.targetUrl" placeholder="https://tudrencasa.com/…">
                                </div>

                                <div class="qrc-flow">
                                    <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0;">
                                        <span class="qrc-sub">Hoy lleva a</span>
                                        <span style="font-size: 14px; overflow-wrap: anywhere;" x-text="editor.currentLabel"></span>
                                    </div>
                                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--qrc-faint);"><path d="M5 12h14M13 6l6 6-6 6"/></svg>
                                    <div style="display: flex; flex-direction: column; gap: 2px; min-width: 0;">
                                        <span class="qrc-sub">Llevará a</span>
                                        <strong style="font-size: 14px; overflow-wrap: anywhere;" x-text="futureLabel()"></strong>
                                    </div>
                                </div>
                                <p class="qrc-hint" style="font-size: 13px; margin: 0;">Enlace impreso en el QR: <span class="qrc-mono" style="color: var(--qrc-text); overflow-wrap: anywhere;" x-text="editor.stableUrl"></span>. No cambia aunque cambies el destino.</p>
                            </section>

                            <section x-show="!editor.loading" style="display: flex; flex-direction: column; gap: 12px;">
                                <h3 class="qrc-h3">2 · Estilo</h3>
                                @include('filament.business.pages.partials.qr-center-style-controls', ['prefix' => 'editor'])
                            </section>

                            <section x-show="!editor.loading" style="display: flex; flex-direction: column; gap: 12px;">
                                <h3 class="qrc-h3">3 · Comprobación</h3>
                                <div class="qrc-preview-row">
                                    <div class="qrc-preview" x-ref="editorPreview" role="img" aria-label="Vista previa del QR"></div>
                                    <ul style="list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 8px; flex: 1; min-width: 220px;" aria-live="polite">
                                        <template x-for="check in checks()" x-bind:key="check.title">
                                            <li class="qrc-check">
                                                <span class="qrc-dot" x-bind:class="'is-' + check.state" aria-hidden="true" x-text="check.state === 'ok' ? '✓' : (check.state === 'fail' ? '!' : '·')"></span>
                                                <span><strong style="font-weight: 600;" x-text="check.title"></strong> <span class="qrc-sub" style="font-size: 13px;" x-text="check.detail"></span></span>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                                <div class="qrc-actions" style="justify-content: flex-start;">
                                    <button type="button" class="qrc-btn" x-on:click="download('png')">Descargar PNG</button>
                                    <button type="button" class="qrc-btn" x-on:click="download('svg')">Descargar SVG</button>
                                </div>
                            </section>
                        </div>

                        <div class="qrc-drawer-foot" x-show="!editor.loading">
                            <div class="qrc-note is-warn" style="font-size: 13px;">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3"/><path d="M12 9v4M12 17h.01"/></svg>
                                <span>Afecta a las tarjetas que se generen desde ahora. Las ya emitidas no cambian. Queda en el historial y se puede restaurar.</span>
                            </div>
                            <div class="qrc-actions">
                                <button type="button" class="qrc-btn is-lg" x-on:click="closeEditor()" x-bind:disabled="editor.saving">Cancelar</button>
                                <button type="button" class="qrc-btn is-primary is-lg" x-on:click="askApply()" x-bind:disabled="!canApply()">
                                    <span class="qrc-spin" aria-hidden="true" x-show="editor.saving"></span>
                                    <span x-text="editor.saving ? 'Aplicando…' : 'Aplicar a las tarjetas'"></span>
                                </button>
                            </div>
                        </div>
                    </aside>

                    <div class="qrc-scrim" x-cloak x-show="history.open" x-transition.opacity x-on:click="history.open = false"></div>
                    <aside class="qrc-drawer qrc" role="dialog" aria-modal="true" aria-labelledby="qrc-history-title" x-cloak x-show="history.open" x-trap.noscroll="history.open" style="gap: 0;">
                        <div class="qrc-drawer-head">
                            <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                <span class="qrc-sub" style="font-size: 13px;" x-text="history.title + ' · ' + history.scopeLabel"></span>
                                <h2 id="qrc-history-title" class="qrc-h2" style="font-size: 21px;">Historial y lecturas</h2>
                            </div>
                            <button type="button" class="qrc-icon-btn" x-ref="historyClose" aria-label="Cerrar" x-on:click="history.open = false">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                            </button>
                        </div>
                        <div class="qrc-drawer-body">
                            <div x-show="history.loading" class="qrc-empty" style="display: flex; gap: 10px; justify-content: center; align-items: center;"><span class="qrc-spin" aria-hidden="true"></span>Cargando…</div>
                            <section x-show="!history.loading" style="display: flex; flex-direction: column; gap: 8px;">
                                <div style="display: flex; justify-content: space-between; gap: 12px; flex-wrap: wrap; align-items: baseline;">
                                    <h3 class="qrc-h3">Lecturas de los últimos {{ $statsDays }} días</h3>
                                    <strong style="font-variant-numeric: tabular-nums;" x-text="totalDaily().toLocaleString('es-VE') + ' lecturas'"></strong>
                                </div>
                                <div class="qrc-bars" role="img" x-bind:aria-label="'Lecturas por día: ' + totalDaily() + ' en total'">
                                    <template x-for="day in history.daily" x-bind:key="day.day">
                                        <span x-bind:style="'height:' + Math.max(2, Math.round(day.scans / maxDaily() * 58)) + 'px;' + (day.scans === 0 ? 'opacity:.25' : '')" x-bind:title="day.day + ': ' + day.scans"></span>
                                    </template>
                                </div>
                                <p class="qrc-hint" style="margin: 0;">Enlace estable: <span class="qrc-mono" x-text="history.stableUrl || '—'"></span></p>
                            </section>
                            <section x-show="!history.loading" style="display: flex; flex-direction: column; gap: 10px;">
                                <h3 class="qrc-h3">Versiones</h3>
                                <ol class="qrc-versions">
                                    <template x-for="version in history.versions" x-bind:key="version.id">
                                        <li class="qrc-version" x-bind:class="version.current && 'is-current'">
                                            <span class="qrc-thumb" style="width: 64px; height: 64px;">
                                                <template x-if="version.image"><img x-bind:src="version.image" x-bind:alt="'QR de la versión ' + version.version"></template>
                                                <template x-if="!version.image"><span class="qrc-sub" style="text-align: center;">Automático</span></template>
                                            </span>
                                            <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                                                <div style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                                                    <strong x-text="'Versión ' + version.version"></strong>
                                                    <span class="qrc-chip is-page" x-show="version.current">En uso</span>
                                                </div>
                                                <span style="font-size: 14px; overflow-wrap: anywhere;" x-text="version.target"></span>
                                                <span class="qrc-sub" x-text="version.meta + (version.note ? ' · ' + version.note : '')"></span>
                                            </div>
                                            <div class="qrc-actions">
                                                <a class="qrc-btn" x-show="version.download" x-bind:href="version.download">Descargar</a>
                                                <button type="button" class="qrc-btn" x-show="!version.current" x-on:click="askRestore(version)" x-bind:disabled="history.restoring !== null">
                                                    <span class="qrc-spin" aria-hidden="true" x-show="history.restoring === version.id"></span>Restaurar
                                                </button>
                                            </div>
                                        </li>
                                    </template>
                                </ol>
                                <p class="qrc-empty" x-show="history.versions.length === 0">Este destino todavía no tiene versiones guardadas.</p>
                            </section>
                        </div>
                    </aside>

                    <div class="qrc-dialog qrc" x-cloak x-show="confirm.open" x-transition.opacity x-on:click.self="confirm.open = false" style="gap: 0;">
                        <div class="qrc-dialog-card" role="alertdialog" aria-modal="true" aria-labelledby="qrc-confirm-title" aria-describedby="qrc-confirm-body" x-trap="confirm.open">
                            <h3 id="qrc-confirm-title" class="qrc-h2" x-text="confirm.title"></h3>
                            <p id="qrc-confirm-body" style="margin: 0; font-size: 14px; line-height: 1.55; color: var(--qrc-muted);" x-text="confirm.body"></p>
                            <div class="qrc-actions" style="margin-top: 4px;">
                                <button type="button" class="qrc-btn is-lg" x-on:click="confirm.open = false">Cancelar</button>
                                <button type="button" class="qrc-btn is-lg" x-bind:class="confirm.danger ? 'is-danger' : 'is-primary'" x-on:click="runConfirm()" x-text="confirm.label"></button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>
    </div>
</x-filament-panels::page>
