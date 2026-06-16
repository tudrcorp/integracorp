@php
    /** @var array<int, array<string, mixed>> $columns */
    $type = (string) ($type ?? 'benefits');
@endphp
@foreach ($columns as $column)
    <th
        wire:key="pg-plan-th-{{ $type }}-{{ $column['column_key'] ?? $loop->index }}"
        class="border border-[#1e40af] px-2 py-2.5 text-center font-bold uppercase min-w-0"
    >
        {{ $column['header_label'] ?? '—' }}
    </th>
@endforeach
