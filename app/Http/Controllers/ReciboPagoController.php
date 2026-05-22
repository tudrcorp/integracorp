<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Http\Requests\RegenerateReciboPagoRequest;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;

class ReciboPagoController extends Controller
{
    public function regenerateAsync(RegenerateReciboPagoRequest $request, Sale $sale): JsonResponse
    {
        $validated = $request->validated();

        $ok = SalesTable::runRegenerateReciboPago($sale, $validated);

        if (! $ok) {
            return response()->json([
                'ok' => false,
                'message' => 'Error al regenerar el recibo de pago.',
            ], 422);
        }

        $filename = 'RDP-'.$sale->invoice_number.'.pdf';
        $version = (string) time();
        $directUrl = asset('storage/reciboDePago/'.$filename).'?t='.$version;

        return response()->json([
            'ok' => true,
            'preview_url' => $directUrl,
            'direct_url' => $directUrl,
        ]);
    }
}
