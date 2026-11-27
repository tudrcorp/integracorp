{{-- Archivo que envió el cliente: imagen con vista previa, nota de voz con reproductor o tarjeta de documento. --}}
<div class="crm-media">
    @if ($media['purged'])
        <span class="crm-media-file" aria-disabled="true">
            <span class="crm-media-ext">{{ mb_substr($media['type_label'], 0, 3) }}</span>
            <span class="crm-media-name"><b>{{ $media['name'] }}</b><small class="crm-hint">Ya no está disponible: pasó el tiempo de resguardo.</small></span>
        </span>
    @elseif ($media['previewable'])
        <a href="{{ $media['url'] }}" target="_blank" rel="noopener" title="Abrir la imagen en otra pestaña">
            <img class="crm-media-img" src="{{ $media['url'] }}" alt="Imagen enviada por el cliente: {{ $media['name'] }}" loading="lazy" decoding="async">
        </a>
    @elseif ($media['audio'])
        <audio controls preload="none" src="{{ $media['url'] }}" aria-label="Nota de voz del cliente"></audio>
    @else
        <a class="crm-media-file" href="{{ $media['url'] }}" target="_blank" rel="noopener" title="Abrir {{ $media['name'] }}">
            <span class="crm-media-ext">{{ pathinfo($media['name'], PATHINFO_EXTENSION) ?: 'doc' }}</span>
            <span class="crm-media-name"><b>{{ $media['name'] }}</b><small class="crm-hint">{{ $media['type_label'] }} · {{ $media['size'] }}</small></span>
        </a>
    @endif
    @unless ($media['purged'])
        <span class="crm-media-actions">
            @if ($media['previewable'] || $media['audio'])
                <a href="{{ $media['url'] }}" target="_blank" rel="noopener">Abrir</a>
            @endif
            <a href="{{ $media['download_url'] }}">Descargar · {{ $media['size'] }}</a>
            @if ($media['filed'])
                <span class="crm-media-tag">{{ $media['filed'] }}</span>
            @endif
        </span>
    @endunless
</div>
