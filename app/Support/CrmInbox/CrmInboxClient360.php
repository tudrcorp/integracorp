<?php

declare(strict_types=1);

namespace App\Support\CrmInbox;

use App\Models\Affiliate;
use App\Models\Affiliation;
use App\Models\Agent;
use App\Models\Collection as CollectionModel;
use App\Models\Plan;
use App\Support\ClinicalEntitlements\ClinicalEntitlementWindow;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Lo que el analista necesita saber del cliente sin salir del chat: su afiliación, el plan, cómo va
 * con los pagos, cuándo renueva y quién es su agente. Unas pocas consultas por clave, una sola vez
 * al abrir el caso. Nada clínico: eso no se muestra en la bandeja.
 */
final class CrmInboxClient360
{
    /**
     * @param  array{kind: 'afiliado'|'agente', id: int, affiliation_id: ?int, name: string}  $match
     * @return list<array{label: string, value: string, tone?: string}>
     */
    public static function forMatch(array $match, ?Carbon $now = null): array
    {
        $now ??= Carbon::now();

        try {
            return $match['kind'] === 'afiliado'
                ? self::affiliate($match, $now)
                : self::agent($match['id']);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @param  array{kind: string, id: int, affiliation_id: ?int, name: string}  $match
     * @return list<array{label: string, value: string, tone?: string}>
     */
    private static function affiliate(array $match, Carbon $now): array
    {
        if ($match['affiliation_id'] === null || ! Schema::hasTable('affiliations')) {
            return [];
        }

        $affiliation = Affiliation::query()->find($match['affiliation_id'], [
            'id', 'code', 'status', 'plan_id', 'payment_frequency', 'effective_date', 'full_name_agent', 'code_agent', 'total_amount',
        ]);

        if ($affiliation === null) {
            return [];
        }

        $lines = [];
        $status = trim((string) $affiliation->status);
        $plan = $affiliation->plan_id !== null ? Plan::query()->whereKey($affiliation->plan_id)->value('description') : null;

        $lines[] = [
            'label' => 'Afiliación',
            'value' => trim(implode(' · ', array_filter([(string) $affiliation->code, $status !== '' ? $status : null]))),
            'tone' => $status === 'ACTIVA' ? 'ok' : ($status === 'EXCLUIDO' ? 'bad' : 'warn'),
        ];

        if (is_string($plan) && trim($plan) !== '') {
            $lines[] = ['label' => 'Plan', 'value' => trim(implode(' · ', array_filter([trim($plan), self::text($affiliation->payment_frequency)])))];
        }

        $members = Affiliate::query()->where('affiliation_id', $affiliation->id)->count();

        if ($members > 0) {
            $lines[] = ['label' => 'Grupo', 'value' => $members.' '.($members === 1 ? 'persona' : 'personas')];
        }

        $renewal = self::renewal($affiliation->effective_date, $now);

        if ($renewal !== null) {
            $lines[] = $renewal;
        }

        $payments = self::payments((string) $affiliation->code, $now);

        if ($payments !== null) {
            $lines[] = $payments;
        }

        $agent = self::text($affiliation->full_name_agent);

        if ($agent !== null) {
            $lines[] = ['label' => 'Agente', 'value' => trim($agent.' '.(self::text($affiliation->code_agent) !== null ? '· '.$affiliation->code_agent : ''))];
        }

        return $lines;
    }

    /**
     * @return array{label: string, value: string, tone?: string}|null
     */
    private static function renewal(mixed $effectiveDate, Carbon $now): ?array
    {
        $effect = ClinicalEntitlementWindow::parseToStartOfDay($effectiveDate);

        if ($effect === null) {
            return null;
        }

        $window = ClinicalEntitlementWindow::currentAnniversaryWindow($effect);
        $ends = Carbon::instance($window['ends_at']);
        $days = (int) floor($now->copy()->startOfDay()->diffInDays($ends, false));

        return [
            'label' => 'Renueva',
            'value' => $ends->format('d/m/Y').($days >= 0 ? ' · en '.$days.' '.($days === 1 ? 'día' : 'días') : ''),
            'tone' => $days >= 0 && $days <= 30 ? 'warn' : 'ok',
        ];
    }

    /**
     * Próxima cuota por pagar y cuántas están vencidas. Usa el índice (affiliation_code, status, filter_next_payment_date).
     *
     * @return array{label: string, value: string, tone?: string}|null
     */
    private static function payments(string $code, Carbon $now): ?array
    {
        if ($code === '' || ! Schema::hasTable('collections')) {
            return null;
        }

        $pending = CollectionModel::query()
            ->where('affiliation_code', $code)
            ->where('status', 'POR PAGAR');

        $next = (clone $pending)->orderBy('filter_next_payment_date')->first(['next_payment_date', 'filter_next_payment_date', 'total_amount']);

        if ($next === null) {
            return ['label' => 'Pagos', 'value' => 'Al día · sin cuotas por pagar', 'tone' => 'ok'];
        }

        $overdue = (clone $pending)->whereDate('filter_next_payment_date', '<', $now->toDateString())->count();
        $amount = is_numeric($next->total_amount) ? ' · USD '.number_format((float) $next->total_amount, 2, ',', '.') : '';
        $value = 'Próxima '.self::text($next->next_payment_date).$amount;

        if ($overdue > 0) {
            $value .= ' · '.$overdue.' '.($overdue === 1 ? 'vencida' : 'vencidas');
        }

        return ['label' => 'Pagos', 'value' => $value, 'tone' => $overdue > 0 ? 'bad' : 'ok'];
    }

    /**
     * @return list<array{label: string, value: string, tone?: string}>
     */
    private static function agent(int $id): array
    {
        $agent = Agent::query()->find($id, ['id', 'code_agent', 'code_agency', 'status']);

        if ($agent === null) {
            return [];
        }

        $status = self::text($agent->status);

        return array_values(array_filter([
            ['label' => 'Agente', 'value' => trim(implode(' · ', array_filter([self::text($agent->code_agent), $status]))), 'tone' => $status === 'ACTIVO' ? 'ok' : 'warn'],
            self::text($agent->code_agency) !== null ? ['label' => 'Agencia', 'value' => (string) $agent->code_agency] : null,
        ]));
    }

    private static function text(mixed $value): ?string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text !== '' ? $text : null;
    }
}
