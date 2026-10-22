{{--
    Estilos del organigrama. Van en CSS propio y no en clases Tailwind porque varios
    paneles internos no compilan un tema propio: una clase nueva no existiría allí.
    Los colores son variables con valor claro por defecto y oscuro bajo `.dark`
    (Filament marca el <html>). El bloque #oc-export-style es el mismo que se
    incrusta en la imagen PNG, por eso usa var(--x, valor-claro).
--}}
<style id="oc-export-style">
    .oc-edge { fill: none; stroke: var(--oc-line, #94a3b8); stroke-width: 1.5; }
    .oc-edge-staff { stroke-dasharray: 5 4; }
    .oc-card { fill: var(--oc-card, #ffffff); stroke: var(--oc-card-stroke, #cbd5e1); stroke-width: 1; }
    .oc-vacant .oc-card { stroke: var(--oc-line, #94a3b8); stroke-dasharray: 6 4; }
    .oc-staff .oc-card { stroke-dasharray: 3 3; }
    .oc-selected .oc-card { stroke: var(--oc-accent, #052f60); stroke-width: 2.5; stroke-dasharray: none; }
    .oc-match .oc-card { stroke: var(--oc-accent, #052f60); stroke-width: 3; }
    .oc-diff-added .oc-card { stroke: var(--oc-green, #15803d); stroke-width: 2.5; stroke-dasharray: none; }
    .oc-diff-moved .oc-card { stroke: var(--oc-amber, #b45309); stroke-width: 2.5; stroke-dasharray: none; }
    .oc-diff-changed .oc-card { stroke: var(--oc-purple, #6d28d9); stroke-width: 2.5; stroke-dasharray: none; }
    .oc-drop-ok .oc-card { stroke: var(--oc-green, #15803d); stroke-width: 3; fill: var(--oc-green-soft, #dcfce7); }
    .oc-drop-bad .oc-card { stroke: var(--oc-red, #b91c1c); stroke-width: 3; }
    .oc-title { font: 600 13px ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; fill: var(--oc-ink, #0f172a); }
    .oc-person { font: 400 12px ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; fill: var(--oc-ink-2, #334155); }
    .oc-vacant-label { fill: var(--oc-muted, #64748b); font-style: italic; }
    .oc-meta { font: 500 10.5px ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; fill: var(--oc-muted, #64748b); }
    .oc-initials { font: 600 12px ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; fill: var(--oc-accent, #052f60); }
    .oc-avatar-bg { fill: var(--oc-accent-soft, #dbe6f4); }
    .oc-avatar-empty { fill: none; stroke: var(--oc-line, #94a3b8); stroke-width: 1.5; stroke-dasharray: 3 3; }
    .oc-bar { fill: var(--oc-c-navy, #052f60); }
    .oc-area-teal { fill: var(--oc-c-teal, #0f766e); }
    .oc-area-green { fill: var(--oc-c-green, #15803d); }
    .oc-area-amber { fill: var(--oc-c-amber, #b45309); }
    .oc-area-red { fill: var(--oc-c-red, #b91c1c); }
    .oc-area-purple { fill: var(--oc-c-purple, #6d28d9); }
    .oc-area-slate { fill: var(--oc-c-slate, #475569); }
    .oc-flag-error { fill: var(--oc-red, #b91c1c); }
    .oc-flag-warning { fill: var(--oc-amber, #b45309); }
    .oc-pill rect { fill: var(--oc-card, #ffffff); stroke: var(--oc-card-stroke, #cbd5e1); }
    .oc-pill text { font: 600 10.5px ui-sans-serif, system-ui, sans-serif; fill: var(--oc-muted, #64748b); }
    .oc-pill-collapsed rect { fill: var(--oc-accent-soft, #dbe6f4); stroke: var(--oc-accent, #052f60); }
    .oc-pill-collapsed text { fill: var(--oc-accent, #052f60); }
    .oc-add { cursor: pointer; opacity: 0; transition: opacity .12s ease; }
    .oc-add circle { fill: var(--oc-accent, #052f60); }
    .oc-add path { stroke: var(--oc-panel, #ffffff); stroke-width: 2; stroke-linecap: round; }
    .oc-add-staff circle { fill: var(--oc-c-slate, #475569); }
    .oc-node:hover .oc-add, .oc-selected .oc-add { opacity: 1; }
    .oc-export .oc-pill, .oc-export .oc-add { display: none; }
    .oc-export-title { font: 700 20px ui-sans-serif, system-ui, sans-serif; fill: #052f60; }
</style>

<style>
    .oc-root {
        --oc-bg: #f6f8fb; --oc-grid: #dfe5ee; --oc-card: #ffffff; --oc-card-stroke: #cbd5e1;
        --oc-ink: #0f172a; --oc-ink-2: #334155; --oc-muted: #64748b; --oc-line: #94a3b8;
        --oc-accent: #052f60; --oc-accent-soft: #dbe6f4; --oc-panel: #ffffff; --oc-border: #e2e8f0;
        --oc-amber: #b45309; --oc-amber-soft: #fef3c7; --oc-green: #15803d; --oc-green-soft: #dcfce7;
        --oc-red: #b91c1c; --oc-red-soft: #fee2e2; --oc-purple: #6d28d9; --oc-purple-soft: #ede9fe;
        --oc-c-navy: #052f60; --oc-c-teal: #0f766e; --oc-c-green: #15803d; --oc-c-amber: #b45309;
        --oc-c-red: #b91c1c; --oc-c-purple: #6d28d9; --oc-c-slate: #475569;
        --oc-shadow: 0 12px 28px rgba(15, 23, 42, .16);
        position: relative;
        color: var(--oc-ink);
        display: flex; flex-direction: column;
        border: 1px solid var(--oc-border); border-radius: 14px; overflow: hidden; background: var(--oc-panel);
        font-size: 13px;
    }
    .oc-root *, .oc-root *::before, .oc-root *::after { box-sizing: border-box; }
    .dark .oc-root {
        --oc-bg: #0b1220; --oc-grid: #1c2638; --oc-card: #111a2b; --oc-card-stroke: #334155;
        --oc-ink: #e5ecf5; --oc-ink-2: #cbd5e1; --oc-muted: #94a3b8; --oc-line: #475569;
        --oc-accent: #8db8ec; --oc-accent-soft: #1b3253; --oc-panel: #0f172a; --oc-border: #1e293b;
        --oc-amber: #f0a73a; --oc-amber-soft: #3a2a10; --oc-green: #5fcf95; --oc-green-soft: #123222;
        --oc-red: #ff8a80; --oc-red-soft: #3d1714; --oc-purple: #b9a2ff; --oc-purple-soft: #2b2050;
        --oc-c-navy: #7aa7e0; --oc-c-teal: #2dd4bf; --oc-c-green: #4ade80; --oc-c-amber: #fbbf24;
        --oc-c-red: #f87171; --oc-c-purple: #a78bfa; --oc-c-slate: #94a3b8;
        --oc-shadow: 0 12px 28px rgba(0, 0, 0, .45);
    }
    .oc-root kbd, .oc-root code { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11px; background: var(--oc-bg); border: 1px solid var(--oc-border); border-bottom-width: 2px; border-radius: 4px; padding: 0 4px; }
    .oc-root code { border-bottom-width: 1px; }

    /* Barra */
    .oc-toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; padding: 10px 12px; border-bottom: 1px solid var(--oc-border); }
    .oc-toolbar-group { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
    .oc-spacer { flex: 1; }
    .oc-input, .oc-select { height: 34px; border: 1px solid var(--oc-border); border-radius: 9px; background: var(--oc-bg); color: var(--oc-ink); padding: 0 10px; font-size: 13px; min-width: 0; }
    .oc-input-lg { height: 44px; font-size: 15px; font-weight: 600; padding: 0 14px; }
    .oc-input:focus, .oc-select:focus, .oc-btn:focus-visible, .oc-link:focus-visible, .oc-pitem:focus-visible, .oc-tab:focus-visible { outline: 2px solid var(--oc-accent); outline-offset: 1px; }
    .oc-btn { height: 34px; display: inline-flex; align-items: center; gap: 6px; padding: 0 12px; border-radius: 9px; border: 1px solid var(--oc-border); background: var(--oc-panel); color: var(--oc-ink); font-weight: 500; font-size: 13px; cursor: pointer; white-space: nowrap; text-decoration: none; }
    .oc-btn:hover { background: var(--oc-bg); }
    .oc-btn[disabled] { opacity: .5; cursor: not-allowed; }
    .oc-btn-lg { height: 44px; padding: 0 18px; font-size: 14px; }
    .oc-btn-primary { background: var(--oc-accent); border-color: var(--oc-accent); color: var(--oc-panel); }
    .oc-btn-primary:hover { background: var(--oc-accent); filter: brightness(1.08); }
    .oc-btn-danger { color: var(--oc-red); border-color: var(--oc-red); }
    .oc-btn-danger-solid { background: var(--oc-red); border-color: var(--oc-red); color: #fff; }
    .oc-btn-danger-solid:hover { background: var(--oc-red); filter: brightness(1.08); }
    .oc-btn-sm { height: 28px; padding: 0 9px; font-size: 12px; }
    .oc-btn-on { background: var(--oc-accent-soft); border-color: var(--oc-accent); color: var(--oc-accent); }
    .oc-icon-btn { padding: 0; width: 34px; justify-content: center; font-weight: 700; }
    .oc-btn-sm.oc-icon-btn { width: 28px; }
    .oc-btn svg, .oc-icon { width: 16px; height: 16px; flex: none; }
    .oc-icon-xs { width: 13px; height: 13px; opacity: .6; }
    .oc-tabs { display: inline-flex; gap: 2px; padding: 3px; border-radius: 10px; background: var(--oc-bg); border: 1px solid var(--oc-border); }
    .oc-tab { height: 28px; padding: 0 12px; border-radius: 8px; font-size: 12.5px; color: var(--oc-muted); cursor: pointer; border: 0; background: transparent; white-space: nowrap; }
    .oc-tab-on { background: var(--oc-panel); color: var(--oc-ink); font-weight: 600; box-shadow: 0 0 0 1px var(--oc-border); }
    .oc-tabs-sm .oc-tab { height: 24px; padding: 0 9px; font-size: 12px; }
    .oc-cmdk { height: 34px; display: inline-flex; align-items: center; justify-content: space-between; gap: 16px; min-width: 220px; padding: 0 8px 0 12px; border: 1px solid var(--oc-border); border-radius: 9px; background: var(--oc-bg); color: var(--oc-muted); cursor: pointer; font-size: 12.5px; }
    .oc-chip { display: inline-flex; align-items: center; gap: 4px; height: 22px; padding: 0 8px; border-radius: 999px; font-size: 11.5px; font-weight: 500; background: var(--oc-bg); color: var(--oc-muted); border: 1px solid var(--oc-border); white-space: nowrap; font-variant-numeric: tabular-nums; }
    .oc-chip-button { cursor: pointer; }
    .oc-chip-button:hover { border-color: var(--oc-accent); color: var(--oc-accent); }
    .oc-chip-amber { background: var(--oc-amber-soft); color: var(--oc-amber); border-color: transparent; }
    .oc-chip-red { background: var(--oc-red-soft); color: var(--oc-red); border-color: transparent; }
    .oc-chip-green { background: var(--oc-green-soft); color: var(--oc-green); border-color: transparent; }
    .oc-chip-blue { background: var(--oc-accent-soft); color: var(--oc-accent); border-color: transparent; }
    .oc-chip-purple { background: var(--oc-purple-soft); color: var(--oc-purple); border-color: transparent; }
    .oc-search { position: relative; flex: 1 1 200px; max-width: 300px; min-width: 160px; }
    .oc-search .oc-input { width: 100%; padding-left: 32px; }
    .oc-search-icon { position: absolute; left: 10px; top: 9px; color: var(--oc-muted); }
    .oc-search-results { position: absolute; z-index: 40; top: 38px; left: 0; right: 0; background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 10px; box-shadow: var(--oc-shadow); max-height: 320px; overflow: auto; }
    .oc-search-item { display: flex; flex-direction: column; gap: 1px; width: 100%; text-align: left; padding: 8px 12px; border: 0; background: transparent; color: var(--oc-ink); cursor: pointer; }
    .oc-search-item:hover, .oc-search-item:focus { background: var(--oc-bg); outline: none; }
    .oc-search-item small { color: var(--oc-muted); }
    .oc-banner { padding: 9px 12px; font-size: 13px; border-bottom: 1px solid var(--oc-border); }
    .oc-coach { display: flex; flex-wrap: wrap; gap: 8px 22px; align-items: center; padding: 9px 12px; font-size: 12.5px; background: var(--oc-accent-soft); color: var(--oc-ink); border-bottom: 1px solid var(--oc-border); }
    .oc-coach button { margin-left: auto; }

    /* Cuerpo */
    .oc-body { display: grid; grid-template-columns: minmax(0, 1fr); position: relative; min-height: 0; }
    .oc-body-edit.oc-layout-canvas { grid-template-columns: minmax(0, 1fr); }
    .oc-body-edit.oc-layout-split { grid-template-columns: minmax(0, 1fr) minmax(300px, 380px); }
    .oc-body-edit.oc-layout-doc { grid-template-columns: minmax(0, 1fr); }
    .oc-body-edit.oc-with-palette.oc-layout-canvas { grid-template-columns: 250px minmax(0, 1fr); }
    .oc-body-edit.oc-with-palette.oc-layout-split { grid-template-columns: 250px minmax(0, 1fr) minmax(300px, 380px); }
    .oc-body-edit.oc-with-palette.oc-layout-doc { grid-template-columns: 250px minmax(0, 1fr); }
    .oc-stage-wrap { position: relative; min-width: 0; }
    .oc-stage { position: relative; height: clamp(480px, calc(100vh - 300px), 980px); background-color: var(--oc-bg); background-image: radial-gradient(var(--oc-grid) 1.2px, transparent 1.2px); background-size: 20px 20px; overflow: hidden; touch-action: none; cursor: grab; user-select: none; }
    .oc-stage:active { cursor: grabbing; }
    .oc-stage svg.oc-svg { width: 100%; height: 100%; display: block; }
    .oc-stage [data-node], .oc-stage [data-toggle] { cursor: pointer; }

    /* Paleta */
    .oc-palette { border-right: 1px solid var(--oc-border); display: flex; flex-direction: column; min-height: 0; height: clamp(480px, calc(100vh - 300px), 980px); background: var(--oc-panel); }
    .oc-palette-tabs { display: flex; gap: 2px; margin: 10px 10px 6px; padding: 3px; background: var(--oc-bg); border: 1px solid var(--oc-border); border-radius: 9px; }
    .oc-palette-tabs button { flex: 1; height: 26px; border: 0; background: transparent; border-radius: 7px; font-size: 12px; color: var(--oc-muted); cursor: pointer; display: inline-flex; gap: 4px; align-items: center; justify-content: center; }
    .oc-palette-tabs button.oc-on { background: var(--oc-panel); color: var(--oc-ink); font-weight: 600; box-shadow: 0 0 0 1px var(--oc-border); }
    .oc-count { min-width: 18px; height: 18px; border-radius: 999px; background: var(--oc-accent); color: var(--oc-panel); font-size: 10.5px; display: inline-grid; place-items: center; padding: 0 4px; }
    .oc-palette-search { margin: 0 10px 8px; }
    .oc-palette-list { flex: 1; overflow-y: auto; padding: 0 10px 8px; display: flex; flex-direction: column; }
    .oc-psect { margin: 10px 2px 5px; font-size: 10.5px; font-weight: 600; text-transform: uppercase; letter-spacing: .06em; color: var(--oc-muted); }
    .oc-pitem { display: flex; align-items: center; gap: 8px; width: 100%; margin-bottom: 5px; padding: 7px 9px; border: 1px solid var(--oc-border); border-radius: 9px; background: var(--oc-panel); color: var(--oc-ink); font-size: 12.5px; text-align: left; cursor: grab; touch-action: none; user-select: none; }
    .oc-pitem:hover { border-color: var(--oc-accent); }
    .oc-pitem small { margin-left: auto; color: var(--oc-muted); font-size: 11px; white-space: nowrap; }
    .oc-pitem-used { opacity: .55; }
    .oc-grip { color: var(--oc-line); font-family: ui-monospace, monospace; font-size: 11px; letter-spacing: -2px; }
    .oc-pname { min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-palette-empty { padding: 8px 2px; }
    .oc-palette-foot { margin: 0; padding: 8px 12px; border-top: 1px solid var(--oc-border); font-size: 11px; color: var(--oc-muted); }

    /* Lienzo en blanco: un solo cuadro */
    .oc-seed-wrap { position: absolute; inset: 0; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; padding: 24px; z-index: 5; pointer-events: none; }
    .oc-seed { pointer-events: auto; position: relative; width: min(300px, 100%); min-height: 92px; display: flex; align-items: center; gap: 12px; padding: 18px 16px 14px; background: var(--oc-card); border: 2px dashed var(--oc-accent); border-radius: 14px; box-shadow: 0 0 0 6px var(--oc-accent-soft); }
    .oc-seed:focus-within { border-style: solid; }
    .oc-seed-readonly { border-color: var(--oc-line); box-shadow: none; }
    .oc-seed-bar { position: absolute; left: -2px; right: -2px; top: -2px; height: 5px; border-radius: 14px 14px 0 0; background: var(--oc-c-navy); }
    .oc-seed-avatar { width: 38px; height: 38px; border-radius: 50%; border: 1.5px dashed var(--oc-line); flex: none; }
    .oc-seed-text { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 3px; }
    .oc-seed-input { width: 100%; border: 0; background: transparent; color: var(--oc-ink); font-size: 15px; font-weight: 600; padding: 0; outline: none; }
    .oc-seed-input::placeholder { color: var(--oc-muted); font-weight: 500; }
    .oc-seed-sub { font-size: 12px; color: var(--oc-muted); font-style: italic; }
    .oc-seed-hint { pointer-events: auto; margin: 0; font-size: 12.5px; color: var(--oc-muted); text-align: center; max-width: 46ch; }

    /* Ventanita y renombrado */
    .oc-popover { position: absolute; left: 0; top: 0; width: 268px; z-index: 22; background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 12px; box-shadow: var(--oc-shadow); }
    .oc-popover-body { padding: 10px; display: flex; flex-direction: column; gap: 9px; }
    .oc-popover-title { display: flex; align-items: center; gap: 6px; width: 100%; border: 1px solid transparent; background: transparent; color: var(--oc-ink); font-weight: 700; font-size: 14px; text-align: left; border-radius: 7px; padding: 3px 5px; cursor: text; }
    .oc-popover-title:hover { border-color: var(--oc-border); }
    .oc-popover-people { display: flex; flex-wrap: wrap; gap: 6px; align-items: center; }
    .oc-person-chip { display: inline-flex; align-items: center; gap: 6px; max-width: 100%; padding: 2px 4px 2px 2px; border: 1px solid var(--oc-border); border-radius: 999px; font-size: 12px; }
    .oc-person-chip span:nth-child(2) { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-person-chip button { border: 0; background: transparent; color: var(--oc-muted); cursor: pointer; font-size: 11px; }
    .oc-popover-row { display: flex; gap: 8px; align-items: center; justify-content: space-between; flex-wrap: wrap; }
    .oc-stepper { display: inline-flex; align-items: center; border: 1px solid var(--oc-border); border-radius: 8px; overflow: hidden; font-size: 12px; }
    .oc-stepper button { width: 26px; height: 26px; border: 0; background: var(--oc-bg); color: var(--oc-ink); cursor: pointer; }
    .oc-stepper button[disabled] { opacity: .4; cursor: not-allowed; }
    .oc-stepper span { padding: 0 8px; white-space: nowrap; font-variant-numeric: tabular-nums; }
    .oc-popover-foot { display: flex; justify-content: space-between; align-items: center; border-top: 1px solid var(--oc-border); padding-top: 8px; }
    .oc-menu-wrap { position: relative; }
    .oc-menu { position: absolute; right: 0; top: calc(100% + 4px); min-width: 200px; z-index: 30; background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 10px; box-shadow: var(--oc-shadow); padding: 4px; display: flex; flex-direction: column; }
    .oc-menu button { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 6px 9px; border: 0; background: transparent; color: var(--oc-ink); border-radius: 7px; cursor: pointer; font-size: 12.5px; }
    .oc-menu button:hover, .oc-menu-on { background: var(--oc-accent-soft) !important; }
    .oc-menu button small { margin-left: auto; color: var(--oc-muted); }
    .oc-menu-danger { color: var(--oc-red) !important; }
    .oc-rename { position: absolute; left: 0; top: 0; z-index: 24; display: flex; flex-direction: column; gap: 3px; }
    .oc-rename .oc-input { height: 38px; font-weight: 600; border: 2px solid var(--oc-accent); box-shadow: var(--oc-shadow); background: var(--oc-panel); }
    .oc-rename small { color: var(--oc-muted); font-size: 11px; background: var(--oc-panel); border-radius: 5px; padding: 1px 5px; align-self: flex-start; }
    .oc-result { display: flex; align-items: center; gap: 6px; padding: 5px; border: 1px solid var(--oc-border); border-radius: 9px; }
    .oc-result-name { flex: 1; min-width: 0; display: flex; flex-direction: column; line-height: 1.25; }
    .oc-result-name strong, .oc-result-name small { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-result-name small { color: var(--oc-muted); }

    /* Controles del lienzo */
    .oc-zoom { position: absolute; left: 12px; bottom: 12px; display: flex; background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 10px; overflow: hidden; z-index: 6; }
    .oc-zoom button { height: 30px; min-width: 32px; padding: 0 8px; border: 0; border-right: 1px solid var(--oc-border); background: transparent; color: var(--oc-ink); cursor: pointer; font-size: 12px; font-variant-numeric: tabular-nums; }
    .oc-zoom button:last-child { border-right: 0; }
    .oc-zoom button:hover { background: var(--oc-bg); }
    .oc-minimap { position: absolute; right: 12px; bottom: 12px; width: 170px; height: 104px; background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 10px; padding: 6px; z-index: 6; }
    .oc-minimap svg { width: 100%; height: 100%; cursor: pointer; }
    .oc-mini-node { fill: var(--oc-line); opacity: .55; }
    .oc-mini-selected { fill: var(--oc-accent); opacity: 1; }
    .oc-mini-viewport { fill: var(--oc-accent); fill-opacity: .08; stroke: var(--oc-accent); stroke-width: 1.5; vector-effect: non-scaling-stroke; }
    .oc-crumbs { position: absolute; left: 12px; top: 12px; display: flex; flex-wrap: wrap; gap: 4px; align-items: center; background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 10px; padding: 4px 8px; font-size: 12px; max-width: calc(100% - 24px); z-index: 6; }
    .oc-crumbs button { border: 0; background: transparent; color: var(--oc-accent); cursor: pointer; padding: 2px 4px; font-size: 12px; }
    .oc-ghost { position: fixed; left: 0; top: 0; z-index: 9999; pointer-events: none; background: var(--oc-panel); border: 1.5px solid var(--oc-accent); border-radius: 10px; padding: 6px 10px; box-shadow: var(--oc-shadow); font-size: 12px; max-width: 300px; color: var(--oc-ink); }
    .oc-ghost strong { display: block; }
    .oc-ghost span { color: var(--oc-muted); }
    .oc-loading { position: absolute; inset: 0; background: color-mix(in srgb, var(--oc-bg) 60%, transparent); display: grid; place-items: center; z-index: 25; font-size: 13px; color: var(--oc-muted); }

    /* Panel de detalle */
    .oc-drawer { position: absolute; top: 0; right: 0; bottom: 0; width: 340px; max-width: 100%; background: var(--oc-panel); border-left: 1px solid var(--oc-border); box-shadow: -12px 0 28px rgba(15, 23, 42, .08); overflow-y: auto; z-index: 20; }
    .oc-drawer-inner { padding: 16px; display: flex; flex-direction: column; gap: 12px; }
    .oc-drawer h3, .oc-dialog h3 { margin: 0; font-size: 16px; font-weight: 700; line-height: 1.25; }
    .oc-drawer-head { display: flex; gap: 10px; align-items: flex-start; justify-content: space-between; }
    .oc-input-title { width: 100%; font-weight: 600; margin-top: 4px; }
    .oc-close { border: 0; background: transparent; color: var(--oc-muted); cursor: pointer; padding: 4px; border-radius: 8px; }
    .oc-close:hover { background: var(--oc-bg); }
    .oc-label { font-size: 11px; font-weight: 600; letter-spacing: .05em; text-transform: uppercase; color: var(--oc-muted); }
    .oc-field { display: flex; flex-direction: column; gap: 5px; }
    .oc-field .oc-input, .oc-field .oc-select { width: 100%; }
    .oc-row2 { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 10px; }
    .oc-row2 > * { min-width: 0; }
    .oc-sect { border: 1px solid var(--oc-border); border-radius: 10px; }
    .oc-sect-head { display: flex; justify-content: space-between; align-items: center; width: 100%; padding: 9px 11px; border: 0; background: transparent; color: var(--oc-ink); font-weight: 600; font-size: 13px; cursor: pointer; text-align: left; }
    .oc-sect-body { padding: 0 11px 11px; display: flex; flex-direction: column; gap: 9px; }
    .oc-history { display: flex; flex-direction: column; font-size: 12.5px; border-left: 2px solid var(--oc-border); padding-left: 8px; }
    .oc-history small { color: var(--oc-muted); }
    .oc-person-row { display: flex; gap: 10px; align-items: center; padding: 8px; border: 1px solid var(--oc-border); border-radius: 10px; }
    .oc-person-row > div { min-width: 0; flex: 1; display: flex; flex-direction: column; line-height: 1.3; }
    .oc-person-row small { color: var(--oc-muted); overflow-wrap: anywhere; }
    .oc-avatar { width: 34px; height: 34px; border-radius: 50%; background: var(--oc-accent-soft); color: var(--oc-accent); display: grid; place-items: center; font-weight: 600; font-size: 12px; flex: none; overflow: hidden; }
    .oc-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .oc-avatar-sm { width: 22px; height: 22px; font-size: 9.5px; }
    .oc-swatches { display: flex; gap: 6px; flex-wrap: wrap; }
    .oc-swatch { width: 24px; height: 24px; border-radius: 7px; border: 2px solid transparent; cursor: pointer; }
    .oc-swatch-sm { width: 20px; height: 20px; border-radius: 6px; }
    .oc-swatch-on { border-color: var(--oc-ink); box-shadow: 0 0 0 2px var(--oc-panel) inset; }
    .oc-list { display: flex; flex-direction: column; gap: 6px; margin: 0; padding: 0; list-style: none; }
    .oc-link { border: 0; background: transparent; color: var(--oc-accent); cursor: pointer; padding: 0; text-align: left; font-size: 13px; }
    .oc-link:hover { text-decoration: underline; }
    .oc-link-danger { color: var(--oc-red); align-self: flex-start; }
    .oc-note { font-size: 12px; color: var(--oc-muted); }
    .oc-muted { color: var(--oc-muted); }
    .oc-callout { border-radius: 10px; padding: 9px 11px; font-size: 12.5px; margin: 0; }
    .oc-callout-amber { background: var(--oc-amber-soft); color: var(--oc-amber); }
    .oc-callout-red { background: var(--oc-red-soft); color: var(--oc-red); }
    .oc-callout-blue { background: var(--oc-accent-soft); color: var(--oc-accent); }
    .oc-toggle-row { display: flex; align-items: center; justify-content: space-between; gap: 10px; font-size: 13px; }

    /* Documento */
    .oc-doc { border-left: 1px solid var(--oc-border); display: flex; flex-direction: column; min-height: 0; height: clamp(480px, calc(100vh - 300px), 980px); background: var(--oc-panel); }
    .oc-layout-doc .oc-doc { border-left: 0; }
    .oc-doc-head { display: flex; flex-wrap: wrap; gap: 4px 10px; align-items: center; justify-content: space-between; padding: 10px 12px; border-bottom: 1px solid var(--oc-border); }
    .oc-doc-list { flex: 1; overflow-y: auto; padding: 6px 0 40px; outline: none; }
    .oc-doc-list:focus-visible { box-shadow: inset 0 0 0 2px var(--oc-accent); }
    .oc-drow { display: grid; grid-template-columns: 16px minmax(0, 1fr) auto; gap: 6px; align-items: center; min-height: 32px; padding-right: 10px; border-left: 3px solid transparent; cursor: pointer; font-size: 13px; }
    .oc-drow:hover { background: var(--oc-bg); }
    .oc-drow-on { background: var(--oc-accent-soft); border-left-color: var(--oc-accent); }
    .oc-drow-added { border-left-color: var(--oc-green); }
    .oc-drow-moved { border-left-color: var(--oc-amber); }
    .oc-drow-changed { border-left-color: var(--oc-purple); }
    .oc-drow.oc-drop-ok { background: var(--oc-green-soft); }
    .oc-drow.oc-drop-bad { background: var(--oc-red-soft); }
    .oc-caret { border: 0; background: transparent; color: var(--oc-muted); cursor: pointer; padding: 0; width: 16px; height: 16px; line-height: 16px; font-size: 10px; }
    .oc-bullet { width: 6px; height: 6px; border-radius: 50%; background: var(--oc-accent); justify-self: center; }
    .oc-bullet-staff { background: transparent; border: 1.5px dashed var(--oc-accent); width: 8px; height: 8px; }
    .oc-dtitle { min-width: 0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .oc-dtitle small { color: var(--oc-muted); margin-left: 6px; }
    .oc-dchips { display: flex; gap: 4px; }
    .oc-doc-edit { position: relative; min-width: 0; }
    .oc-doc-input { width: 100%; height: 30px; border: 1.5px solid var(--oc-accent); border-radius: 7px; background: var(--oc-panel); color: var(--oc-ink); padding: 0 8px; font-size: 13px; font-weight: 600; outline: none; }
    .oc-doc-menu { left: 0; right: auto; min-width: 240px; max-height: 260px; overflow-y: auto; }

    /* Barra inferior */
    .oc-statusbar { display: flex; flex-wrap: wrap; gap: 10px 14px; align-items: center; padding: 8px 12px; border-top: 1px solid var(--oc-border); font-size: 12px; color: var(--oc-muted); font-variant-numeric: tabular-nums; }
    .oc-status-group { display: inline-flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; }
    .oc-statusbar .oc-ok { color: var(--oc-green); }
    .oc-statusbar .oc-err { color: var(--oc-red); }
    .oc-progress { display: inline-flex; gap: 8px; align-items: center; }
    .oc-meter { display: inline-block; width: 120px; height: 6px; border-radius: 3px; background: var(--oc-bg); border: 1px solid var(--oc-border); overflow: hidden; }
    .oc-meter i { display: block; height: 100%; background: var(--oc-green); }
    .oc-issue-dot { width: 8px; height: 8px; border-radius: 50%; margin-top: 5px; flex: none; }

    /* Avisos flotantes */
    .oc-toasts { position: absolute; left: 50%; bottom: 56px; transform: translateX(-50%); z-index: 60; display: flex; flex-direction: column-reverse; gap: 8px; width: max-content; max-width: min(440px, calc(100% - 24px)); pointer-events: none; }
    .oc-toast { pointer-events: auto; display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: 10px; background: var(--oc-panel); border: 1px solid var(--oc-border); box-shadow: var(--oc-shadow); font-size: 13px; border-left-width: 4px; }
    .oc-toast-success { border-left-color: var(--oc-green); }
    .oc-toast-danger { border-left-color: var(--oc-red); }
    .oc-toast-warning { border-left-color: var(--oc-amber); }
    .oc-toast-info { border-left-color: var(--oc-accent); }
    .oc-toast button { border: 0; background: transparent; color: var(--oc-muted); cursor: pointer; margin-left: auto; }

    /* Ventanas */
    .oc-modal { position: fixed; inset: 0; z-index: 70; background: rgba(15, 23, 42, .45); display: flex; align-items: flex-start; justify-content: center; padding: max(48px, 10vh) 16px 16px; }
    .oc-cmd { width: min(620px, 100%); background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 14px; box-shadow: var(--oc-shadow); overflow: hidden; color: var(--oc-ink); }
    .oc-cmd .oc-input { width: 100%; border: 0; border-bottom: 1px solid var(--oc-border); border-radius: 0; background: var(--oc-panel); }
    .oc-cmd-list { max-height: 55vh; overflow-y: auto; padding: 6px; }
    .oc-cmd-item { display: grid; grid-template-columns: 120px minmax(0, 1fr) auto; gap: 10px; align-items: center; width: 100%; text-align: left; padding: 8px 10px; border: 0; background: transparent; color: var(--oc-ink); border-radius: 8px; cursor: pointer; font-size: 13px; }
    .oc-cmd-item span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .oc-cmd-group { color: var(--oc-muted); font-size: 11px; text-transform: uppercase; letter-spacing: .04em; }
    .oc-dialog { width: min(560px, 100%); max-height: 80vh; overflow-y: auto; background: var(--oc-panel); border: 1px solid var(--oc-border); border-radius: 14px; box-shadow: var(--oc-shadow); padding: 18px; display: flex; flex-direction: column; gap: 14px; color: var(--oc-ink); }
    .oc-dialog-sm { width: min(440px, 100%); }
    .oc-dialog-foot { display: flex; gap: 8px; justify-content: flex-end; align-items: center; flex-wrap: wrap; }
    .oc-checklist { display: flex; flex-direction: column; gap: 10px; }
    .oc-check { display: flex; gap: 10px; align-items: flex-start; font-size: 13px; }
    .oc-check > div { display: flex; flex-direction: column; gap: 2px; align-items: flex-start; }
    .oc-check i { width: 20px; height: 20px; border-radius: 50%; flex: none; display: grid; place-items: center; font: 700 11px ui-sans-serif, system-ui, sans-serif; font-style: normal; }
    .oc-check-ok { background: var(--oc-green); color: var(--oc-panel); }
    .oc-check-bad { background: var(--oc-red); color: #fff; }
    .oc-check-warn { background: var(--oc-amber); color: var(--oc-panel); }
    .oc-check-info { border: 1.5px solid var(--oc-border); color: var(--oc-muted); }
    .oc-check-detail { padding-left: 30px; font-size: 12.5px; }
    .oc-consequences { margin: 0; padding-left: 18px; display: flex; flex-direction: column; gap: 6px; font-size: 13px; }
    .oc-confirm-row { padding: 9px 11px; border-radius: 9px; background: var(--oc-red-soft); color: var(--oc-red); font-weight: 600; }
    .oc-keys { display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; margin: 0; font-size: 13px; }
    .oc-keys dt { white-space: nowrap; color: var(--oc-muted); }
    .oc-keys dd { margin: 0; }

    @media (max-width: 1279px) {
        .oc-hide-narrow { display: none; }
        .oc-body-edit.oc-layout-split { grid-template-columns: minmax(0, 1fr); }
        .oc-body-edit.oc-with-palette.oc-layout-split { grid-template-columns: 230px minmax(0, 1fr); }
        .oc-layout-split .oc-doc { display: none; }
    }
    @media (max-width: 1023px) {
        .oc-body-edit.oc-with-palette.oc-layout-canvas, .oc-body-edit.oc-with-palette.oc-layout-split, .oc-body-edit.oc-with-palette.oc-layout-doc { grid-template-columns: minmax(0, 1fr); }
        .oc-palette { height: 260px; border-right: 0; border-bottom: 1px solid var(--oc-border); }
        .oc-minimap { display: none; }
        .oc-cmdk { min-width: 0; }
        .oc-cmdk span { display: none; }
    }
    @media (max-width: 767px) {
        .oc-drawer { top: auto; left: 0; width: 100%; max-height: 60%; border-left: 0; border-top: 1px solid var(--oc-border); border-radius: 14px 14px 0 0; }
        .oc-stage { height: 70vh; }
        .oc-search { max-width: none; }
        .oc-toasts { top: 12px; bottom: auto; flex-direction: column; }
        .oc-popover { width: calc(100% - 16px); }
        .oc-cmd-item { grid-template-columns: minmax(0, 1fr) auto; }
        .oc-cmd-group { display: none; }
    }
    @media (prefers-reduced-motion: reduce) {
        .oc-root *, .oc-add { transition: none !important; animation: none !important; }
    }
</style>
