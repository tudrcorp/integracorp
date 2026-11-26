<x-filament-panels::page>
    @php
        $p = $ficha['patient'];
        $c = $ficha['coverage'];
        $bg = $ficha['background'];
        $none = 'Sin registro';
    @endphp

    <div class="cr-root">
        {{-- Alertas --}}
        <section class="cr-alerts" aria-label="Alertas clínicas">
            <div class="cr-alert {{ $ficha['alerts']['allergies'] !== [] ? 'is-danger' : '' }}">
                <span class="cr-alert-label">⚠ Alergias</span>
                <strong>{{ $ficha['alerts']['allergies'] !== [] ? implode(' · ', array_slice($ficha['alerts']['allergies'], 0, 4)) : $none }}</strong>
            </div>
            <div class="cr-alert {{ $ficha['alerts']['conditions'] !== [] ? 'is-danger' : '' }}">
                <span class="cr-alert-label">Condiciones personales</span>
                <strong>{{ $ficha['alerts']['conditions'] !== [] ? implode(' · ', array_slice($ficha['alerts']['conditions'], 0, 4)) : $none }}</strong>
            </div>
            <div class="cr-alert {{ $ficha['alerts']['medications'] !== [] ? 'is-info' : '' }}">
                <span class="cr-alert-label">Medicamentos en curso</span>
                <strong>{{ $ficha['alerts']['medications'] !== [] ? implode(' · ', array_slice($ficha['alerts']['medications'], 0, 3)) : 'Ninguno indicado ahora' }}</strong>
            </div>
            <div class="cr-alert">
                <span class="cr-alert-label">Grupo sanguíneo</span>
                <strong>{{ $none }}</strong>
            </div>
        </section>

        <p class="cr-note">
            Se arma en este momento con lo que Telemedicina y Operaciones registran: cada consulta, récipe, orden o servicio nuevo aparece aquí sin hacer nada.
            Actualizada el {{ $ficha['generated_at'] }}.
        </p>

        {{-- 1. Datos del paciente (clínica) / Identificación, afiliación y cobertura (técnica) --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>1</span> {{ $technical ? 'Identificación, afiliación y cobertura' : 'Datos del paciente' }}</h2>
            <dl class="cr-grid">
                <div><dt>Nombre</dt><dd><strong>{{ $p['name'] }}</strong></dd></div>
                <div><dt>Documento</dt><dd>{{ $p['document'] ?? $none }}</dd></div>
                <div><dt>Nacimiento · edad</dt><dd>{{ $p['birth'] ?? $none }}{{ $p['age'] ? ' · '.$p['age'] : '' }}</dd></div>
                <div><dt>Sexo</dt><dd>{{ $p['sex'] ?? $none }}</dd></div>
                <div><dt>Teléfono</dt><dd>{{ $p['phone'] ?? $none }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $p['email'] ?? $none }}</dd></div>
                <div class="cr-span2"><dt>Dirección</dt><dd>{{ $p['address'] ?? $none }}</dd></div>
                <div><dt>Contacto alterno</dt><dd>{{ $p['contact'] ?? $none }}</dd></div>
                @if ($technical)
                    <div><dt>Plan</dt><dd>{{ $c['plan'] ?? $none }}{{ $c['coverage'] ? ' · Cobertura '.$c['coverage'] : '' }}</dd></div>
                    <div><dt>Afiliación</dt><dd>{{ $c['type'] ?? $none }}{{ $c['company'] ? ' · '.$c['company'] : '' }}{{ $c['code'] ? ' · '.$c['code'] : '' }}</dd></div>
                    <div><dt>Vigencia</dt><dd>{{ $c['period'] ?? $none }} @if ($c['status'])<span class="cr-tag {{ str_contains(strtoupper($c['status']), 'ACTIV') ? 'is-green' : 'is-gray' }}">{{ $c['status'] }}</span>@endif</dd></div>
                @endif
            </dl>

            @if ($technical && $ficha['quotas'] !== [])
                <div class="cr-scroll cr-mt">
                    <table class="cr-table cr-fixed" style="min-width: 640px">
                        <colgroup><col style="width: 46%"><col style="width: 12%"><col style="width: 16%"><col style="width: 26%"></colgroup>
                        <thead><tr><th>Cupos clínicos del año de afiliación (orden del plan)</th><th>Usados</th><th>Disponibles</th><th>Consumo</th></tr></thead>
                        <tbody>
                            @foreach ($ficha['quotas'] as $quota)
                                <tr>
                                    <td>{{ $quota['label'] }}</td>
                                    <td>{{ $quota['used'] }}</td>
                                    <td>{{ $quota['quota'] === null ? 'Sin límite' : ($quota['remaining'] ?? 0).' de '.$quota['quota'] }}</td>
                                    <td>@if ($quota['quota'] !== null)<div class="cr-bar"><span style="width: {{ $quota['percent'] }}%" class="{{ $quota['percent'] >= 100 ? 'is-full' : '' }}"></span></div>@else<span class="cr-muted cr-small">Sin límite</span>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 2. Problemas --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>2</span> Diagnósticos registrados</h2>
            <p class="cr-hint">Como los escribió el médico en cada consulta. Aún sin código CIE-10.</p>
            @if ($ficha['problems'] === [])
                <p class="cr-empty">{{ $none }}</p>
            @else
                <table class="cr-table">
                    <thead><tr><th class="w-28">Fecha</th><th>Diagnóstico</th><th class="w-40">Estado de la consulta</th><th class="w-48">Médico</th></tr></thead>
                    <tbody>
                        @foreach ($ficha['problems'] as $problem)
                            <tr><td>{{ $problem['date'] }}</td><td>{{ $problem['text'] }}</td><td>@if ($problem['status'])<span class="cr-tag {{ $problem['status'] === 'ALTA MEDICA' ? 'is-gray' : 'is-amber' }}">{{ $problem['status'] }}</span>@endif</td><td>{{ $problem['doctor'] ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- 3. Medicación --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>3</span> Medicación</h2>
            <p class="cr-hint">«En curso» si la fecha de indicación más los días indicados aún no termina. El despacho es el estado de la entrega en Operaciones.</p>
            @if ($ficha['medications'] === [])
                <p class="cr-empty">{{ $none }}</p>
            @else
                <div class="cr-scroll">
                    <table class="cr-table cr-fixed" style="min-width: 960px">
                        <colgroup>
                            <col style="width: 21%"><col style="width: 27%"><col style="width: 9%"><col style="width: 9%">
                            <col style="width: 10%"><col style="width: 10%"><col style="width: 14%">
                        </colgroup>
                        <thead><tr><th>Medicamento</th><th>Indicaciones</th><th>Desde</th><th>Hasta</th><th>Estado</th><th>Despacho</th><th>Indicado por</th></tr></thead>
                        <tbody>
                            @foreach ($ficha['medications'] as $medication)
                                <tr>
                                    <td>
                                        <strong class="cr-block">{{ $medication['name'] }}</strong>
                                        @if ($medication['covered'] === false)<span class="cr-tag is-red cr-tag-block">No cubierto</span>@endif
                                    </td>
                                    <td>{{ $medication['indications'] ?? '—' }}</td>
                                    <td class="cr-nowrap">{{ $medication['since'] }}</td>
                                    <td class="cr-nowrap">{{ $medication['until'] ?? '—' }}</td>
                                    <td><span @class(['cr-tag', 'is-green' => $medication['state'] === 'En curso', 'is-gray' => in_array($medication['state'], ['Terminado', 'Cancelado'], true), 'is-blue' => $medication['state'] === 'Indicado'])>{{ $medication['state'] }}</span></td>
                                    <td class="cr-muted">{{ $medication['dispatch'] ?? '—' }}</td>
                                    <td>{{ $medication['doctor'] ?? '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
            @if ($bg['usual_medication'] !== [])
                <p class="cr-sub"><strong>Medicación habitual (historia):</strong> {{ implode(' · ', $bg['usual_medication']) }}</p>
            @endif
        </section>

        {{-- 4. Alergias --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>4</span> Alergias e intolerancias</h2>
            @if ($ficha['allergies'] === [])
                <p class="cr-empty">{{ $none }}</p>
            @else
                <table class="cr-table">
                    <thead><tr><th>Agente</th><th class="w-32">Reacción</th><th class="w-32">Gravedad</th><th class="w-56">Fuente</th></tr></thead>
                    <tbody>
                        @foreach ($ficha['allergies'] as $allergy)
                            <tr><td><strong>{{ $allergy['agent'] }}</strong></td><td class="cr-muted">{{ $none }}</td><td class="cr-muted">{{ $none }}</td><td>{{ $allergy['source'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- 5. Antecedentes --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>5</span> Antecedentes</h2>
            <p class="cr-hint">
                @if ($bg['history_date'])
                    Historia clínica {{ $bg['history_code'] ? $bg['history_code'].' · ' : '' }}del {{ $bg['history_date'] }}{{ $bg['history_count'] > 1 ? ' (el paciente tiene '.$bg['history_count'].' historias; se muestra la más reciente)' : '' }}.
                @else
                    El paciente no tiene historia clínica cargada.
                @endif
            </p>
            <dl class="cr-grid cr-grid-2">
                <div><dt>Patológicos personales</dt><dd>{{ $bg['personal'] !== [] ? implode(' · ', $bg['personal']) : $none }}@foreach ($bg['personal_notes'] as $note)<span class="cr-line">{{ $note }}</span>@endforeach</dd></div>
                <div><dt>Familiares</dt><dd>{{ $bg['family'] !== [] ? implode(' · ', $bg['family']) : $none }}@foreach ($bg['family_notes'] as $note)<span class="cr-line">{{ $note }}</span>@endforeach</dd></div>
                <div><dt>Quirúrgicos</dt><dd>{{ $bg['surgical'] !== [] ? implode(' · ', $bg['surgical']) : $none }}</dd></div>
                <div><dt>Hábitos</dt><dd>{{ $bg['habits'] !== [] ? implode(' · ', $bg['habits']) : $none }}@foreach ($bg['habits_notes'] as $note)<span class="cr-line">{{ $note }}</span>@endforeach</dd></div>
                <div><dt>Gineco-obstétricos</dt><dd>{{ $bg['gynecological'] !== [] ? implode(' · ', $bg['gynecological']) : $none }}@foreach ($bg['gynecological_notes'] as $note)<span class="cr-line">{{ $note }}</span>@endforeach</dd></div>
                <div><dt>Vacunas</dt><dd>{{ $bg['vaccines'] !== [] ? implode(' · ', $bg['vaccines']) : $none }}</dd></div>
                <div class="cr-span2">
                    <dt>Declarado al afiliarse <span class="cr-tag is-blue cr-ml">Lo declaró el titular, no un médico</span></dt>
                    <dd>
                        @if (! $bg['declared']['answered'])
                            {{ $none }}
                        @elseif ($bg['declared']['items'] === [])
                            Respondió «no» a todas las preguntas del cuestionario médico.
                        @else
                            Respondió «sí» a: {{ implode(' · ', $bg['declared']['items']) }}
                        @endif
                    </dd>
                </div>
            </dl>
        </section>

        {{-- 6. Signos vitales --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>6</span> Signos vitales y mediciones</h2>
            @if ($ficha['vitals'] === [])
                <p class="cr-empty">{{ $none }}</p>
            @else
                <div class="cr-scroll">
                    <table class="cr-table">
                        <thead><tr><th>Fecha</th><th>Origen</th><th>PA</th><th>FC</th><th>FR</th><th>Temp.</th><th>SpO₂</th><th>Peso</th><th>Talla</th><th>IMC</th></tr></thead>
                        <tbody>
                            @foreach ($ficha['vitals'] as $vital)
                                <tr><td>{{ $vital['date'] }}</td><td>{{ $vital['source'] }}</td><td>{{ $vital['pa'] ?? '—' }}</td><td>{{ $vital['fc'] ?? '—' }}</td><td>{{ $vital['fr'] ?? '—' }}</td><td>{{ $vital['temp'] ?? '—' }}</td><td>{{ $vital['sat'] ?? '—' }}</td><td>{{ $vital['weight'] ?? '—' }}</td><td>{{ $vital['height'] ?? '—' }}</td><td>{{ $vital['bmi'] ?? '—' }}</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- 7. Resultados / órdenes --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>7</span> Laboratorios, estudios y especialistas</h2>
            <p class="cr-hint">Órdenes y su gestión. El valor del resultado todavía no se registra en el sistema.</p>
            @if ($ficha['orders'] === [])
                <p class="cr-empty">{{ $none }}</p>
            @else
                <table class="cr-table">
                    <thead><tr><th class="w-28">Fecha</th><th class="w-40">Tipo</th><th>Orden</th><th class="w-36">Estado</th></tr></thead>
                    <tbody>
                        @foreach ($ficha['orders'] as $order)
                            <tr><td>{{ $order['date'] }}</td><td>{{ $order['kind'] }}</td><td>{{ $order['name'] }}</td><td>@if ($order['status'])<span class="cr-tag {{ $order['open'] ? 'is-amber' : ($order['status'] === 'CANCELADA' ? 'is-gray' : 'is-green') }}">{{ $order['status'] }}</span>@endif</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- 8. Línea de tiempo --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>8</span> Línea de tiempo de atenciones</h2>
            @if ($ficha['timeline'] === [])
                <p class="cr-empty">{{ $none }}</p>
            @else
                <ol class="cr-timeline">
                    @foreach ($ficha['timeline'] as $event)
                        <li>
                            <span class="cr-dot is-{{ $event['source'] }}" aria-hidden="true"></span>
                            <div class="cr-event">
                                <div class="cr-event-head">
                                    <span class="cr-event-date">{{ $event['date'] }}</span>
                                    <span class="cr-tag is-gray">{{ $event['type'] }}</span>
                                    @if ($event['url'])
                                        <a href="{{ $event['url'] }}" class="cr-link" wire:navigate>{{ $event['title'] }}</a>
                                    @else
                                        <strong>{{ $event['title'] }}</strong>
                                    @endif
                                    @if ($event['status'])<span class="cr-muted">· {{ $event['status'] }}</span>@endif
                                </div>
                                @if ($event['detail'])<p>{{ $event['detail'] }}</p>@endif
                                <p class="cr-muted cr-small">{{ implode(' · ', array_filter([$event['who'], $event['reference']])) }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if ($ficha['last_consultation'])
                @php $lc = $ficha['last_consultation']; @endphp
                <div class="cr-soap">
                    <p class="cr-soap-head"><strong>Última consulta en detalle (SOAP)</strong> · {{ $lc['date'] }}{{ $lc['doctor'] ? ' · '.$lc['doctor'] : '' }}{{ $lc['status'] ? ' · '.$lc['status'] : '' }}</p>
                    @foreach (['s' => 'Subjetivo', 'o' => 'Objetivo', 'a' => 'Análisis', 'p' => 'Plan'] as $key => $label)
                        <div class="cr-soap-row"><span title="{{ $label }}">{{ strtoupper($key) }}</span><p>{{ $lc[$key] ?? $none }}</p></div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- 9. Plan --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>9</span> Pendientes y seguimiento</h2>
            @if ($ficha['pending'] === [])
                <p class="cr-empty">Nada pendiente.</p>
            @else
                <table class="cr-table">
                    <thead><tr><th class="w-48">Fecha</th><th>Pendiente</th><th class="w-32">Responsable</th><th class="w-36">Estado</th></tr></thead>
                    <tbody>
                        @foreach ($ficha['pending'] as $item)
                            <tr><td>{{ $item['date'] }}</td><td>{{ $item['item'] }}</td><td>{{ $item['owner'] }}</td><td><span class="cr-tag is-amber">{{ $item['status'] }}</span></td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        {{-- 10. Documentos --}}
        <section class="cr-card">
            <h2 class="cr-title"><span>10</span> Documentos y consentimientos</h2>
            <dl class="cr-grid cr-grid-2">
                <div><dt>Consentimiento de telemedicina</dt><dd>{{ $none }}</dd></div>
                <div><dt>Autorización de datos de salud</dt><dd>{{ $none }}</dd></div>
            </dl>
            @if ($ficha['documents'] !== [])
                <table class="cr-table cr-mt">
                    <thead><tr><th class="w-28">Fecha</th><th>Documento</th></tr></thead>
                    <tbody>
                        @foreach ($ficha['documents'] as $document)
                            <tr><td>{{ $document['date'] }}</td><td>{{ $document['name'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </section>

        @if ($technical)
            {{-- 11. Cortesías (solo ficha técnica) --}}
            <section class="cr-card">
                <h2 class="cr-title"><span>11</span> Servicios de cortesía</h2>
                <p class="cr-hint">Servicios dados al paciente sin cargo: ítems marcados con estatus CORTESIA en sus coordinaciones y órdenes de servicio de cortesía.</p>
                @if ($ficha['courtesies'] === [])
                    <p class="cr-empty">El paciente no ha recibido servicios de cortesía.</p>
                @else
                    <div class="cr-scroll">
                        <table class="cr-table cr-fixed" style="min-width: 860px">
                            <colgroup><col style="width: 10%"><col style="width: 13%"><col style="width: 24%"><col style="width: 25%"><col style="width: 14%"><col style="width: 14%"></colgroup>
                            <thead><tr><th>Fecha</th><th>Tipo</th><th>Servicio</th><th>Motivo</th><th>Otorgado por</th><th>Referencia</th></tr></thead>
                            <tbody>
                                @foreach ($ficha['courtesies'] as $courtesy)
                                    <tr>
                                        <td class="cr-nowrap">{{ $courtesy['date'] }}</td>
                                        <td><span class="cr-tag is-amber">{{ $courtesy['type'] }}</span></td>
                                        <td><strong>{{ $courtesy['service'] }}</strong>@if ($courtesy['amount'])<span class="cr-line">{{ $courtesy['amount'] }}</span>@endif</td>
                                        <td>{{ $courtesy['reason'] ?? '—' }}</td>
                                        <td>{{ $courtesy['by'] ?? '—' }}</td>
                                        <td>{{ $courtesy['reference'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <p class="cr-sub">Total: {{ count($ficha['courtesies']) }} {{ count($ficha['courtesies']) === 1 ? 'servicio' : 'servicios' }} de cortesía.</p>
                @endif
            </section>
        @endif

        {{-- Bitácora --}}
        <section class="cr-card cr-audit">
            <h2 class="cr-title cr-title-plain">Quién consultó esta ficha</h2>
            <p class="cr-hint">Información de salud protegida por el secreto médico. Cada apertura y cada descarga quedan registradas.</p>
            @if ($accesses !== [])
                <ul class="cr-access">
                    @foreach ($accesses as $access)
                        <li><strong>{{ $access['who'] }}</strong> · {{ $access['action'] }} · {{ $access['panel'] }} · {{ $access['at'] }}</li>
                    @endforeach
                </ul>
            @endif
        </section>
    </div>

    <style>
        .cr-root { display: flex; flex-direction: column; gap: 16px; font-size: 14px; color: #1e293b; }
        .cr-alerts { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 10px; }
        .cr-alert { padding: 12px 14px; border-radius: 12px; border: 1px solid #e2e8f0; background: #fff; display: flex; flex-direction: column; gap: 4px; }
        .cr-alert.is-danger { background: #fef2f2; border-color: #fecaca; }
        .cr-alert.is-info { background: #eff6ff; border-color: #bfdbfe; }
        .cr-alert-label { font-size: 11px; font-weight: 700; letter-spacing: .05em; text-transform: uppercase; color: #64748b; }
        .cr-alert.is-danger .cr-alert-label { color: #b91c1c; }
        .cr-alert strong { font-size: 13.5px; line-height: 1.35; }
        .cr-note { margin: 0; font-size: 12.5px; color: #64748b; }
        .cr-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 16px 18px; box-shadow: 0 1px 2px rgba(15, 23, 42, .04); }
        .cr-title { display: flex; align-items: center; gap: 10px; margin: 0 0 10px; font-size: 15px; font-weight: 700; color: #052F60; }
        .cr-title > span { display: inline-grid; place-items: center; width: 26px; height: 26px; border-radius: 8px; background: #052F60; color: #fff; font-size: 12.5px; }
        .cr-title-plain { font-size: 14px; }
        .cr-hint { margin: -4px 0 10px; font-size: 12.5px; color: #64748b; }
        .cr-empty { margin: 0; padding: 10px 12px; border-radius: 10px; background: #f8fafc; color: #64748b; font-style: italic; }
        .cr-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px 18px; margin: 0; }
        .cr-grid-2 { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        .cr-span2 { grid-column: span 2; }
        .cr-grid dt { font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; }
        .cr-grid dd { margin: 2px 0 0; overflow-wrap: anywhere; }
        .cr-line { display: block; margin-top: 4px; color: #475569; font-size: 13px; }
        .cr-table { width: 100%; border-collapse: collapse; }
        .cr-table th { text-align: left; font-size: 11px; font-weight: 600; letter-spacing: .04em; text-transform: uppercase; color: #64748b; background: #f8fafc; padding: 7px 10px; border-bottom: 1px solid #e2e8f0; }
        .cr-table td { padding: 8px 10px; border-bottom: 1px solid #f1f5f9; vertical-align: top; overflow-wrap: anywhere; }
        .cr-scroll { overflow-x: auto; }
        .cr-fixed { table-layout: fixed; }
        .cr-fixed td { overflow-wrap: break-word; word-break: normal; hyphens: auto; }
        .cr-block { display: block; }
        .cr-tag-block { margin-top: 4px; }
        .cr-nowrap { white-space: nowrap; }
        .cr-mt { margin-top: 12px; }
        .cr-ml { margin-left: 6px; }
        .cr-sub { margin: 10px 0 0; font-size: 13px; color: #475569; }
        .cr-muted { color: #64748b; }
        .cr-small { font-size: 12px; }
        .cr-tag { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 11.5px; font-weight: 600; background: #f1f5f9; color: #334155; white-space: nowrap; }
        .cr-tag.is-green { background: #dcfce7; color: #166534; }
        .cr-tag.is-amber { background: #fef3c7; color: #92400e; }
        .cr-tag.is-red { background: #fee2e2; color: #991b1b; }
        .cr-tag.is-blue { background: #dbeafe; color: #1e40af; }
        .cr-tag.is-gray { background: #e2e8f0; color: #334155; }
        .cr-bar { height: 8px; border-radius: 999px; background: #e2e8f0; overflow: hidden; }
        .cr-bar span { display: block; height: 100%; background: #f59e0b; border-radius: 999px; }
        .cr-bar span.is-full { background: #dc2626; }
        .cr-timeline { list-style: none; margin: 0; padding: 0 0 0 6px; border-left: 2px solid #e2e8f0; display: flex; flex-direction: column; gap: 12px; }
        .cr-timeline li { position: relative; padding-left: 16px; }
        .cr-dot { position: absolute; left: -8px; top: 5px; width: 12px; height: 12px; border-radius: 999px; background: #94a3b8; border: 2px solid #fff; }
        .cr-dot.is-consultation { background: #0d9488; }
        .cr-dot.is-case { background: #052F60; }
        .cr-dot.is-coordination { background: #d97706; }
        .cr-dot.is-amd { background: #7c3aed; }
        .cr-event-head { display: flex; flex-wrap: wrap; align-items: center; gap: 8px; }
        .cr-event-date { font-variant-numeric: tabular-nums; font-weight: 600; color: #475569; }
        .cr-event p { margin: 3px 0 0; }
        .cr-link { color: #0d9488; font-weight: 600; text-decoration: underline; text-underline-offset: 3px; }
        .cr-soap { margin-top: 16px; border: 1px solid #e2e8f0; border-radius: 12px; overflow: hidden; }
        .cr-soap-head { margin: 0; padding: 9px 12px; background: #f0fdfa; border-left: 3px solid #0d9488; }
        .cr-soap-row { display: flex; border-top: 1px solid #e2e8f0; }
        .cr-soap-row span { flex: none; width: 34px; display: grid; place-items: center; background: #0d9488; color: #fff; font-weight: 700; }
        .cr-soap-row p { margin: 0; padding: 8px 12px; }
        .cr-access { margin: 0; padding-left: 18px; font-size: 13px; color: #475569; }
        @media (max-width: 1024px) { .cr-alerts, .cr-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 640px) { .cr-alerts, .cr-grid, .cr-grid-2 { grid-template-columns: minmax(0, 1fr); } .cr-span2 { grid-column: auto; } }
        .dark .cr-root { color: #e2e8f0; }
        .dark .cr-card, .dark .cr-alert { background: #0f172a; border-color: #1e293b; }
        .dark .cr-alert.is-danger { background: rgba(220, 38, 38, .12); border-color: rgba(248, 113, 113, .35); }
        .dark .cr-alert.is-info { background: rgba(37, 99, 235, .12); border-color: rgba(96, 165, 250, .35); }
        .dark .cr-title { color: #93c5fd; }
        .dark .cr-title > span { background: #1e3a8a; }
        .dark .cr-table th, .dark .cr-empty { background: #1e293b; color: #94a3b8; }
        .dark .cr-table th { border-color: #334155; }
        .dark .cr-table td { border-color: #1e293b; }
        .dark .cr-tag { background: #1e293b; color: #cbd5e1; }
        .dark .cr-tag.is-green { background: rgba(34, 197, 94, .16); color: #4ade80; }
        .dark .cr-tag.is-amber { background: rgba(245, 158, 11, .16); color: #fbbf24; }
        .dark .cr-tag.is-red { background: rgba(239, 68, 68, .16); color: #f87171; }
        .dark .cr-tag.is-blue { background: rgba(59, 130, 246, .16); color: #93c5fd; }
        .dark .cr-bar, .dark .cr-timeline { background: transparent; border-color: #334155; }
        .dark .cr-bar { background: #334155; }
        .dark .cr-dot { border-color: #0f172a; }
        .dark .cr-soap, .dark .cr-soap-row { border-color: #334155; }
        .dark .cr-soap-head { background: rgba(13, 148, 136, .12); }
        .dark .cr-line, .dark .cr-sub, .dark .cr-access, .dark .cr-event-date { color: #94a3b8; }
    </style>
</x-filament-panels::page>
