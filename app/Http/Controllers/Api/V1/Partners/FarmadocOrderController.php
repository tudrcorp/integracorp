<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Partners;

use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateApiPartner;
use App\Http\Requests\ApiPartners\FarmadocOrderIndexRequest;
use App\Models\FarmadocOrder;
use App\Support\ApiPartners\FarmadocOrderFeed;
use App\Support\Farmadoc\FarmadocReceiptNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * API de aliados — pedidos a Farmadoc: estatus en IntegraCorp y nota de recibido. Solo lectura.
 * La autenticación, permisos, límite y bitácora los aplica {@see AuthenticateApiPartner}.
 */
class FarmadocOrderController extends Controller
{
    public function index(FarmadocOrderIndexRequest $request): JsonResponse
    {
        $page = FarmadocOrderFeed::paginate($request->filters());
        $data = array_map(fn (FarmadocOrder $order): array => FarmadocOrderFeed::payload($order, $this->receiptNoteUrl($order)), $page->items());

        AuthenticateApiPartner::recordCount($request, count($data));

        return response()->json([
            'data' => $data,
            'meta' => [
                'pagina_actual' => $page->currentPage(),
                'por_pagina' => $page->perPage(),
                'total' => $page->total(),
                'ultima_pagina' => $page->lastPage(),
                'estatus' => FarmadocOrder::statusOptions(),
                'generado_en' => now()->toIso8601String(),
            ],
        ]);
    }

    public function show(Request $request, string $orderNumber): JsonResponse
    {
        $order = FarmadocOrderFeed::findByNumber($orderNumber);

        if ($order === null) {
            return $this->notFound($request);
        }

        AuthenticateApiPartner::recordCount($request, 1);

        return response()->json([
            'data' => FarmadocOrderFeed::payload($order, $this->receiptNoteUrl($order)),
            'meta' => ['generado_en' => now()->toIso8601String()],
        ]);
    }

    public function receiptNote(Request $request, string $orderNumber): Response|JsonResponse
    {
        $order = FarmadocOrderFeed::findByNumber($orderNumber);

        if ($order === null) {
            return $this->notFound($request);
        }

        if (! FarmadocReceiptNote::available($order)) {
            AuthenticateApiPartner::recordCount($request, 0);

            return response()->json([
                'error' => 'sin_nota_recibido',
                'mensaje' => 'Este pedido todavía no se registró en el inventario de IntegraCorp.',
            ], 404);
        }

        AuthenticateApiPartner::recordCount($request, 1);

        $content = FarmadocReceiptNote::render($order);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.FarmadocReceiptNote::filename($order).'"',
            'Content-Length' => (string) strlen($content),
        ]);
    }

    private function receiptNoteUrl(FarmadocOrder $order): string
    {
        return url('api/v1/aliados/pedidos-farmadoc/'.rawurlencode((string) $order->order_number).'/nota-de-recibido');
    }

    private function notFound(Request $request): JsonResponse
    {
        AuthenticateApiPartner::recordCount($request, 0);

        return response()->json([
            'error' => 'no_encontrado',
            'mensaje' => 'No existe un pedido con ese número.',
        ], 404);
    }
}
