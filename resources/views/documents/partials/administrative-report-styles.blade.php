{{-- Estilos del Informe administrativo: compartidos por la pantalla y el PDF (DomPDF). --}}
<style>
    .adm-report { font-family: 'DejaVu Sans', Helvetica, Arial, sans-serif; color: #1E293B; font-size: 9.5pt; background: #FFFFFF; padding: 0 0 8px; }
    .adm-report table { border-collapse: collapse; width: 100%; }
    .adm-header { background: #FFFFFF; }
    .adm-header td { padding: 6px 14px; vertical-align: middle; }
    .adm-header-logo { width: 30%; }
    .adm-header-title { text-align: right; }
    .adm-title { font-size: 15pt; font-weight: bold; color: #1F4E9E; letter-spacing: .5px; }
    .adm-subtitle { font-size: 8.5pt; color: #64748B; }
    .adm-band { background: #1F4E9E; color: #FFFFFF; font-size: 8.5pt; }
    .adm-band td { padding: 5px 14px; }
    .adm-pill { background: #FFFFFF; color: #1E293B; border-radius: 8px; padding: 3px 12px; font-weight: bold; }
    .adm-card { background: #FFFFFF; border: 1px solid #E2E8F0; border-radius: 10px; margin: 6px 10px 0; padding: 8px 12px; }
    .adm-intro { text-align: center; font-size: 8.5pt; line-height: 1.35; }
    .adm-section { margin-top: 8px; }
    .adm-keep { page-break-inside: avoid; }
    .adm-ribbon { display: inline-block; background: #1F4E9E; color: #FFFFFF; font-weight: bold; font-size: 10pt; padding: 5px 24px 5px 14px; margin: 0 10px; border-radius: 0 14px 14px 0; }
    .adm-section-intro { margin: 3px 12px 0; color: #475569; font-size: 8.5pt; }
    .adm-kpis { margin: 4px 0 0; width: 100%; }
    .adm-kpis td { background: #F1F5F9; border-radius: 10px; text-align: center; padding: 6px 4px; border: 3px solid #FFFFFF; vertical-align: top; }
    .adm-kpi-label { font-size: 7pt; color: #64748B; letter-spacing: .4px; }
    .adm-kpi-value { font-size: 13pt; font-weight: bold; color: #0F172A; margin-top: 2px; }
    .adm-kpi-detail { font-size: 7.5pt; color: #64748B; margin-top: 2px; }
    .adm-card-top { border-bottom: none; border-radius: 10px 10px 0 0; padding-bottom: 2px; }
    .adm-card-bottom { margin-top: 0; border-top: none; border-radius: 0 0 10px 10px; padding-top: 0; }
    .adm-table thead { display: table-header-group; page-break-after: avoid; }
    .adm-table tr { page-break-inside: avoid; }
    .adm-block-title { display: inline-block; background: #1F4E9E; color: #FFFFFF; font-size: 8.5pt; font-weight: bold; padding: 3px 16px 3px 10px; border-radius: 0 10px 10px 0; margin-bottom: 4px; }
    .adm-chart { margin: 2px 0 4px; text-align: center; }
    .adm-side td { vertical-align: middle; }
    .adm-side-chart { width: 36%; text-align: center; }
    .adm-side-table { width: 64%; }
    .adm-table { font-size: 7.5pt; margin-top: 2px; }
    .adm-table th { background: #1F4E9E; color: #FFFFFF; font-weight: bold; padding: 3px 5px; text-align: left; }
    .adm-table td { padding: 2.5px 5px; border-bottom: 1px solid #E2E8F0; }
    .adm-table tr:nth-child(even) td { background: #F8FAFC; }
    .adm-table .adm-num { text-align: right; white-space: nowrap; }
    .adm-table-compact { font-size: 7pt; }
    .adm-table-compact th, .adm-table-compact td { padding: 2.5px 3px; }
    .adm-table-compact td:first-child { font-weight: bold; white-space: nowrap; }
    .adm-table-compact td:last-child, .adm-table-compact th:last-child { background: #D1FAE5; color: #0F172A; font-weight: bold; }
    .adm-table .adm-total td { font-weight: bold; background: #D1FAE5; border-bottom: none; }
    .adm-note { margin: 4px 0 0; font-size: 7.5pt; font-style: italic; color: #334155; text-align: center; line-height: 1.3; }
</style>
