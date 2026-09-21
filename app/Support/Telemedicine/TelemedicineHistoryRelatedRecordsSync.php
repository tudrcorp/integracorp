<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\FamilyHistory;
use App\Models\GynecologicalHistory;
use App\Models\NoPathologicalHistory;
use App\Models\PathologicalHistory;
use App\Models\SurgicalHistory;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

final class TelemedicineHistoryRelatedRecordsSync
{
    /**
     * @return list<array{model: class-string<Model>, source_attribute: string}>
     */
    public static function sourceMap(): array
    {
        return [
            ['model' => FamilyHistory::class, 'source_attribute' => 'observations_personal'],
            ['model' => PathologicalHistory::class, 'source_attribute' => 'observations_pathological'],
            ['model' => NoPathologicalHistory::class, 'source_attribute' => 'observations_not_pathological'],
            ['model' => SurgicalHistory::class, 'source_attribute' => 'history_surgical'],
            ['model' => GynecologicalHistory::class, 'source_attribute' => 'observations_ginecologica'],
        ];
    }

    public static function normalizeObservations(mixed $value): ?string
    {
        if (is_string($value) || is_numeric($value)) {
            $trimmed = trim((string) $value);

            return $trimmed === '' ? null : $trimmed;
        }

        return null;
    }

    public static function normalizeCreatedBy(mixed $value): string
    {
        $trimmed = trim((string) $value);

        return $trimmed === '' ? 'Sistema' : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return list<array{model: class-string<Model>, source_attribute: string, observations: string}>
     */
    public static function payloadsFromAttributes(array $attributes): array
    {
        $payloads = [];

        foreach (self::sourceMap() as $item) {
            $observations = self::normalizeObservations($attributes[$item['source_attribute']] ?? null);

            if ($observations === null) {
                continue;
            }

            $payloads[] = [
                'model' => $item['model'],
                'source_attribute' => $item['source_attribute'],
                'observations' => $observations,
            ];
        }

        return $payloads;
    }

    public static function syncFromHistory(Model $history, mixed $createdBy): int
    {
        $historyId = (int) $history->getKey();
        $patientId = (int) $history->getAttribute('telemedicine_patient_id');

        if ($historyId < 1 || $patientId < 1) {
            return 0;
        }

        $payloads = self::payloadsFromAttributes($history->getAttributes());
        $author = self::normalizeCreatedBy($createdBy);

        if ($payloads === []) {
            return 0;
        }

        DB::transaction(function () use ($payloads, $historyId, $patientId, $author): void {
            foreach ($payloads as $payload) {
                self::persistObservation(
                    $payload['model'],
                    $historyId,
                    $patientId,
                    $payload['observations'],
                    $author,
                );
            }
        });

        return count($payloads);
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    public static function appendObservation(
        string $modelClass,
        Model $history,
        mixed $observations,
        mixed $createdBy,
    ): void {
        $normalized = self::normalizeObservations($observations);

        if ($normalized === null) {
            throw new InvalidArgumentException('El antecedente no puede quedar vacío.');
        }

        $historyId = (int) $history->getKey();
        $patientId = (int) $history->getAttribute('telemedicine_patient_id');

        if ($historyId < 1 || $patientId < 1) {
            throw new InvalidArgumentException('No se pudo asociar el antecedente a la historia clínica.');
        }

        self::persistObservation(
            $modelClass,
            $historyId,
            $patientId,
            $normalized,
            self::normalizeCreatedBy($createdBy),
        );
    }

    /**
     * @param  class-string<Model>  $modelClass
     * @param  array<string, mixed>  $data
     */
    public static function handleRelationCreate(
        string $modelClass,
        Model $history,
        array $data,
    ): void {
        try {
            self::appendObservation(
                $modelClass,
                $history,
                $data['observations'] ?? null,
                $data['created_by'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()
                ->title($exception->getMessage())
                ->danger()
                ->send();
        } catch (\Throwable $throwable) {
            Log::error('Error al registrar un antecedente de historia clínica.', [
                'model' => $modelClass,
                'history_id' => $history->getKey(),
                'patient_id' => $history->getAttribute('telemedicine_patient_id'),
                'message' => $throwable->getMessage(),
            ]);

            Notification::make()
                ->title('No se pudo registrar el antecedente.')
                ->body('Intente de nuevo. Si el problema continúa, contacte a soporte.')
                ->danger()
                ->send();
        }
    }

    /**
     * @param  class-string<Model>  $modelClass
     */
    private static function persistObservation(
        string $modelClass,
        int $historyId,
        int $patientId,
        string $observations,
        string $createdBy,
    ): void {
        /** @var Model $record */
        $record = new $modelClass;
        $record->setAttribute('telemedicine_history_patient_id', $historyId);
        $record->setAttribute('telemedicine_patient_id', $patientId);
        $record->setAttribute('observations', $observations);
        $record->setAttribute('created_by', $createdBy);
        $record->save();
    }
}
