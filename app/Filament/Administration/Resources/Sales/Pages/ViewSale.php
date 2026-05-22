<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\Sales\Pages;

use App\Filament\Administration\Resources\Sales\SaleResource;
use App\Filament\Administration\Resources\Sales\Tables\SalesTable;
use App\Models\Sale;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class ViewSale extends ViewRecord
{
    protected static string $resource = SaleResource::class;

    private const IOS_BUTTON_BASE = 'shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary '.self::IOS_BUTTON_BASE;

    private const SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success '.self::IOS_BUTTON_BASE;

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning '.self::IOS_BUTTON_BASE;

    private const INFO_BUTTON_CLASS = 'aviso-btn-ios-info '.self::IOS_BUTTON_BASE;

    public function getTitle(): string|Htmlable
    {
        /** @var Sale $sale */
        $sale = $this->getRecord();

        $invoice = (string) ($sale->invoice_number ?? 'Sin recibo');
        $affiliate = (string) ($sale->affiliate_full_name ?? 'Sin afiliado');
        $type = (string) ($sale->type ?? 'SIN TIPO');
        $total = number_format((float) ($sale->total_amount ?? 0), 2, ',', '.');
        $badgeStyle = $this->badgeStyleForType($type);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
                .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
                .'Venta · Recibo '.e($invoice)
                .'</span>'
                .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'
                .e($affiliate)
                .'</span>'
                .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
                .'<span style="background-color: '.$badgeStyle['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$badgeStyle['shadow'].';">'
                .e($type)
                .'</span>'
                .'<span class="text-sm text-gray-600 dark:text-gray-300">US$ '.e($total).'</span>'
                .'</div>'
                .'</div>'
        );
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private function badgeStyleForType(string $type): array
    {
        return match ($type) {
            'AFILIACION INDIVIDUAL' => ['bg' => '#2563eb', 'shadow' => '0 8px 20px rgba(37,99,235,.35)'],
            'AFILIACION CORPORATIVA' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }

    protected function resolveRecord(int|string $key): Model
    {
        /** @var Sale $record */
        $record = parent::resolveRecord($key);

        return $record->load([
            'plan',
            'coverage',
            'agency',
            'agent',
            'agencyMasterName',
            'affiliation',
            'paidMembershipIndividual.plan',
            'paidMembershipIndividual.coverage',
            'paidMembershipIndividual.agent',
            'paidMembershipCorporate.plan',
            'paidMembershipCorporate.coverage',
            'paidMembershipCorporate.agent',
            'commission',
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            SalesTable::downloadPdfAction()
                ->color(self::SUCCESS_BUTTON_CLASS)
                ->extraAttributes([
                    'class' => self::SUCCESS_BUTTON_CLASS,
                ]),
            SalesTable::regeneratePdfAction()
                ->color(self::WARNING_BUTTON_CLASS)
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
            SalesTable::printInvoiceAction()
                ->color(self::INFO_BUTTON_CLASS)
                ->extraAttributes([
                    'class' => self::INFO_BUTTON_CLASS,
                ]),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color(self::WARNING_BUTTON_CLASS)
                ->url(SaleResource::getUrl())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
        ];
    }
}
