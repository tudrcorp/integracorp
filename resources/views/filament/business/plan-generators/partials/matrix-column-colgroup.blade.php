@php
    /** @var array<int, array<string, mixed>> $columns */
    $columns = (array) ($columns ?? []);
    $type = (string) ($type ?? 'benefits');
@endphp
<colgroup>
    @if ($type === 'benefits')
        <col class="pg-col-lead">
    @else
        <col class="pg-col-rate-age">
        <col class="pg-col-rate-pop">
    @endif
    @foreach ($columns as $column)
        <col class="pg-col-plan" wire:key="pg-plan-col-{{ $type }}-{{ $column['column_key'] ?? $loop->index }}">
    @endforeach
</colgroup>
