@php
    /**
     * Encabezado de las fichas de afiliado de Operaciones (individual, corporativo y nuevos negocios).
     * Los datos los arma App\Support\Operations\OperationsAffiliateHeader.
     *
     * Estilos en línea a propósito: el tema del panel se compila con Vite y una clase
     * Tailwind nueva no existe hasta el próximo build.
     *
     * @var array<string, mixed> $header
     */
    $tones = [
        'success' => ['solid' => '#16a34a', 'soft' => 'rgba(22, 163, 74, 0.12)', 'line' => 'rgba(22, 163, 74, 0.32)'],
        'warning' => ['solid' => '#d97706', 'soft' => 'rgba(217, 119, 6, 0.12)', 'line' => 'rgba(217, 119, 6, 0.34)'],
        'danger' => ['solid' => '#dc2626', 'soft' => 'rgba(220, 38, 38, 0.11)', 'line' => 'rgba(220, 38, 38, 0.32)'],
        'gray' => ['solid' => '#6b7280', 'soft' => 'rgba(142, 142, 147, 0.14)', 'line' => 'rgba(142, 142, 147, 0.3)'],
        'info' => ['solid' => '#2d89ca', 'soft' => 'rgba(45, 137, 202, 0.12)', 'line' => 'rgba(45, 137, 202, 0.32)'],
    ];
    $statusTone = $tones[$header['status']['tone']] ?? $tones['gray'];
    $coverage = $header['coverage'];
    $coverageTone = $coverage ? ($tones[$coverage['tone']] ?? $tones['gray']) : null;
    $patient = $header['patient'];
    $alert = $header['alert'];
    $pill = 'display: inline-flex; align-items: center; gap: 6px; padding: 5px 12px; border-radius: 999px; font-size: 0.8rem; font-weight: 600; line-height: 1.2;';
@endphp

