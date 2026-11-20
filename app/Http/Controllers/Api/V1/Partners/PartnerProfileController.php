<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Partners;

use App\Enums\ApiPartnerAbility;
use App\Http\Controllers\Controller;
use App\Http\Middleware\AuthenticateApiPartner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API de aliados — datos del propio aliado, para que verifique su conexión, y la
 * respuesta para rutas inexistentes dentro del API (siempre después de autenticar).
 */
class PartnerProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $partner = AuthenticateApiPartner::partner($request);

        return response()->json([
            'data' => [
                'aliado' => $partner?->name,
                'permisos' => array_values(array_filter(array_map(
                    fn (string $ability): ?string => ApiPartnerAbility::tryFrom($ability)?->value,
                    $partner?->abilities ?? [],
                ))),
                'limite_por_minuto' => $partner?->rate_limit_per_minute,
                'vence_en' => $partner?->expires_at?->toIso8601String(),
            ],
            'meta' => ['generado_en' => now()->toIso8601String()],
        ]);
    }

    public function notFound(): JsonResponse
    {
        return AuthenticateApiPartner::error(404, 'ruta_no_encontrada', 'La ruta solicitada no existe en el API de aliados.');
    }
}
