<?php

declare(strict_types=1);

namespace App\Support\AffiliationCorporates;

use App\Filament\Administration\Resources\AffiliationCorporatePaymentFrequencyChanges\AffiliationCorporatePaymentFrequencyChangeResource;
use App\Models\AffiliationCorporatePaymentFrequencyChange;
use Illuminate\Support\Collection;
use Throwable;

/**
 * Textos del aviso de cambio de frecuencia de pago (y de su reverso) para
 * WhatsApp, correo y la notificación del panel de Administración.
 */
final class CorporatePaymentFrequencyChangeNotificationMessage
{
    public const EVENT_APPLIED = 'applied';

    public const EVENT_REVERSED = 'reversed';

    private const WHATSAPP_MAX_LISTED = 10;

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     */
    public static function whatsappBody(Collection $changes, string $event): string
    {
        $first = $changes->first();

        if ($event === self::EVENT_REVERSED) {
            return "↩️ *Cambio de frecuencia de pago REVERTIDO*\n\n"
                .'El analista *'.self::reversedBy($first).'* revirtió el cambio de frecuencia de la afiliación *'.self::affiliationLabel($first).'*: '
                .'vuelve de '.CorporatePaymentFrequency::label($first->new_frequency).' a *'.CorporatePaymentFrequency::label($first->previous_frequency).'*.'
                ."\n\nEl cambio lo había hecho *".self::performedBy($first).'* el '.self::dateTime($first->created_at).'.'
                ."\nMotivo: ".trim((string) $first->reversal_reason)
                ."\n\n📧 Revise su correo para ver el detalle completo de los avisos de cobro restaurados y anulados.";
        }

        $count = $changes->count();

        if ($count === 1) {
            return "🔔 *Cambio de frecuencia de pago*\n\n"
                .'El analista *'.self::performedBy($first).'* acaba de cambiar la frecuencia de pago de la afiliación *'.self::affiliationLabel($first).'* '
                .'de '.CorporatePaymentFrequency::label($first->previous_frequency).' a *'.CorporatePaymentFrequency::label($first->new_frequency).'*.'
                ."\n\n".self::collectionsSentence($first)
                ."\n\n📧 Revise su correo para ver el detalle completo y valide el cambio en INTEGRACORP → Administración → Cambios de frecuencia de pago.";
        }

        $listed = $changes->take(self::WHATSAPP_MAX_LISTED)
            ->map(fn (AffiliationCorporatePaymentFrequencyChange $change): string => '• '.self::affiliationLabel($change))
            ->implode("\n");

        $more = $count > self::WHATSAPP_MAX_LISTED ? "\n• y ".($count - self::WHATSAPP_MAX_LISTED).' más' : '';

        return "🔔 *Cambio masivo de frecuencia de pago*\n\n"
            .'El analista *'.self::performedBy($first).'* acaba de cambiar la frecuencia de pago de *'.$count.' afiliaciones* a *'
            .CorporatePaymentFrequency::label($first->new_frequency)."*:\n".$listed.$more
            ."\n\n📧 Revise su correo para ver el detalle completo de cada afiliación y valide los cambios en INTEGRACORP → Administración → Cambios de frecuencia de pago.";
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     */
    public static function emailSubject(Collection $changes, string $event): string
    {
        $first = $changes->first();

        if ($event === self::EVENT_REVERSED) {
            return 'Cambio de frecuencia REVERTIDO · '.self::affiliationLabel($first);
        }

        return $changes->count() === 1
            ? 'Cambio de frecuencia de pago · '.self::affiliationLabel($first).' → '.CorporatePaymentFrequency::label($first->new_frequency)
            : 'Cambio masivo de frecuencia de pago · '.$changes->count().' afiliaciones → '.CorporatePaymentFrequency::label($first->new_frequency);
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     * @return array{event: string, title: string, intro: string, generatedAt: string, changes: list<array<string, mixed>>}
     */
    public static function emailPayload(Collection $changes, string $event): array
    {
        $first = $changes->first();
        $reversed = $event === self::EVENT_REVERSED;

        $intro = $reversed
            ? 'El analista '.self::reversedBy($first).' revirtió el cambio de frecuencia de pago de la afiliación '.self::affiliationLabel($first).'. '
                .'La afiliación, sus afiliados y su cobranza volvieron exactamente al estado anterior al cambio.'
            : 'El analista '.self::performedBy($first).' cambió la frecuencia de pago de '
                .($changes->count() === 1 ? 'la afiliación '.self::affiliationLabel($first) : $changes->count().' afiliaciones')
                .'. Revise el detalle y valide el cambio en INTEGRACORP → Administración → Cambios de frecuencia de pago. '
                .'Si no está de acuerdo, desde allí puede revertirlo.';

        return [
            'event' => $event,
            'title' => $reversed ? 'Cambio de frecuencia de pago revertido' : 'Cambio de frecuencia de pago',
            'intro' => $intro,
            'generatedAt' => now()->format('d/m/Y H:i'),
            'changes' => $changes->map(fn (AffiliationCorporatePaymentFrequencyChange $change): array => self::changeDetail($change))->values()->all(),
        ];
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     */
    public static function databaseTitle(Collection $changes, string $event): string
    {
        if ($event === self::EVENT_REVERSED) {
            return 'Cambio de frecuencia revertido · '.self::affiliationLabel($changes->first());
        }

        return $changes->count() === 1
            ? 'Cambio de frecuencia de pago · '.self::affiliationLabel($changes->first())
            : 'Cambio masivo de frecuencia de pago · '.$changes->count().' afiliaciones';
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     */
    public static function databaseBody(Collection $changes, string $event): string
    {
        $first = $changes->first();

        if ($event === self::EVENT_REVERSED) {
            return self::reversedBy($first).' revirtió el cambio a '.CorporatePaymentFrequency::label($first->new_frequency)
                .' hecho por '.self::performedBy($first).'. Motivo: '.mb_strimwidth(trim((string) $first->reversal_reason), 0, 160, '…');
        }

        if ($changes->count() === 1) {
            return self::performedBy($first).' cambió de '.CorporatePaymentFrequency::label($first->previous_frequency)
                .' a '.CorporatePaymentFrequency::label($first->new_frequency).'. '.self::collectionsSentence($first).' Revise y valide el cambio.';
        }

        return self::performedBy($first).' cambió '.$changes->count().' afiliaciones a '
            .CorporatePaymentFrequency::label($first->new_frequency).'. Revise y valide cada cambio.';
    }

    /**
     * @param  Collection<int, AffiliationCorporatePaymentFrequencyChange>  $changes
     */
    public static function reviewUrl(Collection $changes): string
    {
        try {
            return $changes->count() === 1
                ? AffiliationCorporatePaymentFrequencyChangeResource::getUrl('view', ['record' => $changes->first()->getKey()], panel: 'administration')
                : AffiliationCorporatePaymentFrequencyChangeResource::getUrl('index', panel: 'administration');
        } catch (Throwable) {
            return rtrim((string) config('app.url'), '/').'/administration';
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function changeDetail(AffiliationCorporatePaymentFrequencyChange $change): array
    {
        return [
            'id' => (int) $change->getKey(),
            'affiliation' => self::affiliationLabel($change),
            'previousFrequency' => CorporatePaymentFrequency::label($change->previous_frequency),
            'newFrequency' => CorporatePaymentFrequency::label($change->new_frequency),
            'feeAnual' => self::money((float) $change->fee_anual),
            'previousTotal' => self::money((float) $change->previous_total_amount),
            'newTotal' => self::money((float) $change->new_total_amount),
            'pendingBalance' => self::money((float) $change->pending_balance),
            'performedBy' => self::performedBy($change),
            'performedAt' => self::dateTime($change->created_at),
            'reversedBy' => $change->isReversed() ? self::reversedBy($change) : null,
            'reversedAt' => $change->isReversed() ? self::dateTime($change->reversed_at) : null,
            'reversalReason' => $change->isReversed() ? trim((string) $change->reversal_reason) : null,
            'cancelled' => self::collectionRows($change->cancelled_collections ?? []),
            'created' => self::collectionRows($change->created_collections ?? []),
            'url' => self::reviewUrl(collect([$change])),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return list<array{invoice: string, date: string, amount: string, months: string}>
     */
    private static function collectionRows(array $rows): array
    {
        return array_values(array_map(fn (array $row): array => [
            'invoice' => (string) ($row['invoice'] ?? '—'),
            'date' => (string) ($row['date'] ?? '—'),
            'amount' => self::money((float) ($row['amount'] ?? 0)),
            'months' => (string) ((int) ($row['months'] ?? 0)).' '.(((int) ($row['months'] ?? 0)) === 1 ? 'mes' : 'meses'),
        ], $rows));
    }

    private static function collectionsSentence(AffiliationCorporatePaymentFrequencyChange $change): string
    {
        $cancelled = count($change->cancelled_collections ?? []);
        $created = count($change->created_collections ?? []);

        if ($cancelled === 0) {
            return 'No tenía avisos de cobro pendientes; la nueva frecuencia rige en los próximos cobros.';
        }

        return 'Se anularon '.$cancelled.' '.($cancelled === 1 ? 'aviso de cobro pendiente' : 'avisos de cobro pendientes')
            .' y se generaron '.$created.' '.($created === 1 ? 'nuevo' : 'nuevos')
            .' por el mismo saldo ('.self::money((float) $change->pending_balance).').';
    }

    private static function affiliationLabel(AffiliationCorporatePaymentFrequencyChange $change): string
    {
        $label = implode(' · ', array_filter([trim((string) $change->affiliation_code), trim((string) $change->affiliation_name)]));

        return $label !== '' ? $label : 'Afiliación #'.$change->affiliation_corporate_id;
    }

    private static function performedBy(AffiliationCorporatePaymentFrequencyChange $change): string
    {
        return trim((string) $change->performed_by_name) !== '' ? trim((string) $change->performed_by_name) : 'Sistema';
    }

    private static function reversedBy(AffiliationCorporatePaymentFrequencyChange $change): string
    {
        return trim((string) $change->reversed_by_name) !== '' ? trim((string) $change->reversed_by_name) : 'Sistema';
    }

    private static function dateTime(mixed $value): string
    {
        return $value instanceof \DateTimeInterface ? $value->format('d/m/Y H:i') : '—';
    }

    private static function money(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' US$';
    }
}
