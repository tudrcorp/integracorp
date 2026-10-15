<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\QrDestination;
use App\Models\QrScan;
use App\Support\QrCenter\QrDestinationManager;
use App\Support\QrCenter\QrScanRecorder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Enlace estable impreso en el QR de las tarjetas (`/q/{code}`): anota la lectura
 * y redirige al destino vigente. Cambiar el destino no exige reimprimir tarjetas.
 */
class QrRedirectController extends Controller
{
    public function __invoke(Request $request, string $code): RedirectResponse|Response
    {
        $destination = QrDestination::query()
            ->with('currentVersion.hostedPage:id,slug')
            ->where('code', strtoupper($code))
            ->first();

        $target = $destination !== null ? QrDestinationManager::targetUrl($destination) : null;

        if ($destination === null || $target === null) {
            return response()->view('qr-center.not-found', [], 404)->header('Cache-Control', 'no-store');
        }

        QrScanRecorder::record(QrScan::KIND_SCAN, $destination->id, null, $request);

        // 302 y sin caché: si el destino cambia, el próximo escaneo debe llegar al nuevo.
        return redirect()->away($target, 302)->header('Cache-Control', 'no-store, max-age=0');
    }
}
