<?php

declare(strict_types=1);

namespace App\Services\PublicAiAgent;

use App\Http\Controllers\NotificationController;
use App\Http\Controllers\UtilsController;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\AgeRange;
use App\Models\IndividualQuote;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ChatIndividualQuoteService
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array{
     *   success: bool,
     *   message: string,
     *   data?: array<string, mixed>,
     *   errors?: array<string, list<string>>
     * }
     */
    public function register(array $payload): array
    {
        try {
            $validated = $this->validatePayload($payload);
        } catch (ValidationException $exception) {
            return [
                'success' => false,
                'message' => $this->firstValidationMessage($exception),
                'errors' => $exception->errors(),
            ];
        }

        $actingUser = $this->resolveActingUser();

        if ($actingUser === null) {
            return [
                'success' => false,
                'message' => 'No se pudo determinar el usuario del sistema para generar la cotización.',
            ];
        }

        try {
            $quote = DB::transaction(function () use ($validated, $actingUser): IndividualQuote {
                $planField = $this->determinePlanField($validated['entries']);
                $agent = $this->resolveAgentForUser($actingUser);
                $ownerCode = $this->resolveOwnerCode($agent);
                $codeAgency = (string) ($agent?->owner_code ?? config('services.chat_individual_quote.default_owner_code', 'TDG-100'));

                $record = new IndividualQuote;
                $record->code = $this->generateQuoteCode();
                $record->full_name = mb_strtoupper((string) $validated['full_name']);
                $record->email = (string) config('services.chat_individual_quote.placeholder_email', 'cotizacion-chat@integracorp.local');
                $record->phone = (string) config('services.chat_individual_quote.placeholder_phone', '00000000000');
                $record->plan = $planField;
                $record->status = 'PRE-APROBADA';
                $record->created_by = mb_strtoupper((string) $validated['agent_name']);
                $record->agent_id = $agent?->id;
                $record->code_agency = $codeAgency;
                $record->owner_code = $ownerCode;
                $record->ownerAccountManagers = $agent?->ownerAccountManagers;
                $record->save();

                $detailsQuote = collect($validated['entries'])
                    ->map(fn (array $entry): array => [
                        'plan_id' => (int) $entry['plan_id'],
                        'age_range_id' => (int) $entry['age_range_id'],
                        'total_persons' => (int) $entry['total_persons'],
                    ])
                    ->values()
                    ->all();

                session()->put('details_quote', $detailsQuote);

                Auth::onceUsingId($actingUser->id);

                $arrayForm = $record->toArray();
                $stored = UtilsController::storeDetailsIndividualQuote(
                    $record,
                    $arrayForm,
                    $detailsQuote,
                    $detailsQuote,
                );

                if ($stored !== true) {
                    throw new \RuntimeException('Error al guardar los detalles de la cotización.');
                }

                NotificationController::createdIndividualQuote($record->code, (string) $validated['agent_name']);

                return $record->refresh();
            });
        } catch (\Throwable $exception) {
            report($exception);

            return [
                'success' => false,
                'message' => 'No pudimos generar la cotización. Intenta nuevamente o contacta al equipo de negocios.',
            ];
        }

        return [
            'success' => true,
            'message' => 'Cotización individual generada exitosamente.',
            'data' => [
                'individual_quote_id' => (int) $quote->id,
                'code' => (string) $quote->code,
                'plan' => $quote->plan,
                'full_name' => (string) $quote->full_name,
                'email' => (string) $quote->email,
            ],
        ];
    }

    public function resolveAgeRangeId(int $planId, int $age): ?int
    {
        $ageRange = $this->resolveAgeRangeForPlanAndAge($planId, $age);

        return $ageRange?->id;
    }

    public function resolveAgeRangeForPlanAndAge(int $planId, int $age): ?AgeRange
    {
        if ($age < 0 || $age > 120) {
            return null;
        }

        return AgeRange::query()
            ->where('plan_id', $planId)
            ->whereNotNull('age_init')
            ->whereNotNull('age_end')
            ->where('age_init', '<=', $age)
            ->where('age_end', '>=', $age)
            ->orderByRaw('(age_end - age_init) ASC')
            ->orderBy('id')
            ->get()
            ->unique(fn (AgeRange $range): string => sprintf(
                '%d-%d',
                (int) $range->age_init,
                (int) $range->age_end,
            ))
            ->first();
    }

    public function resolveSingleAgeRangeIdForPlan(int $planId): ?int
    {
        $uniqueRanges = AgeRange::query()
            ->where('plan_id', $planId)
            ->whereNotNull('age_init')
            ->whereNotNull('age_end')
            ->orderBy('id')
            ->get()
            ->unique(fn (AgeRange $range): string => sprintf(
                '%d-%d',
                (int) $range->age_init,
                (int) $range->age_end,
            ));

        if ($uniqueRanges->count() === 1) {
            return (int) $uniqueRanges->first()->id;
        }

        return null;
    }

    public function planLabel(int $planId): string
    {
        return match ($planId) {
            1 => 'Plan Inicial',
            2 => 'Plan Ideal',
            3 => 'Plan Especial',
            default => "Plan {$planId}",
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function validatePayload(array $payload): array
    {
        $validator = Validator::make($payload, [
            'full_name' => ['required', 'string', 'max:255'],
            'agent_name' => ['required', 'string', 'max:255'],
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.plan_id' => ['required', 'integer', Rule::in([1, 2, 3])],
            'entries.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'entries.*.age_range_id' => ['required', 'integer', 'exists:age_ranges,id'],
            'entries.*.total_persons' => ['required', 'integer', 'min:1', 'max:999'],
        ], [
            'full_name.required' => 'El nombre y apellido del solicitante es obligatorio.',
            'agent_name.required' => 'El nombre del agente es obligatorio.',
            'entries.required' => 'Debes indicar al menos un plan a cotizar.',
            'entries.*.plan_id.in' => 'El plan debe ser 1 (Inicial), 2 (Ideal) o 3 (Especial).',
            'entries.*.age_range_id.exists' => 'El rango de edad indicado no es válido.',
            'entries.*.total_persons.min' => 'El número de personas debe ser al menos 1.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        /** @var array<string, mixed> $validated */
        $validated = $validator->validated();

        $validated['entries'] = $this->normalizeQuoteEntries($validated['entries']);

        return $validated;
    }

    /**
     * @param  list<array<string, mixed>>  $entries
     * @return list<array{plan_id: int, age_range_id: int, total_persons: int, age?: int|null}>
     */
    private function normalizeQuoteEntries(array $entries): array
    {
        return collect($entries)->map(function (array $entry): array {
            $planId = (int) $entry['plan_id'];
            $age = array_key_exists('age', $entry) && $entry['age'] !== null
                ? (int) $entry['age']
                : null;

            if ($age !== null) {
                $ageRange = $this->resolveAgeRangeForPlanAndAge($planId, $age);

                if ($ageRange === null) {
                    throw ValidationException::withMessages([
                        'entries' => [
                            sprintf(
                                'No hay un rango de edad en %s para %d años.',
                                $this->planLabel($planId),
                                $age,
                            ),
                        ],
                    ]);
                }
            } else {
                $ageRange = AgeRange::query()->find((int) $entry['age_range_id']);
            }

            if (! $ageRange instanceof AgeRange) {
                throw ValidationException::withMessages([
                    'entries' => ['El rango de edad indicado no es válido.'],
                ]);
            }

            if ((int) $ageRange->plan_id !== $planId) {
                throw ValidationException::withMessages([
                    'entries' => [
                        sprintf(
                            'El rango de edad %s no pertenece al %s. Verifica el ID del plan (1=Inicial, 2=Ideal, 3=Especial).',
                            (string) $ageRange->range,
                            $this->planLabel($planId),
                        ),
                    ],
                ]);
            }

            if ($age !== null) {
                $ageInit = (int) $ageRange->age_init;
                $ageEnd = (int) $ageRange->age_end;

                if ($age < $ageInit || $age > $ageEnd) {
                    throw ValidationException::withMessages([
                        'entries' => [
                            sprintf(
                                'La edad %d no corresponde al rango %s del %s.',
                                $age,
                                (string) $ageRange->range,
                                $this->planLabel($planId),
                            ),
                        ],
                    ]);
                }
            }

            $normalized = [
                'plan_id' => $planId,
                'age_range_id' => (int) $ageRange->id,
                'total_persons' => (int) $entry['total_persons'],
            ];

            if ($age !== null) {
                $normalized['age'] = $age;
            }

            return $normalized;
        })->values()->all();
    }

    /**
     * @param  list<array{plan_id: int, age_range_id: int, total_persons: int}>  $entries
     */
    private function determinePlanField(array $entries): int|string
    {
        $planIds = collect($entries)
            ->pluck('plan_id')
            ->unique()
            ->values();

        if ($planIds->count() === 1) {
            return (int) $planIds->first();
        }

        return 'CM';
    }

    private function generateQuoteCode(): string
    {
        $maxId = IndividualQuote::query()->max('id');

        return 'COT-IND-000'.(((int) $maxId) + 1);
    }

    private function resolveActingUser(): ?User
    {
        $ownerCode = (string) config('services.chat_individual_quote.default_owner_code', 'TDG-100');

        $agent = Agent::query()
            ->where('owner_code', $ownerCode)
            ->orderBy('id')
            ->first();

        if ($agent !== null) {
            $user = User::query()
                ->where('agent_id', $agent->id)
                ->where('status', 'ACTIVO')
                ->orderBy('id')
                ->first();

            if ($user !== null) {
                return $user;
            }
        }

        return User::query()
            ->where('status', 'ACTIVO')
            ->orderBy('id')
            ->first();
    }

    private function resolveAgentForUser(User $user): ?Agent
    {
        if ($user->agent_id === null) {
            return null;
        }

        return Agent::query()->find($user->agent_id);
    }

    private function resolveOwnerCode(?Agent $agent): string
    {
        $owner = (string) ($agent?->owner_code ?? config('services.chat_individual_quote.default_owner_code', 'TDG-100'));

        if ($owner === 'TDG-100') {
            return $owner;
        }

        $agency = Agency::query()
            ->select('code', 'owner_code')
            ->where('code', $owner)
            ->first();

        if ($agency === null) {
            return $owner;
        }

        $jerarquia = (string) $agency->owner_code;

        if ($owner !== $jerarquia && $jerarquia !== 'TDG-100') {
            return $jerarquia;
        }

        if ($owner !== $jerarquia && $jerarquia === 'TDG-100') {
            return $owner;
        }

        return $owner;
    }

    private function firstValidationMessage(ValidationException $exception): string
    {
        $message = collect($exception->errors())->flatten()->filter()->first();

        return is_string($message) && $message !== ''
            ? $message
            : 'Los datos enviados no son válidos.';
    }
}
