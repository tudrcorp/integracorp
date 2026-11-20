<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Propuesta económica</title>
    <style>
        @page { margin: 0px; }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            margin: 0;
            padding: 0;
        }

        .cover {
            position: relative;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }
    </style>
</head>
<body>
    {{-- Páginas fijas de siempre para la propuesta con diseño de cotización
         (App\Support\Quotes\Design\QuoteDesignedProposal): mismas vistas que la propuesta local. --}}
    @if ($part === 'before')
        @livewire('portada-cotizacion-individual', ['name' => ''])
        @livewire('propuesta-economica.propuesta-economica-page-2')
    @elseif ($part === 'pathologies')
        @livewire('propuesta-economica.propuesta-economica-plan-especial')
    @else
        @livewire('propuesta-economica.propuesta-economica-page-4')
    @endif
</body>
</html>
