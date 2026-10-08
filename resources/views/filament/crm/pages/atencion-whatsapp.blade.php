<x-filament-panels::page fullHeight>
    @php
        $board = $this->board();
        $case = $this->openCase();
        $mark = function (string $name): string {
            $parts = preg_split('/\s+/u', trim($name)) ?: [];
            $letters = mb_strtoupper(mb_substr((string) ($parts[0] ?? ''), 0, 1).mb_substr((string) ($parts[1] ?? ''), 0, 1));

            return $letters !== '' ? $letters : '?';
        };
    @endphp

    <style>
        @media (min-width: 1101px) {
            html.fi:has(.crm-inbox),
            .fi-body:has(.crm-inbox) {
                height: 100dvh;
                max-height: 100dvh;
                overflow: hidden;
            }

            .fi-body:has(.crm-inbox) {
                display: flex;
                flex-direction: column;
            }

            .fi-body:has(.crm-inbox) .fi-topbar-ctn {
                flex: 0 0 auto;
            }

            .fi-body:has(.crm-inbox) .fi-layout {
                flex: 1 1 auto;
                height: auto;
                min-height: 0;
                overflow: hidden;
            }

            .fi-body.fi-body-has-sidebar-collapsible-on-desktop:has(.crm-inbox) .fi-main-ctn,
            .fi-body.fi-body-has-sidebar-fully-collapsible-on-desktop:has(.crm-inbox) .fi-main-ctn,
            .fi-body:has(.crm-inbox) .fi-main-ctn,
            .fi-body:has(.crm-inbox) .fi-main,
            .fi-body:has(.crm-inbox) .fi-page,
            .fi-body:has(.crm-inbox) .fi-page-header-main-ctn,
            .fi-body:has(.crm-inbox) .fi-page-main,
            .fi-body:has(.crm-inbox) .fi-page-content,
            .fi-body:has(.crm-inbox) .crm-inbox,
            .fi-body:has(.crm-inbox) .crm-board {
                flex: 1 1 auto;
                height: auto;
                min-height: 0;
                max-height: 100%;
                overflow: hidden;
            }

            .fi-body:has(.crm-inbox) .fi-main,
            .fi-body:has(.crm-inbox) .fi-page,
            .fi-body:has(.crm-inbox) .fi-page-header-main-ctn,
            .fi-body:has(.crm-inbox) .fi-page-main,
            .fi-body:has(.crm-inbox) .fi-page-content,
            .fi-body:has(.crm-inbox) .crm-inbox {
                display: flex;
                flex-direction: column;
            }

            .fi-body:has(.crm-inbox) .fi-page-header-main-ctn {
                gap: 0;
                padding-top: 0.75rem;
                padding-bottom: 0.75rem;
            }

            .fi-body:has(.crm-inbox) .fi-main {
                padding-top: 0;
                padding-bottom: 0;
            }
        }

        .crm-inbox {
            --crm-line: #d3deeb;
            --crm-line-strong: #c2d1e2;
            --crm-panel: #ffffff;
            --crm-raised: #f6f9fc;
            --crm-soft: #eef3f8;
            --crm-text: #122033;
            --crm-muted: #5a6e84;
            --crm-faint: #8a9bb0;
            --crm-accent: #052f60;
            --crm-accent-2: #1d5fa8;
            --crm-accent-soft: #e2ecf8;
            --crm-action: #052f60;
            --crm-action-text: #ffffff;
            --crm-in: #ffffff;
            --crm-out: #dbe9fa;
            --crm-note-bg: #fff8eb;
            --crm-note-line: #e4c98a;
            --crm-live: #0d7a48;
            --crm-live-bg: #e2f3e9;
            --crm-warn: #9a5b00;
            --crm-warn-bg: #fcefd8;
            --crm-fail: #b42318;
            --crm-fail-bg: #fde7e4;
            --crm-sol: #6941c6;
            --crm-sol-bg: #f4f0fd;
            --crm-shadow: 0 1px 1px rgb(12 32 58 / 4%), 0 2px 6px rgb(12 32 58 / 4%);
            --crm-shadow-2: 0 1px 2px rgb(12 32 58 / 6%), 0 12px 32px rgb(12 32 58 / 12%);
            flex: 1 1 auto;
            height: 100%;
            min-height: 0;
            color: var(--crm-text);
            font-size: 13px;
            line-height: 1.45;
        }

        .dark .crm-inbox {
            --crm-line: rgb(51 65 85);
            --crm-line-strong: rgb(71 85 105);
            --crm-panel: rgb(15 23 42);
            --crm-raised: rgb(19 29 51);
            --crm-soft: rgb(23 34 56);
            --crm-text: rgb(238 242 247);
            --crm-muted: rgb(154 171 191);
            --crm-faint: rgb(107 125 147);
            --crm-accent: rgb(140 200 245);
            --crm-accent-2: rgb(140 200 245);
            --crm-accent-soft: rgb(16 48 79);
            --crm-action: #0b4a86;
            --crm-action-text: #ffffff;
            --crm-in: rgb(23 34 56);
            --crm-out: rgb(15 53 89);
            --crm-note-bg: rgb(68 48 16 / 45%);
            --crm-note-line: rgb(180 130 50);
            --crm-live: #86efac;
            --crm-live-bg: rgb(15 44 28);
            --crm-warn: #fcd34d;
            --crm-warn-bg: rgb(53 39 8);
            --crm-fail: #fda4af;
            --crm-fail-bg: rgb(58 19 22);
            --crm-sol: #c4b5fd;
            --crm-sol-bg: rgb(42 33 72 / 70%);
            --crm-shadow: none;
            --crm-shadow-2: 0 12px 40px rgb(0 0 0 / 45%);
        }

        .crm-inbox * { box-sizing: border-box; }
        .crm-inbox [x-cloak] { display: none !important; }
        .crm-inbox button { font: inherit; color: inherit; }
        .crm-inbox :focus-visible { outline: 2px solid var(--crm-accent-2); outline-offset: 2px; border-radius: 6px; }
        .crm-num { font-variant-numeric: tabular-nums; }

        .crm-kbd {
            display: inline-block;
            min-width: 18px;
            text-align: center;
            font-size: 10px;
            font-weight: 600;
            line-height: 15px;
            border: 1px solid var(--crm-line-strong);
            border-bottom-width: 2px;
            border-radius: 5px;
            padding: 0 4px;
            color: var(--crm-muted);
            background: var(--crm-panel);
        }

        .crm-kicker {
            margin: 0;
            font-size: 10.5px;
            font-weight: 600;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: var(--crm-faint);
        }

        .crm-sr {
            position: absolute;
            width: 1px;
            height: 1px;
            padding: 0;
            margin: -1px;
            overflow: hidden;
            clip: rect(0, 0, 0, 0);
            white-space: nowrap;
            border: 0;
        }

        .crm-board {
            height: 100%;
            display: grid;
            grid-template-columns: minmax(16rem, 19rem) minmax(0, 1fr) auto;
            gap: 10px;
            min-height: 0;
        }

        .crm-pane {
            position: relative;
            background: var(--crm-panel);
            border: 1px solid var(--crm-line);
            border-radius: 14px;
            box-shadow: var(--crm-shadow);
            min-height: 0;
            min-width: 0;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        /* Cola */
        .crm-queue-head { padding: 14px 14px 12px; display: grid; gap: 12px; border-bottom: 1px solid var(--crm-line); flex: 0 0 auto; }
        .crm-queue-top { display: flex; align-items: flex-end; justify-content: space-between; gap: 10px; }

        .crm-alerts { display: grid; justify-items: end; gap: 6px; }
        @keyframes crm-pulse {
            0% { box-shadow: 0 0 0 0 color-mix(in srgb, currentColor 45%, transparent); }
            70%, 100% { box-shadow: 0 0 0 6px transparent; }
        }
        .crm-arrived {
            display: block;
            width: 100%;
            padding: 8px 10px;
            border: 1px solid color-mix(in srgb, var(--crm-accent-2) 30%, var(--crm-line));
            border-radius: 10px;
            background: var(--crm-accent-soft);
            color: var(--crm-text);
            text-align: left;
            cursor: pointer;
            animation: crm-rise .18s ease-out;
        }
        .crm-arrived-title { display: block; font-size: 12px; font-weight: 700; }
        .crm-arrived-body { display: block; margin-top: 2px; font-size: 12.5px; white-space: pre-line; }


        .crm-search { position: relative; }
        .crm-search input {
            width: 100%;
            border: 1px solid var(--crm-line);
            background: var(--crm-raised);
            color: var(--crm-text);
            border-radius: 9px;
            padding: 8px 34px 8px 11px;
            font: inherit;
        }
        .crm-search input:focus { outline: 2px solid var(--crm-accent-2); outline-offset: 1px; }
        .crm-search .crm-kbd { position: absolute; right: 9px; top: 50%; transform: translateY(-50%); }

        .crm-list { flex: 1 1 auto; min-height: 0; overflow: auto; padding: 8px; display: flex; flex-direction: column; gap: 4px; }
        .crm-row {
            width: 100%;
            text-align: left;
            border: 1px solid transparent;
            background: transparent;
            border-radius: 11px;
            padding: 10px;
            display: grid;
            grid-template-columns: 38px minmax(0, 1fr) auto;
            gap: 3px 11px;
            cursor: pointer;
            transition: background .12s;
        }
        .crm-row:hover { background: var(--crm-raised); }
        .crm-row.is-selected { background: var(--crm-accent-soft); border-color: color-mix(in srgb, var(--crm-accent-2) 22%, transparent); }
        .crm-row .crm-ring { grid-row: span 3; }
        .crm-row-name { font-weight: 600; line-height: 1.3; overflow-wrap: anywhere; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .crm-row-time { font-size: 11px; font-weight: 600; align-self: start; white-space: nowrap; color: var(--crm-faint); font-variant-numeric: tabular-nums; }
        .crm-row-last { grid-column: 2 / 4; color: var(--crm-muted); font-size: 12px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .crm-row-tags { grid-column: 2 / 4; display: flex; gap: 5px; flex-wrap: wrap; }
        .crm-tag { display: inline-flex; align-items: center; border-radius: 6px; padding: 1px 7px; font-size: 11px; font-weight: 500; background: var(--crm-soft); color: var(--crm-muted); white-space: nowrap; }
        .crm-tag.is-mine { background: var(--crm-action); color: var(--crm-action-text); }
        .crm-tag.is-for { background: var(--crm-accent-soft); color: var(--crm-accent-2); }
        .crm-tag.is-live { background: var(--crm-live-bg); color: var(--crm-live); }
        .crm-more { border: 0; background: none; color: var(--crm-accent-2); font-weight: 600; padding: 8px 14px; cursor: pointer; text-align: left; flex: 0 0 auto; }
        .crm-list-foot { border-top: 1px solid var(--crm-line); padding: 9px 14px; font-size: 11px; color: var(--crm-faint); display: flex; gap: 12px; flex-wrap: wrap; flex: 0 0 auto; }
        .crm-empty { margin: auto; padding: 28px 18px; text-align: center; color: var(--crm-muted); font-size: 12.5px; max-width: 30rem; }

        /* Semáforo de espera */
        .is-ok { --crm-sla: var(--crm-faint); --crm-sla-bg: var(--crm-soft); }
        .is-warn { --crm-sla: var(--crm-warn); --crm-sla-bg: var(--crm-warn-bg); }
        .is-late { --crm-sla: var(--crm-fail); --crm-sla-bg: var(--crm-fail-bg); }
        .is-held { --crm-sla: var(--crm-live); --crm-sla-bg: var(--crm-live-bg); }
        .crm-row-time.is-warn, .crm-row-time.is-late, .crm-row-time.is-held { color: var(--crm-sla); }

        .crm-ring {
            --crm-p: 0;
            width: 38px;
            height: 38px;
            border-radius: 50%;
            padding: 2.5px;
            background: conic-gradient(var(--crm-sla, var(--crm-faint)) calc(var(--crm-p) * 1%), var(--crm-line) 0);
        }
        .crm-ring > span {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 11.5px;
            font-weight: 650;
            background: var(--crm-panel);
            color: var(--crm-accent-2);
            border: 2px solid var(--crm-panel);
        }
        .crm-ring.is-lg { width: 44px; height: 44px; flex: 0 0 auto; }
        .crm-ring.is-lg > span { font-size: 13px; }

        /* Conversación */
        .crm-progress {
            position: absolute;
            inset: 0 0 auto;
            height: 2px;
            z-index: 3;
            background: linear-gradient(90deg, transparent, var(--crm-accent-2), transparent) 0 0 / 40% 100% no-repeat;
            animation: crm-slide 1s linear infinite;
        }
        @keyframes crm-slide { from { background-position: -40% 0; } to { background-position: 140% 0; } }

        .crm-case-head { padding: 12px 16px; display: flex; align-items: center; gap: 13px; border-bottom: 1px solid var(--crm-line); flex: 0 0 auto; }
        .crm-case-who { min-width: 0; flex: 1 1 auto; display: grid; gap: 2px; }
        .crm-case-name { margin: 0; font-size: 16px; font-weight: 650; letter-spacing: -0.015em; line-height: 1.25; overflow-wrap: anywhere; }
        .crm-link {
            border: 0;
            background: none;
            padding: 0;
            color: var(--crm-accent-2);
            font-weight: 600;
            font-size: 12px;
            text-decoration: underline;
            text-decoration-color: color-mix(in srgb, currentColor 35%, transparent);
            text-underline-offset: 3px;
            cursor: pointer;
            white-space: nowrap;
        }
        .crm-link:hover { text-decoration-color: currentColor; }
        .crm-link.is-danger { color: var(--crm-fail); }
        .crm-link:disabled { opacity: .6; cursor: wait; }
        .crm-icon-btn {
            width: 34px;
            height: 34px;
            flex: 0 0 auto;
            border-radius: 9px;
            border: 1px solid var(--crm-line-strong);
            background: var(--crm-panel);
            display: grid;
            place-items: center;
            color: var(--crm-muted);
            cursor: pointer;
        }
        .crm-icon-btn:hover { color: var(--crm-text); background: var(--crm-raised); }
        .crm-icon-btn svg { width: 16px; height: 16px; }
        .crm-menu-wrap { position: relative; }
        .crm-menu {
            position: absolute;
            right: 0;
            top: 40px;
            z-index: 6;
            min-width: 240px;
            background: var(--crm-panel);
            border: 1px solid var(--crm-line-strong);
            border-radius: 12px;
            box-shadow: var(--crm-shadow-2);
            padding: 6px;
            display: grid;
        }
        .crm-menu { width: max-content; min-width: 15rem; max-width: min(19rem, calc(100vw - 32px)); }
        .crm-menu button { display: flex; align-items: flex-start; gap: 10px; width: 100%; text-align: left; background: none; border: 0; padding: 8px 10px; border-radius: 8px; cursor: pointer; }
        .crm-menu button:hover, .crm-menu button:focus-visible { background: var(--crm-raised); }
        .crm-menu svg { width: 16px; height: 16px; flex: 0 0 auto; margin-top: 1px; color: var(--crm-faint); }
        .crm-menu-text { display: grid; gap: 1px; min-width: 0; }
        .crm-menu-text > span { font-size: 13px; font-weight: 500; white-space: nowrap; }
        .crm-menu-text small { font-size: 11.5px; color: var(--crm-faint); }
        .crm-menu .is-danger, .crm-menu .is-danger svg { color: var(--crm-fail); }
        .crm-menu hr { border: 0; border-top: 1px solid var(--crm-line); margin: 4px 2px; }

        .crm-offline { display: flex; align-items: center; gap: 8px; padding: 7px 16px; background: var(--crm-warn-bg); color: var(--crm-warn); font-size: 12px; font-weight: 600; flex: 0 0 auto; }



        .crm-stream {
            flex: 1 1 auto;
            min-height: 0;
            overflow: auto;
            padding: 20px 18px;
            display: flex;
            flex-direction: column;
            gap: 3px;
            background:
                radial-gradient(circle at 1px 1px, color-mix(in srgb, var(--crm-text) 5%, transparent) 1px, transparent 0) 0 0 / 18px 18px,
                var(--crm-soft);
        }
        .crm-bubble {
            max-width: min(68%, 34rem);
            border-radius: 14px;
            padding: 7px 11px 5px;
            background: var(--crm-in);
            box-shadow: var(--crm-shadow);
            animation: crm-rise .18s ease-out;
        }
        @keyframes crm-rise { from { opacity: 0; transform: translateY(4px); } }
        .crm-bubble p { margin: 0; white-space: pre-wrap; overflow-wrap: anywhere; font-size: 13.5px; color: var(--crm-text); }
        .crm-bubble.is-in { align-self: flex-start; border-bottom-left-radius: 5px; }
        .crm-bubble.is-out { align-self: flex-end; background: var(--crm-out); border-bottom-right-radius: 5px; }
        .crm-bubble.is-note { align-self: stretch; max-width: none; background: var(--crm-note-bg); border: 1px dashed var(--crm-note-line); box-shadow: none; }
        .crm-bubble.is-failed { outline: 1px solid var(--crm-fail); }
        .crm-bubble.is-first { margin-top: 10px; }
        .crm-by { display: block; font-size: 11px; font-weight: 650; margin-bottom: 1px; color: var(--crm-accent-2); }
        .crm-by.is-bot { color: var(--crm-muted); }
        .crm-bubble.is-note .crm-by { color: var(--crm-warn); }
        .crm-meta { display: flex; justify-content: flex-end; gap: 6px; align-items: center; color: var(--crm-faint); font-size: 10.5px; margin-top: 1px; font-variant-numeric: tabular-nums; }
        .crm-meta .is-failed { color: var(--crm-fail); font-weight: 600; }
        .crm-retry { border: 0; background: none; padding: 0; color: var(--crm-fail); font-weight: 700; font-size: 11px; text-decoration: underline; cursor: pointer; }
        .crm-event { align-self: center; display: inline-flex; align-items: center; gap: 7px; color: var(--crm-muted); font-size: 11.5px; margin: 12px 0 2px; text-align: center; }
        .crm-event::before, .crm-event::after { content: ""; width: 28px; height: 1px; background: var(--crm-line-strong); flex: 0 0 auto; }
        .crm-stream-note { align-self: center; margin: 12px 0 0; color: var(--crm-muted); font-size: 12px; }

        /* Pie */
        .crm-foot { position: relative; border-top: 1px solid var(--crm-line); padding: 12px 16px; background: var(--crm-panel); display: grid; gap: 10px; flex: 0 0 auto; }
        .crm-decide { display: flex; align-items: center; gap: 10px 16px; flex-wrap: wrap; }
        .crm-hint { margin: 0; color: var(--crm-muted); font-size: 12px; flex: 1 1 200px; min-width: 0; }
        .crm-btn {
            border: 1px solid var(--crm-line-strong);
            background: var(--crm-panel);
            border-radius: 10px;
            padding: 8px 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            white-space: nowrap;
        }
        .crm-btn:active { transform: translateY(1px); }
        .crm-btn:disabled { opacity: .6; cursor: wait; }
        .crm-btn.is-primary {
            background: var(--crm-action);
            border-color: var(--crm-action);
            color: var(--crm-action-text);
            padding: 9px 16px 9px 18px;
            box-shadow: 0 1px 0 rgb(255 255 255 / 12%) inset, 0 4px 14px color-mix(in srgb, var(--crm-action) 28%, transparent);
        }
        .crm-btn.is-primary .crm-kbd { background: rgb(255 255 255 / 14%); color: var(--crm-action-text); border-color: rgb(255 255 255 / 30%); }
        .crm-btn.is-danger { background: var(--crm-fail); border-color: var(--crm-fail); color: #fff; }
        .dark .crm-btn.is-danger { color: rgb(15 23 42); }
        .crm-links { display: flex; gap: 16px; flex-wrap: wrap; }
        .crm-pass-list { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .crm-pass-list .crm-hint { flex: 0 0 auto; }
        .crm-spin { width: 12px; height: 12px; border: 2px solid currentColor; border-right-color: transparent; border-radius: 50%; animation: crm-spin .7s linear infinite; }
        @keyframes crm-spin { to { transform: rotate(360deg); } }

        .crm-quote-form { display: flex; flex-direction: column; gap: 8px; padding: 10px; border: 1px solid var(--crm-line-strong); border-radius: 12px; background: var(--crm-raised); }
        .crm-quote-grid { display: grid; grid-template-columns: 1.4fr .8fr .8fr; gap: 8px; }
        .crm-quote-form label { display: flex; flex-direction: column; gap: 4px; font-size: 11px; color: var(--crm-faint); }
        .crm-quote-form input, .crm-quote-form select { border: 1px solid var(--crm-line-strong); background: var(--crm-panel); color: inherit; border-radius: 8px; padding: 7px 8px; font: inherit; font-size: 13px; }
        .crm-quote-result { display: flex; justify-content: space-between; gap: 10px; align-items: center; flex-wrap: wrap; }
        .crm-quote-result b { font-size: 14px; }
        .crm-doc { display: inline-flex; margin-bottom: 4px; border-radius: 999px; padding: 1px 7px; font-size: 10px; letter-spacing: .04em; background: var(--crm-accent-soft); color: var(--crm-accent-2); }
        @media (max-width: 720px) { .crm-quote-grid { grid-template-columns: 1fr; } }
        .crm-compose {
            display: flex;
            gap: 6px;
            align-items: flex-end;
            border: 1px solid var(--crm-line-strong);
            background: var(--crm-raised);
            border-radius: 13px;
            padding: 5px 5px 5px 8px;
            transition: border-color .12s, box-shadow .12s;
        }
        .crm-compose:focus-within { border-color: var(--crm-accent-2); box-shadow: 0 0 0 3px color-mix(in srgb, var(--crm-accent-2) 16%, transparent); }
        .crm-compose textarea {
            flex: 1 1 auto;
            min-width: 0;
            min-height: 36px;
            max-height: 200px;
            resize: none;
            overflow-y: hidden;
            border: 0;
            background: none;
            color: var(--crm-text);
            padding: 8px 2px;
            font: inherit;
            font-size: 13.5px;
            line-height: 1.45;
            outline: none;
            box-shadow: none;
        }
        .crm-compose textarea:focus { outline: none; box-shadow: none; }
        .crm-note-btn { border: 0; background: none; color: var(--crm-muted); font-size: 12px; font-weight: 600; padding: 9px 8px; border-radius: 8px; cursor: pointer; white-space: nowrap; }
        .crm-note-btn:hover { background: var(--crm-note-bg); color: var(--crm-warn); }
        .crm-send {
            width: 36px;
            height: 36px;
            flex: 0 0 auto;
            border-radius: 10px;
            border: 0;
            background: var(--crm-action);
            color: var(--crm-action-text);
            display: grid;
            place-items: center;
            cursor: pointer;
        }
        .crm-send svg { width: 16px; height: 16px; }
        .crm-send:disabled, .crm-note-btn:disabled { opacity: .35; cursor: default; }
        .crm-compose-meta { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; font-size: 11px; color: var(--crm-faint); }
        .crm-compose-meta > span { display: inline-flex; gap: 4px; align-items: center; flex-wrap: wrap; }

        /* Ficha */
        .crm-side { width: 18rem; transition: width .2s ease; }
        .crm-side.is-collapsed { width: 3rem; }
        .crm-side-head { display: flex; align-items: center; justify-content: space-between; gap: 6px; padding: 11px 10px 11px 16px; border-bottom: 1px solid var(--crm-line); flex: 0 0 auto; }
        .crm-side.is-collapsed .crm-side-head { padding: 11px 6px; justify-content: center; }
        .crm-side.is-collapsed .crm-side-head .crm-kicker, .crm-side.is-collapsed .crm-side-body { display: none; }
        .crm-side-head svg { transition: transform .2s; }
        .crm-side.is-collapsed .crm-side-head svg { transform: rotate(180deg); }
        .crm-side-body { padding: 16px; display: grid; gap: 18px; overflow: auto; align-content: start; min-height: 0; }
        .crm-section { display: grid; gap: 8px; }

        /* Copiloto */
        .crm-sol { display: inline-flex; align-items: center; border-radius: 6px; padding: 1px 7px; font-size: 10.5px; font-weight: 650; letter-spacing: .02em; background: var(--crm-sol-bg); color: var(--crm-sol); white-space: nowrap; text-transform: none; }
        .crm-kicker .crm-sol { margin-right: 4px; }
        .crm-copilot-summary { margin: 0; font-size: 12.5px; line-height: 1.5; color: var(--crm-text); }
        .crm-facts { margin: 0; display: grid; grid-template-columns: auto minmax(0, 1fr); gap: 6px 12px; font-size: 12px; }
        .crm-facts dt { color: var(--crm-muted); }
        .crm-facts dd { margin: 0; min-width: 0; overflow-wrap: anywhere; }
        .crm-facts dd { display: flex; flex-wrap: wrap; justify-content: flex-end; align-items: baseline; gap: 2px 8px; text-align: right; }
        .crm-fact-value { font-weight: 600; font-variant-numeric: tabular-nums; }
        .crm-facts dd.is-missing { color: var(--crm-faint); font-style: italic; }
        .crm-src { border: 0; background: none; padding: 0; font-size: 10.5px; color: var(--crm-accent-2); text-decoration: underline; text-decoration-color: color-mix(in srgb, currentColor 35%, transparent); text-underline-offset: 2px; cursor: pointer; white-space: nowrap; }
        .crm-src.is-static { color: var(--crm-faint); text-decoration: none; cursor: default; }
        .crm-bubble.is-flash { animation: crm-flash 1.6s ease-out; }
        @keyframes crm-flash { 0%, 35% { box-shadow: 0 0 0 3px var(--crm-sol); } 100% { box-shadow: 0 0 0 3px transparent; } }
        .crm-suggest { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; border: 1px solid color-mix(in srgb, var(--crm-sol) 30%, transparent); background: var(--crm-sol-bg); border-radius: 12px; padding: 8px 10px; }
        .crm-suggest-text { flex: 1 1 220px; min-width: 0; display: grid; gap: 1px; }
        .crm-suggest-text b { font-size: 13px; font-weight: 650; }
        .crm-suggest-text span { font-size: 11.5px; color: var(--crm-muted); }
        .crm-suggest-actions { display: flex; align-items: center; gap: 12px; flex-wrap: wrap; }

        /* Carriles */
        .crm-lane { display: flex; flex-direction: column; gap: 4px; }
        .crm-lane + .crm-lane { margin-top: 8px; }
        .crm-lane-head { display: flex; align-items: center; gap: 6px; padding: 4px 6px 2px; position: sticky; top: -8px; z-index: 1; background: var(--crm-panel); }
        .crm-lane-label { flex: 1 1 auto; font-size: 10.5px; font-weight: 650; letter-spacing: .08em; text-transform: uppercase; color: var(--crm-muted); }
        .crm-lane-n { font-size: 11px; font-weight: 650; border-radius: 999px; padding: 0 7px; background: var(--crm-soft); color: var(--crm-muted); }
        .crm-lane-n.is-hot { background: var(--crm-action); color: var(--crm-action-text); }
        .crm-lane-late { font-size: 10.5px; font-weight: 650; border-radius: 999px; padding: 0 7px; background: var(--crm-fail-bg); color: var(--crm-fail); }
        .crm-timeline { list-style: none; margin: 0; padding: 0; display: grid; }
        .crm-timeline li { display: grid; grid-template-columns: 12px minmax(0, 1fr) auto; gap: 10px; position: relative; padding-bottom: 12px; font-size: 12px; }
        .crm-timeline li::before { content: ""; width: 7px; height: 7px; border-radius: 50%; background: var(--crm-line-strong); margin-top: 5px; margin-left: 2px; }
        .crm-timeline li:not(:last-child)::after { content: ""; position: absolute; left: 5px; top: 15px; bottom: 0; width: 1px; background: var(--crm-line); }
        .crm-timeline li:last-child::before { background: var(--crm-accent-2); box-shadow: 0 0 0 3px var(--crm-accent-soft); }
        .crm-timeline-label { min-width: 0; overflow-wrap: anywhere; }
        .crm-timeline time { color: var(--crm-faint); font-size: 11px; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .crm-skeleton { display: grid; gap: 8px; }
        .crm-skeleton i { display: block; height: 11px; border-radius: 6px; background: linear-gradient(90deg, var(--crm-soft) 0%, var(--crm-raised) 50%, var(--crm-soft) 100%) 0 0 / 200% 100%; animation: crm-shimmer 1.1s linear infinite; }
        @keyframes crm-shimmer { to { background-position: -200% 0; } }

        /* Confirmación */
        .crm-scrim { position: fixed; inset: 0; z-index: 50; background: rgb(8 15 30 / 42%); backdrop-filter: blur(3px); display: grid; place-items: center; padding: 16px; }
        .crm-dialog { background: var(--crm-panel); color: var(--crm-text); border: 1px solid var(--crm-line); border-radius: 16px; max-width: 26rem; width: 100%; padding: 22px; display: grid; gap: 12px; box-shadow: var(--crm-shadow-2); }
        .crm-dialog-icon { width: 38px; height: 38px; border-radius: 10px; display: grid; place-items: center; background: var(--crm-accent-soft); color: var(--crm-accent-2); }
        .crm-dialog-icon.is-danger { background: var(--crm-fail-bg); color: var(--crm-fail); }
        .crm-dialog h3 { margin: 0; font-size: 16px; font-weight: 650; letter-spacing: -0.01em; }
        .crm-dialog p { margin: 0; color: var(--crm-muted); line-height: 1.55; }
        .crm-dialog-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; margin-top: 4px; }

        /* Propuesta D: cola */
        .crm-q-title { display: flex; align-items: center; gap: 8px; }
        .crm-q-title h2 { margin: 0; font-size: 15px; font-weight: 650; letter-spacing: -0.01em; }
        .crm-live { display: inline-flex; align-items: center; gap: 5px; font-size: 11px; font-weight: 600; color: var(--crm-live); }
        .crm-live i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; animation: crm-pulse 2.4s infinite; }
        .crm-queue-top { align-items: center; }
        .crm-alerts { display: flex; align-items: center; }
        .crm-bell { width: 30px; height: 30px; border-radius: 9px; display: grid; place-items: center; border: 1px solid var(--crm-line-strong); background: var(--crm-panel); color: var(--crm-faint); cursor: pointer; }
        .crm-bell svg { width: 16px; height: 16px; }
        .crm-bell:hover { color: var(--crm-accent-2); }
        .crm-bell.is-on { border-color: transparent; background: var(--crm-live-bg); color: var(--crm-live); cursor: default; }
        .crm-bell.is-blocked { cursor: help; color: var(--crm-fail); }
        .crm-sum { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
        .crm-sum-btn { display: grid; gap: 0; text-align: left; border: 1px solid var(--crm-line); background: var(--crm-panel); border-radius: 10px; padding: 6px 9px; cursor: pointer; line-height: 1.15; transition: background .12s, border-color .12s; }
        .crm-sum-btn b { font-size: 18px; font-weight: 650; letter-spacing: -0.02em; }
        .crm-sum-btn small { font-size: 10.5px; color: var(--crm-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .crm-sum-btn:hover { background: var(--crm-raised); }
        .crm-sum-btn.is-hot b { color: var(--crm-warn); }
        .crm-sum-btn[aria-pressed="true"] { border-color: var(--crm-accent-2); background: var(--crm-accent-soft); }
        .crm-lane.is-empty .crm-lane-head { position: static; }
        .crm-lane-dot { width: 7px; height: 7px; border-radius: 50%; flex: 0 0 auto; background: var(--crm-line-strong); }
        .crm-lane-dot.is-hot { background: var(--crm-warn); }
        .crm-lane-dot.is-warm { background: var(--crm-accent-2); }
        .crm-lane-dot.is-calm { background: var(--crm-live); }
        .crm-lane-empty { font-size: 11px; color: var(--crm-faint); }
        .crm-lane.is-empty .crm-lane-label { color: var(--crm-faint); }
        .crm-row { grid-template-columns: 36px minmax(0, 1fr) auto; }
        .crm-row .crm-av { grid-row: span 3; }
        .crm-row.is-unread .crm-row-name, .crm-row.is-unread .crm-row-last { color: var(--crm-text); font-weight: 650; }
        .crm-av { position: relative; width: 36px; height: 36px; border-radius: 50%; display: grid; place-items: center; font-size: 11.5px; font-weight: 650; color: var(--crm-sla, var(--crm-accent-2)); background: var(--crm-panel); box-shadow: inset 0 0 0 2px var(--crm-sla, var(--crm-line-strong)); flex: 0 0 auto; }
        .crm-av.is-ok { --crm-sla: var(--crm-accent-2); }
        .crm-av.is-quiet { --crm-sla: var(--crm-line-strong); color: var(--crm-muted); background: var(--crm-soft); }
        .crm-av.is-lg { width: 40px; height: 40px; font-size: 12.5px; }
        .crm-av-badge { position: absolute; right: -4px; bottom: -4px; width: 17px; height: 17px; border-radius: 50%; display: grid; place-items: center; font-size: 10px; font-weight: 800; font-style: normal; background: var(--crm-panel); color: var(--crm-muted); box-shadow: 0 0 0 2px var(--crm-panel), inset 0 0 0 1px var(--crm-line-strong); }
        .crm-av-badge svg { width: 10px; height: 10px; }
        .crm-av-badge.is-for { color: var(--crm-accent-2); }
        .crm-av-badge.is-late { background: var(--crm-fail); color: #fff; box-shadow: 0 0 0 2px var(--crm-panel); }
        .crm-row.is-selected .crm-av-badge { box-shadow: 0 0 0 2px var(--crm-accent-soft), inset 0 0 0 1px var(--crm-line-strong); }
        .crm-row-draft { color: var(--crm-warn); font-weight: 600; }
        .crm-ticks { color: var(--crm-accent-2); font-size: 10.5px; letter-spacing: -2px; margin-right: 3px; }
        .crm-tag.is-doc { background: var(--crm-accent-soft); color: var(--crm-accent-2); font-variant-numeric: tabular-nums; }
        .crm-tag.is-area { background: none; padding: 0; color: var(--crm-faint); }
        .crm-row-quiet { font-size: 11px; color: var(--crm-faint); white-space: nowrap; }
        .crm-new { font-size: 10.5px; font-weight: 700; color: var(--crm-action-text); background: var(--crm-action); border-radius: 999px; padding: 0 7px; }
        .crm-row-sla { grid-column: 2 / 4; height: 3px; border-radius: 2px; background: var(--crm-soft); overflow: hidden; margin-top: 3px; }
        .crm-row-sla i { display: block; height: 100%; background: var(--crm-sla, var(--crm-line-strong)); transition: width 1s linear; }
        .crm-row-sla i.is-ok { background: var(--crm-line-strong); }
        .crm-q-hint { margin: 10px 8px 0; font-size: 12px; color: var(--crm-faint); line-height: 1.5; }
        .crm-q-hint b { color: var(--crm-muted); font-weight: 600; }

        /* Propuesta D: encabezado del caso */
        .crm-case-head { padding: 10px 14px; gap: 12px; }
        .crm-case-chips { display: flex; flex-wrap: wrap; align-items: center; gap: 5px 6px; margin-top: 3px; }
        .crm-chip-state { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; font-weight: 600; border-radius: 999px; padding: 2px 9px 2px 8px; color: var(--crm-sla, var(--crm-muted)); background: var(--crm-sla-bg, var(--crm-soft)); white-space: nowrap; }
        .crm-chip-state i { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }
        .crm-chip-state.is-mine, .crm-chip-state.is-live { color: var(--crm-live); background: var(--crm-live-bg); }
        .crm-chip-state.is-for { color: var(--crm-accent-2); background: var(--crm-accent-soft); }
        .crm-chip-soft { font-size: 11.5px; border-radius: 999px; padding: 2px 9px; background: var(--crm-soft); color: var(--crm-muted); white-space: nowrap; }
        .crm-pop-wrap { position: relative; display: inline-flex; min-width: 0; }
        .crm-chip-sol { display: inline-flex; align-items: center; gap: 6px; font-size: 11.5px; border: 0; border-radius: 999px; padding: 2px 9px 2px 3px; background: var(--crm-sol-bg); color: var(--crm-text); cursor: help; white-space: nowrap; max-width: 22rem; overflow: hidden; text-overflow: ellipsis; }
        .crm-sol-k { display: inline-flex; align-items: center; border-radius: 999px; padding: 1px 7px; font-size: 10.5px; font-weight: 700; background: var(--crm-sol); color: var(--crm-panel); white-space: nowrap; }
        .crm-pop { position: absolute; top: calc(100% + 6px); left: 0; z-index: 30; width: min(22rem, 80vw); display: grid; gap: 3px; padding: 12px; border-radius: 12px; background: var(--crm-panel); border: 1px solid var(--crm-line); box-shadow: var(--crm-shadow-2); font-size: 12.5px; color: var(--crm-text); white-space: normal; }
        .crm-pop b { font-size: 10.5px; letter-spacing: .08em; text-transform: uppercase; color: var(--crm-faint); font-weight: 650; margin-top: 4px; }
        .crm-pop b:first-child { margin-top: 0; }
        .crm-case-phone { font-size: 12px; color: var(--crm-muted); margin-left: 2px; }

        /* Propuesta D: hilo */
        .crm-event.is-start { margin: 4px 0 2px; }
        .crm-event.is-start .crm-link { font-size: 11.5px; }
        .crm-day { align-self: center; margin: 14px 0 4px; font-size: 11px; font-weight: 600; color: var(--crm-muted); background: var(--crm-panel); border: 1px solid var(--crm-line); border-radius: 999px; padding: 1px 10px; position: sticky; top: 0; z-index: 1; }
        .crm-card-event { align-self: center; display: grid; gap: 3px; justify-items: center; text-align: center; max-width: 30rem; margin: 8px 0; padding: 8px 12px; border-radius: 12px; background: var(--crm-sol-bg); font-size: 12.5px; }
        .crm-card-event-sub { color: var(--crm-muted); font-size: 12px; }
        .crm-bubble.is-bot { background: var(--crm-soft); }
        .crm-bubble.is-card { padding: 0; overflow: hidden; width: min(21rem, 80%); }
        .crm-qcard-head { display: flex; align-items: center; gap: 8px; padding: 10px 12px 8px; border-bottom: 1px solid color-mix(in srgb, var(--crm-accent-2) 22%, transparent); }
        .crm-qcard-head .crm-doc { margin: 0; background: var(--crm-fail-bg); color: var(--crm-fail); font-weight: 700; }
        .crm-qcard-head b { font-size: 13.5px; }
        .crm-qcard-body { margin: 0; padding: 8px 12px 0; display: grid; grid-template-columns: auto 1fr; gap: 2px 12px; font-size: 12.5px; }
        .crm-qcard-body dt { color: var(--crm-muted); }
        .crm-qcard-body dd { margin: 0; text-align: right; }
        .crm-qcard-total { display: flex; justify-content: space-between; align-items: baseline; padding: 6px 12px 0; }
        .crm-qcard-total b { font-size: 18px; font-weight: 650; letter-spacing: -0.02em; }
        .crm-qcard-total span { font-size: 11.5px; color: var(--crm-muted); }
        .crm-bubble.is-card .crm-meta { padding: 0 12px 6px; }
        .crm-qcard-action { display: block; width: 100%; border: 0; border-top: 1px solid color-mix(in srgb, var(--crm-accent-2) 22%, transparent); background: none; padding: 8px; font-size: 12.5px; font-weight: 600; color: var(--crm-accent-2); cursor: pointer; }
        .crm-qcard-action:hover { background: color-mix(in srgb, var(--crm-accent-2) 8%, transparent); }

        /* Propuesta D: cuadro de respuesta */
        .crm-quote-top { display: flex; align-items: center; gap: 10px; }
        .crm-quote-top b { font-size: 13px; }
        .crm-quote-top .crm-hint { flex: 1 1 auto; }
        .crm-icon-btn.is-sm { width: 26px; height: 26px; }
        .crm-icon-btn.is-sm svg { width: 13px; height: 13px; }
        .crm-quote-grid { grid-template-columns: minmax(8rem, 1.5fr) minmax(5rem, .9fr) minmax(6rem, .9fr) minmax(7rem, 1fr) auto; align-items: end; }
        .crm-compose { position: relative; flex-direction: column; align-items: stretch; gap: 2px; padding: 4px 6px 6px; }
        .crm-compose textarea { padding: 8px 6px 4px; }
        .crm-compose-bar { display: flex; align-items: center; gap: 2px; }
        .crm-tool { display: inline-flex; align-items: center; gap: 6px; border: 0; background: none; color: var(--crm-muted); font-size: 12px; font-weight: 600; padding: 6px 8px; border-radius: 8px; cursor: pointer; white-space: nowrap; }
        .crm-tool svg { width: 14px; height: 14px; }
        .crm-tool:hover { background: var(--crm-soft); color: var(--crm-text); }
        .crm-tool .crm-kbd { margin-left: 2px; }
        .crm-compose-bar .crm-send { margin-left: auto; }
        .crm-qr-pop { position: absolute; left: 6px; bottom: calc(100% + 6px); z-index: 30; width: min(26rem, calc(100% - 12px)); display: grid; gap: 2px; padding: 6px; border-radius: 12px; background: var(--crm-panel); border: 1px solid var(--crm-line); box-shadow: var(--crm-shadow-2); }
        .crm-qr-item { display: grid; gap: 1px; text-align: left; border: 0; background: none; padding: 7px 9px; border-radius: 8px; cursor: pointer; }
        .crm-qr-item b { font-size: 12.5px; }
        .crm-qr-item span { font-size: 11.5px; color: var(--crm-muted); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .crm-qr-item[aria-selected="true"] { background: var(--crm-accent-soft); }
        .crm-qr-none { margin: 6px 9px; font-size: 12px; color: var(--crm-muted); }
        .crm-qr-help { margin: 4px 9px 2px; font-size: 10.5px; color: var(--crm-faint); display: flex; gap: 4px; align-items: center; flex-wrap: wrap; }

        /* Propuesta D: panel del cliente */
        .crm-side { width: 20rem; }
        .crm-id { align-items: flex-start; gap: 10px; padding: 12px 10px 12px 14px; }
        .crm-id-text { min-width: 0; flex: 1 1 auto; display: grid; gap: 1px; }
        .crm-id-name { font-size: 14px; font-weight: 650; letter-spacing: -0.01em; line-height: 1.3; overflow-wrap: anywhere; }
        .crm-id-phone { display: inline-flex; align-items: center; gap: 4px; font-size: 12px; color: var(--crm-muted); }
        .crm-copy { width: 22px; height: 22px; border: 0; background: none; border-radius: 6px; display: grid; place-items: center; color: var(--crm-faint); cursor: pointer; }
        .crm-copy:hover { background: var(--crm-soft); color: var(--crm-text); }
        .crm-copy svg { width: 12px; height: 12px; }
        .crm-id-meta { font-size: 11.5px; color: var(--crm-faint); }
        .crm-side.is-collapsed .crm-id .crm-av, .crm-side.is-collapsed .crm-id-text { display: none; }
        .crm-h { margin: 0; display: flex; align-items: center; gap: 8px; font-size: 10.5px; font-weight: 650; letter-spacing: .08em; text-transform: uppercase; color: var(--crm-faint); }
        .crm-h .crm-sol-k { letter-spacing: .02em; text-transform: none; }
        .crm-need { margin: 0; font-size: 13px; line-height: 1.5; color: var(--crm-text); }
        .crm-copilot-summary { color: var(--crm-muted); font-size: 12px; }
        .crm-copilot-note { margin: 0; font-size: 12px; color: var(--crm-muted); line-height: 1.5; }
        .crm-copilot-note span { font-weight: 650; color: var(--crm-faint); margin-right: 2px; }
        .crm-prog { flex: 1 1 auto; max-width: 5rem; height: 4px; border-radius: 2px; background: var(--crm-soft); overflow: hidden; margin-left: auto; }
        .crm-prog i { display: block; height: 100%; background: var(--crm-live); transition: width .3s ease; }
        .crm-prog-n { letter-spacing: 0; text-transform: none; font-weight: 600; color: var(--crm-muted); }
        .crm-facts { grid-template-columns: 4.5rem minmax(0, 1fr); gap: 9px 10px; }
        .crm-facts dd { flex-direction: column; align-items: flex-end; gap: 0; }
        .crm-fact-value { font-weight: 600; color: var(--crm-text); }
        .crm-facts dd.is-missing { flex-direction: row; justify-content: flex-end; align-items: center; gap: 8px; font-style: normal; }
        .crm-facts dd.is-missing > span { color: var(--crm-warn); font-weight: 600; }
        .crm-ask { border: 1px solid var(--crm-line-strong); background: var(--crm-panel); border-radius: 7px; padding: 1px 8px; font-size: 11.5px; font-weight: 600; cursor: pointer; }
        .crm-ask:hover { border-color: var(--crm-accent-2); color: var(--crm-accent-2); }
        .crm-btn.is-block { width: 100%; justify-content: center; }
        .crm-ficha { display: flex; gap: 10px; align-items: flex-start; border: 1px dashed var(--crm-line-strong); border-radius: 11px; padding: 10px 12px; font-size: 12px; color: var(--crm-muted); }
        .crm-ficha b { display: block; font-size: 13px; color: var(--crm-text); margin-bottom: 1px; }
        .crm-ficha-ico { width: 24px; height: 24px; border-radius: 7px; flex: 0 0 auto; display: grid; place-items: center; font-weight: 700; background: var(--crm-soft); color: var(--crm-muted); }
        .crm-ficha.is-match { border-style: solid; border-color: color-mix(in srgb, var(--crm-live) 35%, transparent); background: var(--crm-live-bg); }
        .crm-ficha.is-match .crm-ficha-ico { background: var(--crm-panel); color: var(--crm-live); }
        .crm-props { list-style: none; margin: 0; padding: 0; display: grid; gap: 4px; }
        .crm-props li { display: grid; grid-template-columns: auto minmax(0, 1fr) auto; gap: 8px; align-items: center; padding: 6px 8px; border-radius: 9px; font-size: 12px; }
        .crm-props li.is-current { background: var(--crm-raised); }
        .crm-props b { font-weight: 650; white-space: nowrap; }
        .crm-prop-text { min-width: 0; color: var(--crm-muted); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .crm-who { font-size: 10.5px; font-weight: 650; border-radius: 6px; padding: 0 6px; white-space: nowrap; }
        .crm-who.is-sol { background: var(--crm-sol-bg); color: var(--crm-sol); }
        .crm-who.is-team { background: var(--crm-accent-soft); color: var(--crm-accent-2); }
        .crm-acc { display: flex; align-items: center; gap: 8px; width: 100%; border: 0; border-top: 1px solid var(--crm-line); background: none; padding: 12px 0 0; cursor: pointer; font-size: 10.5px; font-weight: 650; letter-spacing: .08em; text-transform: uppercase; color: var(--crm-faint); text-align: left; }
        .crm-acc span { letter-spacing: 0; text-transform: none; font-weight: 400; }
        .crm-acc svg { width: 12px; height: 12px; margin-left: auto; transition: transform .15s; }
        .crm-acc svg.is-open { transform: rotate(90deg); }

        @media (max-width: 1100px) {
            .crm-inbox { height: auto; min-height: 0; }
            .crm-board { height: auto; grid-template-columns: minmax(0, 1fr); }
            .crm-pane { min-height: 22rem; }
            .crm-list { max-height: 22rem; }
            .crm-board.has-case .crm-talk { order: -1; min-height: 70dvh; }
            .crm-side, .crm-side.is-collapsed { width: auto; min-height: 0; }
            .crm-side.is-collapsed .crm-side-body { display: grid; }
            .crm-side-head .crm-icon-btn { display: none; }
            .crm-list-foot { display: none; }
            .crm-bubble { max-width: 88%; }
        }

        @media (prefers-reduced-motion: reduce) {
            .crm-inbox *, .crm-inbox *::before { animation: none !important; transition: none !important; }
        }
    </style>

    @php
        $viewer = $viewerId;
        $rows = $board['rows'];
        $mine = $case !== null && $case['taken'] && $viewer !== null && $case['taken_by'] === $viewer;
        $context = $case['context'] ?? [];
        $currency = (string) config('crm-inbox.quote_currency', 'USD');
        $quickReplies = $case !== null && $case['taken'] ? $this::quickReplies($case) : [];
        $copilot = $case !== null ? $this->copilot($case) : null;
        $suggestion = $mine ? ($copilot['suggestion'] ?? null) : null;
        $lanes = \App\Support\CrmInbox\CrmInboxQueue::lanes($rows, $viewer, true);
        $laneCount = collect($lanes)->mapWithKeys(fn (array $lane): array => [$lane['key'] => count($lane['rows'])]);
        $givenName = $case !== null && $case['name'] !== 'Sin nombre' ? \App\Support\CrmInbox\CrmAnalystName::given($case['name']) : '';
        $hhmm = fn (?int $timestamp): string => $timestamp ? \Illuminate\Support\Carbon::createFromTimestamp($timestamp)->timezone('America/Caracas')->format('H:i') : '';
    @endphp

    <div
        class="crm-inbox"
        data-case="{{ $case['handoff_id'] ?? '' }}"
        x-data="{
            q: '',
            filter: 'all',
            sideOpen: true,
            now: Date.now(),
            skew: {{ $serverNow }} * 1000 - Date.now(),
            warn: {{ $slaWarnSeconds }},
            late: {{ $slaLateSeconds }},
            me: @js($viewer),
            taking: false,
            confirm: { open: false, title: '', body: '', label: '', danger: true, run: null },
            timer: null,
            ticks: 0,
            alerted: {},
            seen: {},
            init() {
                try {
                    this.filter = localStorage.getItem('crm-inbox-filter') || 'all';
                    this.sideOpen = localStorage.getItem('crm-inbox-side') !== 'closed';
                    this.seen = JSON.parse(localStorage.getItem('crm-inbox-seen') || '{}') || {};
                } catch (error) {}
                if (!['all', 'reply', 'open', 'waiting'].includes(this.filter)) this.filter = 'all';
                this.markSeen();
                this.$nextTick(() => this.watchLate(true));
                this.timer = setInterval(() => {
                    this.now = Date.now();
                    if (++this.ticks % 10 === 0) this.watchLate(false);
                }, 1000);
            },
            lateRows(lane) {
                return [...this.$root.querySelectorAll('.crm-lane[data-lane=' + lane + '] .crm-row')].filter((row) => {
                    const waited = this.waited(Number(row.dataset.since) || null);
                    return waited !== null && waited >= this.late;
                });
            },
            watchLate(quiet) {
                ['reply', 'open'].forEach((lane) => {
                    this.lateRows(lane).forEach((row) => {
                        const key = row.dataset.handoff + ':' + row.dataset.since;
                        if (this.alerted[key]) return;
                        this.alerted[key] = true;
                        if (quiet) return;
                        window.dispatchEvent(new CustomEvent('crm-late', { detail: { id: row.dataset.handoff, since: row.dataset.since, name: row.dataset.name || 'Un cliente', lane } }));
                    });
                });
            },
            async jump(anchor) {
                if (!anchor) return;
                let el = document.getElementById(anchor);
                if (!el && anchor.startsWith('crm-bot-')) {
                    await this.$wire.openSummary();
                    await new Promise((resolve) => setTimeout(resolve, 80));
                    el = document.getElementById(anchor);
                }
                if (!el) return;
                const still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
                el.scrollIntoView({ block: 'center', behavior: still ? 'auto' : 'smooth' });
                el.classList.remove('is-flash');
                void el.offsetWidth;
                el.classList.add('is-flash');
            },
            destroy() {
                clearInterval(this.timer);
            },
            waited(since) {
                if (!since) return null;
                return Math.max(0, Math.floor((this.now + this.skew) / 1000) - since);
            },
            clock(since) {
                const s = this.waited(since);
                if (s === null) return '';
                if (s < 3600) return Math.floor(s / 60) + ':' + String(s % 60).padStart(2, '0');
                if (s < 86400) return Math.floor(s / 3600) + ' h ' + String(Math.floor((s % 3600) / 60)).padStart(2, '0') + ' min';
                return Math.floor(s / 86400) + ' d ' + Math.floor((s % 86400) / 3600) + ' h';
            },
            level(since, held) {
                if (held) return 'is-held';
                const s = this.waited(since);
                if (s === null || s < this.warn) return 'is-ok';
                return s < this.late ? 'is-warn' : 'is-late';
            },
            ring(since, held) {
                const s = this.waited(since);
                return '--crm-p:' + (held ? 100 : (s === null ? 0 : Math.min(100, (s / this.late) * 100)));
            },
            visible(el) {
                const needle = this.q.trim().toLocaleLowerCase();
                const text = (el.dataset.find || '').toLocaleLowerCase();
                if (needle !== '' && !text.includes(needle)) return false;
                if (this.filter !== 'all' && el.closest('[data-lane]')?.dataset.lane !== this.filter) return false;
                return true;
            },
            setFilter(value) {
                this.filter = this.filter === value ? 'all' : value;
                try { localStorage.setItem('crm-inbox-filter', this.filter) } catch (error) {}
            },
            markSeen() {
                const id = this.$root.dataset.case || '';
                if (id === '') return;
                this.seen[id] = Math.floor((Date.now() + this.skew) / 1000);
                const keys = Object.keys(this.seen);
                if (keys.length > 300) keys.sort((a, b) => this.seen[a] - this.seen[b]).slice(0, keys.length - 300).forEach((key) => delete this.seen[key]);
                try { localStorage.setItem('crm-inbox-seen', JSON.stringify(this.seen)) } catch (error) {}
            },
            unread(el) {
                if (el.classList.contains('is-selected')) return false;
                const lane = el.closest('[data-lane]')?.dataset.lane;
                if (lane !== 'reply' && lane !== 'open') return false;
                const since = Number(el.dataset.since) || 0;
                return since > 0 && since > (this.seen[el.dataset.handoff] || 0);
            },
            draftOf(id) {
                try { return (sessionStorage.getItem('crm-draft-' + id) || '').trim() } catch (error) { return '' }
            },
            ago(since) {
                const s = this.waited(since);
                if (s === null) return '';
                if (s < 60) return 'menos de 1 min';
                if (s < 3600) return Math.floor(s / 60) + ' min';
                if (s < 86400) return Math.floor(s / 3600) + ' h ' + Math.floor((s % 3600) / 60) + ' min';
                return Math.floor(s / 86400) + ' d';
            },
            toggleSide() {
                this.sideOpen = !this.sideOpen;
                try { localStorage.setItem('crm-inbox-side', this.sideOpen ? 'open' : 'closed') } catch (error) {}
            },
            ask(options) {
                this.confirm = { open: true, danger: true, action: '', ...options };
            },
            accept() {
                const action = this.confirm.action;
                const run = this.confirm.run;
                this.confirm.open = false;
                if (action === 'release') this.$wire.release();
                else if (action === 'close') this.$wire.close();
                else if (typeof run === 'function') run();
            },
            async take() {
                if (this.taking || !this.$root.querySelector('[data-take]')) return;
                this.taking = true;
                try { await this.$wire.take() } finally { this.taking = false }
            },
            step(direction) {
                const rows = [...this.$root.querySelectorAll('.crm-row')].filter((row) => row.offsetParent !== null);
                if (rows.length === 0) return;
                const current = rows.findIndex((row) => row.classList.contains('is-selected'));
                const next = rows[Math.min(rows.length - 1, Math.max(0, current === -1 ? 0 : current + direction))];
                if (next && !next.classList.contains('is-selected')) {
                    next.click();
                    next.scrollIntoView({ block: 'nearest' });
                }
            },
            keys(event) {
                if (this.confirm.open || event.defaultPrevented || event.metaKey || event.ctrlKey || event.altKey) return;
                const target = event.target;
                if (target && (target.closest('input, textarea, select, [contenteditable]') || target.closest('.fi-modal'))) return;
                const key = event.key.toLowerCase();
                if (key === 'j' || key === 'k') { event.preventDefault(); this.step(key === 'j' ? 1 : -1); }
                else if (key === 't') { event.preventDefault(); this.take(); }
                else if (key === 'q') { const quote = this.$root.querySelector('[data-quote]'); if (quote) { event.preventDefault(); quote.click(); } }
                else if (key === 'r') { const draft = this.$root.querySelector('.crm-compose textarea'); if (draft) { event.preventDefault(); draft.focus(); } }
                else if (key === '/') { event.preventDefault(); this.$refs.search && this.$refs.search.focus(); }
            },
            follow(force) {
                const el = this.$refs.stream;
                if (!el) return;
                const gap = el.scrollHeight - el.scrollTop - el.clientHeight;
                if (force || gap < 160) el.scrollTop = el.scrollHeight;
            }
        }"
        x-on:keydown.window="keys($event)"
        x-on:crm-ask="ask($event.detail)"
        x-init="
            $nextTick(() => follow(true));
            if (window.Livewire && !window.__crmInboxHook) {
                window.__crmInboxHook = true;
                Livewire.hook('morphed', () => {
                    const root = document.querySelector('.crm-inbox');
                    if (root && window.Alpine) Alpine.$data(root)?.markSeen?.();
                    const el = root?.querySelector('.crm-stream');
                    if (!root || !el) return;
                    const id = root.dataset.case || '';
                    const changed = id !== (root.dataset.seen || '');
                    root.dataset.seen = id;
                    const gap = el.scrollHeight - el.scrollTop - el.clientHeight;
                    if (changed || gap < 160) el.scrollTop = el.scrollHeight;
                });
            }
        "
    >
        {{-- Sondeo por huella: si la cola y el hilo no cambiaron, el servidor no repinta. 3 s solo con un caso en atención. --}}
        @if ($case !== null && $case['taken'])
            <span wire:key="crm-poll-fast" wire:poll.3s="heartbeat" hidden></span>
        @else
            <span wire:key="crm-poll-slow" wire:poll.10s="heartbeat" hidden></span>
        @endif

        <div class="crm-board {{ $case ? 'has-case' : '' }}">
            <section class="crm-pane" aria-label="Cola">
                <div class="crm-queue-head">
                    <div class="crm-queue-top">
                        <div class="crm-q-title">
                            <h2>Cola</h2>
                            <span class="crm-live" title="La cola se actualiza sola"><i aria-hidden="true"></i>En vivo</span>
                            @if ($board['count'] === 0)
                                <span class="crm-sr">Nadie está esperando.</span>
                            @endif
                        </div>
                        <div
                            class="crm-alerts"
                            wire:ignore
                            x-data="{
                                state: 'off',
                                key: @js((string) config('crm-inbox.push_public_key')),
                                async init() {
                                    if (this.key === '' || !('Notification' in window) || !('serviceWorker' in navigator) || !('PushManager' in window)) {
                                        this.state = 'unavailable';
                                        return;
                                    }
                                    if (Notification.permission === 'denied') {
                                        this.state = 'blocked';
                                        return;
                                    }
                                    if (Notification.permission === 'granted') {
                                        await this.remember();
                                    }
                                },
                                async worker() {
                                    return navigator.serviceWorker.register('/crm-inbox/sw.js?v=5', { scope: '/crm-inbox/' });
                                },
                                async remember() {
                                    const reg = await this.worker();
                                    const existing = await reg.pushManager.getSubscription();
                                    if (!existing) {
                                        this.state = 'off';
                                        return;
                                    }
                                    await this.$wire.savePushSubscription(existing.toJSON(), true);
                                    this.state = 'on';
                                },
                                keyBytes(value) {
                                    const padding = '='.repeat((4 - (value.length % 4)) % 4);
                                    const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
                                    const raw = atob(base64);
                                    const bytes = new Uint8Array(raw.length);
                                    for (let index = 0; index < raw.length; index++) bytes[index] = raw.charCodeAt(index);
                                    return bytes;
                                },
                                async enable() {
                                    try {
                                        const permission = await Notification.requestPermission();
                                        if (permission !== 'granted') {
                                            this.state = permission === 'denied' ? 'blocked' : 'off';
                                            return;
                                        }
                                        const reg = await this.worker();
                                        const sub = await reg.pushManager.subscribe({
                                            userVisibleOnly: true,
                                            applicationServerKey: this.keyBytes(this.key),
                                        });
                                        await this.$wire.savePushSubscription(sub.toJSON());
                                        this.state = 'on';
                                    } catch {
                                        this.state = 'off';
                                    }
                                }
                            }"
                        >
                            <button type="button" class="crm-bell" x-show="state === 'off'" x-on:click="enable()" title="Activar avisos en este navegador" aria-label="Activar avisos">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 11V7a4 4 0 018 0v4l1 1.5H3z"/><path d="M6.5 14a1.5 1.5 0 003 0"/><path d="M2 2l12 12"/></svg>
                            </button>
                            <span class="crm-bell is-on" x-cloak x-show="state === 'on'" title="Avisos activos: este navegador te avisa cuando entra un caso" role="img" aria-label="Avisos activos">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 11V7a4 4 0 018 0v4l1 1.5H3z"/><path d="M6.5 14a1.5 1.5 0 003 0"/></svg>
                            </span>
                            <span class="crm-bell is-blocked" x-cloak x-show="state === 'blocked'" title="Avisos bloqueados en este navegador. Actívalos en la configuración del sitio." role="img" aria-label="Avisos bloqueados">
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 11V7a4 4 0 018 0v4l1 1.5H3z"/><path d="M6.5 14a1.5 1.5 0 003 0"/><path d="M2 2l12 12"/></svg>
                            </span>
                        </div>
                    </div>

                    <div
                        wire:ignore
                        x-data="{
                            incomingTitle: '',
                            incomingBody: '',
                            incomingId: '',
                            known: [],
                            seen: [],
                            init() {
                                this.known = this.ids();
                                if ('serviceWorker' in navigator) {
                                    navigator.serviceWorker.addEventListener('message', (event) => {
                                        const data = event.data || {};
                                        if (data.type === 'crm-handoff') this.ring(data.tag, data.title, data.body);
                                        if (data.type === 'crm-open' && data.handoffId) this.$wire.select(String(data.handoffId));
                                    });
                                }
                                if (window.Livewire) {
                                    Livewire.hook('morphed', () => this.watchQueue());
                                }
                            },
                            ids() {
                                return [...document.querySelectorAll('.crm-list [data-handoff]')].map((el) => el.dataset.handoff);
                            },
                            watchQueue() {
                                const current = this.ids();
                                current.filter((id) => !this.known.includes(id)).forEach((id) => {
                                    this.ring('crm-handoff-' + id, 'Nueva conversación', 'Hay una persona esperando.');
                                });
                                this.known = current;
                            },
                            ring(tag, title, body) {
                                tag = String(tag || '');
                                if (tag === '' || this.seen.includes(tag)) return;
                                this.seen.push(tag);
                                const id = tag.replace(/^crm-(?:handoff|late)-/, '').split('-')[0];
                                if (id !== '' && !this.known.includes(id)) this.known.push(id);
                                this.incomingId = id;
                                this.incomingTitle = title || 'Nueva conversación';
                                this.incomingBody = body || 'Hay una persona esperando.';
                                this.beep();
                                if (id !== '') this.$wire.announce(this.incomingTitle, this.incomingBody, id);
                                if (document.visibilityState !== 'visible' || !('Notification' in window) || Notification.permission !== 'granted') return;
                                try {
                                    new Notification(title || 'Nueva conversación', { body: body || '', tag, requireInteraction: true });
                                } catch (error) {}
                            },
                            beep() {
                                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                                if (!AudioCtx) return;
                                const ctx = this.audio || new AudioCtx();
                                this.audio = ctx;
                                ctx.resume().then(() => {
                                    const now = ctx.currentTime;
                                    [880, 1175].forEach((freq, index) => {
                                        const osc = ctx.createOscillator();
                                        const gain = ctx.createGain();
                                        const at = now + (index * 0.16);
                                        osc.frequency.value = freq;
                                        osc.connect(gain);
                                        gain.connect(ctx.destination);
                                        gain.gain.setValueAtTime(0.0001, at);
                                        gain.gain.exponentialRampToValueAtTime(0.08, at + 0.02);
                                        gain.gain.exponentialRampToValueAtTime(0.0001, at + 0.18);
                                        osc.start(at);
                                        osc.stop(at + 0.2);
                                    });
                                }).catch(() => {});
                            }
                        }"
                        x-cloak
                        x-show="incomingTitle !== ''"
                        x-on:crm-late.window="ring(
                            'crm-late-' + $event.detail.id + '-' + $event.detail.since,
                            $event.detail.name + ' lleva más de ' + Math.round({{ $slaLateSeconds }} / 60) + ' min sin respuesta',
                            $event.detail.lane === 'open' ? 'Nadie lo ha tomado todavía.' : 'Te toca responder.'
                        )"
                    >
                        <button type="button" class="crm-arrived" x-on:click="if (incomingId !== '') $wire.select(incomingId); incomingTitle = ''">
                            <span class="crm-arrived-title" x-text="incomingTitle"></span>
                            <span class="crm-arrived-body" x-text="incomingBody"></span>
                        </button>
                    </div>

                    <div class="crm-sum" role="group" aria-label="Filtrar la cola">
                        @foreach (['reply' => 'te toca', 'open' => 'sin tomar', 'waiting' => 'esperando'] as $key => $label)
                            <button
                                type="button"
                                class="crm-sum-btn {{ $key === 'reply' && ($laneCount[$key] ?? 0) > 0 ? 'is-hot' : '' }}"
                                x-on:click="setFilter(@js($key))"
                                x-bind:aria-pressed="filter === @js($key)"
                                aria-pressed="false"
                                title="Mostrar solo «{{ $label }}». Otro clic muestra todo."
                            ><b class="crm-num">{{ $laneCount[$key] ?? 0 }}</b><small>{{ $label }}</small></button>
                        @endforeach
                    </div>

                    <div class="crm-search" wire:ignore>
                        <input type="search" x-ref="search" x-model="q" placeholder="Buscar nombre, teléfono o mensaje" aria-label="Buscar en la cola" autocomplete="off" x-on:keydown.escape="q = ''; $el.blur()">
                        <span class="crm-kbd" aria-hidden="true">/</span>
                    </div>
                </div>

                <div class="crm-list">
                    @foreach ($lanes as $lane)
                        @php
                            $laneRows = $lane['rows'];
                            $dot = match (true) {
                                $laneRows === [] => 'is-calm',
                                $lane['key'] === 'reply' => 'is-hot',
                                $lane['key'] === 'open' => 'is-warm',
                                default => 'is-quiet',
                            };
                        @endphp
                        <div
                            class="crm-lane {{ $laneRows === [] ? 'is-empty' : '' }}"
                            data-lane="{{ $lane['key'] }}"
                            wire:key="lane-{{ $lane['key'] }}"
                            x-show="filter === 'all' || filter === @js($lane['key'])"
                        >
                            <div class="crm-lane-head" title="{{ $lane['hint'] }}">
                                <span class="crm-lane-dot {{ $dot }}" aria-hidden="true"></span>
                                <span class="crm-lane-label">{{ $lane['label'] }}</span>
                                @if ($laneRows === [])
                                    <span class="crm-lane-empty">{{ $lane['empty'] }}</span>
                                @else
                                    @if (in_array($lane['key'], ['reply', 'open'], true))
                                        <span class="crm-lane-late" x-cloak x-show="now && lateRows(@js($lane['key'])).length > 0" x-text="lateRows(@js($lane['key'])).length + ' tarde'"></span>
                                    @endif
                                    <span class="crm-lane-n crm-num">{{ count($laneRows) }}</span>
                                @endif
                            </div>
                            @foreach ($laneRows as $row)
                                @php
                                    $lane_ = $lane['key'];
                                    $selected = $case !== null && $case['handoff_id'] === $row['handoff_id'];
                                    $forViewer = ! $row['taken'] && $viewer !== null && $row['assigned_to'] === $viewer;
                                    $since = $row['waiting_since'] ?? 'null';
                                    $oursLast = is_int($row['last_reply_at'] ?? null) && (! is_int($row['waiting_since']) || $row['last_reply_at'] >= $row['waiting_since']);
                                    $hot = in_array($lane_, ['reply', 'open'], true);
                                @endphp
                                <button
                                    type="button"
                                    wire:key="handoff-{{ $row['handoff_id'] }}"
                                    data-handoff="{{ $row['handoff_id'] }}"
                                    data-find="{{ $row['name'] }} {{ $row['phone_label'] }} {{ $row['last_line'] }}"
                                    data-assigned="{{ $row['assigned_to'] ?? '' }}"
                                    data-taker="{{ $row['taken_by'] ?? '' }}"
                                    data-since="{{ $row['waiting_since'] ?? '' }}"
                                    data-name="{{ $row['name'] }}"
                                    wire:click="select('{{ $row['handoff_id'] }}')"
                                    class="crm-row {{ $selected ? 'is-selected' : '' }}"
                                    @if ($selected) aria-current="true" @endif
                                    x-show="visible($el)"
                                    x-bind:class="now && unread($el) && 'is-unread'"
                                >
                                    <span class="crm-av {{ $hot ? '' : 'is-quiet' }}" @if ($hot) x-bind:class="level({{ $since }}, false)" @endif aria-hidden="true">
                                        {{ $mark($row['name']) }}
                                        @if ($lane_ === 'waiting')
                                            <i class="crm-av-badge" title="Esperas al cliente"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><circle cx="8" cy="8" r="6"/><path d="M8 5v3l2 1.5"/></svg></i>
                                        @elseif ($forViewer)
                                            <i class="crm-av-badge is-for" title="Te lo pasaron"><svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 8h9M9 4.5L12.5 8 9 11.5"/></svg></i>
                                        @elseif ($hot)
                                            <i class="crm-av-badge is-late" x-cloak x-show="level({{ $since }}, false) === 'is-late'" title="Va tarde">!</i>
                                        @endif
                                    </span>
                                    <span class="crm-row-name">{{ $row['name'] }}</span>
                                    @if ($lane_ === 'waiting')
                                        <span class="crm-row-time" title="Tu último mensaje">{{ $hhmm($row['last_reply_at'] ?? null) }}</span>
                                    @elseif ($lane_ === 'others')
                                        <span class="crm-row-time">{{ $hhmm($row['waiting_since'] ?? null) }}</span>
                                    @else
                                        <span
                                            class="crm-row-time"
                                            x-bind:class="level({{ $since }}, false)"
                                            @if ($row['waiting_since']) x-text="clock({{ $since }})" @endif
                                            title="Tiempo desde el último mensaje del cliente"
                                        >{{ $row['waiting'] }}</span>
                                    @endif
                                    <span class="crm-row-last" x-data="{ draft: draftOf(@js($row['handoff_id'])) }">
                                        <template x-if="draft !== '' && !$el.closest('.crm-row')?.classList.contains('is-selected')"><span><span class="crm-row-draft">Borrador:</span> <span x-text="draft"></span></span></template>
                                        <span x-show="draft === '' || $el.closest('.crm-row')?.classList.contains('is-selected')">
                                            @if ($oursLast && ($row['last_reply_text'] ?? null))
                                                <span class="crm-ticks" aria-hidden="true">✓✓</span> Tú: {{ $row['last_reply_text'] }}
                                            @else
                                                {{ $row['last_line'] }}
                                            @endif
                                        </span>
                                    </span>
                                    <span class="crm-row-tags">
                                        @if ($row['last_quote'] ?? null)
                                            <span class="crm-tag is-doc">{{ \Illuminate\Support\Str::limit(str_replace(' al año', '', $row['last_quote']), 34) }}</span>
                                        @endif
                                        @if ($lane_ === 'waiting')
                                            <span class="crm-row-quiet">Sin respuesta · <span x-text="ago({{ $row['last_reply_at'] ?? 'null' }})"></span></span>
                                        @elseif ($lane_ === 'others')
                                            <span class="crm-tag is-live">Lo atiende {{ $row['taken_by'] !== null ? 'otro analista' : 'el equipo' }}</span>
                                        @elseif ($forViewer)
                                            <span class="crm-tag is-for">Para ti</span>
                                        @elseif ($row['assignee'])
                                            <span class="crm-tag is-for">Para {{ $row['assignee'] }}</span>
                                        @endif
                                        <span class="crm-tag is-area">{{ $row['area_label'] }}</span>
                                        <span class="crm-new" x-cloak x-show="now && unread($el.closest('.crm-row'))">Nuevo</span>
                                    </span>
                                    @if ($hot && $row['waiting_since'])
                                        <span class="crm-row-sla" aria-hidden="true"><i x-bind:class="level({{ $since }}, false)" x-bind:style="'width:' + Math.min(100, (waited({{ $since }}) / late) * 100) + '%'"></i></span>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    @endforeach

                    @if ($rows === [])
                        <p class="crm-q-hint">Cuando llegue una persona, aparece en <b>Sin tomar</b> y suena el aviso.</p>
                    @elseif (($laneCount['reply'] ?? 0) === 0 && ($laneCount['waiting'] ?? 0) > 0)
                        <p class="crm-q-hint">Cuando un cliente conteste, su caso sube a <b>Te toca responder</b>.</p>
                    @endif

                    @if ($rows !== [])
                        <p class="crm-empty" x-cloak x-show="q.trim() !== '' && [...$el.parentElement.querySelectorAll('.crm-row')].every((row) => !visible(row))">Ningún caso coincide con «<span x-text="q.trim()"></span>».</p>
                    @endif
                </div>

                @if ($board['count'] > count($rows))
                    <button type="button" class="crm-more" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore">Ver más</button>
                @endif

                <div class="crm-list-foot" aria-hidden="true">
                    <span><span class="crm-kbd">J</span> <span class="crm-kbd">K</span> moverte</span>
                    <span><span class="crm-kbd">T</span> tomar</span>
                    <span><span class="crm-kbd">R</span> responder</span>
                    <span><span class="crm-kbd">Q</span> cotizar</span>
                </div>
            </section>

            <section class="crm-pane crm-talk" aria-label="Conversación">
                <div class="crm-progress" wire:loading.delay wire:target="select,take,close,release,moveTo,assignTo,loadMore,previewQuote,sendQuote"></div>

                @if ($case === null)
                    <p class="crm-empty">Elige a alguien de la cola. Arriba está quien lleva más tiempo esperando.</p>
                @else
                    @php
                        $caseSince = $case['waiting_since'] ?? 'null';
                        $caseHeld = $case['taken'] ? 'true' : 'false';
                    @endphp
                    <div class="crm-case-head">
                        <span class="crm-ring is-lg" x-bind:class="level({{ $caseSince }}, {{ $caseHeld }})" x-bind:style="ring({{ $caseSince }}, {{ $caseHeld }})" aria-hidden="true"><span>{{ $mark($case['name']) }}</span></span>
                        <div class="crm-case-who">
                            <h2 class="crm-case-name">{{ $case['name'] }}</h2>
                            <div class="crm-case-chips">
                                @if ($mine)
                                    <span class="crm-chip-state is-mine" title="El bot ya no responde y el cliente ve tu nombre."><i aria-hidden="true"></i>Lo atiendes tú</span>
                                @elseif ($case['taken'])
                                    <span class="crm-chip-state is-live" title="El bot ya no responde a este número."><i aria-hidden="true"></i>Lo atiende {{ $case['taker'] ?: 'otro analista' }}</span>
                                @else
                                    <span class="crm-chip-state" x-bind:class="level({{ $caseSince }}, false)" title="SolIA sigue en la conversación mientras nadie lo toma.">
                                        <i aria-hidden="true"></i>
                                        @if ($case['waiting_since'])
                                            Sin tomar · <span class="crm-num" x-text="clock({{ $caseSince }})">{{ $case['waiting'] }}</span>
                                        @else
                                            Sin tomar
                                        @endif
                                    </span>
                                    @if ($case['assignee'])
                                        <span class="crm-chip-state is-for">Para {{ $case['assigned_to'] === $viewer ? 'ti' : $case['assignee'] }}</span>
                                    @endif
                                @endif
                                <span class="crm-chip-soft">{{ $case['area_label'] }}</span>
                                <span class="crm-pop-wrap" x-data="{ open: false }" x-on:mouseenter="open = true" x-on:mouseleave="open = false" x-on:keydown.escape.stop="open = false" wire:key="ctx-{{ $case['handoff_id'] }}">
                                    <button type="button" class="crm-chip-sol" x-on:click="open = !open" x-on:focus="open = true" x-on:blur="open = false" x-bind:aria-expanded="open" aria-controls="crm-ctx-{{ $case['handoff_id'] }}">
                                        <span class="crm-sol-k">SolIA</span>
                                        {{ \Illuminate\Support\Str::limit($context['motivo'] ?? 'sin resumen', 34) }}
                                    </button>
                                    <span class="crm-pop" id="crm-ctx-{{ $case['handoff_id'] }}" role="tooltip" x-cloak x-show="open" x-transition.opacity.duration.120ms>
                                        <b>Motivo</b>
                                        <span>{{ $context['motivo'] ?? 'SolIA no dejó el motivo.' }}</span>
                                        @if ($context['necesidad'] ?? null)
                                            <b>Necesidad</b>
                                            <span>{{ $context['necesidad'] }}</span>
                                        @endif
                                        @if ($context['quote_control'] ?? null)
                                            <b>Propuesta de SolIA</b>
                                            <span class="crm-num">{{ $context['quote_control'] }}@if ($context['quote_total'] ?? null) · {{ $currency }} {{ $context['quote_total'] }}@endif</span>
                                        @endif
                                    </span>
                                </span>
                                <span class="crm-case-phone crm-num">{{ $case['phone_label'] }}</span>
                            </div>
                        </div>

                        @if ($directory !== null && $directory['status'] === 'match' && $directory['url'])
                            <a class="crm-link" href="{{ $directory['url'] }}" target="_blank" rel="noopener noreferrer">Ficha · {{ $directory['kind_label'] }}</a>
                        @endif

                        @if ($mine && ! $quoteOpen)
                            <button type="button" class="crm-btn is-primary" data-quote wire:click="openQuote" wire:loading.attr="disabled" wire:target="openQuote">
                                Cotizar <span class="crm-kbd" aria-hidden="true">Q</span>
                            </button>
                        @endif

                        <div class="crm-menu-wrap" x-data="{ open: false }" x-on:keydown.escape.stop="open = false" x-on:click.outside="open = false">
                            <button type="button" class="crm-icon-btn" x-on:click="open = !open" x-bind:aria-expanded="open" aria-haspopup="true" aria-label="Más acciones">
                                <svg viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><circle cx="3" cy="8" r="1.4"/><circle cx="8" cy="8" r="1.4"/><circle cx="13" cy="8" r="1.4"/></svg>
                            </button>
                            <div class="crm-menu" x-cloak x-show="open" x-transition.origin.top.right role="menu" aria-label="Acciones del caso">
                                <button type="button" role="menuitem" x-on:click="open = false; $wire.toggleSummary()">
                                    <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M2.5 4.5a2 2 0 012-2h7a2 2 0 012 2v5a2 2 0 01-2 2H7l-3 2.5v-2.5h0a2 2 0 01-1.5-2z"/></svg>
                                    <span class="crm-menu-text">
                                        <span>{{ $summaryOpen ? 'Ocultar chat con SolIA' : 'Ver chat con SolIA' }}</span>
                                        <small>{{ $summaryOpen ? 'Quitarlo del hilo' : 'Lo que habló antes de pasar el caso' }}</small>
                                    </span>
                                </button>
                                @if (! $case['taken'])
                                    <button type="button" role="menuitem" x-on:click="open = false; $wire.showDestinations()">
                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 8h9M9 4.5L12.5 8 9 11.5"/></svg>
                                        <span class="crm-menu-text"><span>Pasar a otro equipo</span><small>Sale de esta bandeja</small></span>
                                    </button>
                                    <button type="button" role="menuitem" x-on:click="open = false; $wire.showColleagues()">
                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="6" cy="5.5" r="2.5"/><path d="M1.5 13.5c.6-2.3 2.3-3.5 4.5-3.5s3.9 1.2 4.5 3.5M11 5h3.5M12.75 3.25v3.5"/></svg>
                                        <span class="crm-menu-text"><span>No puedo atender</span><small>Marcarlo para un compañero</small></span>
                                    </button>
                                    <hr>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="is-danger"
                                        x-on:click="open = false; $dispatch('crm-ask', {
                                            title: '¿Cerrar este caso?',
                                            body: 'Sale de la bandeja. El cliente no recibe ningún mensaje y SolIA sigue en la conversación.',
                                            label: 'Cerrar el caso',
                                            action: 'close',
                                        })"
                                    >
                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                        <span class="crm-menu-text"><span>Cerrar el caso…</span><small>SolIA sigue con el cliente</small></span>
                                    </button>
                                @else
                                    <hr>
                                    <button
                                        type="button"
                                        role="menuitem"
                                        class="is-danger"
                                        x-on:click="open = false; $dispatch('crm-ask', {
                                            title: '¿Devuelves el caso al bot?',
                                            body: 'El cliente verá quién lo atendió y SolIA retomará la conversación.',
                                            label: 'Devolver al bot',
                                            action: 'release',
                                        })"
                                    >
                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M6 4.5L2.5 8 6 11.5M3 8h7.5a3 3 0 010 6H9"/></svg>
                                        <span class="crm-menu-text"><span>Devolver al bot…</span><small>SolIA retoma la conversación</small></span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>

                    <div class="crm-offline" wire:offline role="status">
                        <span class="crm-spin" aria-hidden="true"></span>
                        Sin conexión. Reintentando… lo que escribas se conserva.
                    </div>

                    <div class="crm-stream" x-ref="stream" aria-live="polite">
                        <p class="crm-event is-start">
                            SolIA lo pasó{{ $case['accepted_time'] ? ' a las '.$case['accepted_time'] : '' }} ·
                            <button type="button" class="crm-link" wire:click="toggleSummary" wire:loading.attr="disabled" wire:target="toggleSummary,openSummary">{{ $summaryOpen ? 'Ocultar la charla con SolIA' : 'Ver la charla con SolIA' }}</button>
                        </p>

                        @if ($copilot['handover'] ?? null)
                            <div class="crm-card-event" role="note">
                                <span class="crm-sol-k">Transferido</span>
                                <span><b>{{ $copilot['handover']['label'] }}</b> · <span class="crm-num">{{ $copilot['handover']['time'] }}</span></span>
                                @if (! $case['taken'] && ($copilot['summary'] ?? null))
                                    <span class="crm-card-event-sub">{{ \Illuminate\Support\Str::limit($copilot['summary'], 220) }}</span>
                                @endif
                            </div>
                        @endif

                        @if ($summaryOpen)
                            <span class="crm-event">Conversación con SolIA</span>
                            @php $previous = null; @endphp
                            @forelse ($case['messages'] as $message)
                                @php
                                    $fromClient = $message['author'] === 'Cliente';
                                    $label = $message['author'] === 'Bot' ? 'SolIA' : $message['author'];
                                    $first = $previous !== $message['author'];
                                    $previous = $message['author'];
                                @endphp
                                <article id="crm-bot-{{ $loop->index }}" class="crm-bubble {{ $fromClient ? 'is-in' : 'is-out is-bot' }} {{ $first ? 'is-first' : '' }}" wire:key="bot-{{ $case['handoff_id'] }}-{{ $loop->index }}">
                                    @if ($first)
                                        <span class="crm-by {{ $fromClient ? '' : 'is-bot' }}">{{ $label }}</span>
                                    @endif
                                    <p>{{ $message['text'] }}</p>
                                    @if ($message['time'])
                                        <span class="crm-meta">{{ $message['time'] }}</span>
                                    @endif
                                </article>
                            @empty
                                <p class="crm-stream-note">El bot no envió el texto de la conversación.</p>
                            @endforelse
                        @endif

                        @if (! $case['taken'])
                            <span class="crm-event">Pidió hablar con una persona</span>
                            <article class="crm-bubble is-in is-first" wire:key="last-{{ $case['handoff_id'] }}">
                                <span class="crm-by">Cliente</span>
                                <p>{{ $case['last_line'] }}</p>
                            </article>
                        @else
                            <span class="crm-event">{{ $mine ? 'Tomaste el caso · el bot ya no responde' : 'En atención · el bot ya no responde' }}</span>
                            @php
                                $previous = null;
                                $lastDay = null;
                                $today = now()->timezone('America/Caracas')->toDateString();
                                $yesterday = now()->timezone('America/Caracas')->subDay()->toDateString();
                            @endphp
                            @forelse ($case['attention'] as $message)
                                @php
                                    $day = $message['day'] ?? null;
                                    $newDay = $day !== null && $day !== $lastDay;
                                    $lastDay = $day ?? $lastDay;
                                    $first = $newDay || $previous !== $message['role'];
                                    $previous = $message['role'];
                                    $quoteMeta = $message['document'] ? ($message['quote'] ?? null) : null;
                                @endphp
                                @if ($newDay)
                                    <span class="crm-day" wire:key="day-{{ $day }}">{{ $day === $today ? 'Hoy' : ($day === $yesterday ? 'Ayer' : \Illuminate\Support\Carbon::parse($day)->format('d/m/Y')) }}</span>
                                @endif
                                <article
                                    id="crm-msg-{{ \App\Support\CrmInbox\CrmInboxCopilot::anchorId($message['id']) }}"
                                    class="crm-bubble is-{{ $message['role'] }} {{ $first ? 'is-first' : '' }} {{ $message['status'] === 'failed' ? 'is-failed' : '' }} {{ $quoteMeta ? 'is-card' : '' }}"
                                    wire:key="att-{{ $message['id'] }}"
                                    @if ($quoteMeta) aria-label="{{ $message['text'] }}" @endif
                                >
                                    @if ($quoteMeta)
                                        <div class="crm-qcard-head">
                                            <span class="crm-doc">PDF</span>
                                            <b>Propuesta {{ $quoteMeta['control'] ?? '' }}</b>
                                        </div>
                                        <dl class="crm-qcard-body">
                                            @if (($quoteMeta['plan'] ?? '') !== '')
                                                <dt>Plan</dt>
                                                <dd>{{ $quoteMeta['plan'] }}@if ($quoteMeta['coverage'] ?? null) · {{ $currency }} {{ number_format((int) $quoteMeta['coverage'], 0, ',', '.') }}@endif</dd>
                                            @endif
                                            @if (($quoteMeta['people'] ?? 0) > 0)
                                                <dt>Personas</dt>
                                                <dd>{{ $quoteMeta['people'] }}@if (($quoteMeta['ages'] ?? '') !== '') · {{ $quoteMeta['ages'] }} años @endif</dd>
                                            @endif
                                        </dl>
                                        <div class="crm-qcard-total">
                                            <b class="crm-num">{{ $currency }} {{ $quoteMeta['total'] ?? '' }}</b>
                                            <span>al año</span>
                                        </div>
                                        <span class="crm-meta">
                                            @if ($message['status'] === 'pending')
                                                <span>Enviando…</span>
                                            @elseif ($message['status'] === 'failed')
                                                <span class="is-failed">No se envió</span>
                                                <button type="button" class="crm-retry" wire:click="deliverReply('{{ $message['id'] }}')" wire:loading.attr="disabled" wire:target="deliverReply">Reintentar</button>
                                            @elseif ($message['status'] === 'sent')
                                                <span title="Enviado por WhatsApp">✓</span>
                                            @endif
                                            <span>{{ $message['time'] }}</span>
                                        </span>
                                        @if ($mine && $message['status'] === 'sent')
                                            <button type="button" class="crm-qcard-action" wire:click="prefillQuote" wire:loading.attr="disabled" wire:target="prefillQuote">Ajustar y reenviar</button>
                                        @endif
                                    @else
                                        @if ($first || $message['role'] === 'note')
                                            <span class="crm-by">{{ $message['role'] === 'note' ? 'Nota interna · solo el equipo' : $message['author'] }}</span>
                                        @endif
                                        @if ($message['document'])
                                            <span class="crm-doc">PDF</span>
                                        @endif
                                        <p>{{ $message['text'] }}</p>
                                        <span class="crm-meta">
                                            @if ($message['status'] === 'pending')
                                                <span>Enviando…</span>
                                            @elseif ($message['status'] === 'failed')
                                                <span class="is-failed">No se envió</span>
                                                <button type="button" class="crm-retry" wire:click="deliverReply('{{ $message['id'] }}')" wire:loading.attr="disabled" wire:target="deliverReply">Reintentar</button>
                                            @elseif ($message['role'] === 'out' && $message['status'] === 'sent')
                                                <span title="Enviado por WhatsApp">✓</span>
                                            @endif
                                            @if ($message['time'])
                                                <span>{{ $message['time'] }}</span>
                                            @endif
                                        </span>
                                    @endif
                                </article>
                            @empty
                                <p class="crm-stream-note">Cuando la persona escriba de nuevo, la frase aparece aquí.</p>
                            @endforelse
                        @endif
                    </div>

                    @if (! $case['taken'])
                        <div class="crm-foot">
                            <div class="crm-decide">
                                <button type="button" class="crm-btn is-primary" data-take wire:click="take" wire:loading.attr="disabled" wire:target="take" x-bind:disabled="taking">
                                    <span wire:loading.remove wire:target="take">Tomar el caso</span>
                                    <span wire:loading.remove wire:target="take" class="crm-kbd" aria-hidden="true">T</span>
                                    <span wire:loading wire:target="take" class="crm-spin" aria-hidden="true"></span>
                                    <span wire:loading wire:target="take">Tomando…</span>
                                </button>
                                <p class="crm-hint">El bot se pausa y el cliente ve tu nombre.</p>
                                <div class="crm-links">
                                    <button type="button" class="crm-link" wire:click="showDestinations" wire:loading.attr="disabled" wire:target="showDestinations">Pasar a otro equipo</button>
                                    <button type="button" class="crm-link" wire:click="showColleagues" wire:loading.attr="disabled" wire:target="showColleagues">No puedo atender</button>
                                </div>
                            </div>

                            @if ($colleaguesOpen)
                                <div class="crm-pass-list">
                                    <p class="crm-hint">¿A quién se lo pasas?</p>
                                    @forelse ($colleagues as $colleague)
                                        <button
                                            type="button"
                                            class="crm-btn"
                                            wire:key="colleague-{{ $colleague['id'] }}"
                                            x-on:click="ask({
                                                title: @js('¿Se lo pasas a '.$colleague['name'].'?'),
                                                body: @js('El caso sigue en la cola, marcado para '.$colleague['name'].'. El cliente no recibe ningún mensaje.'),
                                                label: 'Pasar el caso',
                                                danger: false,
                                                run: () => $wire.assignTo({{ (int) $colleague['id'] }}),
                                            })"
                                        >{{ $colleague['name'] }}</button>
                                    @empty
                                        <p class="crm-hint">No hay otro analista en este departamento.</p>
                                    @endforelse
                                </div>
                            @endif

                            @if ($destinationsOpen)
                                <div class="crm-pass-list">
                                    <p class="crm-hint">¿A qué equipo?</p>
                                    @foreach ($destinations as $destination)
                                        <button
                                            type="button"
                                            class="crm-btn"
                                            wire:key="destination-{{ $destination['area'] }}"
                                            x-on:click="ask({
                                                title: @js('¿Lo pasas a '.$destination['label'].'?'),
                                                body: @js('Sale de esta bandeja y entra en '.$destination['label'].'. El cliente no recibe ningún mensaje.'),
                                                label: 'Pasar el caso',
                                                danger: false,
                                                run: () => $wire.moveTo(@js($destination['area'])),
                                            })"
                                        >{{ $destination['label'] }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @else
                        <div
                            class="crm-foot"
                            wire:key="composer-{{ $case['handoff_id'] }}"
                            x-data="{
                                sending: false,
                                chars: 0,
                                draftKey: @js('crm-draft-'.$case['handoff_id']),
                                replies: @js($quickReplies),
                                qr: { open: false, filter: '', index: 0, typed: false },
                                matches() {
                                    const needle = this.qr.filter.trim().toLocaleLowerCase();
                                    return this.replies.filter((reply) => needle === '' || reply.label.toLocaleLowerCase().includes(needle) || reply.text.toLocaleLowerCase().includes(needle));
                                },
                                openReplies(typed) {
                                    if (this.replies.length === 0) return;
                                    this.qr = { open: true, filter: '', index: 0, typed };
                                },
                                closeReplies() {
                                    this.qr.open = false;
                                },
                                watchSlash() {
                                    const value = this.$refs.draft.value;
                                    if (value.startsWith('/') && !value.includes('\n')) {
                                        if (!this.qr.open) this.openReplies(true);
                                        this.qr.filter = value.slice(1);
                                        this.qr.index = 0;
                                    } else if (this.qr.open && this.qr.typed) {
                                        this.closeReplies();
                                    }
                                },
                                pick(reply) {
                                    if (!reply) return;
                                    if (this.qr.typed) this.$refs.draft.value = '';
                                    this.closeReplies();
                                    this.insert(reply.text);
                                },
                                repliesKey(event) {
                                    if (!this.qr.open) return false;
                                    const list = this.matches();
                                    if (event.key === 'ArrowDown') { event.preventDefault(); this.qr.index = Math.min(list.length - 1, this.qr.index + 1); return true; }
                                    if (event.key === 'ArrowUp') { event.preventDefault(); this.qr.index = Math.max(0, this.qr.index - 1); return true; }
                                    if (event.key === 'Enter' && !event.shiftKey) { event.preventDefault(); this.pick(list[this.qr.index]); return true; }
                                    if (event.key === 'Escape') { event.preventDefault(); event.stopPropagation(); this.closeReplies(); return true; }
                                    return false;
                                },
                                restore() {
                                    try {
                                        const saved = sessionStorage.getItem(this.draftKey) || '';
                                        if (saved !== '' && this.$refs.draft.value === '') this.$refs.draft.value = saved;
                                    } catch (error) {}
                                    this.chars = this.$refs.draft.value.length;
                                },
                                remember() {
                                    try {
                                        const value = this.$refs.draft.value;
                                        value === '' ? sessionStorage.removeItem(this.draftKey) : sessionStorage.setItem(this.draftKey, value);
                                    } catch (error) {}
                                },
                                grow() {
                                    const field = this.$refs.draft;
                                    if (!field) return;
                                    const max = 200;
                                    field.style.height = 'auto';
                                    const full = field.scrollHeight;
                                    field.style.overflowY = full > max ? 'auto' : 'hidden';
                                    field.style.height = Math.min(Math.max(full, 36), max) + 'px';
                                },
                                insert(text) {
                                    const field = this.$refs.draft;
                                    field.value = field.value.trim() === '' ? text : field.value.replace(/\s+$/, '') + ' ' + text;
                                    this.chars = field.value.length;
                                    this.remember();
                                    this.grow();
                                    field.focus();
                                },
                                clear() {
                                    this.$refs.draft.value = '';
                                    this.chars = 0;
                                    this.remember();
                                    this.grow();
                                },
                                async submit() {
                                    if (this.sending) return;
                                    const field = this.$refs.draft;
                                    const text = (field.value || '').trim();
                                    if (text === '' || text.length > 2000) return;
                                    const id = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : '';
                                    if (id === '') return;
                                    this.sending = true;
                                    this.clear();
                                    try {
                                        await this.$wire.stageReply(id, text);
                                        await this.$wire.deliverReply(id);
                                    } finally {
                                        this.sending = false;
                                    }
                                },
                                async note() {
                                    if (this.sending) return;
                                    const field = this.$refs.draft;
                                    const text = (field.value || '').trim();
                                    if (text === '' || text.length > 2000) return;
                                    this.sending = true;
                                    this.clear();
                                    try {
                                        await this.$wire.saveNote(text);
                                    } finally {
                                        this.sending = false;
                                    }
                                }
                            }"
                            x-init="$nextTick(() => { restore(); grow(); $refs.draft && $refs.draft.focus() })"
                            x-on:crm-insert.window="insert($event.detail.text)"
                        >
                            @if ($suggestion)
                                <div class="crm-suggest" wire:key="suggest-{{ $case['handoff_id'] }}-{{ $suggestion['key'] }}" role="status">
                                    <span class="crm-sol">Sugerencia</span>
                                    <span class="crm-suggest-text">
                                        <b>{{ $suggestion['title'] }}</b>
                                        <span>Porque: {{ $suggestion['reason'] }}</span>
                                    </span>
                                    <span class="crm-suggest-actions">
                                        @if ($suggestion['action'] === 'quote')
                                            <button type="button" class="crm-btn is-primary" wire:click="useSuggestion('quote')" wire:loading.attr="disabled" wire:target="useSuggestion,prefillQuote">Cotizar con estos datos</button>
                                        @elseif ($suggestion['action'] === 'insert')
                                            <button type="button" class="crm-btn" x-on:click="insert(@js($suggestion['text'])); $wire.useSuggestion(@js($suggestion['key']))" title="{{ $suggestion['text'] }}">Usar este texto</button>
                                        @else
                                            <button type="button" class="crm-btn" x-on:click="$refs.draft.focus(); $wire.useSuggestion(@js($suggestion['key']))">Responder</button>
                                        @endif
                                        <button type="button" class="crm-link" wire:click="dismissSuggestion(@js($suggestion['key']))" wire:loading.attr="disabled" wire:target="dismissSuggestion">No aplica</button>
                                    </span>
                                </div>
                            @endif

                            @if ($quoteOpen)
                                <div class="crm-quote-form" wire:key="quote-{{ $case['handoff_id'] }}">
                                    <div class="crm-quote-top">
                                        <b>Cotizar</b>
                                        <span class="crm-hint">Lleno con lo que ya dijo el cliente. Revisa y calcula.</span>
                                        <button type="button" class="crm-icon-btn is-sm" wire:click="discardQuote" aria-label="Cerrar el cotizador" title="Cerrar">
                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M4 4l8 8M12 4l-8 8"/></svg>
                                        </button>
                                    </div>
                                    <div class="crm-quote-grid">
                                        <label>Titular
                                            <input type="text" maxlength="80" wire:model="quoteHolder" placeholder="Nombre del titular" autocomplete="off">
                                        </label>
                                        <label>Edades
                                            <input type="text" wire:model="quoteAges" placeholder="25, 40, 8" inputmode="numeric" autocomplete="off">
                                        </label>
                                        <label>Plan
                                            <select wire:model.live="quotePlan">
                                                @foreach (\App\Support\CrmInbox\CrmInboxQuote::PLANS as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                        </label>
                                        @if (isset(\App\Support\CrmInbox\CrmInboxQuote::COVERAGES[$quotePlan]))
                                            <label>Cobertura
                                                <select wire:model="quoteCoverage">
                                                    <option value="">Elige el monto</option>
                                                    @foreach (\App\Support\CrmInbox\CrmInboxQuote::COVERAGES[$quotePlan] as $amount)
                                                        <option value="{{ $amount }}">USD {{ number_format($amount, 0, ',', '.') }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        @endif
                                        <button type="button" class="crm-btn is-primary" wire:click="previewQuote" wire:loading.attr="disabled" wire:target="previewQuote,sendQuote">
                                            <span wire:loading.remove wire:target="previewQuote">Calcular</span>
                                            <span wire:loading wire:target="previewQuote">Calculando…</span>
                                        </button>
                                    </div>
                                    @if ($quoteDraft)
                                        <div class="crm-quote-result">
                                            <div>
                                                <b>{{ $quoteDraft['caption'] }}</b>
                                                <p class="crm-hint">{{ $quoteDraft['plan'] }} · {{ $quoteDraft['people'] }} {{ $quoteDraft['people'] === 1 ? 'persona' : 'personas' }}. Si cambias los datos, calcula de nuevo.</p>
                                            </div>
                                            <button type="button" class="crm-btn is-primary" wire:click="sendQuote" wire:loading.attr="disabled" wire:target="sendQuote,previewQuote">
                                                <span wire:loading.remove wire:target="sendQuote">Enviar PDF</span>
                                                <span wire:loading wire:target="sendQuote">Enviando…</span>
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endif

                            <div class="crm-compose">
                                <div class="crm-qr-pop" x-cloak x-show="qr.open" x-on:click.outside="closeReplies()" role="listbox" aria-label="Respuestas rápidas">
                                    <template x-for="(reply, index) in matches()" x-bind:key="reply.label">
                                        <button type="button" class="crm-qr-item" role="option" x-bind:aria-selected="index === qr.index" x-on:mouseenter="qr.index = index" x-on:click="pick(reply)">
                                            <b x-text="reply.label"></b>
                                            <span x-text="reply.text"></span>
                                        </button>
                                    </template>
                                    <p class="crm-qr-none" x-show="matches().length === 0">Ninguna respuesta coincide.</p>
                                    <p class="crm-qr-help"><span class="crm-kbd">↑</span><span class="crm-kbd">↓</span> elegir · <span class="crm-kbd">Enter</span> insertar · <span class="crm-kbd">Esc</span> cerrar</p>
                                </div>
                                <label class="crm-sr" for="crm-draft-{{ $case['handoff_id'] }}">Respuesta</label>
                                <textarea
                                    id="crm-draft-{{ $case['handoff_id'] }}"
                                    x-ref="draft"
                                    wire:ignore
                                    rows="1"
                                    maxlength="2000"
                                    placeholder="{{ $givenName !== '' ? 'Escribe a '.$givenName.'…' : 'Escribe tu respuesta…' }}"
                                    x-on:input="chars = $refs.draft.value.length; remember(); grow(); watchSlash()"
                                    x-on:keydown="repliesKey($event)"
                                    x-on:keydown.enter="if (!$event.defaultPrevented && !$event.shiftKey) { $event.preventDefault(); submit() }"
                                    x-on:keydown.escape="if (!$event.defaultPrevented) $el.blur()"
                                ></textarea>
                                <div class="crm-compose-bar">
                                    @if ($quickReplies !== [])
                                        <button type="button" class="crm-tool" x-on:click="qr.open ? closeReplies() : openReplies(false)" x-bind:aria-expanded="qr.open" title="Respuestas rápidas">
                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M9 1.5L3.5 9H8l-1 5.5L12.5 7H8z"/></svg>
                                            Respuestas <span class="crm-kbd" aria-hidden="true">/</span>
                                        </button>
                                    @endif
                                    @if (! $quoteOpen)
                                        <button type="button" class="crm-tool" wire:click="openQuote" wire:loading.attr="disabled" wire:target="openQuote" title="Cotizar y enviar el PDF">
                                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M4 1.5h5.5L13 5v9.5H4z"/><path d="M9 1.5V5h4M6.5 9h4M6.5 11.5h4"/></svg>
                                            Cotizar
                                        </button>
                                    @endif
                                    <button type="button" class="crm-tool crm-note-btn" x-on:click="note()" x-bind:disabled="sending || chars === 0" title="Queda en el hilo. No sale a WhatsApp.">
                                        <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><path d="M3 2.5h10v8l-3 3H3z"/><path d="M10 13.5v-3h3"/></svg>
                                        Nota interna
                                    </button>
                                    <button type="button" class="crm-send" x-on:click="submit()" x-bind:disabled="sending || chars === 0" title="Enviar por WhatsApp">
                                        <svg x-show="!sending" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M8 13V3M3.5 7.5L8 3l4.5 4.5"/></svg>
                                        <span x-cloak x-show="sending" class="crm-spin" aria-hidden="true"></span>
                                        <span class="crm-sr">Enviar por WhatsApp</span>
                                    </button>
                                </div>
                            </div>

                            <div class="crm-compose-meta">
                                <span><span class="crm-kbd">Enter</span> envía · <span class="crm-kbd">Shift</span>+<span class="crm-kbd">Enter</span> nueva línea</span>
                                <span x-cloak x-show="chars >= 1600" class="crm-num" x-text="chars + ' / 2000'"></span>
                                <button
                                    type="button"
                                    class="crm-link is-danger"
                                    wire:loading.attr="disabled"
                                    wire:target="release"
                                    x-on:click="$dispatch('crm-ask', {
                                        title: '¿Devuelves el caso al bot?',
                                        body: 'El cliente verá quién lo atendió y SolIA retomará la conversación.',
                                        label: 'Devolver al bot',
                                        action: 'release',
                                    })"
                                >Devolver al bot</button>
                            </div>
                        </div>
                    @endif
                @endif
            </section>

            <aside class="crm-pane crm-side" x-bind:class="sideOpen ? '' : 'is-collapsed'" aria-label="Cliente">
                @if ($case === null)
                    <div class="crm-side-head">
                        <p class="crm-kicker">Cliente</p>
                        <button type="button" class="crm-icon-btn" x-on:click="toggleSide()" x-bind:aria-expanded="sideOpen" x-bind:aria-label="sideOpen ? 'Plegar el panel' : 'Abrir el panel'">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
                        </button>
                    </div>
                    <div class="crm-side-body">
                        <p class="crm-empty">Elige un caso y aquí ves quién es, qué busca y qué falta para cotizar.</p>
                    </div>
                @else
                    @php
                        $client = $copilot['client'] ?? ['since' => null, 'conversations' => 0];
                        $conversations = (int) ($client['conversations'] ?? 0);
                        $ready = (int) ($copilot['ready'] ?? 0);
                    @endphp
                    <div class="crm-side-head crm-id">
                        <span class="crm-av is-quiet is-lg" aria-hidden="true">{{ $mark($case['name']) }}</span>
                        <div class="crm-id-text">
                            <b class="crm-id-name">{{ $case['name'] }}</b>
                            <span class="crm-id-phone" x-data="{ copied: false }">
                                <span class="crm-num">{{ $case['phone_label'] }}</span>
                                <button
                                    type="button"
                                    class="crm-copy"
                                    x-on:click="navigator.clipboard?.writeText(@js($case['phone_label'])).then(() => { copied = true; setTimeout(() => copied = false, 1500) }).catch(() => {})"
                                    x-bind:title="copied ? 'Copiado' : 'Copiar teléfono'"
                                    aria-label="Copiar teléfono"
                                >
                                    <svg x-show="!copied" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="5" y="5" width="8" height="8" rx="1.5"/><path d="M3 10V4a1 1 0 011-1h6"/></svg>
                                    <svg x-cloak x-show="copied" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M3 8.5l3 3 7-7"/></svg>
                                </button>
                            </span>
                            <span class="crm-id-meta">
                                {{ $client['since'] ? 'Cliente desde '.$client['since'] : 'Llegó por WhatsApp' }}@if ($conversations > 1) · {{ $conversations }}.ª conversación @endif
                            </span>
                        </div>
                        <button type="button" class="crm-icon-btn" x-on:click="toggleSide()" x-bind:aria-expanded="sideOpen" x-bind:aria-label="sideOpen ? 'Plegar el panel' : 'Abrir el panel'">
                            <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
                        </button>
                    </div>
                    <div class="crm-side-body">
                        @if ($copilot !== null)
                            <section class="crm-section">
                                <h3 class="crm-h"><span class="crm-sol-k">SolIA</span>Lo que busca</h3>
                                @if ($copilot['need'] || $copilot['summary'] || $copilot['notes'])
                                    @if ($copilot['need'])
                                        <p class="crm-need">{{ $copilot['need'] }}</p>
                                    @endif
                                    @if ($copilot['summary'])
                                        <p class="crm-copilot-summary">{{ $copilot['summary'] }}</p>
                                    @endif
                                    @if ($copilot['notes'])
                                        <p class="crm-copilot-note"><span>Notas</span> {{ $copilot['notes'] }}</p>
                                    @endif
                                @else
                                    <p class="crm-hint">SolIA no dejó resumen de este caso.</p>
                                @endif
                            </section>

                            <section class="crm-section">
                                <h3 class="crm-h">
                                    Datos para cotizar
                                    <span class="crm-prog" role="progressbar" aria-valuemin="0" aria-valuemax="4" aria-valuenow="{{ $ready }}" aria-label="Datos listos"><i style="width: {{ $ready * 25 }}%"></i></span>
                                    <span class="crm-prog-n crm-num">{{ $ready }} de 4</span>
                                </h3>
                                <dl class="crm-facts">
                                    @foreach ($copilot['facts'] as $fact)
                                        <dt>{{ $fact['label'] }}</dt>
                                        <dd wire:key="fact-{{ $case['handoff_id'] }}-{{ $fact['key'] }}">
                                            <span class="crm-fact-value">{{ $fact['value'] }}</span>
                                            @if ($fact['anchor'])
                                                <button type="button" class="crm-src" x-on:click="jump(@js($fact['anchor']))" title="Ir al mensaje del que salió">{{ $fact['source'] }} ↗</button>
                                            @else
                                                <span class="crm-src is-static">{{ $fact['source'] }}</span>
                                            @endif
                                        </dd>
                                    @endforeach
                                    @foreach ($copilot['missing'] as $gap)
                                        <dt>{{ $gap['label'] }}</dt>
                                        <dd class="is-missing" wire:key="gap-{{ $case['handoff_id'] }}-{{ $gap['key'] }}">
                                            <span>Falta</span>
                                            @if ($mine && isset($copilot['asks'][$gap['key']]))
                                                <button type="button" class="crm-ask" x-on:click="$dispatch('crm-insert', { text: @js($copilot['asks'][$gap['key']]) })" title="{{ $copilot['asks'][$gap['key']] }}">Pedirlo</button>
                                            @endif
                                        </dd>
                                    @endforeach
                                </dl>
                                @if ($mine && $copilot['quote'] && ! $quoteOpen)
                                    <button type="button" class="crm-btn is-primary is-block" wire:click="prefillQuote" wire:loading.attr="disabled" wire:target="prefillQuote,useSuggestion">Cotizar con estos datos</button>
                                @elseif (! $mine && $copilot['facts'] !== [])
                                    <p class="crm-hint">Toma el caso para cotizar con estos datos.</p>
                                @endif
                            </section>
                        @endif

                        <section class="crm-section">
                            <h3 class="crm-h">IntegraCorp</h3>
                            @if ($directory === null)
                                <div class="crm-skeleton" wire:key="directory-{{ $case['handoff_id'] }}" x-init="$wire.loadDirectory()" aria-label="Buscando la ficha">
                                    <i style="width: 70%"></i>
                                    <i style="width: 45%"></i>
                                </div>
                            @elseif ($directory['status'] === 'none')
                                <div class="crm-ficha">
                                    <span class="crm-ficha-ico" aria-hidden="true">?</span>
                                    <div>
                                        <b>Sin ficha en IntegraCorp</b>
                                        @if (isset($directory['document']))
                                            <span>Ni este número ni la cédula <span class="crm-num">{{ number_format((int) $directory['document'], 0, ',', '.') }}</span> están en afiliados ni agentes.</span>
                                        @else
                                            <span>Se busca sola cuando el cliente escriba su cédula.</span>
                                        @endif
                                    </div>
                                </div>
                            @else
                                <div class="crm-ficha is-match">
                                    <span class="crm-ficha-ico" aria-hidden="true">✓</span>
                                    <div>
                                        <b>{{ $directory['kind_label'] }} · {{ $directory['name'] }}</b>
                                        <span>
                                            {{ ($directory['via'] ?? null) === 'document' ? 'Encontrada por cédula' : 'Encontrada por el teléfono' }}
                                            @if ($directory['url'])
                                                · <a class="crm-link" href="{{ $directory['url'] }}" target="_blank" rel="noopener noreferrer">Abrir ficha</a>
                                            @endif
                                        </span>
                                    </div>
                                </div>
                            @endif
                        </section>

                        @if (($copilot['proposals'] ?? []) !== [])
                            <section class="crm-section">
                                <h3 class="crm-h">Propuestas</h3>
                                <ul class="crm-props">
                                    @foreach ($copilot['proposals'] as $proposal)
                                        <li class="{{ $loop->last ? 'is-current' : '' }}" wire:key="prop-{{ $case['handoff_id'] }}-{{ $loop->index }}">
                                            <span class="crm-who {{ $proposal['mine'] ? 'is-team' : 'is-sol' }}">{{ $proposal['by'] }}</span>
                                            <span class="crm-prop-text"><span class="crm-num">{{ $proposal['control'] }}</span>@if ($proposal['detail'] !== '') · {{ $proposal['detail'] }}@endif</span>
                                            <b class="crm-num">{{ $proposal['total'] }}</b>
                                        </li>
                                    @endforeach
                                </ul>
                            </section>
                        @endif

                        @if ($case['timeline'] !== [])
                            <section class="crm-section" x-data="{ open: false }">
                                <button type="button" class="crm-acc" x-on:click="open = !open" x-bind:aria-expanded="open" aria-controls="crm-tl-{{ $case['handoff_id'] }}">
                                    Recorrido <span>{{ count($case['timeline']) }} {{ count($case['timeline']) === 1 ? 'evento' : 'eventos' }}</span>
                                    <svg x-bind:class="open && 'is-open'" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 3l5 5-5 5"/></svg>
                                </button>
                                <ol class="crm-timeline" id="crm-tl-{{ $case['handoff_id'] }}" x-cloak x-show="open" x-collapse>
                                    @foreach ($case['timeline'] as $event)
                                        <li><span class="crm-timeline-label">{{ $event['label'] }}</span><time>{{ $event['time'] }}</time></li>
                                    @endforeach
                                </ol>
                            </section>
                        @endif
                    </div>
                @endif
            </aside>
        </div>

        <div class="crm-scrim" wire:ignore x-cloak x-show="confirm.open" x-transition.opacity x-on:keydown.escape.window="confirm.open = false" x-on:click.self="confirm.open = false">
            <div class="crm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="crm-confirm-title" aria-describedby="crm-confirm-body" x-trap.noscroll="confirm.open">
                <div class="crm-dialog-icon" x-bind:class="confirm.danger && 'is-danger'" aria-hidden="true">
                    <svg viewBox="0 0 16 16" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M8 5v4M8 11.5v.01M2 13.5L8 2.5l6 11H2z"/></svg>
                </div>
                <h3 id="crm-confirm-title" x-text="confirm.title"></h3>
                <p id="crm-confirm-body" x-text="confirm.body"></p>
                <div class="crm-dialog-actions">
                    <button type="button" class="crm-btn" x-on:click="confirm.open = false">Cancelar</button>
                    <button type="button" class="crm-btn" x-bind:class="confirm.danger ? 'is-danger' : 'is-primary'" x-on:click="accept()" x-text="confirm.label"></button>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
