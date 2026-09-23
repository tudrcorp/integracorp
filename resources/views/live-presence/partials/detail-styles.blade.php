<style>
    .lxd { --x-bg: #ffffff; --x-soft: #f8fafc; --x-border: #e5e7eb; --x-text: #0f172a; --x-muted: #64748b; display: flex; flex-direction: column; gap: 12px; color: var(--x-text); font-size: 13px; }
    .dark .lxd { --x-bg: #0b1220; --x-soft: #0f172a; --x-border: #1e293b; --x-text: #e2e8f0; --x-muted: #94a3b8; }
    .lxd-box { background: var(--x-soft); border: 1px solid var(--x-border); border-radius: 12px; padding: 12px 14px; min-width: 0; }
    .lxd-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; }
    .lxd-kicker { font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--x-muted); margin-bottom: 4px; }
    .lxd-title { font-size: 15px; font-weight: 800; margin-bottom: 2px; }
    .lxd-text { color: var(--x-muted); line-height: 1.55; }
    .lxd-message { margin-top: 4px; white-space: pre-wrap; word-break: break-word; line-height: 1.5; }
    .lxd-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; word-break: break-all; }
    .lxd-origin { font-size: 13px; font-weight: 700; color: #dc2626; }
    .lxd-frame { display: flex; flex-direction: column; padding: 6px 0; border-top: 1px dashed var(--x-border); }
    .lxd-frame:first-of-type { border-top: 0; }
    .lxd-file { font-weight: 700; }
    .lxd-call { color: var(--x-muted); }
    .lxd-pre { margin: 8px 0 0; max-height: 420px; overflow: auto; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 11.5px; white-space: pre-wrap; word-break: break-all; }
    .lxd-facts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 8px; }
    .lxd-fact { background: var(--x-soft); border: 1px solid var(--x-border); border-radius: 10px; padding: 8px 10px; min-width: 0; }
    .lxd-fact strong { display: block; font-size: 13px; word-break: break-word; }
    .lxd-warn { border-radius: 12px; padding: 10px 14px; background: rgba(217, 119, 6, .12); color: #b45309; font-weight: 600; }
    .dark .lxd-warn { color: #fbbf24; }
    .lxd-top { display: flex; justify-content: space-between; align-items: center; gap: 12px; flex-wrap: wrap; }
    @media (max-width: 768px) { .lxd-grid, .lxd-facts { grid-template-columns: 1fr 1fr; } }
</style>
