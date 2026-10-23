<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ProspectAgent;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;

class ProspectAgentFichaPdfService
{
    /**
     * v1: base64 en caché (driver database / UTF-8).
     */
    private const PDF_CACHE_KEY_PREFIX = 'prospect_agent_ficha_pdf:v1:';

    private const PDF_CACHE_TTL_FALLBACK_SECONDS = 900;

    /**
     * @return list<string>
     */
    private const FICHA_RELATIONS = [
        'state',
        'city',
        'country',
        'prospectAgentContacts',
        'prospect_agent_observations',
        'prospect_agent_tasks.rrhh_colaborador',
    ];

    /**
     * @return list<string>
     */
    private static function logoCandidatePaths(): array
    {
        return [
            public_path('storage/administracion/logoNewPdfTDEC.png'),
            public_path('storage/logo1-pdf.png'),
            public_path('image/logoNewPdf.png'),
            public_path('image/logoNewTDG.png'),
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

    public static function downloadFilename(ProspectAgent $prospect): string
    {
        return 'Ficha-Prospecto-'.$prospect->id.'.pdf';
    }

    /**
     * Invalida caché al cambiar el prospecto o tablas relacionadas que alimentan la ficha.
     */
    public static function pdfCacheVersion(ProspectAgent $prospect): string
    {
        $id = $prospect->id;

        $parts = [
            (string) $id,
            (string) ($prospect->updated_at ?? ''),
            (string) DB::table('prospect_agent_contacts')->where('prospect_agent_id', $id)->max('updated_at'),
            (string) DB::table('prospect_agent_observations')->where('prospect_agent_id', $id)->max('updated_at'),
            (string) DB::table('prospect_agent_tasks')->where('prospect_agent_id', $id)->max('updated_at'),
        ];

        return hash('sha256', implode('|', $parts));
    }

    public static function prospectWithFichaRelations(ProspectAgent $prospect): ProspectAgent
    {
        return $prospect->load(self::FICHA_RELATIONS);
    }

    public static function outputBinary(ProspectAgent $prospect): string
    {
        $html = View::make('documents.prospect-agent-ficha', [
            'prospect' => self::prospectWithFichaRelations($prospect),
            'logoDataUri' => self::logoDataUri(),
            'generatedAt' => now()->timezone(config('app.timezone')),
        ])->render();

        return Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => true,
                'defaultFont' => 'sans-serif',
            ])
            ->output();
    }

    public static function outputBinaryCached(ProspectAgent $prospect): string
    {
        $ttl = (int) config('supplier-report.pdf_cache_ttl_seconds', self::PDF_CACHE_TTL_FALLBACK_SECONDS);

        $encoded = Cache::remember(
            self::PDF_CACHE_KEY_PREFIX.self::pdfCacheVersion($prospect),
            max(60, $ttl),
            fn (): string => base64_encode(self::outputBinary($prospect)),
        );

        $binary = base64_decode($encoded, true);

        return is_string($binary) ? $binary : self::outputBinary($prospect);
    }
}
