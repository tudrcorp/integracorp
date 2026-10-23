@php
    /** @var array<string, mixed> $report */
    $logoSrc = \App\Support\PdfOpaqueImage::fromPath(public_path('image/logoNewPdf.png'));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $report['title'] }}</title>
    <style>
        @page { margin: 10mm 8mm 14mm; size: A4 portrait; }
        body { margin: 0; background: #FFFFFF; }
    </style>
    @include('documents.partials.administrative-report-styles')
</head>
<body>
    @include('documents.partials.administrative-report-body', ['report' => $report, 'logoSrc' => $logoSrc, 'chartScales' => $chartScales ?? [], 'blockSpacing' => $blockSpacing ?? []])
</body>
</html>
