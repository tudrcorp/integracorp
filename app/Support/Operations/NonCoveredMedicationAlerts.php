<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\OperationCoordinationServices\Pages\ManageCoordinationServiceItems;
use App\Models\Coverage;
use App\Models\OperationCoordinationService;
use App\Models\OperationNonCoveredMedicationAlert;
use App\Models\OperationNonCoveredMedicationAlertDismissal;
use App\Models\Plan;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientMedications;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineMedicationCoverage;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Aviso a los analistas TDG de Operaciones cuando el médico receta medicamentos
 * NO CUBIERTOS a un afiliado con Plan Especial de 30K, 40K o 50K.
 *
 * - Nace al guardar la consulta, uno por coordinación de medicamentos.
 * - «OK» lo cierra solo para ese analista y lo deja en su campanita.
 * - «Ir a gestionar» lo toma para todos (con bloqueo de fila: nunca lo toman
 *   dos) y abre el asistente en el paso «Cotización» con los medicamentos marcados.
 * - Un analista que entra tarde ve los avisos de las últimas 24 horas que nadie tomó.
 */
final class NonCoveredMedicationAlerts
{
    public const PLAN_NAME_PREFIX = 'PLAN ESPECIAL';

    /** @var list<int> */
    public const COVERAGE_AMOUNTS = [30000, 40000, 50000];

    public const WINDOW_HOURS = 24;

    public const MAX_PENDING = 20;

    public const QUERY_PARAMETER = 'medicamentos';

    private static ?bool $tablesExist = null;

    /**
     * Plan y cobertura del paciente si es un Plan Especial de 30K, 40K o 50K.
     *
     * @return array{plan_id: int, coverage_id: int, amount: float, plan_name: string}|null
     */
    public static function qualifyingCoverage(TelemedicinePatient $patient): ?array
    {
        $resolved = TelemedicinePatientPlanBridge::planAndCoverage($patient);

        if ($resolved === null || $resolved['coverage_id'] === null) {
            return null;
        }

        $plan = Plan::query()->whereKey($resolved['plan_id'])->first(['id', 'description']);
        $coverage = Coverage::query()->whereKey($resolved['coverage_id'])->first(['id', 'price', 'plan_id']);

        if ($plan === null || $coverage === null) {
            return null;
        }

        if (! self::isSpecialPlanName((string) $plan->description) || ! self::isQualifyingAmount($coverage->price)) {
            return null;
        }

        return [
            'plan_id' => (int) $plan->getKey(),
            'coverage_id' => (int) $coverage->getKey(),
            'amount' => (float) $coverage->price,
            'plan_name' => trim((string) $plan->description),
        ];
    }

    public static function isSpecialPlanName(string $description): bool
    {
        return str_starts_with(mb_strtoupper(trim($description)), self::PLAN_NAME_PREFIX);
    }

    public static function isQualifyingAmount(mixed $amount): bool
    {
        if (! is_numeric($amount)) {
            return false;
        }

        return in_array((int) round((float) $amount), self::COVERAGE_AMOUNTS, true);
    }

    /**
     * Medicamento que el analista puede gestionar como no cubierto: ni cubierto,
     * ni en cortesía, ni ya en gestión o finalizado.
     */
    public static function isPendingNonCovered(TelemedicinePatientMedications $medication): bool
    {
        return ! TelemedicineMedicationCoverage::isCovered($medication)
            && ! CoordinationServiceCourtesy::itemIsCourtesy($medication->courtesy_status ?? null)
            && CoordinationServiceItemsManager::isManagementItemSelectable((string) ($medication->status ?? ''));
    }

