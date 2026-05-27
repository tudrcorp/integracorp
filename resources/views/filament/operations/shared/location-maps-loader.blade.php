@php
    $mapsKey = (string) config('services.google_maps.api_key', '');
@endphp

@if ($mapsKey !== '')
    <script>
        window.__supplierMapsApiKey = @json($mapsKey);
        window.__operationsMapsApiKey = @json($mapsKey);
    </script>
    <script src="{{ asset('js/supplier-location-maps.js') }}?v={{ filemtime(public_path('js/supplier-location-maps.js')) }}" defer></script>
@endif
