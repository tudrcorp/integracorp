@props([
    /** @var list<array{service: string, items: list<string>|null, provider: string|null}> $rows */
    'rows',
    /** @var string|null $teamLabel */
    'teamLabel',
])

@if ($rows === [])
    <p class="text-sm text-gray-500 dark:text-gray-400">
        Aún no hay nada seleccionado. Marque un servicio principal o agregue laboratorios, estudios, especialistas o medicamentos.
    </p>
@else
    <div style="display: flex; flex-direction: column; gap: 12px;">
        <div class="rounded-xl border border-gray-200 dark:border-white/10" style="overflow: hidden;">
            <table style="width: 100%; border-collapse: collapse; font-size: .875rem;">
                <thead>
                    <tr class="bg-gray-50 dark:bg-white/5">
                        <th class="text-gray-500 dark:text-gray-400" style="text-align: left; padding: 8px 12px; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;">Solicitud</th>
                        <th class="text-gray-500 dark:text-gray-400" style="text-align: left; padding: 8px 12px; font-size: .7rem; font-weight: 700; letter-spacing: .06em; text-transform: uppercase;">Proveedor</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-t border-gray-200 dark:border-white/10">
                            <td style="padding: 8px 12px; vertical-align: top;">
                                <div class="text-gray-900 dark:text-white" style="font-weight: 600;">{{ $row['service'] }}</div>
                                @if (filled($row['items']))
                                    <div class="text-gray-500 dark:text-gray-400" style="font-size: .78rem;">{{ implode(', ', $row['items']) }}</div>
                                @endif
                            </td>
                            <td style="padding: 8px 12px; vertical-align: top;">
                                @if (filled($row['provider']))
                                    <span class="text-gray-900 dark:text-white">{{ $row['provider'] }}</span>
                                @else
                                    <span style="color: #d97706; font-weight: 600;">Falta el proveedor</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-gray-200 dark:border-white/10" style="display: flex; align-items: center; gap: 10px; padding: 10px 14px; background: {{ $teamLabel !== null ? 'rgba(22, 163, 74, .10)' : 'rgba(100, 116, 139, .10)' }};">
            <x-filament::icon
                :icon="$teamLabel !== null ? 'heroicon-o-video-camera' : 'heroicon-o-building-office'"
                style="width: 20px; height: 20px; flex-shrink: 0; color: {{ $teamLabel !== null ? '#16a34a' : '#64748b' }};"
            />
            <span class="text-sm text-gray-700 dark:text-gray-200">
                @if ($teamLabel !== null)
                    El caso llega al <strong>{{ $teamLabel }}</strong>: lo toma el médico de guardia en el panel de Telemedicina.
                @else
                    El caso queda solo en Operaciones: ningún médico lo verá en Telemedicina.
                @endif
                Se crearán <strong>{{ count($rows) }}</strong> {{ count($rows) === 1 ? 'solicitud' : 'solicitudes' }} en Coordinación de Servicios.
            </span>
        </div>
    </div>
@endif
