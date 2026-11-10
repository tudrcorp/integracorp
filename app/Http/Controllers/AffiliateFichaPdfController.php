<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AffiliateFichaScope;
use App\Models\User;
use App\Services\AffiliateFichaPdfService;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaAccess;
use App\Support\SecurityAudit;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Vista previa y descarga de la ficha del afiliado. La URL es firmada y
 * temporal (la arma {@see self::url()} desde la acción de Filament), y aun así
 * la autorización se vuelve a evaluar aquí con el panel de origen.
 */
class AffiliateFichaPdfController extends Controller
{
    public const MODE_PREVIEW = 'vista';

    public const MODE_DOWNLOAD = 'descarga';

    /** Ventana de vigencia: la URL vale entre una y dos ventanas. */
    private const URL_WINDOW_SECONDS = 1800;

    /**
     * La expiración se redondea a la ventana para que la URL sea idéntica en
     * cada re-render de la modal (el panel refresca solo). Si cambiara con cada
     * segundo, el iframe recargaría el PDF en cada refresco.
     */
    public static function url(string $kind, int $affiliateId, AffiliateFichaScope $scope, string $panelId, string $mode): string
    {
        $expiresAt = Carbon::createFromTimestamp((intdiv(now()->getTimestamp(), self::URL_WINDOW_SECONDS) + 2) * self::URL_WINDOW_SECONDS);

        return URL::temporarySignedRoute('affiliate-ficha.pdf', $expiresAt, [
            'kind' => $kind,
            'affiliate' => $affiliateId,
            'scope' => $scope->value,
            'panel' => $panelId,
            'mode' => $mode,
        ]);
    }

    public function __invoke(Request $request, string $kind, int $affiliate, string $scope, string $panel, string $mode): Response
    {
        @set_time_limit(120);

        $user = $request->user();
        $fichaScope = AffiliateFichaScope::tryFrom($scope);

        if (! $user instanceof User || $fichaScope === null || ! in_array($mode, [self::MODE_PREVIEW, self::MODE_DOWNLOAD], true)) {
            abort(404);
        }

        if (! AffiliateFichaAccess::canUse($user, $panel, $fichaScope)) {
            SecurityAudit::log('AUDIT_AFFILIATE_FICHA_ACCESS_DENIED', 'affiliate-ficha.pdf', [
                'kind' => $kind,
                'affiliate_id' => $affiliate,
                'scope' => $fichaScope->value,
                'panel' => $panel,
            ]);

            abort(403, 'No tiene permiso para generar esta ficha.');
        }

        $record = AffiliateFichaPdfService::find($kind, $affiliate);

        if ($record === null) {
            abort(404, 'El afiliado no existe.');
        }

        try {
            $pdf = AffiliateFichaPdfService::render($kind, $record, $fichaScope);
        } catch (Throwable $exception) {
            Log::error('Ficha del afiliado: no se pudo generar el PDF.', [
                'kind' => $kind,
                'affiliate_id' => $affiliate,
                'scope' => $fichaScope->value,
                'error' => $exception->getMessage(),
            ]);

            return response()->view('documents.affiliate-ficha-error', [], 500);
        }

        SecurityAudit::log(
            $mode === self::MODE_DOWNLOAD ? 'AUDIT_AFFILIATE_FICHA_DOWNLOADED' : 'AUDIT_AFFILIATE_FICHA_VIEWED',
            'affiliate-ficha.pdf',
            [
                'kind' => $kind,
                'affiliate_id' => $affiliate,
                'affiliation_code' => $pdf['data']['affiliationCode'] ?? null,
                'scope' => $fichaScope->value,
                'panel' => $panel,
                'filename' => $pdf['filename'],
            ],
        );

        $disposition = $mode === self::MODE_DOWNLOAD ? 'attachment' : 'inline';

        return response($pdf['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$pdf['filename'].'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
