<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use Illuminate\Support\HtmlString;

final class MergeIndividualAffiliationsPreview
{
    /**
     * @param  list<string>  $blockers
     * @param  list<string>  $warnings
     * @param  array{id: int, code: string, titular: string, status: string, plan_id: int|null, fee_anual: float, total_amount: float, family_members: int, agency: string, frequency: string}  $target
     * @param  list<array{id: int, code: string, titular: string, status: string, fee_anual: float, family_members: int}>  $sources
     * @param  list<array{affiliate_id: int, name: string, identification: string, from_code: string, old_relationship: string, new_relationship: string, fee_before: float|null, fee_after: float|null, status: string}>  $members
     * @param  list<array{code: string, invoice: string|null, amount: float}>  $collectionsToCancel
     * @param  list<array{code: string, invoice: string|null, amount: float}>  $annualCollectionsToCancel
     * @param  list<array{code: string, status: string}>  $renovationsToCancel
     * @param  list<array{identification: string, name: string}>  $telemedicinePatients
     * @param  array{
     *     effective_date?: string|null,
     *     frequency?: string,
     *     renewal_date?: string|null,
     *     period_amount?: float,
     *     creates_collections?: bool,
     *     installments?: list<array{date: string, due: string, amount: float, overdue: bool}>,
     *     paid_through?: list<array{code: string, date: string|null}>,
     *     blockers?: list<string>,
     *     warnings?: list<string>
     * }  $billing
     */
    public function __construct(
        public array $blockers,
        public array $warnings,
        public array $target,
        public array $sources,
        public array $members,
        public array $collectionsToCancel,
        public array $annualCollectionsToCancel,
        public array $renovationsToCancel,
        public array $telemedicinePatients,
        public float $newFeeAnual,
        public float $newTotalAmount,
        public int $newFamilyMembers,
        public int $pendingCollectionsToRecalculate,
        public array $billing = [],
    ) {}

    public function canExecute(): bool
    {
        return $this->blockers === [];
    }

    public function toHtml(): HtmlString
    {
        $blockerHtml = $this->listHtml($this->blockers, 'No se puede unificar hasta resolver esto:', 'text-red-700 dark:text-red-300');
        $warningHtml = $this->listHtml($this->warnings, 'Advertencias (no bloquean):', 'text-amber-700 dark:text-amber-300');

        $sourceRows = '';
        foreach ($this->sources as $source) {
            $sourceRows .= '<tr>'
                .'<td class="px-2 py-1">'.e($source['code']).'</td>'
                .'<td class="px-2 py-1">'.e($source['titular']).'</td>'
                .'<td class="px-2 py-1">'.e($source['status']).'</td>'
                .'<td class="px-2 py-1 text-right">'.e(number_format((float) $source['fee_anual'], 2, ',', '.')).'</td>'
                .'</tr>';
        }

        $memberRows = '';
        foreach ($this->members as $member) {
            $feeAfter = $member['fee_after'] === null ? '—' : number_format((float) $member['fee_after'], 2, ',', '.');
            $memberRows .= '<tr>'
                .'<td class="px-2 py-1">'.e($member['name']).'</td>'
                .'<td class="px-2 py-1">'.e($member['identification']).'</td>'
                .'<td class="px-2 py-1">'.e($member['from_code']).'</td>'
                .'<td class="px-2 py-1">'.e($member['old_relationship']).' → <strong>'.e($member['new_relationship']).'</strong></td>'
                .'<td class="px-2 py-1 text-right">'.$feeAfter.'</td>'
                .'</tr>';
        }

        $cancelCount = count($this->collectionsToCancel) + count($this->annualCollectionsToCancel);
        $newCollections = ($this->billing['creates_collections'] ?? false) ? count($this->billing['installments'] ?? []) : 0;
        $renovationCount = count($this->renovationsToCancel);
        $telemedicineCount = count($this->telemedicinePatients);

        $html = <<<HTML
<div class="space-y-4 text-sm">
    {$blockerHtml}
    {$warningHtml}
    <div>
        <p class="font-semibold mb-1">Póliza que sobrevive</p>
        <p>{$this->escape($this->target['code'])} — {$this->escape($this->target['titular'])} ({$this->escape($this->target['status'])})</p>
        <p>Tarifa actual: {$this->money($this->target['fee_anual'])} · Personas: {$this->target['family_members']}</p>
        <p>Tarifa unificada: {$this->money($this->newFeeAnual)} · Monto del período: {$this->money($this->newTotalAmount)} · Personas activas: {$this->newFamilyMembers}</p>
    </div>
    <div>
        <p class="font-semibold mb-1">Pólizas que pasarán a EXCLUIDO (no se borran)</p>
        <table class="w-full text-left border-collapse">
            <thead><tr><th class="px-2 py-1">Código</th><th class="px-2 py-1">Titular</th><th class="px-2 py-1">Estatus</th><th class="px-2 py-1 text-right">Tarifa</th></tr></thead>
            <tbody>{$sourceRows}</tbody>
        </table>
    </div>
    <div>
        <p class="font-semibold mb-1">Grupo familiar resultante</p>
        <table class="w-full text-left border-collapse">
            <thead><tr><th class="px-2 py-1">Persona</th><th class="px-2 py-1">Cédula</th><th class="px-2 py-1">Origen</th><th class="px-2 py-1">Parentesco</th><th class="px-2 py-1 text-right">Tarifa anual</th></tr></thead>
            <tbody>{$memberRows}</tbody>
        </table>
    </div>
    {$this->billingSectionHtml()}
    <div>
        <p class="font-semibold mb-1">Impacto operativo</p>
        <ul class="list-disc pl-5 space-y-1">
            <li>Avisos POR PAGAR que se anulan y reemplazan (no se borran): {$cancelCount}</li>
            <li>Avisos de cobro nuevos que se crean en Administración: {$newCollections}</li>
            <li>Renovaciones abiertas a anular en origen: {$renovationCount}</li>
            <li>Pacientes de telemedicina a reasignar: {$telemedicineCount}</li>
            <li>Ventas, comisiones y recibos ya emitidos no se modifican.</li>
        </ul>
    </div>
</div>
HTML;

        return new HtmlString($html);
    }

