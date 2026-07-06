<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ObservationCommercialStructure;
use App\Models\TravelAgency;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

class TravelAgencyFichaPdfService
{
    /**
     * @return list<string>
     */
    private static function logoCandidatePaths(): array
    {
        return [
            public_path('storage/administracion/logoNewPdfTDEC.png'),
            public_path('storage/logo1-pdf.png'),
            public_path('image/logoNewPdf.png'),
        ];
    }

    public static function logoDataUri(): string
    {
        foreach (self::logoCandidatePaths() as $path) {
            if (is_file($path)) {
                return 'data:image/png;base64,'.base64_encode((string) file_get_contents($path));
            }
        }

        return '';
    }

    public static function observationsForTravelAgency(int $travelAgencyId): Collection
    {
        return ObservationCommercialStructure::query()
            ->where('travel_agency_id', $travelAgencyId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public static function make(TravelAgency $travelAgency): PdfDocument
    {
        $travelAgency->loadMissing(['country', 'state', 'city', 'travelAgents']);
        $observations = self::observationsForTravelAgency((int) $travelAgency->getKey());

        return Pdf::loadView('documents.travel-agency-ficha-detalle', [
            'travelAgency' => $travelAgency,
            'observations' => $observations,
            'logoDataUri' => self::logoDataUri(),
            'generatedAt' => now()->timezone(config('app.timezone')),
        ])->setPaper('a4', 'portrait');
    }

    public static function outputBinary(TravelAgency $travelAgency): string
    {
        return (string) self::make($travelAgency)->output();
    }

    public static function filename(TravelAgency $travelAgency): string
    {
        $slug = filled($travelAgency->numberIdentification)
            ? (string) $travelAgency->numberIdentification
            : 'id-'.$travelAgency->getKey();
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug) ?: 'agencia-viajes';

        return 'ficha-agencia-viajes-'.$safe.'.pdf';
    }

    public static function codeLabel(TravelAgency $travelAgency): string
    {
        if (filled($travelAgency->numberIdentification)) {
            return 'J/V/E-'.$travelAgency->numberIdentification;
        }

        return 'AVJ-000'.$travelAgency->getKey();
    }

    public static function whatsappStorageRelativePath(TravelAgency $travelAgency): string
    {
        return 'business-fichas/travel-agencies/'.self::filename($travelAgency);
    }

    public static function persistForWhatsApp(TravelAgency $travelAgency): string
    {
        $relativePath = self::whatsappStorageRelativePath($travelAgency);
        $absolutePath = public_path('storage/'.$relativePath);
        $directory = dirname($absolutePath);

        if (! is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        file_put_contents($absolutePath, self::outputBinary($travelAgency));

        return $relativePath;
    }

    public static function whatsappCaption(TravelAgency $travelAgency): string
    {
        $codeLabel = self::codeLabel($travelAgency);
        $displayName = (string) ($travelAgency->name ?? 'Agencia de viajes');

        return <<<TEXT
        📎 *Ficha de agencia de viajes*

        Agencia: *{$displayName}*
        Identificación: *{$codeLabel}*

        Documento generado por Integracorp · Tu Dr en Casa.
        TEXT;
    }
}
