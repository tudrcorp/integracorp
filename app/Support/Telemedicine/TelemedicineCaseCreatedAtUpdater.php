<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\ObservationCase;
use App\Models\TelemedicineCase;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Actualiza {@see TelemedicineCase::$created_at} y registra el cambio en la bitácora del caso.
 */
final class TelemedicineCaseCreatedAtUpdater
{
    public const OBSERVATION_PREFIX = 'Cambio de fecha de creación del caso.';

    public static function buildBitacoraDescription(
        CarbonInterface $previousCreatedAt,
        CarbonInterface $newCreatedAt,
        string $reason,
    ): string {
        return self::OBSERVATION_PREFIX
            ."\n".'Fecha anterior: '.$previousCreatedAt->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
            ."\n".'Fecha nueva: '.$newCreatedAt->timezone(config('app.timezone'))->format('d/m/Y H:i:s')
            ."\n".'Motivo: '.trim($reason);
    }

    /**
     * @return array{case: TelemedicineCase, observation: ObservationCase}
     */
    public static function execute(
        TelemedicineCase $case,
        CarbonInterface|string $newCreatedAt,
        string $reason,
        ?User $user = null,
    ): array {
        $user ??= Auth::user();
        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('El motivo del cambio de fecha debe tener al menos 10 caracteres.');
        }

        $previous = $case->created_at;

        if ($previous === null) {
            throw new InvalidArgumentException('El caso no tiene fecha de creación registrada.');
        }

        $new = $newCreatedAt instanceof CarbonInterface
            ? Carbon::instance($newCreatedAt)->timezone(config('app.timezone'))
            : Carbon::parse($newCreatedAt, config('app.timezone'));

        if ($previous->equalTo($new)) {
            throw new InvalidArgumentException('La nueva fecha de creación es igual a la fecha actual del caso.');
        }

        if ($new->greaterThan(now()->timezone(config('app.timezone')))) {
            throw new InvalidArgumentException('La fecha de creación no puede ser futura.');
        }

        $bitacoraDescription = self::buildBitacoraDescription($previous, $new, $reason);
        $userId = $user?->id;

        $observation = null;

        DB::transaction(function () use ($case, $new, $bitacoraDescription, $userId, &$observation): void {
            TelemedicineCase::query()
                ->whereKey($case->id)
                ->update(['created_at' => $new]);

            $observation = ObservationCase::query()->create([
                'telemedicine_case_id' => $case->id,
                'description' => $bitacoraDescription,
                'created_by' => $userId !== null ? (string) $userId : null,
            ]);
        });

        return [
            'case' => $case->fresh() ?? $case,
            'observation' => $observation,
        ];
    }
}
