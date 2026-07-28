<?php

declare(strict_types=1);

namespace App\Support\IndividualQuotes;

use App\Models\Agency;
use App\Models\Agent;
use App\Models\IndividualQuote;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class IndividualQuoteFollowUp
{
    public const ELIGIBLE_STATUS = 'PRE-APROBADA';

    /**
     * @return Collection<int, Collection<int, IndividualQuote>>
     */
    public static function groupedQuotesForDate(int $followUpDays, ?Carbon $referenceDate = null): Collection
    {
        $targetDate = ($referenceDate ?? now())
            ->timezone((string) config('app.timezone'))
            ->subDays($followUpDays)
            ->toDateString();

        return IndividualQuote::query()
            ->with(['agent:id,name,phone,email', 'agency:code,name_corporative,phone,email'])
            ->where('status', self::ELIGIBLE_STATUS)
            ->whereDate('created_at', $targetDate)
            ->when(
                self::isRestrictedToCollaborators(),
                fn (Builder $query): Builder => self::constrainToCollaboratorEmails($query),
            )
            ->orderBy('code')
            ->get()
            ->groupBy(fn (IndividualQuote $quote): string => self::groupKey($quote));
    }

    /**
     * Filtro temporal de pruebas: solo cotizaciones cuyo email coincide
     * con un correo de rrhh_colaboradors (corporativo, alternativo o personal).
     */
    public static function isRestrictedToCollaborators(): bool
    {
        return (bool) config('individual-quotes.follow_up_only_collaborators');
    }

    /**
     * @param  Builder<IndividualQuote>  $query
     * @return Builder<IndividualQuote>
     */
    public static function constrainToCollaboratorEmails(Builder $query): Builder
    {
        return $query
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->whereExists(function ($subQuery): void {
                $subQuery->selectRaw('1')
                    ->from('rrhh_colaboradors')
                    ->where(function ($emailQuery): void {
                        $emailQuery
                            ->whereRaw('LOWER(TRIM(rrhh_colaboradors.emailCorporativo)) = LOWER(TRIM(individual_quotes.email))')
                            ->orWhereRaw('LOWER(TRIM(rrhh_colaboradors.emailAlternativo)) = LOWER(TRIM(individual_quotes.email))')
                            ->orWhereRaw('LOWER(TRIM(rrhh_colaboradors.emailPersonal)) = LOWER(TRIM(individual_quotes.email))');
                    });
            });
    }

    public static function groupKey(IndividualQuote $quote): string
    {
        if (filled($quote->agent_id)) {
            return 'agent:'.$quote->agent_id;
        }

        return 'agency:'.(string) ($quote->code_agency ?? 'sin-agencia');
    }

    public static function resolveAllyName(Collection $quotes): string
    {
        /** @var IndividualQuote $first */
        $first = $quotes->first();

        if ($first->agent?->name) {
            return (string) $first->agent->name;
        }

        if ($first->relationLoaded('agency') && filled($first->agency?->name_corporative)) {
            return (string) $first->agency->name_corporative;
        }

        if (filled($first->code_agency)) {
            $agencyName = Agency::query()
                ->where('code', $first->code_agency)
                ->value('name_corporative');

            if (filled($agencyName)) {
                return (string) $agencyName;
            }
        }

        return 'Aliado comercial';
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     */
    public static function formatClientNames(Collection $quotes): string
    {
        return $quotes
            ->pluck('full_name')
            ->filter(fn (mixed $name): bool => filled($name))
            ->unique()
            ->values()
            ->map(fn (string $name): string => '*'.$name.'*')
            ->implode(', ');
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     */
    public static function formatQuoteCodes(Collection $quotes): string
    {
        $codes = $quotes
            ->pluck('code')
            ->filter(fn (mixed $code): bool => filled($code))
            ->values();

        if ($codes->count() === 1) {
            return (string) $codes->first();
        }

        $suffixes = $codes
            ->map(fn (string $code): string => self::quoteNumericSuffix($code))
            ->implode('/');

        return 'COT-IND-: '.$suffixes;
    }

    public static function quoteNumericSuffix(string $code): string
    {
        if (str_starts_with($code, 'COT-IND-')) {
            return substr($code, strlen('COT-IND-'));
        }

        return $code;
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     */
    public static function trackingFooter(Collection $quotes, string $trackingNote): string
    {
        $quoteCount = $quotes->count();
        $codesLine = self::formatQuoteCodes($quotes);
        $createdDate = self::formatCreatedDate($quotes);

        return <<<TEXT
        ──────────────
        *El sistema automatizado*
        {$trackingNote}

        Total de cotizaciones: *{$quoteCount}*
        Código(s): *{$codesLine}*
        Fecha de creación: *{$createdDate}*
        TEXT;
    }

    public static function publicAssetUrl(string $relativePath): string
    {
        return rtrim((string) config('parameters.PUBLIC_URL'), '/').'/'.ltrim($relativePath, '/');
    }

    /**
     * Ruta absoluta en disco para un asset público de seguimiento.
     */
    public static function localPublicAssetPath(string $relativePath): ?string
    {
        $relativePath = ltrim($relativePath, '/');

        $candidates = [
            storage_path('app/public/'.$relativePath),
            public_path('storage/'.$relativePath),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Teléfono WhatsApp del aliado que creó las cotizaciones del grupo:
     * - con agent_id → agents.phone
     * - sin agent_id → agencies.phone vía code_agency
     *
     * @param  Collection<int, IndividualQuote>  $quotes
     * @return list<string>
     */
    public static function resolveRecipientPhones(Collection $quotes): array
    {
        /** @var IndividualQuote|null $first */
        $first = $quotes->first();

        if ($first === null) {
            return [];
        }

        $phone = filled($first->agent_id)
            ? self::resolveAgentPhone($first)
            : self::resolveAgencyPhone($first);

        if ($phone === null) {
            return [];
        }

        return [$phone];
    }

    /**
     * Correo del aliado que creó las cotizaciones del grupo:
     * - con agent_id → agents.email
     * - sin agent_id → agencies.email vía code_agency
     *
     * @param  Collection<int, IndividualQuote>  $quotes
     * @return list<string>
     */
    public static function resolveRecipientEmails(Collection $quotes): array
    {
        /** @var IndividualQuote|null $first */
        $first = $quotes->first();

        if ($first === null) {
            return [];
        }

        $email = filled($first->agent_id)
            ? self::resolveAgentEmail($first)
            : self::resolveAgencyEmail($first);

        if ($email === null) {
            return [];
        }

        return [$email];
    }

    private static function resolveAgentPhone(IndividualQuote $quote): ?string
    {
        if ($quote->relationLoaded('agent')) {
            return self::normalizePhone($quote->agent?->phone);
        }

        if (! filled($quote->agent_id)) {
            return null;
        }

        return self::normalizePhone(
            Agent::query()->whereKey($quote->agent_id)->value('phone')
        );
    }

    private static function resolveAgencyPhone(IndividualQuote $quote): ?string
    {
        if ($quote->relationLoaded('agency')) {
            return self::normalizePhone($quote->agency?->phone);
        }

        if (! filled($quote->code_agency)) {
            return null;
        }

        return self::normalizePhone(
            Agency::query()->where('code', $quote->code_agency)->value('phone')
        );
    }

    private static function resolveAgentEmail(IndividualQuote $quote): ?string
    {
        if ($quote->relationLoaded('agent')) {
            return self::normalizeEmail($quote->agent?->email);
        }

        if (! filled($quote->agent_id)) {
            return null;
        }

        return self::normalizeEmail(
            Agent::query()->whereKey($quote->agent_id)->value('email')
        );
    }

    private static function resolveAgencyEmail(IndividualQuote $quote): ?string
    {
        if ($quote->relationLoaded('agency')) {
            return self::normalizeEmail($quote->agency?->email);
        }

        if (! filled($quote->code_agency)) {
            return null;
        }

        return self::normalizeEmail(
            Agency::query()->where('code', $quote->code_agency)->value('email')
        );
    }

    private static function normalizePhone(mixed $phone): ?string
    {
        $normalized = trim((string) $phone);

        return $normalized !== '' ? $normalized : null;
    }

    private static function normalizeEmail(mixed $email): ?string
    {
        $normalized = trim((string) $email);

        if ($normalized === '' || ! filter_var($normalized, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return $normalized;
    }

    public static function schedulingStartDate(): Carbon
    {
        return Carbon::parse(
            (string) config('individual-quotes.follow_up_scheduling_start_date'),
            (string) config('app.timezone'),
        )->startOfDay();
    }

    public static function isSchedulingActive(): bool
    {
        return now()
            ->timezone((string) config('app.timezone'))
            ->startOfDay()
            ->greaterThanOrEqualTo(self::schedulingStartDate());
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     */
    private static function formatCreatedDate(Collection $quotes): string
    {
        /** @var IndividualQuote $first */
        $first = $quotes->first();

        return $first->created_at
            ?->timezone((string) config('app.timezone'))
            ->format('d/m/Y') ?? '—';
    }
}
