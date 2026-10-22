<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\OrgStructure\OrgChartPdfExporter;
use App\Support\OrgStructure\OrgStructureAccess;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Descarga un organigrama exportado de la carpeta privada del usuario.
 *
 * Cada usuario solo baja sus propios archivos: la ruta se arma con su id y el
 * nombre se valida contra un patrón fijo.
 */
class OrgChartExportDownloadController extends Controller
{
    public function __invoke(Request $request, string $file): BinaryFileResponse
    {
        $user = $request->user();

        abort_unless(OrgStructureAccess::canView($user), 403);

        $path = OrgChartPdfExporter::absolutePathFor((int) $user->getAuthIdentifier(), $file);

        abort_if($path === null, 404, 'El organigrama ya no está disponible. Los archivos se conservan '.OrgChartPdfExporter::RETENTION_DAYS.' días.');

        return response()->download($path, $file, ['Content-Type' => 'application/pdf']);
    }
}
