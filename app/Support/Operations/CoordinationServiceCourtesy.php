<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\ObservationCase;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicinePatientLab;
use App\Models\TelemedicinePatientMedications;
use App\Models\TelemedicinePatientSpecialty;
use App\Models\TelemedicinePatientStudy;
use App\Models\User;
use App\Support\Filament\Operations\OperationsSupplierScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CoordinationServiceCourtesy
{
    public const STATUS = 'CORTESIA';

    public const MARK_PREFIX = 'Asignación de segundo estatus CORTESIA.';

    public const REVERSE_PREFIX = 'Reverso de segundo estatus CORTESIA.';

    /**
     * @param  list<string>  $itemKeys
     */
    public static function markItems(
        OperationCoordinationService $record,
        array $itemKeys,
        string $reason,
        ?User $user = null,
    ): int {
        return self::apply($record, $itemKeys, $reason, mark: true, user: $user);
    }

    /**
     * @param  list<string>  $itemKeys
     */
    public static function reverseItems(
        OperationCoordinationService $record,
        array $itemKeys,
        string $reason,
        ?User $user = null,
    ): int {
        return self::apply($record, $itemKeys, $reason, mark: false, user: $user);
    }

    public static function itemIsCourtesy(?string $courtesyStatus): bool
    {
        return mb_strtoupper(trim((string) $courtesyStatus)) === self::STATUS;
    }

    public static function coordinationHasCourtesyItems(OperationCoordinationService $record): bool
    {
        if ($record->telemedicinePatientMedications()->where('courtesy_status', self::STATUS)->exists()) {
            return true;
        }

        if ($record->telemedicinePatientLabs()->where('courtesy_status', self::STATUS)->exists()) {
            return true;
        }

        if ($record->telemedicinePatientStudies()->where('courtesy_status', self::STATUS)->exists()) {
            return true;
        }

        return $record->telemedicinePatientSpecialties()->where('courtesy_status', self::STATUS)->exists();
    }

    public static function courtesyItemsCount(OperationCoordinationService $record): int
    {
        return (int) $record->telemedicinePatientMedications()->where('courtesy_status', self::STATUS)->count()
            + (int) $record->telemedicinePatientLabs()->where('courtesy_status', self::STATUS)->count()
            + (int) $record->telemedicinePatientStudies()->where('courtesy_status', self::STATUS)->count()
            + (int) $record->telemedicinePatientSpecialties()->where('courtesy_status', self::STATUS)->count();
    }

    /**
     * @param  list<string>  $keys
     * @return array{courtesy: list<string>, regular: list<string>}
     */
    public static function partitionKeysByCourtesy(OperationCoordinationService $record, array $keys): array
    {
        $keys = array_values(array_unique(array_filter($keys, fn (mixed $key): bool => is_string($key) && $key !== '')));
        $courtesy = [];
        $regular = [];

        foreach ($keys as $key) {
            $model = self::resolveItemModel($record, $key);

            if ($model === null) {
                $regular[] = $key;

                continue;
            }

            if (self::itemIsCourtesy($model->getAttribute('courtesy_status'))) {
                $courtesy[] = $key;
            } else {
                $regular[] = $key;
            }
        }

        return [
            'courtesy' => $courtesy,
            'regular' => $regular,
        ];
    }

    /**
     * @param  list<OperationCoordinationService>  $records
     * @return array<string, string>
     */
    public static function checkboxOptionsForCoordinations(iterable $records, bool $onlyCourtesy = false): array
    {
        $options = [];

        foreach ($records as $record) {
            if (! $record instanceof OperationCoordinationService) {
                continue;
            }

            $prefix = filled($record->reference_number)
                ? (string) $record->reference_number
                : 'Coord #'.$record->id;
            $patient = filled($record->patient) ? (string) $record->patient : 'Sin paciente';

            foreach (CoordinationServiceItemsManager::associatedServiceItemsForManagement($record) as $item) {
                $key = (string) ($item['key'] ?? '');

                if ($key === '') {
                    continue;
                }

                $model = self::resolveItemModel($record, $key);
                $isCourtesy = self::itemIsCourtesy($model?->getAttribute('courtesy_status'));

                if ($onlyCourtesy && ! $isCourtesy) {
                    continue;
                }

                if (! $onlyCourtesy && $isCourtesy) {
                    continue;
                }

                $options[$record->id.'|'.$key] = $prefix.' · '.$patient.' · '
                    .($item['category'] ?? 'Ítem').': '.($item['label'] ?? $key)
                    .($isCourtesy ? ' [CORTESÍA]' : '');
            }
        }

        return $options;
    }

    /**
     * @param  list<string>  $compositeKeys  format coordinationId|itemKey
     * @return array<int, list<string>>
     */
    public static function groupCompositeKeysByCoordination(array $compositeKeys): array
    {
        $grouped = [];

        foreach ($compositeKeys as $composite) {
            if (! is_string($composite) || ! str_contains($composite, '|')) {
                continue;
            }

            [$coordinationId, $itemKey] = explode('|', $composite, 2);
            $id = (int) $coordinationId;

            if ($id <= 0 || $itemKey === '') {
                continue;
            }

            $grouped[$id] ??= [];
            $grouped[$id][] = $itemKey;
        }

        return $grouped;
    }

    /**
     * @param  list<string>  $itemKeys
     */
    private static function apply(
        OperationCoordinationService $record,
        array $itemKeys,
        string $reason,
        bool $mark,
        ?User $user = null,
    ): int {
        $user ??= Auth::user();

        if ($user === null || ! OperationsSupplierScope::authenticatedUserIsTdgAnalyst()) {
            throw new InvalidArgumentException('Solo un analista TDG puede gestionar el estatus CORTESÍA.');
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10) {
            throw new InvalidArgumentException('El motivo debe tener al menos 10 caracteres.');
        }

        $itemKeys = array_values(array_unique(array_filter(
            $itemKeys,
            fn (mixed $key): bool => is_string($key) && $key !== '',
        )));

        if ($itemKeys === []) {
            throw new InvalidArgumentException('Debes seleccionar al menos un ítem del servicio.');
        }

        $userName = filled($user->name) ? (string) $user->name : 'SISTEMA';
        $labels = [];
        $updated = 0;

        DB::transaction(function () use (
            $record,
            $itemKeys,
            $reason,
            $mark,
            $user,
            $userName,
            &$labels,
            &$updated,
        ): void {
            foreach ($itemKeys as $key) {
                $model = self::resolveItemModel($record, $key);

                if ($model === null) {
                    continue;
                }

                $currentlyCourtesy = self::itemIsCourtesy($model->getAttribute('courtesy_status'));

                if ($mark && $currentlyCourtesy) {
                    continue;
                }

                if (! $mark && ! $currentlyCourtesy) {
                    continue;
                }

                $model->forceFill([
                    'courtesy_status' => $mark ? self::STATUS : null,
                    'courtesy_reason' => $reason,
                    'courtesy_updated_at' => now(),
                    'courtesy_updated_by' => $userName,
                ])->save();

                $labels[] = self::labelForModel($model, $key);
                $updated++;
            }

            if ($updated === 0) {
                return;
            }

            $prefix = $mark ? self::MARK_PREFIX : self::REVERSE_PREFIX;
            $bitacora = $prefix
                ."\n".'Motivo: '.$reason
                ."\n".'Ítems: '.implode('; ', $labels)
                ."\n".'Analista: '.$userName;

            $previous = trim((string) ($record->observations ?? ''));
            $record->observations = $previous !== '' ? $previous."\n\n".$bitacora : $bitacora;
            $record->updated_by = $userName;
            $record->save();

            if (filled($record->telemedicine_case_id)) {
                ObservationCase::query()->create([
                    'telemedicine_case_id' => $record->telemedicine_case_id,
                    'description' => $bitacora,
                    'created_by' => $user->id !== null ? (string) $user->id : null,
                ]);
            }
        });

        return $updated;
    }

    public static function resolveItemModel(OperationCoordinationService $record, string $key): ?Model
    {
        if (! str_contains($key, ':')) {
            return null;
        }

        [$type, $idRaw] = explode(':', $key, 2);
        $id = (int) $idRaw;

        if ($id <= 0) {
            return null;
        }

        return match ($type) {
            'medication' => TelemedicinePatientMedications::query()
                ->whereKey($id)
                ->where('operation_coordination_service_id', $record->id)
                ->first(),
            'lab' => TelemedicinePatientLab::query()
                ->whereKey($id)
                ->where('operation_coordination_service_id', $record->id)
                ->first(),
            'study' => TelemedicinePatientStudy::query()
                ->whereKey($id)
                ->where('operation_coordination_service_id', $record->id)
                ->first(),
            'specialty' => TelemedicinePatientSpecialty::query()
                ->whereKey($id)
                ->where('operation_coordination_service_id', $record->id)
                ->first(),
            default => null,
        };
    }

    private static function labelForModel(Model $model, string $key): string
    {
        $name = match (true) {
            $model instanceof TelemedicinePatientMedications => (string) ($model->medicine ?? 'Medicamento'),
            $model instanceof TelemedicinePatientLab => (string) ($model->laboratory ?? 'Laboratorio'),
            $model instanceof TelemedicinePatientStudy => (string) ($model->study ?? 'Estudio'),
            $model instanceof TelemedicinePatientSpecialty => (string) ($model->specialty ?? 'Especialista'),
            default => $key,
        };

        return $name.' ('.$key.')';
    }
}
