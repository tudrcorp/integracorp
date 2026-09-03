<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Cotización</title>
    <style>
        @page {
            margin: 0;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }
    </style>
</head>
<body>
    @php
        $number = \App\Support\Storefront\StorefrontQuotePdf::controlNumber((string) ($details['code'] ?? ''));
    @endphp

    @livewire('planes-cotizacion-estructura', [
        'data' => $group_collect,
        'name' => $details['name'] ?? '',
        'name_user' => $name_user,
        'number_control' => $number,
        'planId' => $details['plan'],
        'compact' => true,
        'showConditions' => false,
        'storefrontFooter' => true,
    ])
</body>
</html>
