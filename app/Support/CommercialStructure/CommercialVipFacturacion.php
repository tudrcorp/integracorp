<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use App\Models\Agency;
use App\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Nivel VIP comercial según facturación acumulada en USD (tabla sales.total_amount).
 *
 * Rangos acordados con Negocios (2026): de 1 a 5 estrellas según volumen facturado.
 * Línea directa: agente/agencia con grupos corporativos que están facturando.
 */
final class CommercialVipFacturacion
{
    public const float MIN_ONE_STAR = 1_000.0;

    public const float MIN_TWO_STARS = 10_000.0;

    public const float MIN_THREE_STARS = 100_000.0;

    public const float MIN_FOUR_STARS = 500_000.0;

    public const float MIN_FIVE_STARS = 1_000_000.0;

    public const string BILLING_ATTRIBUTE = 'vip_facturacion_usd';

    public const string LINEA_DIRECTA_ATTRIBUTE = 'vip_linea_directa';

    public static function starCountFromAmount(float $amount): int
    {
        if ($amount < self::MIN_ONE_STAR) {
            return 0;
        }

        if ($amount < self::MIN_TWO_STARS) {
            return 1;
        }

        if ($amount < self::MIN_THREE_STARS) {
            return 2;
        }

        if ($amount < self::MIN_FOUR_STARS) {
            return 3;
        }

        if ($amount < self::MIN_FIVE_STARS) {
            return 4;
        }

        return 5;
    }

