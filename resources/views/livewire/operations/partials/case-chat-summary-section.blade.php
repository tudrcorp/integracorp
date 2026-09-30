@php
    $sectionValue = $section['value'] ?? '';
@endphp
<div class="fi-operations-case-chat-summary-section">
    <dt>{{ $section['label'] }}</dt>
    <dd>
        @if (is_array($sectionValue))
            <ul>
                @foreach ($sectionValue as $item)
                    <li>{{ $item }}</li>
                @endforeach
            </ul>
        @else
            {{ $sectionValue }}
        @endif
    </dd>
</div>
