@php
    $avatars = $avatars ?? [];
    $overflowCount = (int) ($overflowCount ?? 0);
    $tooltipLines = $tooltipLines ?? [];
    $tooltipTitle = $tooltipTitle ?? 'Colaboradores del día';
@endphp

<x-collaborator-avatar-stack
    :avatars="$avatars"
    :overflow-count="$overflowCount"
    :tooltip-title="$tooltipTitle"
    :tooltip-items="collect($tooltipLines)
        ->map(fn (array $line): array => [
            'name' => $line['name'] ?? '',
            'detail' => $line['offices'] ?? null,
        ])
        ->all()"
/>
