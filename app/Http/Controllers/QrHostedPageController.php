<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QrHostedPage;
use App\Models\QrScan;
use App\Support\QrCenter\QrCenterStorage;
use App\Support\QrCenter\QrScanRecorder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Sirve una página alojada (`/p/{slug}`) AISLADA del sistema.
 *
 * El HTML lo sube un analista y trae su propio JavaScript. La cabecera CSP
 * `sandbox` sin `allow-same-origin` lo ejecuta en un origen opaco: no puede leer
 * cookies, sesión ni almacenamiento de integracorp.tudrgroup.com, ni llamar a sus
 * rutas con la sesión de quien la abre. No quitar esa cabecera ni añadirle
 * `allow-same-origin`: sería ejecutar código subido con la sesión de un analista.
 */
class QrHostedPageController extends Controller
{
    public const SANDBOX_POLICY = 'sandbox allow-scripts allow-forms allow-popups allow-popups-to-escape-sandbox allow-downloads allow-modals';

    public function __invoke(Request $request, string $slug): Response
    {
        $page = QrHostedPage::query()
            ->with('currentVersion:id,file_path,sha256')
            ->where('slug', $slug)
            ->first();

        $html = $page?->currentVersion !== null ? QrCenterStorage::get($page->currentVersion->file_path) : null;

        if ($page === null || $html === null) {
            return response()->view('qr-center.not-found', [], 404)->header('Cache-Control', 'no-store');
        }

        QrScanRecorder::record(QrScan::KIND_VIEW, null, $page->id, $request);

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => self::SANDBOX_POLICY,
            'X-Content-Type-Options' => 'nosniff',
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Corto: una versión nueva debe verse en minutos, no en horas.
            'Cache-Control' => 'public, max-age=60',
            'ETag' => '"'.$page->currentVersion->sha256.'"',
        ]);
    }
}
