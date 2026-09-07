<?php

declare(strict_types=1);

namespace App\Support\Storefront;

use App\Models\IndividualQuote;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Cotizaciones que el usuario autenticado generó desde la PWA.
 *
 * @phpstan-type QuoteCard array{
 *     id: int,
 *     code: string,
 *     client: string,
 *     phone: string,
 *     email: string,
 *     plan: string,
 *     status: string,
 *     status_tone: string,
 *     persons: int,
 *     persons_label: string,
 *     has_amount: bool,
 *     amount: float,
 *     amount_label: string,
 *     amount_prefix: string,
 *     amount_period: string,
 *     when: string,
 *     when_full: string,
 *     url: string,
 *     pdf_url: string,
 *     pay_url: string,
 *     has_receipt: bool
 * }
 */
final class StorefrontQuotesIndex
{
    public const PER_PAGE = 8;

    /**
     * @return Builder<IndividualQuote>
     */
    public static function queryFor(User $user, string $search = '', string $status = 'all'): Builder
    {
        $query = IndividualQuote::query()
            ->where('storefront_user_id', (int) $user->id)
            ->with(['detailsQuote'])
            ->withCount('paymentReceipts')
            ->latest('id');

        $status = self::normalizeStatus($status);

        if ($status === 'ready') {
            $query->whereIn('status', ['PRE-APROBADA', 'APROBADA']);
        }

        if ($status === 'closed') {
            $query->whereIn('status', ['ANULADA', 'VENCIDA']);
        }

        $needle = self::normalizeSearch($search);

        if ($needle === '') {
            return $query;
        }

        $digits = preg_replace('/\D+/', '', $needle) ?: '';

        return $query->where(function (Builder $inner) use ($needle, $digits): void {
            $like = '%'.$needle.'%';
            $inner->where('code', 'like', $like)
                ->orWhere('full_name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('phone', 'like', $like)
                ->orWhere('status', 'like', $like);

            if ($digits !== '') {
                $inner->orWhere('phone', 'like', '%'.$digits.'%')
                    ->orWhere('code', 'like', '%'.$digits.'%');
            }
        });
    }

    /**
     * @return LengthAwarePaginator<int, QuoteCard>
     */
    public static function paginate(User $user, string $search = '', int $page = 1, string $status = 'all'): LengthAwarePaginator
    {
        $page = max(1, $page);

        /** @var LengthAwarePaginator<int, IndividualQuote> $paginator */
        $paginator = self::queryFor($user, $search, $status)
            ->paginate(self::PER_PAGE, ['*'], 'page', $page);

        $plans = self::plansFor($paginator->getCollection());

        return $paginator->through(
            static fn (IndividualQuote $quote): array => self::card($quote, $plans)
        );
    }

    /**
     * @param  Collection<int, Plan>  $plans
     * @return QuoteCard
     */
    public static function card(IndividualQuote $quote, Collection $plans): array
    {
        $persons = self::personsFromDetails($quote->detailsQuote);
        $status = strtoupper(trim((string) $quote->status));
        $createdAt = $quote->created_at;
        $plan = $plans->get((int) $quote->plan);
        $planModel = $plan instanceof Plan ? $plan : null;
        $payment = self::paymentFromDetails($quote->detailsQuote, $planModel);
        $entryRoute = StorefrontQuoteCoverages::needsSelection($planModel, $quote->detailsQuote)
            ? 'storefront.quote.coverages'
            : 'storefront.quote.frequency';

        return [
            'id' => (int) $quote->id,
            'code' => (string) $quote->code,
            'client' => StorefrontPlanNarrative::personName((string) $quote->full_name),
            'phone' => StorefrontPlanNarrative::phoneLabel((string) $quote->phone),
            'email' => (string) $quote->email,
            'plan' => self::planTitle($planModel),
            'status' => self::statusLabel($status),
            'status_tone' => self::statusTone($status),
            'persons' => $persons,
            'persons_label' => self::personsLabel($persons),
            'has_amount' => $payment['has_amount'],
            'amount' => $payment['amount'],
            'amount_label' => $payment['amount_label'],
            'amount_prefix' => $payment['amount_prefix'],
            'amount_period' => $payment['amount_period'],
            'when' => $createdAt?->timezone((string) config('app.timezone'))->diffForHumans() ?? '',
            'when_full' => $createdAt?->timezone((string) config('app.timezone'))->format('d/m/Y · H:i') ?? '',
            'url' => route('storefront.quote.proposal', ['code' => $quote->code]),
            'pdf_url' => route('storefront.quote.pdf', ['code' => $quote->code]),
            'pay_url' => route($entryRoute, ['code' => $quote->code]),
            'has_receipt' => (int) ($quote->payment_receipts_count ?? $quote->paymentReceipts->count()) > 0,
        ];
    }

    /**
     * Personas cotizadas por rango de edad. En planes con coberturas cada
     * rango se repite por cobertura: no se suman esas copias.
     *
     * @param  Collection<int, mixed>  $details
     */
    public static function personsFromDetails(Collection $details): int
    {
        $lines = $details->filter(static fn (mixed $line): bool => is_object($line));

        $byRange = (int) $lines
            ->filter(static fn (mixed $line): bool => (int) ($line->age_range_id ?? 0) > 0)
            ->groupBy(static fn (mixed $line): int => (int) $line->age_range_id)
            ->sum(static fn (Collection $group): int => (int) ($group->first()->total_persons ?? 0));

        $withoutRange = (int) $lines
            ->filter(static fn (mixed $line): bool => (int) ($line->age_range_id ?? 0) <= 0)
            ->sum(static fn (mixed $line): int => (int) ($line->total_persons ?? 0));

        return $byRange + $withoutRange;
    }

    public static function personsLabel(int $persons): string
    {
        if ($persons === 1) {
            return '1 persona';
        }

        if ($persons < 1) {
            return '';
        }

        return $persons.' personas';
    }

    /**
     * Monto anual que el titular debe pagar, según las líneas ya cotizadas.
     * En planes con coberturas se muestra el menor total («Desde»), no la suma.
     *
     * @param  Collection<int, mixed>  $details
     * @param  list<int>|null  $coverageIds
     * @return array{has_amount: bool, amount: float, amount_label: string, amount_prefix: string, amount_period: string, frequency: string, frequency_label: string, installments: int, annual_total: float, annual_label: string, breakdown: string}
     */
    public static function paymentFromDetails(
        Collection $details,
        ?Plan $plan,
        string $frequency = StorefrontQuoteFrequency::Annual,
        ?array $coverageIds = null,
    ): array {
        return StorefrontQuoteFrequency::paymentFromDetails($details, $plan, $frequency, $coverageIds);
    }

    public static function statusLabel(string $status): string
    {
        return match ($status) {
            'PRE-APROBADA' => 'Lista',
            'APROBADA' => 'Aprobada',
            'ANULADA' => 'Anulada',
            'VENCIDA' => 'Vencida',
            default => $status !== '' ? ucfirst(mb_strtolower($status)) : 'En proceso',
        };
    }

    public static function statusTone(string $status): string
    {
        return match ($status) {
            'PRE-APROBADA', 'APROBADA' => 'ok',
            'ANULADA', 'VENCIDA' => 'warn',
            default => 'mute',
        };
    }

    public static function normalizeSearch(string $raw): string
    {
        return trim(preg_replace('/\s+/', ' ', $raw) ?? '');
    }

    public static function normalizeStatus(string $raw): string
    {
        $value = strtolower(trim($raw));

        return in_array($value, ['all', 'ready', 'closed'], true) ? $value : 'all';
    }

    private static function planTitle(?Plan $plan): string
    {
        if (! $plan instanceof Plan) {
            return 'Plan';
        }

        return StorefrontPlanNarrative::planLabel(
            (string) StorefrontPlanNarrative::for($plan)['title']
        );
    }

    /**
     * @param  Collection<int, IndividualQuote>  $quotes
     * @return Collection<int, Plan>
     */
    private static function plansFor(Collection $quotes): Collection
    {
        $ids = $quotes
            ->map(static fn (IndividualQuote $quote): int => (int) $quote->plan)
            ->filter(static fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        return Plan::query()
            ->whereIn('id', $ids->all())
            ->get()
            ->keyBy('id');
    }
}
