<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Http\Controllers\UtilsController;
use App\Jobs\NotifyAnalystsOfStorefrontIndividualQuoteJob;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Persiste una cotización individual. El detalle se guarda en el acto;
 * el PDF de una sola hoja (estructura dinámica) se arma cuando el cliente
 * lo descarga desde la PWA, no la propuesta económica de 3–4 páginas.
 * El aviso a analistas va en cola para no retrasar la UX.
 */
final class StorefrontQuoteCreator
{
    /**
     * @param  list<array{plan_id: int, age_range_id: int, total_persons: int}>  $entries
     * @return array{quote: IndividualQuote, code: string}
     */
    public static function create(
        Plan $plan,
        array $entries,
        string $fullName,
        string $email,
        string $phone,
        ?User $actingUser = null,
    ): array {
        $fullName = mb_strtoupper(trim($fullName));
        $email = mb_strtolower(trim($email));
        $phone = preg_replace('/\s+/', '', trim($phone)) ?? '';

        self::assertContact($fullName, $email, $phone);
        self::assertEntries($entries, (int) $plan->getKey());

        $catalogPlan = StorefrontCatalog::findActiveBasic((int) $plan->getKey());

        if ($catalogPlan === null) {
            throw ValidationException::withMessages([
                'plan' => ['Este plan no está disponible para cotizar.'],
            ]);
        }

        $agentUser = $actingUser;
        $isAgentSession = StorefrontAuth::isAgent($agentUser);
        $systemUser = $isAgentSession ? $agentUser : self::resolvePublicActingUser();

        if (! $systemUser instanceof User) {
            throw new RuntimeException('No se pudo determinar el usuario del sistema para generar la cotización.');
        }

        $agentLabel = $isAgentSession
            ? (string) ($agentUser?->name ?? 'Agente')
            : 'PWA público';

        try {
            $quote = DB::transaction(function () use ($catalogPlan, $entries, $fullName, $email, $phone, $systemUser, $isAgentSession, $agentUser): IndividualQuote {
                $agent = $isAgentSession ? StorefrontAuth::agent($agentUser) : null;
                $ownerCode = self::resolveOwnerCode($agent);
                $codeAgency = (string) ($agent?->owner_code ?? config('services.chat_individual_quote.default_owner_code', 'TDG-100'));
                $createdBy = $isAgentSession
                    ? mb_strtoupper((string) ($agentUser?->name ?? 'AGENTE'))
                    : (string) config('services.chat_individual_quote.created_by_label', 'PWA PUBLICO');

                if (! $isAgentSession) {
                    $createdBy = 'PWA PUBLICO';
                }

                $record = new IndividualQuote;
                $record->code = self::generateQuoteCode();
                $record->full_name = $fullName;
                $record->email = $email;
                $record->phone = $phone;
                $record->plan = (int) $catalogPlan->getKey();
                $record->status = 'PRE-APROBADA';
                $record->created_by = $createdBy;
                $record->agent_id = $agent?->id;
                $record->code_agency = $codeAgency;
                $record->owner_code = $ownerCode;
                $record->ownerAccountManagers = $agent?->ownerAccountManagers;
                $record->save();

                Auth::onceUsingId($systemUser->id);

                $stored = UtilsController::storeDetailsIndividualQuote(
                    $record,
                    $record->toArray(),
                    $entries,
                    $entries,
                    false,
                );

                if ($stored !== true) {
                    throw new RuntimeException('Error al guardar los detalles de la cotización.');
                }

                return $record->refresh();
            });
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            throw new RuntimeException('No pudimos generar la cotización. Intenta nuevamente.');
        }

        NotifyAnalystsOfStorefrontIndividualQuoteJob::dispatch(
            (string) $quote->code,
            $agentLabel,
        );

        StorefrontQuoteDraft::clear();
        StorefrontQuoteShare::rememberCode((string) $quote->code);

        return [
            'quote' => $quote,
            'code' => (string) $quote->code,
        ];
    }

    public static function generateQuoteCode(): string
    {
        $maxId = IndividualQuote::query()->max('id');

        return 'COT-IND-000'.(((int) $maxId) + 1);
    }

    private static function assertContact(string $fullName, string $email, string $phone): void
    {
        $errors = [];

        if ($fullName === '') {
            $errors['full_name'] = ['Indica el nombre y apellido.'];
        }

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $errors['email'] = ['Indica un correo electrónico válido.'];
        }

        $digits = preg_replace('/\D/', '', $phone) ?? '';

        if (strlen($digits) < 10) {
            $errors['phone'] = ['Indica un teléfono de contacto válido.'];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  list<array{plan_id: int, age_range_id: int, total_persons: int}>  $entries
     */
    private static function assertEntries(array $entries, int $planId): void
    {
        if ($entries === []) {
            throw ValidationException::withMessages([
                'people' => ['Agrega al menos una persona para cotizar.'],
            ]);
        }

        foreach ($entries as $entry) {
            if ((int) ($entry['plan_id'] ?? 0) !== $planId) {
                throw ValidationException::withMessages([
                    'people' => ['Los datos de la cotización no coinciden con este plan.'],
                ]);
            }

            if ((int) ($entry['age_range_id'] ?? 0) <= 0 || (int) ($entry['total_persons'] ?? 0) < 1) {
                throw ValidationException::withMessages([
                    'people' => ['Revisa las edades y la cantidad de personas.'],
                ]);
            }
        }
    }

    private static function resolvePublicActingUser(): ?User
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

            if ($user instanceof User) {
                return $user;
            }
        }

        $fallback = User::query()
            ->where('status', 'ACTIVO')
            ->orderBy('id')
            ->first();

        return $fallback instanceof User ? $fallback : null;
    }

    private static function resolveOwnerCode(?Agent $agent): string
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
}
