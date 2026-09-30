<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OperationReportFormat;
use App\Filament\Operations\Pages\GeneradorDeReportes;
use App\Support\Operations\Reports\OperationReportGenerator;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga un reporte de Operaciones de la carpeta privada del usuario.
 *
 * Cada usuario sólo puede bajar sus propios archivos: la ruta se arma con su
 * id y el nombre se valida contra un patrón fijo.
 */
class OperationReportDownloadController extends Controller
{
    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless(
            $user->canAccessPanel(Filament::getPanel('operations')) && GeneradorDeReportes::canAccess(),
            403,
        );

        $path = OperationReportGenerator::absolutePathFor((int) $user->getAuthIdentifier(), $file);

        abort_if($path === null, 404, 'El reporte ya no está disponible. Los reportes se conservan 7 días.');

        $format = OperationReportFormat::tryFrom((string) pathinfo($file, PATHINFO_EXTENSION));

        return response()->download($path, $file, [
            'Content-Type' => $format?->mimeType() ?? 'application/octet-stream',
        ]);
    }
}
