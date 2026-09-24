<x-filament-panels::page>
    @php
        $failed = $report['failed'] ?? [];
        $workers = $report['workers'] ?? ['known' => false, 'alive' => []];
        $categoryClasses = ['provider' => 'amber', 'network' => 'amber', 'code' => 'red', 'resources' => 'red', 'config' => 'blue', 'data' => 'gray', 'unknown' => 'gray'];
        $statusClasses = ['unattended' => 'red', 'stuck' => 'red', 'zombie' => 'amber', 'busy' => 'blue', 'ok' => 'green', 'unknown' => 'gray'];
        $errorClasses = ['regression' => 'red', 'new' => 'amber', 'active' => 'blue', 'resolved' => 'green'];
        $openErrors = count(array_filter($errors, static fn (array $error): bool => $error['status'] !== 'resolved'));
        $tabCounts = [
            'causas' => $failed['total'] ?? null,
            'fallidos' => $failed['total'] ?? null,
            'errores' => $openErrors,
            'colas' => $report['pending_total'] ?? null,
        ];
    @endphp

    <style>
        .lqc { --q-bg: #ffffff; --q-soft: #f8fafc; --q-border: #e5e7eb; --q-text: #0f172a; --q-muted: #64748b; --q-red: #dc2626; --q-amber: #d97706; --q-green: #16a34a; --q-blue: #0284c7;
               display: flex; flex-direction: column; gap: 16px; color: var(--q-text); }
        .dark .lqc { --q-bg: #0b1220; --q-soft: #0f172a; --q-border: #1e293b; --q-text: #e2e8f0; --q-muted: #94a3b8; --q-blue: #38bdf8; }
        .lqc-card { background: var(--q-bg); border: 1px solid var(--q-border); border-radius: 16px; }
        .lqc-top { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: center; gap: 10px; font-size: 13px; color: var(--q-muted); }
        .lqc-tabs { display: flex; flex-wrap: wrap; gap: 8px; }
        .lqc-tab { display: inline-flex; align-items: center; gap: 8px; border: 1px solid var(--q-border); background: var(--q-bg); color: var(--q-text); border-radius: 999px; padding: 7px 14px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .lqc-tab.on { background: var(--q-blue); border-color: var(--q-blue); color: #fff; }
        .lqc-tab span { font-size: 11px; opacity: .75; }
        .lqc-kicker { white-space: nowrap; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--q-muted); }
        .lqc-muted { color: var(--q-muted); font-size: 12.5px; }
        .lqc-mono { font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 12px; word-break: break-all; }
        .lqc-queue-name { font-weight: 700; white-space: nowrap; word-break: normal; }
        .lqc-badge { display: inline-flex; align-items: center; border-radius: 6px; padding: 2px 8px; font-size: 11px; font-weight: 800; white-space: nowrap; background: rgba(100, 116, 139, .15); color: var(--q-muted); }
        .lqc-badge.red { background: rgba(220, 38, 38, .15); color: var(--q-red); } .lqc-badge.amber { background: rgba(217, 119, 6, .16); color: var(--q-amber); }
        .lqc-badge.green { background: rgba(22, 163, 74, .14); color: var(--q-green); } .lqc-badge.blue { background: rgba(2, 132, 199, .13); color: var(--q-blue); }
        .lqc-group { display: grid; grid-template-columns: 90px minmax(0, 1fr) auto; gap: 16px; align-items: start; padding: 14px 18px; border-top: 1px solid var(--q-border); }
        .lqc-group:first-of-type { border-top: 0; }
        .lqc-count { font-size: 30px; font-weight: 800; line-height: 1; font-variant-numeric: tabular-nums; }
        .lqc-title { font-size: 15px; font-weight: 800; margin: 4px 0 2px; }
        .lqc-advice { font-size: 13px; line-height: 1.5; }
        .lqc-error { margin-top: 6px; font-size: 12px; color: var(--q-muted); word-break: break-word; }
        .lqc-actions { display: flex; flex-direction: column; gap: 6px; align-items: flex-end; }
        .lqc-reco { font-size: 11px; font-weight: 700; color: var(--q-muted); }
        .lqc-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .lqc-table th { text-align: left; font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: .05em; color: var(--q-muted); padding: 9px 14px; border-bottom: 1px solid var(--q-border); white-space: nowrap; }
        .lqc-table td { padding: 9px 14px; border-top: 1px solid var(--q-border); vertical-align: top; font-variant-numeric: tabular-nums; }
        .lqc-table .num { text-align: right; }
        .lqc-chip { display: inline-block; font-size: 11px; border: 1px solid var(--q-border); border-radius: 999px; padding: 1px 8px; margin: 2px 4px 0 0; }
        .lqc-head { display: flex; justify-content: space-between; align-items: center; gap: 10px; padding: 12px 18px; border-bottom: 1px solid var(--q-border); flex-wrap: wrap; }
        .lqc-empty { padding: 16px 18px; font-size: 13px; color: var(--q-muted); }
        .lqc-empty::before { content: '✓ '; color: var(--q-green); font-weight: 800; }
        .lqc-kpis { display: grid; grid-template-columns: repeat(5, minmax(0, 1fr)); }
        .lqc-kpis > div { padding: 12px 18px; border-left: 1px solid var(--q-border); }
        .lqc-kpis > div:first-child { border-left: 0; }
        .lqc-kpi { font-size: 22px; font-weight: 800; font-variant-numeric: tabular-nums; }
        .lqc-scroll { overflow-x: auto; }
        .lqc-pager { display: flex; justify-content: space-between; align-items: center; gap: 10px; flex-wrap: wrap; padding: 10px 18px; border-top: 1px solid var(--q-border); }
        .lqc-pages { display: flex; align-items: center; gap: 4px; }
        .lqc-page { min-width: 32px; height: 32px; border: 1px solid var(--q-border); background: var(--q-bg); color: var(--q-text); border-radius: 8px; font-size: 13px; font-weight: 700; cursor: pointer; }
        .lqc-page.on { background: var(--q-blue); border-color: var(--q-blue); color: #fff; }
        .lqc-page:disabled { opacity: .4; cursor: default; }
        @media (max-width: 900px) { .lqc-group { grid-template-columns: minmax(0, 1fr); } .lqc-actions { align-items: flex-start; flex-direction: row; flex-wrap: wrap; } .lqc-kpis { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
    </style>

    <div class="lqc" wire:poll.15s.visible>
        <div class="lqc-top">
            <span>Se actualiza cada 15 s · {{ $refreshedAt }}</span>
            <span>Los fallidos de más de {{ (int) config('live-presence.queues.prune_failed_days', 30) }} días se borran solos cada madrugada.</span>
        </div>

        @include('live-presence.partials.advisor', ['advice' => $advice, 'actions' => true, 'lights' => ['queues', 'errors']])

        <div class="lqc-card lqc-kpis">
            <div><div class="lqc-kicker">Fallidos · 1 h</div><div class="lqc-kpi" style="{{ ($failed['last_hour'] ?? 0) > 0 ? 'color: var(--q-red);' : '' }}">{{ $failed['last_hour'] ?? '—' }}</div></div>
            <div><div class="lqc-kicker">Fallidos · 24 h</div><div class="lqc-kpi">{{ $failed['last_24h'] ?? '—' }}</div></div>
            <div><div class="lqc-kicker">Fallidos · total</div><div class="lqc-kpi">{{ $failed['total'] ?? '—' }}</div></div>
            <div><div class="lqc-kicker">Procesados · 30 min</div><div class="lqc-kpi">{{ $report['throughput']['processed_30m'] ?? '—' }}</div></div>
            <div><div class="lqc-kicker">Workers vivos</div><div class="lqc-kpi">{{ $workers['known'] ? count($workers['alive']) : '—' }}</div></div>
        </div>

        <div class="lqc-tabs" role="tablist">
            @foreach ($tabs as $key => $label)
                <button type="button" role="tab" aria-selected="{{ $tab === $key ? 'true' : 'false' }}" class="lqc-tab {{ $tab === $key ? 'on' : '' }}" wire:click="selectTab('{{ $key }}')" wire:key="tab-{{ $key }}">
                    {{ $label }}@if (($tabCounts[$key] ?? null) !== null)<span>{{ $tabCounts[$key] }}</span>@endif
                </button>
            @endforeach
        </div>

        @if ($tab === 'causas')
            <div class="lqc-card">
                <div class="lqc-head">
                    <span class="lqc-kicker">Fallidos agrupados por causa</span>
                    <span class="lqc-muted">Cada grupo trae su diagnóstico y la acción recomendada. Eliminar y reintentar afectan a todo el grupo.</span>
                </div>
                @forelse ($groups as $group)
                    <div class="lqc-group" wire:key="group-{{ $group['fingerprint'] }}">
                        <div>
                            <div class="lqc-count">{{ $group['count'] }}</div>
                            <div class="lqc-muted">{{ $group['count'] === 1 ? 'fallido' : 'fallidos' }}</div>
                            <div class="lqc-muted" style="margin-top: 6px;">último {{ $group['last_ago'] }}</div>
                        </div>
                        <div style="min-width: 0;">
                            <span class="lqc-badge {{ $categoryClasses[$group['diagnosis']['category']] ?? 'gray' }}">{{ $group['diagnosis']['category_label'] }}</span>
                            <span class="lqc-muted" style="margin-left: 6px;"><strong style="color: var(--q-text);">{{ $group['job'] }}</strong> · {{ implode(', ', $group['queues']) }}</span>
                            <div class="lqc-title">{{ $group['diagnosis']['title'] }}</div>
                            <div class="lqc-advice">{{ $group['diagnosis']['advice'] }}</div>
                            <div class="lqc-error lqc-mono" title="{{ $group['message'] }}">{{ $group['exception'] }}: {{ \Illuminate\Support\Str::limit($group['message'], 220) }}</div>
                            <div class="lqc-muted" style="margin-top: 4px;">Del {{ $group['first_at'] }} al {{ $group['last_at'] }}@if ($group['sends_messages']) · ⚠ envía mensajes: reintentar puede duplicar envíos @endif</div>
                        </div>
                        <div class="lqc-actions">
                            <span class="lqc-reco">Recomendado: {{ $group['diagnosis']['action_label'] }}</span>
                            {{ ($this->retryGroupAction)(['fingerprint' => $group['fingerprint']]) }}
                            {{ ($this->deleteGroupAction)(['fingerprint' => $group['fingerprint']]) }}
                            {{ ($this->viewFailedAction)(['uuid' => $group['last_uuid']]) }}
                        </div>
                    </div>
                @empty
                    <div class="lqc-empty">No hay trabajos fallidos. Las colas están limpias.</div>
                @endforelse
                @include('live-presence.partials.pager', ['pager' => $groupsPager, 'list' => 'groups'])
            </div>
        @elseif ($tab === 'fallidos')
            {{ $this->table }}
        @elseif ($tab === 'errores')
            <div class="lqc-card">
                <div class="lqc-head">
                    <span class="lqc-kicker">Errores del sistema · últimos {{ (int) config('live-presence.errors.retention_days', 7) }} días</span>
                    <span class="lqc-muted">Agrupados por la línea de nuestro código donde se originan. Marque «resuelto» al desplegar la corrección: si vuelve, se avisa como regresión.</span>
                </div>
                @forelse ($errorsVisible as $error)
                    <div class="lqc-group" wire:key="error-{{ $error['fingerprint'] }}" style="{{ $error['status'] === 'resolved' ? 'opacity: .6;' : '' }}">
                        <div>
                            <div class="lqc-count">{{ $error['count'] }}</div>
                            <div class="lqc-muted">{{ $error['count'] === 1 ? 'vez' : 'veces' }}</div>
                            <div class="lqc-muted" style="margin-top: 6px;">{{ $error['users'] }} {{ $error['users'] === 1 ? 'usuario' : 'usuarios' }}</div>
                        </div>
                        <div style="min-width: 0;">
                            <span class="lqc-badge {{ $errorClasses[$error['status']] }}">{{ $error['status_label'] }}</span>
                            <span class="lqc-badge {{ $categoryClasses[$error['diagnosis']['category']] ?? 'gray' }}" style="margin-left: 4px;">{{ $error['diagnosis']['category_label'] }}</span>
                            <span class="lqc-muted" style="margin-left: 6px;">último {{ $error['last_ago'] }}</span>
                            <div class="lqc-title">{{ $error['short_class'] }}: {{ \Illuminate\Support\Str::limit($error['message'], 160) }}</div>
                            <div class="lqc-mono" style="color: var(--q-red); font-weight: 700; margin-top: 2px;">{{ $error['origin'] ?: $error['location'] }}</div>
                            <div class="lqc-error">{{ $error['contexts'][0] ?? '' }}@if (count($error['contexts']) > 1) · y {{ count($error['contexts']) - 1 }} lugares más @endif</div>
                        </div>
                        <div class="lqc-actions">
                            {{ ($this->viewErrorAction)(['fingerprint' => $error['fingerprint']]) }}
                            @if ($error['status'] === 'resolved')
                                {{ ($this->reopenErrorAction)(['fingerprint' => $error['fingerprint']]) }}
                            @else
                                {{ ($this->resolveErrorAction)(['fingerprint' => $error['fingerprint']]) }}
                            @endif
                            {{ ($this->forgetErrorAction)(['fingerprint' => $error['fingerprint']]) }}
                        </div>
                    </div>
                @empty
                    <div class="lqc-empty">Sin errores registrados. Cualquier error que ocurra en paneles, trabajos o consola aparecerá aquí al instante.</div>
                @endforelse
                @include('live-presence.partials.pager', ['pager' => $errorsPager, 'list' => 'errors'])
            </div>
        @else
            @if ($report === null)
                <div class="lqc-card lqc-empty">No se pudo leer el estado de las colas.</div>
            @else
                <div class="lqc-card">
                    <div class="lqc-head">
                        <span class="lqc-kicker">Colas · driver {{ $health['queue_driver'] }}</span>
                        <span style="display: inline-flex; align-items: center; gap: 8px;">
                            <span class="lqc-mono">{{ $report['worker_command'] }}</span>
                            @include('live-presence.partials.copy-button', ['text' => $report['worker_command'], 'label' => 'Copiar'])
                        </span>
                    </div>
                    <div class="lqc-scroll">
                        <table class="lqc-table">
                            <thead>
                                <tr>
                                    <th>Cola</th><th>Estado</th><th class="num">Pendientes</th><th class="num">En proceso</th><th class="num">Programados</th>
                                    <th class="num">Más viejo</th><th class="num">Workers</th><th class="num">30 min</th><th>Qué espera</th><th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($report['queues'] as $queue)
                                    <tr wire:key="queue-{{ $queue['name'] }}">
                                        <td class="lqc-mono lqc-queue-name">{{ $queue['name'] }}</td>
                                        <td style="min-width: 220px;">
                                            <span class="lqc-badge {{ $statusClasses[$queue['status']] ?? 'gray' }}">{{ $queue['status_label'] }}</span>
                                            <div class="lqc-muted" style="margin-top: 4px;">{{ $queue['advice'] }}</div>
                                        </td>
                                        <td class="num">{{ $queue['pending'] ?? '—' }}</td>
                                        <td class="num">{{ $queue['reserved'] ?? '—' }}@if (($queue['zombies'] ?? 0) > 0)<div class="lqc-muted" style="color: var(--q-amber);">{{ $queue['zombies'] }} colgados</div>@endif</td>
                                        <td class="num">{{ $queue['delayed'] ?? '—' }}</td>
                                        <td class="num">{{ \App\Support\LivePresence\QueueHealth::ageLabel($queue['oldest_seconds']) }}</td>
                                        <td class="num">{{ $workers['known'] ? $queue['listeners'] : '—' }}</td>
                                        <td class="num">{{ $queue['processed_30m'] }} ok<div class="lqc-muted" style="{{ $queue['failed_30m'] > 0 ? 'color: var(--q-red);' : '' }}">{{ $queue['failed_30m'] }} fallidos</div></td>
                                        <td>
                                            @forelse ($queue['pending_by_class'] as $job => $count)
                                                <span class="lqc-chip">{{ $count }} × {{ $job }}</span>
                                            @empty
                                                <span class="lqc-muted">—</span>
                                            @endforelse
                                        </td>
                                        <td style="text-align: right;">
                                            @if ($canReleaseQueues && ((int) $queue['pending'] + (int) $queue['reserved'] + (int) $queue['delayed']) > 0)
                                                {{ ($this->releaseQueueAction)(['queue' => $queue['name']]) }}
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="lqc-card">
                    <div class="lqc-head">
                        <span class="lqc-kicker">Workers vivos</span>
                        <span class="lqc-muted">Cada worker late cada 10 s; sin latido en {{ (int) config('live-presence.queues.worker_alive_seconds', 60) }} s se da por muerto.</span>
                    </div>
                    @if (! $workers['known'])
                        <div class="lqc-empty" style="--q-green: var(--q-amber);">Todavía ningún worker reporta su latido. Reinícielos una vez (<span class="lqc-mono">php artisan queue:restart</span>) para que empiecen a hacerlo.</div>
                    @elseif ($workers['alive'] === [])
                        <div class="lqc-empty" style="color: var(--q-red);">Ningún worker dio señal de vida en el último minuto. Nada en cola se está ejecutando.</div>
                    @else
                        <div class="lqc-scroll">
                            <table class="lqc-table">
                                <thead><tr><th>Servidor</th><th>Colas que escucha (en orden)</th><th class="num">Último latido</th><th class="num">Activo hace</th><th class="num">Procesados</th></tr></thead>
                                <tbody>
                                    @foreach ($workers['alive'] as $worker)
                                        <tr wire:key="worker-{{ $worker['id'] }}">
                                            <td><strong>{{ $worker['host'] }}</strong><div class="lqc-muted">PID {{ $worker['pid'] ?? '—' }} · {{ $worker['connection'] }}</div></td>
                                            <td>@foreach ($worker['queues'] as $queueName)<span class="lqc-chip">{{ $queueName }}</span>@endforeach</td>
                                            <td class="num">{{ $worker['last_beat_ago'] }}</td>
                                            <td class="num">{{ $worker['uptime'] }}</td>
                                            <td class="num">{{ $worker['processed'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <div class="lqc-card">
                    <div class="lqc-head">
                        <span class="lqc-kicker">Duración por tipo de trabajo · 24 h</span>
                        <span class="lqc-muted">Los más lentos primero: candidatos a dividir en partes o a subir su timeout.</span>
                    </div>
                    @if ($jobStats === [])
                        <div class="lqc-empty">Aún no hay mediciones: aparecen en cuanto los workers procesen trabajos con este código.</div>
                    @else
                        <div class="lqc-scroll">
                            <table class="lqc-table">
                                <thead><tr><th>Trabajo</th><th class="num">Procesados</th><th class="num">Fallidos</th><th class="num">Media</th><th class="num">Máxima</th><th class="num">Última</th><th class="num">Última vez</th></tr></thead>
                                <tbody>
                                    @foreach ($jobStats as $stat)
                                        <tr wire:key="stat-{{ $stat['job'] }}">
                                            <td><strong>{{ $stat['job'] }}</strong></td>
                                            <td class="num">{{ $stat['count'] }}</td>
                                            <td class="num" style="{{ $stat['failed'] > 0 ? 'color: var(--q-red);' : '' }}">{{ $stat['failed'] }}</td>
                                            <td class="num">{{ number_format($stat['avg_ms'] / 1000, 2) }} s</td>
                                            <td class="num">{{ number_format($stat['max_ms'] / 1000, 2) }} s</td>
                                            <td class="num">{{ number_format($stat['last_ms'] / 1000, 2) }} s</td>
                                            <td class="num">{{ $stat['last_ago'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif
        @endif
    </div>

    {{--
        En una página con tabla, Filament solo pinta las ventanas de las acciones
        junto a la tabla. Como la tabla vive en una sola pestaña, se pintan aquí
        para las demás; si la tabla ya lo hizo, esto no repite nada.
    --}}
    <x-filament-actions::modals />
</x-filament-panels::page>
