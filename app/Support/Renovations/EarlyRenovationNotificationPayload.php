<?php

declare(strict_types=1);

namespace App\Support\Renovations;

use App\Filament\Business\Resources\AffiliationCorporateRenovationHistories\AffiliationCorporateRenovationHistoryResource;
use App\Filament\Business\Resources\AffiliationRenovationHistories\AffiliationRenovationHistoryResource;
use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\AffiliationCorporateRenovationHistory;
use App\Models\AffiliationRenovationHistory;
use Filament\Facades\Filament;
use Illuminate\Support\Str;
use Throwable;

/**
 * Contenido del aviso de renovación anticipada a los SUPERADMIN. Se arma en el
 * momento de aceptar (cuando el histórico ya existe) y viaja serializado al job:
 * así el aviso describe exactamente lo aplicado aunque después cambie la afiliación.
 */
final class EarlyRenovationNotificationPayload
{
    /**
     * @return array<string, mixed>
     */
    public static function individualItem(AffiliationRenovationHistory $history, Affiliation $affiliation, ?int $daysBeforeRenewal): array
    {
        $history->loadMissing('plan');

        return [
            'history_id' => $history->id,
            'affiliation_code' => (string) $history->code_affiliation,
            'holder' => self::clean($affiliation->full_name_ti),
            'date_renewal' => $history->date_renewal?->format('d/m/Y'),
            'days_before_renewal' => $daysBeforeRenewal,
            'previous_effective_date' => $history->previous_effective_date,
            'new_effective_date' => $history->new_effective_date,
            'plan' => self::clean($history->plan?->description),
            'annual_amount' => (float) $history->subtotal_anual,
            'payment_frequency' => (string) $history->payment_frequency,
            'total_persons' => (int) $history->total_persons,
            'url' => self::historyUrl(AffiliationRenovationHistoryResource::class, $history->id),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function corporateItem(AffiliationCorporateRenovationHistory $history, AffiliationCorporate $affiliation, ?int $daysBeforeRenewal): array
    {
        $history->loadMissing('plan');

        return [
            'history_id' => $history->id,
            'affiliation_code' => (string) $history->code_affiliation,
            'holder' => self::clean($affiliation->name_corporate),
            'date_renewal' => $history->date_renewal?->format('d/m/Y'),
            'days_before_renewal' => $daysBeforeRenewal,
            'previous_effective_date' => $history->previous_effective_date,
            'new_effective_date' => $history->new_effective_date,
            'plan' => self::clean($history->plan?->description),
            'annual_amount' => (float) $history->subtotal_anual,
            'payment_frequency' => (string) $history->payment_frequency,
            'total_persons' => (int) $history->total_persons,
            'url' => self::historyUrl(AffiliationCorporateRenovationHistoryResource::class, $history->id),
        ];
    }

    /**
     * @param  'individual'|'corporate'  $kind
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public static function build(string $kind, array $items, EarlyRenovationAuthorization $authorization): array
    {
        return [
            'delivery_key' => (string) Str::uuid(),
            'kind' => $kind,
            'kind_label' => $kind === 'corporate' ? 'corporativa' : 'individual',
            'items' => array_values($items),
            'reason' => $authorization->reason,
            'authorized_by' => $authorization->userName,
            'authorized_by_email' => $authorization->userEmail,
            'authorized_by_user_id' => $authorization->userId,
            'panel' => self::currentPanelLabel(),
            'renewal_period_days' => EarlyRenovationAcceptance::RENEWAL_PERIOD_DAYS,
            'accepted_at' => now()->format('d/m/Y h:i A'),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function emailSubject(array $payload): string
    {
        $items = $payload['items'] ?? [];
        $count = count($items);

        if ($count === 1) {
            return 'Renovación anticipada · '.($items[0]['affiliation_code'] ?? '—').' · faltaban '.self::daysText($items[0]['days_before_renewal'] ?? null);
        }

        return "Renovación anticipada · {$count} renovaciones ".($payload['kind_label'] ?? '').' aceptadas antes del período';
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function whatsappBody(array $payload): string
    {
        $items = $payload['items'] ?? [];
        $period = (int) ($payload['renewal_period_days'] ?? EarlyRenovationAcceptance::RENEWAL_PERIOD_DAYS);

        $lines = [
            '⚠️ *RENOVACIÓN ANTICIPADA*',
            '',
            'Se aceptó '.(count($items) === 1 ? 'una renovación' : count($items).' renovaciones').' '.($payload['kind_label'] ?? '')
                ." *antes del período de renovación* (se abre a {$period} días de la fecha de renovación).",
            '',
        ];

        foreach ($items as $item) {
            $lines[] = '• *'.($item['affiliation_code'] ?? '—').'* · '.($item['holder'] ?? '—');
            $lines[] = '   Fecha de renovación: '.($item['date_renewal'] ?? '—').' (faltaban '.self::daysText($item['days_before_renewal'] ?? null).')';
            $lines[] = '   Plan: '.($item['plan'] ?? '—').' · '.self::money((float) ($item['annual_amount'] ?? 0)).' anual · '.($item['payment_frequency'] ?? '—');
        }

        $lines[] = '';
        $lines[] = '*Autorizado por:* '.($payload['authorized_by'] ?? '—').(filled($payload['authorized_by_email'] ?? null) ? ' ('.$payload['authorized_by_email'].')' : '');
        $lines[] = '*Panel:* '.($payload['panel'] ?? '—');
        $lines[] = '*Fecha:* '.($payload['accepted_at'] ?? '—');
        $lines[] = '*Motivo:* '.($payload['reason'] ?? '—');
        $lines[] = '';
        $lines[] = 'Queda registrada en el histórico de renovaciones como anticipada.';

        return implode("\n", $lines);
    }

    public static function daysText(mixed $days): string
    {
        if (! is_numeric($days)) {
            return '— días';
        }

        $days = (int) $days;

        return $days === 1 ? '1 día' : "{$days} días";
    }

    public static function money(float $amount): string
    {
        return 'US$ '.number_format($amount, 2, ',', '.');
    }

    private static function clean(mixed $value): string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : '—';
    }

    /**
     * @param  class-string  $resource
     */
    private static function historyUrl(string $resource, int $historyId): ?string
    {
        try {
            return $resource::getUrl('view', ['record' => $historyId], panel: 'business');
        } catch (Throwable) {
            return null;
        }
    }

    private static function currentPanelLabel(): string
    {
        try {
            return match (Filament::getCurrentPanel()?->getId()) {
                'business' => 'Negocios',
                'administration' => 'Administración',
                null => 'Sistema',
                default => (string) Filament::getCurrentPanel()?->getId(),
            };
        } catch (Throwable) {
            return 'Sistema';
        }
    }
}