    public static function tierLabel(int $stars): string
    {
        return match ($stars) {
            0 => 'Sin nivel VIP',
            1 => 'VIP · 1 estrella',
            2 => 'VIP · 2 estrellas',
            3 => 'VIP · 3 estrellas',
            4 => 'VIP · 4 estrellas',
            5 => 'VIP · 5 estrellas',
            default => 'Sin nivel VIP',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function starFilterOptions(): array
    {
        return [
            '1' => '★ · US$ 1.000 a 10.000',
            '2' => '★★ · US$ 10.000 a 100.000',
            '3' => '★★★ · US$ 100.000 a 500.000',
            '4' => '★★★★ · US$ 500.000 a 1.000.000',
            '5' => '★★★★★ · Más de US$ 1.000.000',
        ];
    }

    public static function starsGlyphLine(int $stars): string
    {
        if ($stars <= 0) {
            return '';
        }

        $stars = min(5, max(1, $stars));

        return str_repeat('★', $stars);
    }

    public static function billingTooltip(float $amount, int $stars): string
    {
        $formatted = 'US$ '.number_format($amount, 2, ',', '.');

        return self::tierLabel($stars).' · Facturación acumulada: '.$formatted;
    }

    public static function nameWithVipStarsHtml(string $name, float $billingUsd, bool $lineaDirecta = false): HtmlString
    {
        $safeName = e($name !== '' ? $name : '—');
        $stars = self::starCountFromAmount($billingUsd);
        $starsLine = self::starsGlyphLine($stars);
        $title = e(self::billingTooltip($billingUsd, $stars));

        $above = '';
        if ($starsLine !== '') {
            $above .= '<span class="text-amber-500 dark:text-amber-400 text-xs leading-none tracking-tight" title="'.$title.'">'.$starsLine.'</span>';
        }
        if ($lineaDirecta) {
            $above .= '<span class="inline-flex items-center rounded-full bg-amber-500 px-1.5 py-px text-[0.6rem] font-bold uppercase tracking-wide text-white">Línea directa</span>';
        }

        if ($above === '') {
            return new HtmlString('<span class="font-medium">'.$safeName.'</span>');
        }

        return new HtmlString(
            '<div class="flex flex-col items-start gap-0.5">'
            .'<span class="inline-flex flex-wrap items-center gap-1">'.$above.'</span>'
            .'<span class="font-medium">'.$safeName.'</span>'
            .'</div>'
        );
    }

    public static function pageTitleVipRowHtml(float $billingUsd, bool $lineaDirecta): string
    {
        $stars = self::starCountFromAmount($billingUsd);
        $starsLine = self::starsGlyphLine($stars);
        $parts = [];

        if ($starsLine !== '') {
            $title = e(self::billingTooltip($billingUsd, $stars));
            $parts[] = '<span style="color:#f59e0b;font-size:1.15rem;letter-spacing:0.12em;line-height:1;" title="'.$title.'">'.$starsLine.'</span>';
        }

        if ($lineaDirecta) {
            $parts[] = '<span style="background-color:#d97706;color:#fff;padding:3px 10px;border-radius:999px;font-size:.68rem;font-weight:700;letter-spacing:.06em;text-transform:uppercase;">Línea directa</span>';
        }

        if ($parts === []) {
            return '';
        }

        return '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">'.implode('', $parts).'</div>';
    }

    public static function vipBadgeColor(int $stars): string
    {
        return match ($stars) {
            0 => 'gray',
            1, 2 => 'warning',
            3 => 'success',
            default => 'amber',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function recordRowClasses(object $record): array
    {
        if (self::lineaDirectaFromRecord($record)) {
            return ['bg-amber-50/80 dark:bg-amber-950/25 border-l-4 border-amber-500'];
        }

        $stars = self::starCountFromAmount(self::billingAmountFromRecord($record));
        if ($stars >= 3) {
            return ['border-l-4 border-amber-300/90'];
        }

        return [];
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function appendAgentBillingSubquery(Builder $query): Builder
    {
        $billing = DB::table('sales')
            ->select([
                'agent_id',
                DB::raw('COALESCE(SUM(total_amount), 0) as '.self::BILLING_ATTRIBUTE),
            ])
            ->whereNotNull('agent_id')
            ->groupBy('agent_id');

        $groups = DB::table('affiliation_corporates')
            ->select('agent_id')
            ->whereNotNull('agent_id')
            ->groupBy('agent_id');

        $corporateSales = DB::table('sales')
            ->select('agent_id')
            ->whereNotNull('agent_id')
            ->where('total_amount', '>', 0)
            ->where('type', 'like', '%CORPORATIV%')
            ->groupBy('agent_id');

        return $query
            ->leftJoinSub($billing, 'vip_billing_agent', fn ($join) => $join->on('vip_billing_agent.agent_id', '=', 'agents.id'))
            ->leftJoinSub($groups, 'vip_corp_groups_agent', fn ($join) => $join->on('vip_corp_groups_agent.agent_id', '=', 'agents.id'))
            ->leftJoinSub($corporateSales, 'vip_corp_sales_agent', fn ($join) => $join->on('vip_corp_sales_agent.agent_id', '=', 'agents.id'))
            ->addSelect(DB::raw('COALESCE(vip_billing_agent.'.self::BILLING_ATTRIBUTE.', 0) as '.self::BILLING_ATTRIBUTE))
            ->addSelect(DB::raw('CASE WHEN vip_corp_groups_agent.agent_id IS NOT NULL AND vip_corp_sales_agent.agent_id IS NOT NULL THEN 1 ELSE 0 END as '.self::LINEA_DIRECTA_ATTRIBUTE));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function appendAgencyBillingSubquery(Builder $query): Builder
    {
        $billing = DB::table('sales')
            ->select([
                'code_agency',
                DB::raw('COALESCE(SUM(total_amount), 0) as '.self::BILLING_ATTRIBUTE),
            ])
            ->whereNotNull('code_agency')
            ->where('code_agency', '!=', '')
            ->groupBy('code_agency');

        $groups = DB::table('affiliation_corporates')
            ->select('code_agency')
            ->whereNotNull('code_agency')
            ->where('code_agency', '!=', '')
            ->groupBy('code_agency');

        $corporateSales = DB::table('sales')
            ->select('code_agency')
            ->whereNotNull('code_agency')
            ->where('code_agency', '!=', '')
            ->where('total_amount', '>', 0)
            ->where('type', 'like', '%CORPORATIV%')
            ->groupBy('code_agency');

        return $query
            ->leftJoinSub($billing, 'vip_billing_agency', fn ($join) => $join->on('vip_billing_agency.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($groups, 'vip_corp_groups_agency', fn ($join) => $join->on('vip_corp_groups_agency.code_agency', '=', 'agencies.code'))
            ->leftJoinSub($corporateSales, 'vip_corp_sales_agency', fn ($join) => $join->on('vip_corp_sales_agency.code_agency', '=', 'agencies.code'))
            ->addSelect(DB::raw('COALESCE(vip_billing_agency.'.self::BILLING_ATTRIBUTE.', 0) as '.self::BILLING_ATTRIBUTE))
            ->addSelect(DB::raw('CASE WHEN vip_corp_groups_agency.code_agency IS NOT NULL AND vip_corp_sales_agency.code_agency IS NOT NULL THEN 1 ELSE 0 END as '.self::LINEA_DIRECTA_ATTRIBUTE));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function orderByPriorityThenCreatedAt(Builder $query): Builder
    {
        return $query
            ->orderByDesc(self::LINEA_DIRECTA_ATTRIBUTE)
            ->orderByDesc(self::BILLING_ATTRIBUTE)
            ->orderByDesc($query->getModel()->getTable().'.created_at');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function orderByBillingAttribute(Builder|QueryBuilder $query, string $direction): Builder|QueryBuilder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy(self::BILLING_ATTRIBUTE, $direction);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function orderByLineaDirectaAttribute(Builder|QueryBuilder $query, string $direction): Builder|QueryBuilder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy(self::LINEA_DIRECTA_ATTRIBUTE, $direction);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function constrainAgentToLineaDirecta(Builder $query): Builder
    {
        return $query
            ->whereNotNull('vip_corp_groups_agent.agent_id')
            ->whereNotNull('vip_corp_sales_agent.agent_id');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function constrainAgencyToLineaDirecta(Builder $query): Builder
    {
        return $query
            ->whereNotNull('vip_corp_groups_agency.code_agency')
            ->whereNotNull('vip_corp_sales_agency.code_agency');
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function constrainAgentBillingStarCount(Builder $query, int $stars): Builder
    {
        return self::constrainBillingStarCount($query, 'vip_billing_agent.'.self::BILLING_ATTRIBUTE, $stars);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    public static function constrainAgencyBillingStarCount(Builder $query, int $stars): Builder
    {
        return self::constrainBillingStarCount($query, 'vip_billing_agency.'.self::BILLING_ATTRIBUTE, $stars);
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @return Builder<\Illuminate\Database\Eloquent\Model>
     */
    private static function constrainBillingStarCount(Builder $query, string $column, int $stars): Builder
    {
        [$min, $maxExclusive] = match ($stars) {
            1 => [self::MIN_ONE_STAR, self::MIN_TWO_STARS],
            2 => [self::MIN_TWO_STARS, self::MIN_THREE_STARS],
            3 => [self::MIN_THREE_STARS, self::MIN_FOUR_STARS],
            4 => [self::MIN_FOUR_STARS, self::MIN_FIVE_STARS],
            5 => [self::MIN_FIVE_STARS, null],
            default => [0.0, self::MIN_ONE_STAR],
        };

        $query->whereRaw('COALESCE('.$column.', 0) >= ?', [$min]);

        if ($maxExclusive !== null) {
            $query->whereRaw('COALESCE('.$column.', 0) < ?', [$maxExclusive]);
        }

        return $query;
    }

    public static function rememberOnRecord(Agent|Agency $record): void
    {
        if (! array_key_exists(self::BILLING_ATTRIBUTE, $record->getAttributes())) {
            $record->setAttribute(
                self::BILLING_ATTRIBUTE,
                $record instanceof Agent
                    ? self::billingAmountForAgentId((int) $record->id)
                    : self::billingAmountForAgencyCode((string) $record->code)
            );
        }

        if (! array_key_exists(self::LINEA_DIRECTA_ATTRIBUTE, $record->getAttributes())) {
            $hasLineaDirecta = $record instanceof Agent
                ? self::agentHasLineaDirecta((int) $record->id)
                : self::agencyHasLineaDirecta((string) $record->code);

            $record->setAttribute(self::LINEA_DIRECTA_ATTRIBUTE, $hasLineaDirecta ? 1 : 0);
        }
    }

    public static function billingAmountFromRecord(object $record): float
    {
        return self::resolveBillingUsd($record);
    }

    public static function resolveBillingUsd(object $record): float
    {
        $attributes = $record instanceof Agent || $record instanceof Agency
            ? $record->getAttributes()
            : [];

        if (array_key_exists(self::BILLING_ATTRIBUTE, $attributes)) {
            return (float) $attributes[self::BILLING_ATTRIBUTE];
        }

        if ($record instanceof Agent) {
            return self::billingAmountForAgentId((int) $record->id);
        }

        if ($record instanceof Agency) {
            return self::billingAmountForAgencyCode((string) $record->code);
        }

        $raw = $record->{self::BILLING_ATTRIBUTE} ?? 0;

        return (float) $raw;
    }

    public static function lineaDirectaFromRecord(object $record): bool
    {
        $attributes = $record instanceof Agent || $record instanceof Agency
            ? $record->getAttributes()
            : [];

        if (array_key_exists(self::LINEA_DIRECTA_ATTRIBUTE, $attributes)) {
            return (int) $attributes[self::LINEA_DIRECTA_ATTRIBUTE] === 1;
        }

        if ($record instanceof Agent) {
            return self::agentHasLineaDirecta((int) $record->id);
        }

        if ($record instanceof Agency) {
            return self::agencyHasLineaDirecta((string) $record->code);
        }

        return (int) ($record->{self::LINEA_DIRECTA_ATTRIBUTE} ?? 0) === 1;
    }

    public static function billingAmountForAgentId(int $agentId): float
    {
        return (float) DB::table('sales')
            ->where('agent_id', $agentId)
            ->sum('total_amount');
    }

    public static function billingAmountForAgencyCode(string $code): float
    {
        if ($code === '') {
            return 0.0;
        }

        return (float) DB::table('sales')
            ->where('code_agency', $code)
            ->sum('total_amount');
    }

    public static function agentHasLineaDirecta(int $agentId): bool
    {
        $hasGroups = DB::table('affiliation_corporates')
            ->where('agent_id', $agentId)
            ->exists();

        if (! $hasGroups) {
            return false;
        }

        return DB::table('sales')
            ->where('agent_id', $agentId)
            ->where('total_amount', '>', 0)
            ->where('type', 'like', '%CORPORATIV%')
            ->exists();
    }

    public static function agencyHasLineaDirecta(string $code): bool
    {
        if ($code === '') {
            return false;
        }

        $hasGroups = DB::table('affiliation_corporates')
            ->where('code_agency', $code)
            ->exists();

        if (! $hasGroups) {
            return false;
        }

        return DB::table('sales')
            ->where('code_agency', $code)
            ->where('total_amount', '>', 0)
            ->where('type', 'like', '%CORPORATIV%')
            ->exists();
    }
}
