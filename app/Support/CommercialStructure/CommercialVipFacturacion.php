<?php

declare(strict_types=1);

namespace App\Support\CommercialStructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Nivel VIP comercial según facturación acumulada en USD (tabla sales.total_amount).
 *
 * Rangos acordados con Negocios (2026): de 1 a 5 estrellas según volumen facturado.
 */
final class CommercialVipFacturacion
{
    public const float MIN_ONE_STAR = 1_000.0;

    public const float MIN_TWO_STARS = 10_000.0;

    public const float MIN_THREE_STARS = 100_000.0;

    public const float MIN_FOUR_STARS = 500_000.0;

    public const float MIN_FIVE_STARS = 1_000_000.0;

    public const string BILLING_ATTRIBUTE = 'vip_facturacion_usd';

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

    public static function nameWithVipStarsHtml(string $name, float $billingUsd): HtmlString
    {
        $safeName = e($name !== '' ? $name : '—');
        $stars = self::starCountFromAmount($billingUsd);
        $starsLine = self::starsGlyphLine($stars);

        if ($starsLine === '') {
            return new HtmlString('<span class="font-medium">'.$safeName.'</span>');
        }

        $title = e(self::billingTooltip($billingUsd, $stars));

        return new HtmlString(
            '<div class="flex flex-col items-start gap-0.5">'
            .'<span class="text-amber-500 dark:text-amber-400 text-xs leading-none tracking-tight" title="'.$title.'">'.$starsLine.'</span>'
            .'<span class="font-medium">'.$safeName.'</span>'
            .'</div>'
        );
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

        return $query
            ->leftJoinSub($billing, 'vip_billing_agent', fn ($join) => $join->on('vip_billing_agent.agent_id', '=', 'agents.id'))
            ->addSelect(DB::raw('COALESCE(vip_billing_agent.'.self::BILLING_ATTRIBUTE.', 0) as '.self::BILLING_ATTRIBUTE));
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

        return $query
            ->leftJoinSub($billing, 'vip_billing_agency', fn ($join) => $join->on('vip_billing_agency.code_agency', '=', 'agencies.code'))
            ->addSelect(DB::raw('COALESCE(vip_billing_agency.'.self::BILLING_ATTRIBUTE.', 0) as '.self::BILLING_ATTRIBUTE));
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>|QueryBuilder  $query
     */
    public static function orderByBillingAttribute(Builder|QueryBuilder $query, string $direction): Builder|QueryBuilder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy(self::BILLING_ATTRIBUTE, $direction);
    }

    public static function billingAmountFromRecord(object $record): float
    {
        $raw = $record->{self::BILLING_ATTRIBUTE} ?? 0;

        return (float) $raw;
    }
}
