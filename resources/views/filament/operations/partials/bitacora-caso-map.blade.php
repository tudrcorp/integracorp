@php
    $open = (bool) ($open ?? true);
    $filledMap = collect($map ?? [])
        ->filter(function (mixed $value): bool {
            if (is_bool($value) || is_array($value)) {
                return false;
            }

            $text = trim((string) $value);

            return $text !== '' && $text !== '—';
        })
        ->all();
@endphp

<x-operations.bitacora-accordion :title="$title" :open="$open">
    @if ($filledMap === [])
        <p class="px-4 py-3 text-sm text-slate-500">Sin información en esta sección.</p>
    @else
        <dl class="grid gap-x-4 gap-y-2 border-t border-slate-100 px-4 py-3 sm:grid-cols-2 dark:border-white/10">
            @foreach ($filledMap as $label => $value)
                <div>
                    <dt class="text-[11px] font-semibold uppercase tracking-wide text-slate-400">{{ $label }}</dt>
                    <dd class="mt-0.5 text-sm text-slate-900 dark:text-slate-100">{{ $value }}</dd>
                </div>
            @endforeach
        </dl>
    @endif
</x-operations.bitacora-accordion>
