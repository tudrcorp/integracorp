<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\AffiliateStatusMovement;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\CompanyAssociate;
use App\Support\Business\CapitationPlans\CapitationSubject;
use BackedEnum;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

/**
 * Guarda en `affiliate_status_movements` cada alta, baja o cambio de estatus de un afiliado
 * (individual, corporativo, de Nuevos Negocios) o de su afiliación, con la fecha en que
 * ocurrió. Es lo que permite saber cuándo salió alguien (la pre-facturación de capitado lo
 * usa para fechar los retiros).
 *
 * Nunca interrumpe el guardado del afiliado: si la bitácora falla, queda en el log y sigue.
 * Las actualizaciones masivas por query builder (sin modelos) no pasan por aquí.
 */
class AffiliateStatusMovementObserver
{
    public const SUBJECT_AFFILIATION = 'affiliation';

    public const SUBJECT_AFFILIATION_CORPORATE = 'affiliation_corporate';

    /** Estatus que cuentan como «activo» en cualquiera de los cinco modelos. */
    private const ACTIVE_STATUSES = ['ACTIVO', 'ACTIVA', 'ACTIVO-SIN-VAUCHER-ILS'];

    private static ?bool $tableExists = null;

    public function created(Model $model): void
    {
        $status = self::status($model->getAttribute('status'));

        $this->record($model, self::isActive($status) ? AffiliateStatusMovement::EVENT_ALTA : AffiliateStatusMovement::EVENT_CAMBIO, null, $status);
    }

    public function updated(Model $model): void
    {
        if (! $model->wasChanged('status')) {
            return;
        }

        $from = self::status($model->getOriginal('status'));
        $to = self::status($model->getAttribute('status'));

        if ($from === $to) {
            return;
        }

        $event = match (true) {
            ! self::isActive($from) && self::isActive($to) => AffiliateStatusMovement::EVENT_ALTA,
            self::isActive($from) && ! self::isActive($to) => AffiliateStatusMovement::EVENT_BAJA,
            default => AffiliateStatusMovement::EVENT_CAMBIO,
        };

        $this->record($model, $event, $from, $to);
    }

    public function deleted(Model $model): void
    {
        $status = self::status($model->getAttribute('status'));

        $this->record($model, AffiliateStatusMovement::EVENT_ELIMINADO, $status, null);
    }

    public function restored(Model $model): void
    {
        $status = self::status($model->getAttribute('status'));

        $this->record($model, AffiliateStatusMovement::EVENT_RESTAURADO, null, $status);
    }

    public static function subjectType(Model $model): ?string
    {
        return match (true) {
            $model instanceof Affiliate, $model instanceof AffiliateCorporate, $model instanceof CompanyAssociate => CapitationSubject::type($model),
            $model instanceof Affiliation => self::SUBJECT_AFFILIATION,
            $model instanceof AffiliationCorporate => self::SUBJECT_AFFILIATION_CORPORATE,
            default => null,
        };
    }

    public static function isActive(?string $status): bool
    {
        return $status !== null && in_array($status, self::ACTIVE_STATUSES, true);
    }

    /**
     * Descarta la cache de existencia de la tabla (tests).
     */
    public static function flush(): void
    {
        self::$tableExists = null;
    }

    private function record(Model $model, string $event, ?string $from, ?string $to): void
    {
        try {
            $type = self::subjectType($model);

            if ($type === null || $model->getKey() === null || ! self::tableExists($model)) {
                return;
            }

            $user = Auth::user();

            AffiliateStatusMovement::query()->create([
                'subject_type' => $type,
                'subject_id' => (int) $model->getKey(),
                'event' => $event,
                'from_status' => $from !== null ? Str::limit($from, 40, '') : null,
                'to_status' => $to !== null ? Str::limit($to, 40, '') : null,
                'occurred_at' => now(),
                'actor_id' => $user?->getAuthIdentifier(),
                'actor_name' => $user !== null ? Str::limit((string) ($user->name ?? ''), 255, '') : null,
            ]);
        } catch (Throwable $exception) {
            Log::warning('AffiliateStatusMovementObserver: no se guardó el movimiento', [
                'model' => $model::class,
                'id' => $model->getKey(),
                'event' => $event,
                'error' => $exception->getMessage(),
            ]);
        }
    }

    private static function status(mixed $value): ?string
    {
        if ($value instanceof BackedEnum) {
            $value = $value->value;
        }

        $value = Str::upper(Str::squish((string) ($value ?? '')));

        return $value === '' ? null : $value;
    }

    private static function tableExists(Model $model): bool
    {
        return self::$tableExists ??= Schema::connection($model->getConnectionName())->hasTable('affiliate_status_movements');
    }
}
