<?php

declare(strict_types=1);

namespace App\Support\QrCenter;

use App\Models\QrDestination;
use App\Support\AffiliateCard\AffiliateCardPageLayout;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Qué imagen de QR imprime una tarjeta: la personalizada del centro de QR si el
 * destino la tiene; si no, `null` y la tarjeta sigue con su QR de siempre.
 *
 * Un lote corporativo dibuja miles de tarjetas, y los workers viven horas: el mapa
 * se guarda en la caché compartida (se borra al guardar un QR) y en el proceso
 * solo unos segundos, para que un cambio llegue también a los workers en marcha.
 */
final class CardQrResolver
{
    private const CACHE_KEY = 'qr-center:card-images:v1';

    private const CACHE_SECONDS = 600;

    private const PROCESS_SECONDS = 15;

    /** @var array<string, string>|null */
    private static ?array $map = null;

    private static int $loadedAt = 0;

    public static function customImagePath(string $scope, ?int $planId): ?string
    {
        $relative = self::map()[QrDestinationManager::key($scope, $planId)] ?? null;

        if ($relative === null) {
            return null;
        }

        $absolute = QrCenterStorage::absolutePath($relative);

        return is_file($absolute) ? $absolute : null;
    }

    /**
     * El alcance de una tarjeta según los datos con que se dibuja.
     *
     * @param  array<string, mixed>  $data
     */
    public static function scopeForCard(array $data): ?string
    {
        // Las tarjetas de una empresa aliada llevan otra marca: conservan su QR automático.
        if (($data['template_key'] ?? null) === AffiliateCardPageLayout::TEMPLATE_INDIVIDUAL_AFFILIATION_ALLIED) {
            return null;
        }

        $explicit = $data['qr_scope'] ?? null;

        if (is_string($explicit) && in_array($explicit, QrDestination::scopes(), true)) {
            return $explicit;
        }

        if (($data['plan_qr_filename'] ?? null) === 'qr-plan-inclusion.png') {
            return QrDestination::SCOPE_INCLUSION;
        }

        return QrDestination::SCOPE_INDIVIDUAL;
    }

    public static function forget(): void
    {
        self::$map = null;
        self::$loadedAt = 0;

        try {
            Cache::forget(self::CACHE_KEY);
        } catch (Throwable $exception) {
            Log::warning('Centro de QR: no se pudo limpiar la caché de QR de tarjetas', ['error' => $exception->getMessage()]);
        }
    }

    /**
     * @return array<string, string>
     */
    private static function map(): array
    {
        if (self::$map !== null && time() - self::$loadedAt < self::PROCESS_SECONDS) {
            return self::$map;
        }

        try {
            $map = Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, self::load(...));
        } catch (Throwable $exception) {
            // Sin caché (o sin tabla todavía) la tarjeta no debe fallar: se lee directo o se usa el QR automático.
            Log::warning('Centro de QR: caché no disponible al resolver el QR de una tarjeta', ['error' => $exception->getMessage()]);
            $map = self::loadSafely();
        }

        self::$map = is_array($map) ? $map : [];
        self::$loadedAt = time();

        return self::$map;
    }

    /**
     * @return array<string, string>
     */
    private static function load(): array
    {
        return QrDestination::query()
            ->where('target_type', '!=', QrDestination::TARGET_AUTOMATIC)
            ->whereNotNull('current_version_id')
            ->with('currentVersion:id,image_path')
            ->get(['id', 'scope', 'plan_id', 'current_version_id'])
            ->filter(fn (QrDestination $destination): bool => is_string($destination->currentVersion?->image_path))
            ->mapWithKeys(fn (QrDestination $destination): array => [
                QrDestinationManager::key($destination->scope, $destination->plan_id) => (string) $destination->currentVersion->image_path,
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function loadSafely(): array
    {
        try {
            return self::load();
        } catch (Throwable) {
            return [];
        }
    }
}