    /**
     * Crea (o actualiza mientras siga pendiente) el aviso de una coordinación de
     * medicamentos. Nunca interrumpe el guardado de la consulta: si algo falla, se registra.
     */
    public static function registerForCoordination(OperationCoordinationService $coordination): ?OperationNonCoveredMedicationAlert
    {
        try {
            if (! self::tablesExist()
                || mb_strtoupper(trim((string) $coordination->specific_service)) !== 'MEDICAMENTOS'
                || RetailServiceRegistration::isRetailCoordination($coordination)) {
                return null;
            }

            $medicationIds = $coordination->telemedicinePatientMedications()
                ->with('operationInventory:id,is_covered')
                ->orderBy('id')
                ->get(['id', 'status', 'courtesy_status', 'is_covered', 'operation_inventory_id'])
                ->filter(fn (TelemedicinePatientMedications $medication): bool => self::isPendingNonCovered($medication))
                ->map(fn (TelemedicinePatientMedications $medication): int => (int) $medication->getKey())
                ->values()
                ->all();

            if ($medicationIds === []) {
                return null;
            }

            $patient = TelemedicinePatient::query()->find($coordination->telemedicine_patient_id);

            if ($patient === null) {
                return null;
            }

            $coverage = self::qualifyingCoverage($patient);

            if ($coverage === null) {
                return null;
            }

            $alert = OperationNonCoveredMedicationAlert::query()
                ->firstOrNew(['operation_coordination_service_id' => (int) $coordination->getKey()]);

            if ($alert->exists && $alert->status !== OperationNonCoveredMedicationAlert::STATUS_PENDING) {
                return $alert;
            }

            $alert->fill([
                'telemedicine_case_id' => $coordination->telemedicine_case_id,
                'telemedicine_patient_id' => $patient->getKey(),
                'plan_id' => $coverage['plan_id'],
                'coverage_id' => $coverage['coverage_id'],
                'coverage_amount' => $coverage['amount'],
                'plan_name' => $coverage['plan_name'],
                'patient_name' => self::firstFilled($coordination->patient, $patient->full_name),
                'patient_document' => self::firstFilled($coordination->ci_patient, $patient->nro_identificacion),
                'medication_ids' => $medicationIds,
                'status' => OperationNonCoveredMedicationAlert::STATUS_PENDING,
                'created_by_name' => $alert->exists ? $alert->created_by_name : Auth::user()?->name,
            ])->save();

            return $alert;
        } catch (Throwable $exception) {
            Log::warning('NonCoveredMedicationAlerts: no se pudo registrar el aviso', [
                'operation_coordination_service_id' => $coordination->getKey(),
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Analistas TDG de Operaciones que pueden gestionar coordinaciones.
     */
    public static function userCanReceive(?User $user): bool
    {
        if ($user === null || $user->supplier_id !== null || $user->isProveedorAmd()) {
            return false;
        }

        if (in_array('ATENMEDI', (array) ($user->departament ?? []), true)) {
            return false;
        }

        $isAuthenticatedUser = Auth::id() !== null && (int) Auth::id() === (int) $user->getKey();

        if ($isAuthenticatedUser && ! OperationCoordinationServiceResource::canAccess()) {
            return false;
        }

        return self::tablesExist();
    }

    /**
     * Avisos que el analista debe ver ahora, del más antiguo al más reciente, con
     * solo los medicamentos que siguen pendientes. Solo lee: no escribe nada.
     *
     * @return list<array{
     *     id: int,
     *     coordination_id: int,
     *     patient_name: string,
     *     patient_document: string,
     *     plan_name: string,
     *     coverage_label: string,
     *     reference: string,
     *     created_at: string,
     *     medications: list<array{id: int, name: string, indications: string}>
     * }>
     */
    public static function pendingFor(User $user): array
    {
        if (! self::userCanReceive($user)) {
            return [];
        }

        $alerts = OperationNonCoveredMedicationAlert::query()
            ->where('status', OperationNonCoveredMedicationAlert::STATUS_PENDING)
            ->where('created_at', '>=', now()->subHours(self::WINDOW_HOURS))
            ->whereDoesntHave('dismissals', fn ($dismissals) => $dismissals->where('user_id', $user->getKey()))
            ->with('operationCoordinationService')
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(self::MAX_PENDING)
            ->get();

        if ($alerts->isEmpty()) {
            return [];
        }

        $medications = self::pendingMedicationsByAlert($alerts);

        return $alerts
            ->filter(fn (OperationNonCoveredMedicationAlert $alert): bool => self::isManagedByTdg($alert->operationCoordinationService)
                && ($medications[$alert->getKey()] ?? []) !== [])
            ->map(fn (OperationNonCoveredMedicationAlert $alert): array => [
                'id' => (int) $alert->getKey(),
                'coordination_id' => (int) $alert->operation_coordination_service_id,
                'patient_name' => (string) ($alert->patient_name ?: 'Paciente'),
                'patient_document' => (string) ($alert->patient_document ?: '—'),
                'plan_name' => self::planLabel((string) $alert->plan_name),
                'coverage_label' => self::coverageLabel($alert->coverage_amount),
                'reference' => (string) ($alert->operationCoordinationService?->reference_number ?: '—'),
                'created_at' => $alert->created_at?->format('d/m/Y h:i a') ?? '',
                'medications' => $medications[$alert->getKey()],
            ])
            ->values()
            ->all();
    }

    /**
     * «OK»: cierra el aviso solo para este analista y lo deja en su campanita.
     */
    public static function dismiss(int $alertId, User $user): void
    {
        $alert = OperationNonCoveredMedicationAlert::query()->find($alertId);

        if ($alert === null) {
            return;
        }

        $created = OperationNonCoveredMedicationAlertDismissal::query()->insertOrIgnore([
            'operation_non_covered_medication_alert_id' => $alert->getKey(),
            'user_id' => $user->getKey(),
            'dismissed_at' => now(),
        ]);

        if ($created < 1 || $alert->status !== OperationNonCoveredMedicationAlert::STATUS_PENDING) {
            return;
        }

        Notification::make()
            ->title('Medicamento no cubierto por gestionar')
            ->body(self::notificationBody($alert))
            ->warning()
            ->icon('heroicon-o-exclamation-triangle')
            ->actions([
                Action::make('gestionar')
                    ->label('Ir a gestionar')
                    ->url(self::manageUrl((int) $alert->operation_coordination_service_id, $alert->medicationIds()))
                    ->markAsRead(),
            ])
            ->sendToDatabase($user);
    }

    /**
     * «Ir a gestionar»: toma el aviso para todos y devuelve a dónde ir. Bloquea la
     * fila para que dos analistas no tomen el mismo aviso a la vez.
     *
     * @param  array<int, mixed>  $medicationIds
     * @return array{ok: bool, url: string|null, message: string|null}
     */
    public static function claim(int $alertId, User $user, array $medicationIds): array
    {
        $requested = array_values(array_unique(array_filter(
            array_map(fn (mixed $id): int => is_numeric($id) ? (int) $id : 0, $medicationIds),
            fn (int $id): bool => $id > 0,
        )));

        if ($requested === []) {
            return self::claimFailure('Marque al menos un medicamento para gestionar.');
        }

        return DB::transaction(function () use ($alertId, $user, $requested): array {
            $alert = OperationNonCoveredMedicationAlert::query()->lockForUpdate()->find($alertId);

            if ($alert === null) {
                return self::claimFailure('El aviso ya no existe.');
            }

            if ($alert->status !== OperationNonCoveredMedicationAlert::STATUS_PENDING) {
                return self::claimFailure('Este aviso ya lo tomó '.($alert->managed_by_name ?: 'otro analista').'.');
            }

            if (! self::isManagedByTdg($alert->operationCoordinationService()->first())) {
                return self::claimFailure('Este servicio ya lo gestiona el proveedor asignado.');
            }

            $stillPending = TelemedicinePatientMedications::query()
                ->whereIn('id', array_intersect($requested, $alert->medicationIds()))
                ->where('operation_coordination_service_id', $alert->operation_coordination_service_id)
                ->with('operationInventory:id,is_covered')
                ->get(['id', 'status', 'courtesy_status', 'is_covered', 'operation_inventory_id'])
                ->filter(fn (TelemedicinePatientMedications $medication): bool => self::isPendingNonCovered($medication))
                ->map(fn (TelemedicinePatientMedications $medication): int => (int) $medication->getKey())
                ->sort()
                ->values()
                ->all();

            if ($stillPending === []) {
                return self::claimFailure('Los medicamentos marcados ya se están gestionando o fueron finalizados.');
            }

            $alert->forceFill([
                'status' => OperationNonCoveredMedicationAlert::STATUS_MANAGED,
                'managed_by_id' => $user->getKey(),
                'managed_by_name' => $user->name,
                'managed_at' => now(),
                'managed_medication_ids' => $stillPending,
            ])->save();

            return [
                'ok' => true,
                'url' => self::manageUrl((int) $alert->operation_coordination_service_id, $stillPending),
                'message' => null,
            ];
        });
    }

    /**
     * @param  list<int>  $medicationIds
     */
    public static function manageUrl(int $coordinationId, array $medicationIds): string
    {
        return ManageCoordinationServiceItems::getUrl([
            'record' => $coordinationId,
            self::QUERY_PARAMETER => implode(',', $medicationIds),
        ], panel: 'operations');
    }

    /**
     * Claves del asistente («medication:ID») que llegan en la URL y siguen
     * siendo gestionables en esta coordinación. Lo demás se descarta.
     *
     * @return list<string>
     */
    public static function preselectedKeys(OperationCoordinationService $coordination, mixed $raw): array
    {
        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $keys = collect(explode(',', $raw))
            ->map(fn (string $id): string => trim($id))
            ->filter(fn (string $id): bool => ctype_digit($id) && (int) $id > 0)
            ->map(fn (string $id): string => 'medication:'.(int) $id)
            ->unique()
            ->values();

        return $keys
            ->filter(fn (string $key): bool => CoordinationServiceItemsManager::isManagementItemKeySelectable($coordination, $key))
            ->values()
            ->all();
    }

    public static function coverageLabel(mixed $amount): string
    {
        return 'USD '.number_format((float) $amount, 0, ',', '.');
    }

    public static function flushTableCache(): void
    {
        self::$tablesExist = null;
    }

    /**
     * @param  Collection<int, OperationNonCoveredMedicationAlert>  $alerts
     * @return array<int, list<array{id: int, name: string, indications: string}>>
     */
    private static function pendingMedicationsByAlert(Collection $alerts): array
    {
        $ids = $alerts->flatMap(fn (OperationNonCoveredMedicationAlert $alert): array => $alert->medicationIds())->unique()->values();

        $medications = TelemedicinePatientMedications::query()
            ->whereIn('id', $ids)
            ->with('operationInventory:id,is_covered')
            ->get(['id', 'medicine', 'indications', 'status', 'courtesy_status', 'is_covered', 'operation_inventory_id', 'operation_coordination_service_id'])
            ->keyBy('id');

        $byAlert = [];

        foreach ($alerts as $alert) {
            $byAlert[$alert->getKey()] = collect($alert->medicationIds())
                ->map(fn (int $id): ?TelemedicinePatientMedications => $medications->get($id))
                ->filter(fn (?TelemedicinePatientMedications $medication): bool => $medication !== null
                    && (int) $medication->operation_coordination_service_id === (int) $alert->operation_coordination_service_id
                    && self::isPendingNonCovered($medication))
                ->map(fn (TelemedicinePatientMedications $medication): array => [
                    'id' => (int) $medication->getKey(),
                    'name' => trim((string) ($medication->medicine ?: 'Medicamento sin nombre')),
                    'indications' => trim((string) ($medication->indications ?? '')),
                ])
                ->values()
                ->all();
        }

        return $byAlert;
    }

    /**
     * Los no cubiertos los gestiona TDG salvo que TDG haya asignado el servicio a un proveedor.
     */
    private static function isManagedByTdg(?OperationCoordinationService $coordination): bool
    {
        return $coordination !== null
            && CoordinationServiceAccess::itemOwner($coordination, false) === CoordinationServiceAccess::OWNER_TDG;
    }

    private static function notificationBody(OperationNonCoveredMedicationAlert $alert): string
    {
        $count = count($alert->medicationIds());

        return ($alert->patient_name ?: 'Un paciente')
            .' ('.self::planLabel((string) $alert->plan_name).' · '.self::coverageLabel($alert->coverage_amount).') tiene '
            .($count === 1 ? '1 medicamento no cubierto' : $count.' medicamentos no cubiertos')
            .' recetado'.($count === 1 ? '' : 's').'.';
    }

    private static function planLabel(string $planName): string
    {
        $planName = trim($planName);

        return $planName === '' ? 'Plan Especial' : mb_convert_case(mb_strtolower($planName), MB_CASE_TITLE);
    }

    /**
     * @return array{ok: bool, url: string|null, message: string|null}
     */
    private static function claimFailure(string $message): array
    {
        return ['ok' => false, 'url' => null, 'message' => $message];
    }

    private static function firstFilled(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));

            if ($value !== '' && $value !== '—' && $value !== 'NO ESPECIFICADO') {
                return $value;
            }
        }

        return null;
    }

    private static function tablesExist(): bool
    {
        return self::$tablesExist ??= Schema::hasTable('operation_non_covered_medication_alerts')
            && Schema::hasTable('operation_non_covered_medication_alert_dismissals');
    }
}
