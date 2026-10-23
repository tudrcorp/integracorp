<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\OperationReportFormat;
use App\Filament\Administration\Pages\CentroDeReportes;
use App\Filament\Administration\Pages\InformeAdministrativo;
use App\Support\Administration\Insights\AdministrativeReportPdf;
use App\Support\Administration\Reports\AdministrationReportGenerator;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga un reporte del Centro de Reportes de Administración de la carpeta
 * privada del usuario.
 *
 * Cada usuario solo puede bajar sus propios archivos: la ruta se arma con su id
 * y el nombre se valida contra un patrón fijo.
 */
class AdministrationReportDownloadController extends Controller
{
    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless(
            $user->canAccessPanel(Filament::getPanel('administration')) && CentroDeReportes::canAccess(),
            403,
        );

        // El informe administrativo tiene permiso propio: quien lo pierde ya no lo descarga.
        abort_if(
            str_starts_with($file, 'reporte-administracion-'.AdministrativeReportPdf::FILE_SLUG.'-') && ! InformeAdministrativo::canAccess(),
            403,
        );

        $path = AdministrationReportGenerator::absolutePathFor((int) $user->getAuthIdentifier(), $file);

        abort_if($path === null, 404, 'El reporte ya no está disponible. Los reportes se conservan 7 días.');

        $format = OperationReportFormat::tryFrom((string) pathinfo($file, PATHINFO_EXTENSION));

        return response()->download($path, $file, [
            'Content-Type' => $format?->mimeType() ?? 'application/octet-stream',
        ]);
    }
}
