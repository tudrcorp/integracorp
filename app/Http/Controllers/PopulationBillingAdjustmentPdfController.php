<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\GeneratePopulationBillingAdjustmentPdf;
use App\Models\CorporateBillingAdjustment;
use App\Support\Filament\UserNavigationAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * PDF del aviso de cobro por ajuste de población: vista previa (inline) y descarga (?download=1).
 *
 * Normalmente el job ya lo dejó guardado al aplicar el ajuste y se sirve al instante desde el
 * disco. Si todavía no existe (cola atrasada, reintento), se genera aquí mismo una sola vez, con
 * candado, y queda guardado para la próxima.
 */
class PopulationBillingAdjustmentPdfController extends Controller
{
    public const MENU_SLUG = 'ajustes-por-poblacion';

    public function __invoke(Request $request, CorporateBillingAdjustment $adjustment): BinaryFileResponse
    {
        $user = Auth::user();

        abort_unless($user !== null && UserNavigationAccess::canPerformModuleAction($user, 'ADMINISTRACION', self::MENU_SLUG), 403, 'No tiene permiso para ver los ajustes por población.');

        $disk = Storage::disk(GeneratePopulationBillingAdjustmentPdf::DISK);
        $path = GeneratePopulationBillingAdjustmentPdf::pathFor($adjustment);

        if (! $disk->exists($path)) {
            Cache::lock('population-billing-pdf:'.$adjustment->getKey(), 120)->block(60, function () use ($adjustment, $disk, $path): void {
                if ($disk->exists($path)) {
                    return;
                }

                abort_unless($disk->put($path, GeneratePopulationBillingAdjustmentPdf::render($adjustment)), 500, 'No se pudo generar el PDF del ajuste.');
                CorporateBillingAdjustment::query()->whereKey($adjustment->getKey())->update(['pdf_path' => $path]);
            });
        }

        $invoice = $adjustment->chargeCollection?->collection_invoice_number ?? $adjustment->public_id;
        $name = 'Aviso-ajuste-poblacion-'.Str::slug((string) $adjustment->affiliation_code).'-'.Str::slug((string) $invoice).'.pdf';
        $headers = ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff'];

        return $request->boolean('download')
            ? response()->download($disk->path($path), $name, $headers)
            : response()->file($disk->path($path), $headers + ['Content-Disposition' => 'inline; filename="'.$name.'"']);
    }
}