    /**
     * Solo el bloque de vigencia y cobro, con sus bloqueos y avisos: lo usa el paso «Vigencia y cobro».
     */
    public function billingHtml(): HtmlString
    {
        $blockers = $this->listHtml($this->billing['blockers'] ?? [], 'Hay que corregir esto antes de continuar:', 'text-red-700 dark:text-red-300');
        $warnings = $this->listHtml($this->billing['warnings'] ?? [], 'Advertencias (no bloquean):', 'text-amber-700 dark:text-amber-300');

        return new HtmlString('<div class="space-y-4 text-sm">'.$blockers.$warnings.$this->billingSectionHtml().'</div>');
    }

    private function billingSectionHtml(): string
    {
        if ($this->billing === []) {
            return '';
        }

        $paidRows = '';
        foreach ($this->billing['paid_through'] ?? [] as $row) {
            $paidRows .= '<tr><td class="px-2 py-1">'.e($row['code']).'</td>'
                .'<td class="px-2 py-1">'.e($row['date'] ?? 'Sin dato: confirmar con Administración').'</td></tr>';
        }

        $installmentRows = '';
        $total = 0.0;
        foreach ($this->billing['installments'] ?? [] as $index => $installment) {
            $total += (float) $installment['amount'];
            $state = $installment['overdue']
                ? '<span class="text-red-700 dark:text-red-300">Fecha pasada: se crea a cobrar</span>'
                : 'Por cobrar';
            $installmentRows .= '<tr>'
                .'<td class="px-2 py-1">'.($index + 1).'</td>'
                .'<td class="px-2 py-1">'.e($installment['date']).'</td>'
                .'<td class="px-2 py-1">'.e($installment['due']).'</td>'
                .'<td class="px-2 py-1 text-right">'.$this->money((float) $installment['amount']).'</td>'
                .'<td class="px-2 py-1">'.$state.'</td>'
                .'</tr>';
        }

        $effective = e($this->billing['effective_date'] ?? 'sin definir');
        $renewal = e($this->billing['renewal_date'] ?? 'sin definir');
        $frequency = e((string) ($this->billing['frequency'] ?? ''));
        $period = $this->money((float) ($this->billing['period_amount'] ?? 0));
        $totalHtml = $this->money($total);

        $calendar = $installmentRows === ''
            ? '<p class="text-gray-600 dark:text-gray-400">Elija la fecha de vigencia para ver el calendario de cobro.</p>'
            : '<table class="w-full text-left border-collapse"><thead><tr><th class="px-2 py-1">#</th><th class="px-2 py-1">Fecha de pago</th><th class="px-2 py-1">Vence</th><th class="px-2 py-1 text-right">Monto</th><th class="px-2 py-1">Estado</th></tr></thead><tbody>'
                .$installmentRows
                .'<tr class="font-semibold"><td class="px-2 py-1" colspan="3">Total del año</td><td class="px-2 py-1 text-right">'.$totalHtml.'</td><td></td></tr>'
                .'</tbody></table>';

        $createNote = ($this->billing['creates_collections'] ?? false)
            ? 'Al unificar se crean estos avisos POR PAGAR en Administración.'
            : 'La póliza destino no está ACTIVA: los avisos se generarán al aprobar su primer pago, como hoy.';

        return <<<HTML
    <div>
        <p class="font-semibold mb-1">Vigencia y cobro del grupo unificado</p>
        <p>Vigencia: <strong>{$effective}</strong> · Frecuencia: <strong>{$frequency}</strong> · Monto por cuota: <strong>{$period}</strong> · Próxima renovación: <strong>{$renewal}</strong></p>
        <p class="font-semibold mt-2 mb-1">Pagado hasta (por póliza)</p>
        <table class="w-full text-left border-collapse"><thead><tr><th class="px-2 py-1">Póliza</th><th class="px-2 py-1">Cubierto hasta</th></tr></thead><tbody>{$paidRows}</tbody></table>
        <p class="font-semibold mt-2 mb-1">Próximas fechas de pago</p>
        {$calendar}
        <p class="mt-1 text-gray-600 dark:text-gray-400">{$createNote}</p>
    </div>
HTML;
    }

    /**
     * @param  list<string>  $items
     */
    private function listHtml(array $items, string $title, string $class): string
    {
        if ($items === []) {
            return '';
        }

        $lis = '';
        foreach ($items as $item) {
            $lis .= '<li>'.e($item).'</li>';
        }

        return '<div class="'.$class.'"><p class="font-semibold mb-1">'.e($title).'</p><ul class="list-disc pl-5 space-y-1">'.$lis.'</ul></div>';
    }

    private function escape(mixed $value): string
    {
        return e((string) $value);
    }

    private function money(float $amount): string
    {
        return e(number_format($amount, 2, ',', '.'));
    }
}