<div style="width: 100%; display: flex; flex-direction: column; gap: 10px; padding: 10px 0 4px; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; letter-spacing: normal; font-size: 1rem; font-weight: 400;">
    <div style="display: flex; align-items: center; gap: 16px; flex-wrap: wrap;">
        <span aria-hidden="true" style="flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center; width: 64px; height: 64px; border-radius: 22px; background: linear-gradient(145deg, {{ $statusTone['solid'] }}, #052F60); color: #fff; font-size: 1.35rem; font-weight: 700; letter-spacing: 0.02em; box-shadow: 0 12px 26px -12px {{ $statusTone['solid'] }}, inset 0 1px 0 rgba(255, 255, 255, 0.25);">
            {{ $header['initials'] }}
        </span>

        <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0; flex: 1 1 260px;">
            <span class="text-gray-500 dark:text-gray-400" style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em;">
                {{ $header['eyebrow'] }}
            </span>

            <span class="text-gray-900 dark:text-white" style="font-size: 1.75rem; line-height: 1.15; font-weight: 700; letter-spacing: -0.01em; overflow-wrap: anywhere;">
                {{ $header['name'] }}
            </span>

            @if ($header['facts'] !== [])
                <div class="text-gray-600 dark:text-gray-300" style="display: flex; flex-wrap: wrap; align-items: center; column-gap: 14px; row-gap: 4px; font-size: 0.85rem; font-weight: 500; line-height: 1.4;">
                    @foreach ($header['facts'] as $fact)
                        <span title="{{ $fact['label'] }}: {{ $fact['value'] }}" style="display: inline-flex; align-items: center; gap: 4px; min-width: 0; max-width: 100%;">
                            @if (! $loop->first)
                                <span aria-hidden="true" style="opacity: 0.35; margin-right: 10px;">·</span>
                            @endif
                            <span class="text-gray-900 dark:text-white" style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 220px;">{{ $fact['value'] }}</span>
                            @if (filled($fact['copy'] ?? null))
                                <button
                                    type="button"
                                    x-data="{ copied: false }"
                                    x-on:click="navigator.clipboard?.writeText(@js($fact['copy'])).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                                    x-bind:title="copied ? 'Copiado' : 'Copiar {{ mb_strtolower($fact['label']) }}'"
                                    x-bind:aria-label="copied ? 'Copiado' : 'Copiar {{ mb_strtolower($fact['label']) }}'"
                                    class="text-gray-400 hover:text-gray-700 dark:text-gray-500 dark:hover:text-gray-200"
                                    style="flex-shrink: 0; display: inline-flex; padding: 1px; border-radius: 6px; background: none; border: 0; cursor: pointer;"
                                >
                                    <svg x-show="! copied" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 13px; height: 13px;">
                                        <path d="M7 3.5A1.5 1.5 0 0 1 8.5 2h3.879a1.5 1.5 0 0 1 1.06.44l3.122 3.12A1.5 1.5 0 0 1 17 6.622V12.5a1.5 1.5 0 0 1-1.5 1.5h-1v-3.379a3 3 0 0 0-.879-2.121L10.5 5.379A3 3 0 0 0 8.379 4.5H7v-1Z" />
                                        <path d="M4.5 6A1.5 1.5 0 0 0 3 7.5v9A1.5 1.5 0 0 0 4.5 18h7a1.5 1.5 0 0 0 1.5-1.5v-5.879a1.5 1.5 0 0 0-.44-1.06L9.44 6.439A1.5 1.5 0 0 0 8.378 6H4.5Z" />
                                    </svg>
                                    <svg x-show="copied" x-cloak xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="display: none; width: 13px; height: 13px; color: #16a34a;">
                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 0 1 .143 1.052l-8 10.5a.75.75 0 0 1-1.127.075l-4.5-4.5a.75.75 0 0 1 1.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 0 1 1.05-.143Z" clip-rule="evenodd" />
                                    </svg>
                                </button>
                            @endif
                        </span>
                    @endforeach
                </div>
            @endif

            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-top: 4px;">
                <span style="{{ $pill }} background-color: {{ $statusTone['solid'] }}; color: #fff; font-weight: 700; box-shadow: 0 6px 16px -8px {{ $statusTone['solid'] }};">
                    <span style="display: inline-block; width: 7px; height: 7px; border-radius: 999px; background-color: #fff;"></span>
                    {{ $header['status']['label'] }}
                </span>

                @foreach ($header['tags'] as $tag)
                    <span class="text-gray-700 dark:text-gray-200" style="{{ $pill }} background-color: {{ $tones['info']['soft'] }}; border: 1px solid {{ $tones['info']['line'] }};">
                        {{ $tag }}
                    </span>
                @endforeach

                @if ($coverage)
                    <span class="text-gray-700 dark:text-gray-200" title="Vigencia de la afiliación" style="{{ $pill }} background-color: {{ $coverageTone['soft'] }}; border: 1px solid {{ $coverageTone['line'] }};">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 14px; height: 14px; color: {{ $coverageTone['solid'] }};">
                            <path fill-rule="evenodd" d="M5.75 2a.75.75 0 0 1 .75.75V4h7V2.75a.75.75 0 0 1 1.5 0V4h.25A2.75 2.75 0 0 1 18 6.75v8.5A2.75 2.75 0 0 1 15.25 18H4.75A2.75 2.75 0 0 1 2 15.25v-8.5A2.75 2.75 0 0 1 4.75 4H5V2.75A.75.75 0 0 1 5.75 2Zm-1 5.5c-.69 0-1.25.56-1.25 1.25v6.5c0 .69.56 1.25 1.25 1.25h10.5c.69 0 1.25-.56 1.25-1.25v-6.5c0-.69-.56-1.25-1.25-1.25H4.75Z" clip-rule="evenodd" />
                        </svg>
                        {{ $coverage['label'] }}
                        @if ($coverage['hint'])
                            <span style="color: {{ $coverageTone['solid'] }}; font-weight: 700;">· {{ $coverage['hint'] }}</span>
                        @endif
                    </span>
                @endif

                @if ($patient['linked'])
                    @if ($patient['url'])
                        <a href="{{ $patient['url'] }}" wire:navigate class="text-gray-700 dark:text-gray-200" style="{{ $pill }} text-decoration: none; background-color: {{ $tones['success']['soft'] }}; border: 1px solid {{ $tones['success']['line'] }};">
                    @else
                        <span class="text-gray-700 dark:text-gray-200" style="{{ $pill }} background-color: {{ $tones['success']['soft'] }}; border: 1px solid {{ $tones['success']['line'] }};">
                    @endif
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" style="width: 14px; height: 14px; color: {{ $tones['success']['solid'] }};">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm3.857-9.809a.75.75 0 0 0-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 1 0-1.06 1.061l2.5 2.5a.75.75 0 0 0 1.137-.089l4-5.5Z" clip-rule="evenodd" />
                        </svg>
                        Paciente de telemedicina
                        @if ($patient['url'])
                            <span style="color: {{ $tones['success']['solid'] }}; font-weight: 700;">· Ver ficha →</span>
                        @endif
                    @if ($patient['url'])
                        </a>
                    @else
                        </span>
                    @endif
                @endif
            </div>
        </div>
    </div>

    @if ($alert)
        <div role="alert" style="padding: 12px 14px; border-radius: 14px; border: 1px solid {{ $tones['danger']['line'] }}; background-color: {{ $tones['danger']['soft'] }};">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 4px;">
                <span style="font-size: 0.72rem; font-weight: 800; letter-spacing: 0.04em; text-transform: uppercase; color: {{ $tones['danger']['solid'] }};">{{ $alert['title'] }}</span>
                @if ($alert['meta'])
                    <span class="text-gray-600 dark:text-gray-300" style="font-size: 0.75rem; font-weight: 600;">{{ $alert['meta'] }}</span>
                @endif
            </div>
            <p class="text-gray-900 dark:text-white" style="margin: 0; font-size: 0.9rem; line-height: 1.45; font-weight: 600;">{{ $alert['body'] }}</p>
        </div>
    @endif
</div>
