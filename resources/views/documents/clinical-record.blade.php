<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<style>
  @page { margin: 22mm 14mm 18mm 14mm; }
  body { font-family: 'DejaVu Sans', sans-serif; font-size: 8.6pt; color: #1e293b; line-height: 1.38; }
  table { border-collapse: collapse; width: 100%; table-layout: fixed; }
  td, th { vertical-align: top; }
  h1 { font-size: 15pt; color: #052F60; margin: 0; }
  h2 { font-size: 10.5pt; color: #052F60; margin: 0; }
  .muted { color: #64748b; }
  .small { font-size: 7.4pt; }
  .tag { display: inline-block; padding: 1px 6px; border-radius: 8px; font-size: 7.2pt; font-weight: bold; }
  .t-red { background: #fee2e2; color: #991b1b; }
  .t-amber { background: #fef3c7; color: #92400e; }
  .t-green { background: #dcfce7; color: #166534; }
  .t-gray { background: #e2e8f0; color: #334155; }
  .t-blue { background: #dbeafe; color: #1e40af; }
  .head td { padding: 0 0 8px 0; }
  .band td { padding: 7px 9px; }
  .alert { background: #fef2f2; border: 1px solid #fecaca; }
  .alert .lbl { color: #991b1b; font-size: 7pt; font-weight: bold; text-transform: uppercase; letter-spacing: .5px; }
  .sec { margin-top: 11px; }
  .sec-title td { padding: 5px 8px; background: #052F60; color: #fff; font-weight: bold; font-size: 9pt; }
  .sec-title .num { width: 22px; }
  .grid td { padding: 4px 8px; border-bottom: 1px solid #e2e8f0; }
  .grid .k { color: #64748b; font-size: 7.4pt; text-transform: uppercase; }
  .data th { background: #f1f5f9; color: #475569; font-size: 7.2pt; text-transform: uppercase; text-align: left; padding: 4px 6px; border-bottom: 1px solid #cbd5e1; }
  .data td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
  .data tr.alt td { background: #f8fafc; }
  .soap td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
  .soap .l { width: 18px; font-weight: bold; color: #fff; background: #0d9488; text-align: center; }
  .note td { padding: 6px 9px; background: #f8fafc; border-left: 3px solid #0d9488; }
  .code { font-family: 'DejaVu Sans Mono', monospace; font-size: 7.6pt; color: #0f172a; }
  .hi { color: #b91c1c; font-weight: bold; }
  .ok { color: #15803d; font-weight: bold; }
  .bar { height: 7px; background: #0d9488; }
  .bar-bg { background: #e2e8f0; }
  .page-break { page-break-before: always; }
  .kpi td { padding: 7px 8px; border: 1px solid #e2e8f0; text-align: center; }
  .kpi .v { font-size: 13pt; font-weight: bold; color: #052F60; }
  .kpi .d { font-size: 7pt; color: #64748b; }
  .gap td { padding: 4px 6px; border-bottom: 1px solid #e2e8f0; }
</style>
</head>
<body>
@php
    $p = $record['patient'];
    $c = $record['coverage'];
    $bg = $record['background'];
    $none = 'Sin registro';
    /** DomPDF no parte palabras largas: se da un punto de corte tras «/», «+» y en tramos de más de 16 letras. */
    $breakable = static fn (?string $text): string => (string) preg_replace('/(\S{16})(?=\S{4})/u', '$1 ', str_replace(['/', '+'], ['/ ', '+ '], (string) $text));
@endphp

<table class="head">
  <tr>
    <td style="width:26%">@if ($logo)<img src="{{ $logo }}" style="height:38px">@endif</td>
    <td style="width:50%">
      <h1>{{ $technical ? 'Ficha técnica del paciente' : 'Ficha clínica del paciente' }}</h1>
      @if ($technical)<div style="margin:2px 0 3px"><span class="tag t-amber" style="font-size:8pt">USO INTERNO · NO ENTREGAR AL PACIENTE</span></div>@endif
      <div class="muted small">{{ $technical ? 'Ficha clínica más afiliación, cobertura, cupos y cortesías' : 'Resumen clínico con estructura IPS · Armado con lo registrado en Telemedicina y Operaciones' }}</div>
    </td>
    <td style="width:24%; text-align:right" class="small">
      <b>Emitida:</b> {{ $record['generated_at'] }}<br>
      <b>Por:</b> {{ $issuer }}<br>
      @if ($p['code'])<b>Código:</b> {{ $p['code'] }}@endif
    </td>
  </tr>
</table>

<table class="band alert">
  <tr>
    <td style="width:30%"><div class="lbl">⚠ Alergias</div><b>{{ $record['alerts']['allergies'] !== [] ? implode(' · ', array_slice($record['alerts']['allergies'], 0, 4)) : $none }}</b></td>
    <td style="width:30%"><div class="lbl">Condiciones personales</div><b>{{ $record['alerts']['conditions'] !== [] ? implode(' · ', array_slice($record['alerts']['conditions'], 0, 4)) : $none }}</b></td>
    <td style="width:24%"><div class="lbl">Medicamentos en curso</div><b>{{ $record['alerts']['medications'] !== [] ? implode(' · ', array_slice($record['alerts']['medications'], 0, 3)) : 'Ninguno' }}</b></td>
    <td style="width:16%"><div class="lbl">Grupo sanguíneo</div><b>{{ $none }}</b></td>
  </tr>
</table>

<table class="sec sec-title"><tr><td class="num" style="width:4%">1</td><td style="width:96%">{{ $technical ? 'Identificación, afiliación y cobertura' : 'Datos del paciente' }}</td></tr></table>
<table class="grid">
  <tr>
    <td style="width:25%"><div class="k">Nombre</div><b>{{ $p['name'] }}</b></td>
    <td style="width:25%"><div class="k">Documento</div>{{ $p['document'] ?? $none }}</td>
    <td style="width:25%"><div class="k">Nacimiento · edad</div>{{ $p['birth'] ?? $none }}{{ $p['age'] ? ' · '.$p['age'] : '' }}</td>
    <td style="width:25%"><div class="k">Sexo</div>{{ $p['sex'] ?? $none }}</td>
  </tr>
  <tr>
    <td><div class="k">Teléfono</div>{{ $p['phone'] ?? $none }}</td>
    <td><div class="k">Correo</div>{{ $p['email'] ?? $none }}</td>
    <td colspan="2"><div class="k">Dirección</div>{{ $p['address'] ?? $none }}</td>
  </tr>
  <tr>
    <td @if (! $technical) colspan="4" @endif><div class="k">Contacto alterno</div>{{ $p['contact'] ?? $none }}</td>
    @if ($technical)
    <td><div class="k">Plan</div>{{ $c['plan'] ?? $none }}{{ $c['coverage'] ? ' · '.$c['coverage'] : '' }}</td>
    <td><div class="k">Afiliación</div>{{ $c['type'] ?? $none }}{{ $c['company'] ? ' · '.$c['company'] : '' }}{{ $c['code'] ? ' · '.$c['code'] : '' }}</td>
    <td><div class="k">Vigencia</div>{{ $c['period'] ?? $none }}@if ($c['status']) <span class="tag t-gray">{{ $c['status'] }}</span>@endif</td>
    @endif
  </tr>
</table>
@if ($technical && $record['quotas'] !== [])
<table class="data" style="margin-top:5px">
  <tr><th style="width:44%">Cupos clínicos del año de afiliación</th><th style="width:12%">Usados</th><th style="width:16%">Disponibles</th><th style="width:28%">Consumo</th></tr>
  @foreach ($record['quotas'] as $i => $quota)
  <tr @class(['alt' => $i % 2 === 1])><td>{{ $quota['label'] }}</td><td>{{ $quota['used'] }}</td><td>{{ $quota['quota'] === null ? 'Sin límite' : ($quota['remaining'] ?? 0).' de '.$quota['quota'] }}</td>
    <td>@if ($quota['quota'] !== null)<table><tr>@if ($quota['percent'] > 0)<td class="bar" style="width:{{ $quota['percent'] }}%; background:{{ $quota['percent'] >= 100 ? '#dc2626' : '#f59e0b' }}"></td>@endif<td style="background:#e2e8f0; height:7px"></td></tr></table>@endif</td></tr>
  @endforeach
</table>
@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">2</td><td style="width:96%">Diagnósticos registrados (texto del médico · aún sin CIE-10)</td></tr></table>
@if ($record['problems'] === [])
<p class="small muted">{{ $none }}</p>
@else
<table class="data">
  <tr><th style="width:12%">Fecha</th><th style="width:52%">Diagnóstico</th><th style="width:16%">Consulta</th><th style="width:20%">Médico</th></tr>
  @foreach ($record['problems'] as $i => $problem)
  <tr @class(['alt' => $i % 2 === 1])><td>{{ $problem['date'] }}</td><td>{{ $problem['text'] }}</td><td>{{ $problem['status'] ?? '—' }}</td><td>{{ $problem['doctor'] ?? '—' }}</td></tr>
  @endforeach
</table>
@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">3</td><td style="width:96%">Medicación</td></tr></table>
@if ($record['medications'] === [])
<p class="small muted">{{ $none }}</p>
@else
<table class="data">
  <tr><th style="width:22%">Medicamento</th><th style="width:27%">Indicaciones</th><th style="width:11%">Desde</th><th style="width:11%">Hasta</th><th style="width:12%">Estado</th><th style="width:17%">Indicado por</th></tr>
  @foreach ($record['medications'] as $i => $m)
  <tr @class(['alt' => $i % 2 === 1])><td><b>{{ $breakable($m['name']) }}</b>@if ($m['covered'] === false)<div style="margin-top:2px"><span class="tag t-red">No cubierto</span></div>@endif</td><td>{{ $m['indications'] !== null ? $breakable($m['indications']) : '—' }}</td><td>{{ $m['since'] }}</td><td>{{ $m['until'] ?? '—' }}</td>
    <td><span class="tag {{ $m['state'] === 'En curso' ? 't-green' : ($m['state'] === 'Indicado' ? 't-blue' : 't-gray') }}">{{ $m['state'] }}</span></td><td>{{ $m['doctor'] ?? '—' }}</td></tr>
  @endforeach
</table>
@endif
@if ($bg['usual_medication'] !== [])<div class="small muted" style="margin-top:3px">Medicación habitual (historia): {{ implode(' · ', $bg['usual_medication']) }}</div>@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">4</td><td style="width:96%">Alergias e intolerancias</td></tr></table>
@if ($record['allergies'] === [])
<p class="small muted">{{ $none }}</p>
@else
<table class="data">
  <tr><th style="width:46%">Agente</th><th style="width:16%">Reacción</th><th style="width:14%">Gravedad</th><th style="width:24%">Fuente</th></tr>
  @foreach ($record['allergies'] as $i => $a)
  <tr @class(['alt' => $i % 2 === 1])><td><b>{{ $a['agent'] }}</b></td><td class="muted">{{ $none }}</td><td class="muted">{{ $none }}</td><td>{{ $a['source'] }}</td></tr>
  @endforeach
</table>
@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">5</td><td style="width:96%">Antecedentes</td></tr></table>
<table class="grid">
  <tr>
    <td style="width:50%"><div class="k">Patológicos personales</div>{{ $bg['personal'] !== [] ? implode(' · ', $bg['personal']) : $none }}@foreach ($bg['personal_notes'] as $note)<br><span class="small muted">{{ $note }}</span>@endforeach</td>
    <td style="width:50%"><div class="k">Familiares</div>{{ $bg['family'] !== [] ? implode(' · ', $bg['family']) : $none }}@foreach ($bg['family_notes'] as $note)<br><span class="small muted">{{ $note }}</span>@endforeach</td>
  </tr>
  <tr>
    <td><div class="k">Quirúrgicos</div>{{ $bg['surgical'] !== [] ? implode(' · ', $bg['surgical']) : $none }}</td>
    <td><div class="k">Hábitos</div>{{ $bg['habits'] !== [] ? implode(' · ', $bg['habits']) : $none }}@foreach ($bg['habits_notes'] as $note)<br><span class="small muted">{{ $note }}</span>@endforeach</td>
  </tr>
  <tr>
    <td><div class="k">Gineco-obstétricos</div>{{ $bg['gynecological'] !== [] ? implode(' · ', $bg['gynecological']) : $none }}@foreach ($bg['gynecological_notes'] as $note)<br><span class="small muted">{{ $note }}</span>@endforeach</td>
    <td><div class="k">Vacunas</div>{{ $bg['vaccines'] !== [] ? implode(' · ', $bg['vaccines']) : $none }}</td>
  </tr>
  <tr>
    <td colspan="2"><div class="k">Declarado al afiliarse (lo declaró el titular, no un médico)</div>
      @if (! $bg['declared']['answered']){{ $none }}@elseif ($bg['declared']['items'] === [])Respondió «no» a todas las preguntas del cuestionario médico.@else Respondió «sí» a: {{ implode(' · ', $bg['declared']['items']) }}@endif
    </td>
  </tr>
</table>

<table class="sec sec-title"><tr><td class="num" style="width:4%">6</td><td style="width:96%">Signos vitales y mediciones</td></tr></table>
@if ($record['vitals'] === [])
<p class="small muted">{{ $none }}</p>
@else
<table class="data">
  <tr><th style="width:11%">Fecha</th><th style="width:15%">Origen</th><th style="width:10%">PA</th><th style="width:8%">FC</th><th style="width:8%">FR</th><th style="width:10%">Temp.</th><th style="width:8%">SpO₂</th><th style="width:11%">Peso</th><th style="width:9%">Talla</th><th style="width:10%">IMC</th></tr>
  @foreach ($record['vitals'] as $i => $v)
  <tr @class(['alt' => $i % 2 === 1])><td>{{ $v['date'] }}</td><td>{{ $v['source'] }}</td><td>{{ $v['pa'] ?? '—' }}</td><td>{{ $v['fc'] ?? '—' }}</td><td>{{ $v['fr'] ?? '—' }}</td><td>{{ $v['temp'] ?? '—' }}</td><td>{{ $v['sat'] ?? '—' }}</td><td>{{ $v['weight'] ?? '—' }}</td><td>{{ $v['height'] ?? '—' }}</td><td>{{ $v['bmi'] ?? '—' }}</td></tr>
  @endforeach
</table>
@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">7</td><td style="width:96%">Laboratorios, estudios y especialistas (órdenes)</td></tr></table>
@if ($record['orders'] === [])
<p class="small muted">{{ $none }}</p>
@else
<table class="data">
  <tr><th style="width:12%">Fecha</th><th style="width:18%">Tipo</th><th style="width:52%">Orden</th><th style="width:18%">Estado</th></tr>
  @foreach ($record['orders'] as $i => $o)
  <tr @class(['alt' => $i % 2 === 1])><td>{{ $o['date'] }}</td><td>{{ $o['kind'] }}</td><td>{{ $o['name'] }}</td><td><span class="tag {{ $o['open'] ? 't-amber' : (strtoupper((string) $o['status']) === 'CANCELADA' ? 't-gray' : 't-green') }}">{{ $o['status'] ?? '—' }}</span></td></tr>
  @endforeach
</table>
@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">8</td><td style="width:96%">Línea de tiempo de atenciones</td></tr></table>
@if ($record['timeline'] === [])
<p class="small muted">{{ $none }}</p>
@else
<table class="data">
  <tr><th style="width:11%">Fecha</th><th style="width:13%">Tipo</th><th style="width:46%">Atención</th><th style="width:16%">Profesional</th><th style="width:14%">Referencia</th></tr>
  @foreach ($record['timeline'] as $i => $e)
  <tr @class(['alt' => $i % 2 === 1])><td>{{ $e['date'] }}</td><td>{{ $e['type'] }}</td><td><b>{{ $e['title'] }}</b>{{ $e['status'] ? ' · '.$e['status'] : '' }}@if ($e['detail'])<br>{{ $e['detail'] }}@endif</td><td>{{ $e['who'] ?? '—' }}</td><td class="code">{{ $e['reference'] ?? '' }}</td></tr>
  @endforeach
</table>
@endif

@if ($record['last_consultation'])
@php $lc = $record['last_consultation']; @endphp
<table class="sec note"><tr><td><b>Última consulta en detalle (SOAP)</b> · {{ $lc['date'] }}{{ $lc['doctor'] ? ' · '.$lc['doctor'] : '' }}</td></tr></table>
<table class="soap">
  @foreach (['s', 'o', 'a', 'p'] as $key)
  <tr><td class="l" style="width:4%">{{ strtoupper($key) }}</td><td style="width:96%">{{ $lc[$key] ?? $none }}</td></tr>
  @endforeach
</table>
@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">9</td><td style="width:96%">Pendientes y seguimiento</td></tr></table>
@if ($record['pending'] === [])
<p class="small muted">Nada pendiente.</p>
@else
<table class="data">
  <tr><th style="width:20%">Fecha</th><th style="width:50%">Pendiente</th><th style="width:15%">Responsable</th><th style="width:15%">Estado</th></tr>
  @foreach ($record['pending'] as $i => $item)
  <tr @class(['alt' => $i % 2 === 1])><td>{{ $item['date'] }}</td><td>{{ $item['item'] }}</td><td>{{ $item['owner'] }}</td><td><span class="tag t-amber">{{ $item['status'] }}</span></td></tr>
  @endforeach
</table>
@endif

<table class="sec sec-title"><tr><td class="num" style="width:4%">10</td><td style="width:96%">Documentos y consentimientos</td></tr></table>
<table class="data">
  <tr><th style="width:15%">Fecha</th><th style="width:85%">Documento</th></tr>
  <tr><td>—</td><td>Consentimiento de telemedicina: {{ $none }} · Autorización de datos de salud: {{ $none }}</td></tr>
  @foreach ($record['documents'] as $i => $d)
  <tr @class(['alt' => $i % 2 === 0])><td>{{ $d['date'] }}</td><td>{{ $d['name'] }}</td></tr>
  @endforeach
</table>

@if ($technical)
<table class="sec sec-title"><tr><td class="num" style="width:4%">11</td><td style="width:96%">Servicios de cortesía</td></tr></table>
@if ($record['courtesies'] === [])
<p class="small muted">El paciente no ha recibido servicios de cortesía.</p>
@else
<table class="data">
  <tr><th style="width:11%">Fecha</th><th style="width:14%">Tipo</th><th style="width:23%">Servicio</th><th style="width:24%">Motivo</th><th style="width:13%">Otorgado por</th><th style="width:15%">Referencia</th></tr>
  @foreach ($record['courtesies'] as $i => $courtesy)
  <tr @class(['alt' => $i % 2 === 1])><td>{{ $courtesy['date'] }}</td><td><span class="tag t-amber">{{ $courtesy['type'] }}</span></td><td><b>{{ $breakable($courtesy['service']) }}</b>@if ($courtesy['amount'])<br><span class="small muted">{{ $courtesy['amount'] }}</span>@endif</td><td>{{ $courtesy['reason'] ?? '—' }}</td><td>{{ $courtesy['by'] ?? '—' }}</td><td class="code">{{ $courtesy['reference'] ?? '—' }}</td></tr>
  @endforeach
</table>
<div class="small muted" style="margin-top:3px">Total: {{ count($record['courtesies']) }} {{ count($record['courtesies']) === 1 ? 'servicio' : 'servicios' }} de cortesía.</div>
@endif
@endif

<table class="sec" style="margin-top:12px"><tr>
  <td style="padding:7px 9px; border:1px solid #e2e8f0; background:#f8fafc" class="small">
    <b>Confidencialidad.</b> Información de salud protegida por el secreto médico, solo para el equipo clínico tratante y el titular.@if ($technical) Documento de uso interno de Tu Dr. Group: incluye datos de afiliación, cupos y cortesías.@endif
    Esta descarga quedó registrada a nombre de {{ $issuer }}.
  </td>
</tr></table>
</body>
</html>
