<?php

declare(strict_types=1);

namespace App\Observers;

use App\Support\Operations\Providers\ProviderActivityLog;
use App\Support\Operations\Providers\ProviderNetworkAnalytics;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

/**
 * Proveedores jurídicos, naturales y aliados corporativos: deja quién los registró y quién los
 * actualizó, anota cada cambio en la bitácora del equipo y renueva la caché del Dashboard de
 * proveedores.
 */
class ProviderActivityObserver
{
    public function creating(Model $provider): void
    {
        $actor = ProviderActivityLog::actorName();

        if ($actor === null) {
            return;
        }

        if (self::hasColumn($provider, 'created_by') && trim((string) $provider->getAttribute('created_by')) === '') {
            $provider->setAttribute('created_by', $actor);
        }

        if (self::hasColumn($provider, 'updated_by')) {
            $provider->setAttribute('updated_by', $actor);
        }
    }

    public function updating(Model $provider): void
    {
        $actor = ProviderActivityLog::actorName();

        if ($actor !== null && self::hasColumn($provider, 'updated_by')) {
            $provider->setAttribute('updated_by', $actor);
        }
    }

    public function created(Model $provider): void
    {
        ProviderActivityLog::created($provider);
        ProviderNetworkAnalytics::flush();
    }

    public function updated(Model $provider): void
    {
        ProviderActivityLog::updated($provider);
        ProviderNetworkAnalytics::flush();
    }

    public function deleted(Model $provider): void
    {
        ProviderActivityLog::deleted($provider);
        ProviderNetworkAnalytics::flush();
    }

    public function restored(Model $provider): void
    {
        ProviderActivityLog::restored($provider);
        ProviderNetworkAnalytics::flush();
    }

    private static function hasColumn(Model $provider, string $column): bool
    {
        static $columns = [];
        $key = $provider->getTable().'.'.$column;

        return $columns[$key] ??= Schema::hasColumn($provider->getTable(), $column);
    }
}
