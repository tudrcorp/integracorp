<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Agency;
use App\Models\AgencyNoteBlog;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Illuminate\Support\Collection;

class AgencyFichaPdfService
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

    public static function notesForAgency(int $agencyId): Collection
    {
        return AgencyNoteBlog::query()
            ->where('agency_id', $agencyId)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();
    }

    public static function make(Agency $agency): PdfDocument
    {
        $agency->loadMissing(['country', 'state', 'city', 'typeAgency', 'accountManager']);
        $agency->loadCount('agents');

        $notes = self::notesForAgency((int) $agency->getKey());

        return Pdf::loadView('documents.agency-ficha-detalle', [
            'agency' => $agency,
            'notes' => $notes,
            'logoDataUri' => self::logoDataUri(),
            'generatedAt' => now()->timezone(config('app.timezone')),
        ])->setPaper('a4', 'portrait');
    }

    public static function outputBinary(Agency $agency): string
    {
        return (string) self::make($agency)->output();
    }

    public static function filename(Agency $agency): string
    {
        $slug = (string) ($agency->code ?: 'id-'.$agency->getKey());
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $slug) ?: 'agencia';

        return 'ficha-agencia-'.$safe.'.pdf';
    }

    public static function codeLabel(Agency $agency): string
    {
        $def = $agency->typeAgency?->definition;

        return filled($def) ? $def.' — '.$agency->code : (string) $agency->code;
    }
}
