@php
    use App\Support\AffiliationCorporates\Population\PopulationFieldComparison as Field;

    $explanation = (array) $getState();
    $dot = [
        'success' => 'bg-emerald-500',
        'warning' => 'bg-amber-500',
        'danger' => 'bg-rose-500',
        'info' => 'bg-sky-500',
        'gray' => 'bg-gray-400',
    ][$explanation['tone'] ?? 'gray'] ?? 'bg-gray-400';
    $chips = collect($explanation['fields'] ?? [])->filter(fn (array $row): bool => in_array($row['field'], [Field::FIELD_DOCUMENT, Field::FIELD_FIRST_NAME, Field::FIELD_LAST_NAME, Field::FIELD_BIRTH_DATE], true));
    $history = count($explanation['history'] ?? []);
@endphp

<div class="space-y-1.5 py-1">
    <p class="flex items-start gap-2 text-sm text-gray-950 dark:text-white">
        <span class="mt-1.5 size-2 shrink-0 rounded-full {{ $dot }}"></span>
        <span>{{ $explanation['headline'] ?? '' }}</span>
    </p>

    @if ($chips->isNotEmpty())
        <div class="flex flex-wrap gap-1 ps-4">
            @foreach ($chips as $row)
                @php
                    $ok = in_array($row['status'], [Field::SAME, Field::FORMAT], true);
                    $accepted = $row['accepted'] ?? false;
                    $class = match (true) {
                        $ok => 'bg-emerald-50 text-emerald-700 ring-emerald-600/20 dark:bg-emerald-500/10 dark:text-emerald-300 dark:ring-emerald-400/30',
                        $accepted => 'bg-gray-100 text-gray-600 ring-gray-500/20 dark:bg-white/10 dark:text-gray-300 dark:ring-white/10',
                        $row['counted'] => 'bg-rose-50 text-rose-700 ring-rose-600/20 dark:bg-rose-500/10 dark:text-rose-300 dark:ring-rose-400/30',
                        default => 'bg-gray-50 text-gray-500 ring-gray-500/20 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10',
                    };
                @endphp
                <span title="{{ $row['note'] }}" class="inline-flex items-center rounded px-1.5 py-0.5 text-[11px] font-medium ring-1 ring-inset {{ $class }}">
                    {{ $ok ? '✓' : ($accepted ? '≈' : ($row['counted'] ? '✗' : '·')) }} {{ $row['label'] }}
                </span>
            @endforeach
        </div>
    @endif

    @if (filled($explanation['next'] ?? null))
        <p class="ps-4 text-xs text-gray-500 dark:text-gray-400">{{ $explanation['next'] }}</p>
    @endif

    @if ($history > 0)
        <p class="ps-4 text-xs font-medium text-primary-600 dark:text-primary-400">{{ $history }} {{ $history === 1 ? 'acción del analista' : 'acciones del analista' }} · ver en «Explicación»</p>
    @endif
</div>
