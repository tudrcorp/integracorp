<?php

declare(strict_types=1);

namespace App\Support\Operations\Providers;

use App\Enums\ProviderNetworkType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Bitácora de lo que hace el equipo con los proveedores (`provider_activity_events`). Es la base
 * de la productividad del Dashboard de proveedores desde el 26/10/2026.
 *
 * Nunca interrumpe el guardado del proveedor: si la bitácora falla, se reporta y se sigue.
 */
final class ProviderActivityLog
{
    public const CREATED = 'CREADO';

    public const UPDATED = 'ACTUALIZADO';

    public const STATUS = 'ESTATUS';

    public const AGREEMENT = 'CONVENIO';

    public const LETTER = 'CARTA';

    public const DELETED = 'ELIMINADO';

    public const RESTORED = 'RESTAURADO';

    /**
     * Columnas que no cuentan como «actualización» del proveedor.
     *
     * @var list<string>
     */
    private const IGNORED = ['updated_at', 'updated_by', 'created_at', 'created_by', 'deleted_at', 'deleted_by_user_id', 'deleted_by_name', 'deletion_reason'];

    public static function actorName(): ?string
    {
        $name = Auth::user()?->name;

        return is_string($name) && trim($name) !== '' ? trim($name) : null;
    }

    public static function created(Model $provider): void
    {
        self::write($provider, self::CREATED);
    }

    public static function deleted(Model $provider): void
    {
        self::write($provider, self::DELETED, after: (string) ($provider->getAttribute('deletion_reason') ?? ''));
    }

    public static function restored(Model $provider): void
    {
        self::write($provider, self::RESTORED);
    }

    public static function updated(Model $provider): void
    {
        $type = ProviderNetworkType::fromModel($provider);
        $changes = array_values(array_diff(array_keys($provider->getChanges()), self::IGNORED));

        if ($changes === []) {
            return;
        }

        $original = $provider->getOriginal();
        $current = $provider->getAttributes();

        self::write($provider, self::UPDATED, changed: $changes);

        $before = ProviderNetworkCatalog::status($type, $original);
        $after = ProviderNetworkCatalog::status($type, $current);

        if ($before['raw'] !== $after['raw']) {
            self::write($provider, self::STATUS, field: 'estatus', before: $before['key'] === ProviderNetworkCatalog::STATUS_OTHER ? $before['raw'] : $before['key'], after: $after['key'] === ProviderNetworkCatalog::STATUS_OTHER ? $after['raw'] : $after['key']);
        }

        $agreementBefore = ProviderNetworkCatalog::agreement($type, $original);
        $agreementAfter = ProviderNetworkCatalog::agreement($type, $current);

        if ($agreementBefore !== $agreementAfter) {
            self::write($provider, self::AGREEMENT, field: 'tipo de convenio', before: $agreementBefore, after: $agreementAfter);
        }

        if ($type !== ProviderNetworkType::Aliado && in_array('carta_acceptance', $changes, true) && trim((string) ($current['carta_acceptance'] ?? '')) !== '') {
            self::write($provider, self::LETTER, field: 'carta de aceptación', before: trim((string) ($original['carta_acceptance'] ?? '')) === '' ? 'sin carta' : 'carta anterior', after: 'carta cargada');
        }
    }

    /**
     * @param  list<string>|null  $changed
     */
    private static function write(Model $provider, string $action, ?string $field = null, ?string $before = null, ?string $after = null, ?array $changed = null): void
    {
        try {
            if (! self::available()) {
                return;
            }

            $type = ProviderNetworkType::fromModel($provider);
            $name = $provider->getAttribute($type === ProviderNetworkType::Aliado ? 'company_name' : 'name') ?: $provider->getAttribute('razon_social');

            DB::table('provider_activity_events')->insert([
                'provider_type' => $type->value,
                'provider_id' => (int) $provider->getKey(),
                'provider_name' => Str::limit(trim((string) $name), 250, ''),
                'action' => $action,
                'field' => $field,
                'before' => $before,
                'after' => $after,
                'changed_fields' => $changed !== null ? json_encode($changed) : null,
                'user_id' => Auth::id(),
                'user_name' => self::actorName(),
                'created_at' => now(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('ProviderActivityLog: no se pudo registrar la actividad', ['action' => $action, 'message' => $exception->getMessage()]);
        }
    }

    private static function available(): bool
    {
        static $available = false;

        return $available = $available || Schema::hasTable('provider_activity_events');
    }
}
