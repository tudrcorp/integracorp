<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Affiliation;
use App\Services\AffiliationFichaPdfService;
use App\Support\Affiliations\AffiliationFichaPdfAccess;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AffiliationFichaPdfController extends Controller
{
    public function download(Affiliation $affiliation): Response
    {
        self::authorizeFicha();

        self::prepareLongRunningPdfResponse();

        $binary = AffiliationFichaPdfService::outputBinaryCached($affiliation);
        $filename = AffiliationFichaPdfService::downloadFilename($affiliation);
        $routeName = (string) (request()->route()?->getName() ?: 'administration.affiliations.ficha.download');

        SecurityAudit::log(self::auditEvent($routeName, 'DOWNLOADED'), $routeName, [
            'affiliation_id' => $affiliation->id,
            'affiliation_code' => $affiliation->code,
            'titular_name' => $affiliation->full_name_ti,
            'filename' => $filename,
        ]);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'attachment; filename="'.$filename.'"');
    }

    public function preview(Affiliation $affiliation): Response
    {
        self::authorizeFicha();

        self::prepareLongRunningPdfResponse();

        $binary = AffiliationFichaPdfService::outputBinaryCached($affiliation);
        $filename = AffiliationFichaPdfService::downloadFilename($affiliation);
        $routeName = (string) (request()->route()?->getName() ?: 'administration.affiliations.ficha.preview');

        SecurityAudit::log(self::auditEvent($routeName, 'VIEWED'), $routeName, [
            'affiliation_id' => $affiliation->id,
            'affiliation_code' => $affiliation->code,
            'titular_name' => $affiliation->full_name_ti,
            'filename' => $filename,
        ]);

        return response($binary, 200)
            ->header('Content-Type', 'application/pdf')
            ->header('Content-Disposition', 'inline; filename="'.$filename.'"');
    }

    /**
     * 403 si quien pide la ficha no puede abrir la afiliación en el panel de la ruta.
     */
    private static function authorizeFicha(): void
    {
        abort_unless(
            AffiliationFichaPdfAccess::userCanAccess(Auth::user(), AffiliationFichaPdfAccess::KIND_INDIVIDUAL, request()->route()?->getName()),
            403,
            'No tiene permiso para ver la ficha técnica de esta afiliación.',
        );
    }

    private static function auditEvent(string $routeName, string $action): string
    {
        $isBusiness = str_starts_with($routeName, 'business.');

        return match ($action) {
            'DOWNLOADED' => $isBusiness
                ? 'AUDIT_BUSINESS_AFFILIATION_INDIVIDUAL_FICHA_DOWNLOADED'
                : 'AUDIT_ADMINISTRATION_AFFILIATION_INDIVIDUAL_FICHA_DOWNLOADED',
            default => $isBusiness
                ? 'AUDIT_BUSINESS_AFFILIATION_INDIVIDUAL_FICHA_VIEWED'
                : 'AUDIT_ADMINISTRATION_AFFILIATION_INDIVIDUAL_FICHA_VIEWED',
        };
    }

    private static function prepareLongRunningPdfResponse(): void
    {
        @set_time_limit(300);
        @ini_set('max_execution_time', '300');

        $limit = config('supplier-report.pdf_memory_limit');
        if (is_string($limit) && $limit !== '' && $limit !== '0') {
            @ini_set('memory_limit', $limit);
        }
    }
}
