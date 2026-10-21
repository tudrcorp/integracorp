<?php

declare(strict_types=1);

namespace App\Support\ClinicalEntitlements;

use App\Models\PlanClinicalUsageEvent;
use App\Models\User;
use App\Support\SecurityAudit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Traza del uso clínico de los planes.
 *
 * Cada hecho queda en dos lugares: `plan_clinical_usage_events` (lo que la
 * pantalla muestra en «Historial») y `SecurityAudit` (la auditoría general de
 * Negocios). Se escribe dentro de la misma transacción que el cambio: si la
 * fila de traza no entra, el cambio tampoco. La única tolerancia es que la
 * tabla aún no exista (despliegue sin migrar): entonces queda solo en
 * SecurityAudit y en el log.
 */
final class PlanClinicalUsageTrace
{
    public const MAP_SAVED = 'MAP_SAVED';

    public const COPY_APPLIED = 'COPY_APPLIED';

    public const BENEFIT_LINKED = 'BENEFIT_LINKED';

    public const BENEFIT_CREATED = 'BENEFIT_CREATED';

    public const SERVICE_MACRO_CREATED = 'SERVICE_MACRO_CREATED';

    public const SAVE_REJECTED = 'SAVE_REJECTED';

    private static ?bool $tableExists = null;

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function record(
        string $event,
        ?int $planId,
        string $description,
        array $changes = [],
        ?int $sourcePlanId = null,
        ?User $actor = null,
    ): void {
        $actor ??= Auth::user() instanceof User ? Auth::user() : null;

        SecurityAudit::log('AUDIT_PLAN_CLINICAL_USAGE_'.$event, 'business.plans.clinical-usage', [
            'plan_id' => $planId,
            'source_plan_id' => $sourcePlanId,
            'description' => $description,
            'changes' => $changes,
        ], $actor);

        if (! self::tableExists()) {
            Log::warning('plan_clinical_usage_events no existe; la traza quedó solo en SecurityAudit.', [
                'event' => $event,
                'plan_id' => $planId,
            ]);

            return;
        }

        PlanClinicalUsageEvent::query()->create([
            'plan_id' => $planId,
            'source_plan_id' => $sourcePlanId,
            'event' => $event,
            'description' => $description,
            'changes' => $changes === [] ? null : $changes,
            'actor_id' => $actor?->id,
            'actor_name' => $actor?->name ?? 'sistema',
            'ip' => request()?->ip(),
        ]);
    }

    public static function label(string $event): string
    {
        return match ($event) {
            self::MAP_SAVED => 'Mapa guardado',
            self::COPY_APPLIED => 'Copia aplicada',
            self::BENEFIT_LINKED => 'Beneficio vinculado',
            self::BENEFIT_CREATED => 'Beneficio creado',
            self::SERVICE_MACRO_CREATED => 'Servicio macro creado',
            self::SAVE_REJECTED => 'Guardado rechazado',
            default => $event,
        };
    }

    public static function color(string $event): string
    {
        return match ($event) {
            self::MAP_SAVED => 'success',
            self::COPY_APPLIED => 'info',
            self::BENEFIT_LINKED, self::BENEFIT_CREATED => 'primary',
            self::SERVICE_MACRO_CREATED => 'warning',
            self::SAVE_REJECTED => 'danger',
            default => 'gray',
        };
    }

    public static function tableExists(): bool
    {
        return self::$tableExists ??= Schema::hasTable('plan_clinical_usage_events');
    }
}
