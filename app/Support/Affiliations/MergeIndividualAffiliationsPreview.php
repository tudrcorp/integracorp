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
    <div>
        <p class="font-semibold mb-1">Impacto operativo</p>
        <ul class="list-disc pl-5 space-y-1">
            <li>Cuotas POR PAGAR a cancelar en pólizas origen: {$cancelCount}</li>
            <li>Cuotas POR PAGAR de la póliza destino a recalcular: {$this->pendingCollectionsToRecalculate}</li>
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
