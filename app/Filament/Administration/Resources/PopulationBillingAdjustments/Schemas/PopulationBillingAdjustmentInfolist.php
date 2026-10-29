<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\PopulationBillingAdjustments\Schemas;

use App\Models\CorporateBillingAdjustment;
use App\Models\CorporatePopulationUpdate;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingAdjuster;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingPreview;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingTray;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Throwable;

/**
 * Ficha de una carga de población: estado de su facturación, el cálculo explicado paso a paso
 * (el guardado si ya se ajustó; una vista previa con la fecha de aplicación si no) y el historial.
 */
class PopulationBillingAdjustmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Carga de población')
                ->icon(Heroicon::OutlinedUsers)
                ->columnSpanFull()
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('affiliationCorporate.code')->label('Afiliación')->weight('bold')->copyable(),
                        TextEntry::make('affiliationCorporate.name_corporate')->label('Empresa'),
                        TextEntry::make('applied_at')->label('Población aplicada')->dateTime('d/m/Y H:i'),
                        TextEntry::make('billing_state')
                            ->label('Facturación')
                            ->badge()
                            ->state(fn (CorporatePopulationUpdate $record): string => PopulationBillingTray::stateOptions()[PopulationBillingTray::state($record)])
                            ->color(fn (CorporatePopulationUpdate $record): string => match (PopulationBillingTray::state($record)) {
                                PopulationBillingTray::STATE_PENDING => 'warning',
                                PopulationBillingTray::STATE_ADJUSTED => 'success',
                                default => 'gray',
                            }),
                    ]),
                ]),
            Section::make('Ajuste aplicado')
                ->icon(Heroicon::OutlinedCheckBadge)
                ->columnSpanFull()
                ->visible(fn (CorporatePopulationUpdate $record): bool => PopulationBillingTray::displayedAdjustment($record) instanceof CorporateBillingAdjustment)
                ->schema([
                    Grid::make(4)->schema([
                        TextEntry::make('adjustment_status')->label('Estado')->badge()
                            ->state(fn (CorporatePopulationUpdate $record): ?string => PopulationBillingTray::displayedAdjustment($record)?->statusLabel())
                            ->color(fn (CorporatePopulationUpdate $record): string => PopulationBillingTray::displayedAdjustment($record)?->isApplied() ? 'success' : 'gray'),
                        TextEntry::make('adjustment_effective')->label('Fecha efectiva')
                            ->state(fn (CorporatePopulationUpdate $record): ?string => PopulationBillingTray::displayedAdjustment($record)?->effective_date?->format('d/m/Y')),
                        TextEntry::make('adjustment_charge')->label('Aviso por ajuste')
                            ->state(fn (CorporatePopulationUpdate $record): string => self::chargeLabel(PopulationBillingTray::displayedAdjustment($record))),
                        TextEntry::make('adjustment_delta')->label('Cambio por cuota')
                            ->state(fn (CorporatePopulationUpdate $record): ?string => ($adjustment = PopulationBillingTray::displayedAdjustment($record)) instanceof CorporateBillingAdjustment
                                ? PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($adjustment->installment_delta)).' en '.$adjustment->installments_adjusted.' '.($adjustment->installments_adjusted === 1 ? 'cuota' : 'cuotas')
                                : null),
                        TextEntry::make('adjustment_by')->label('Aplicado por')
                            ->state(fn (CorporatePopulationUpdate $record): ?string => ($adjustment = PopulationBillingTray::displayedAdjustment($record)) instanceof CorporateBillingAdjustment
                                ? $adjustment->created_by_name.' · '.$adjustment->created_at?->format('d/m/Y H:i')
                                : null),
                        TextEntry::make('adjustment_reason')->label('Motivo')->columnSpan(3)
                            ->state(fn (CorporatePopulationUpdate $record): ?string => PopulationBillingTray::displayedAdjustment($record)?->reason),
                        TextEntry::make('adjustment_revert')->label('Reversión')->columnSpanFull()
                            ->visible(fn (CorporatePopulationUpdate $record): bool => PopulationBillingTray::displayedAdjustment($record)?->reverted_at !== null)
                            ->state(fn (CorporatePopulationUpdate $record): ?string => ($adjustment = PopulationBillingTray::displayedAdjustment($record)) instanceof CorporateBillingAdjustment
                                ? $adjustment->reverted_by_name.' · '.$adjustment->reverted_at?->format('d/m/Y H:i').' — '.$adjustment->revert_reason
                                : null),
                    ]),
                ]),
            Section::make('Cómo se calculó')
                ->description(fn (CorporatePopulationUpdate $record): string => PopulationBillingTray::displayedAdjustment($record) instanceof CorporateBillingAdjustment
                    ? 'Es el cálculo guardado al aplicar: no cambia aunque después cambie la cobranza.'
                    : 'Vista previa con la fecha en que se aplicó la población. Todavía no se ha guardado nada.')
                ->icon(Heroicon::OutlinedCalculator)
                ->columnSpanFull()
                ->schema([
                    TextEntry::make('calculation')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->state(fn (CorporatePopulationUpdate $record): HtmlString => self::calculation($record)),
                ]),
            Section::make('Historial de ajustes')
                ->icon(Heroicon::OutlinedClock)
                ->columnSpanFull()
                ->collapsed()
                ->visible(fn (CorporatePopulationUpdate $record): bool => $record->billingAdjustments->count() > 1)
                ->schema([
                    TextEntry::make('history')
                        ->hiddenLabel()
                        ->columnSpanFull()
                        ->state(fn (CorporatePopulationUpdate $record): HtmlString => new HtmlString('<ul class="list-disc space-y-1 pl-5 text-sm">'
                            .$record->billingAdjustments->sortByDesc('id')->map(fn (CorporateBillingAdjustment $adjustment): string => '<li>'
                                .e($adjustment->created_at?->format('d/m/Y H:i').' · '.$adjustment->statusLabel().' · fecha efectiva '.$adjustment->effective_date?->format('d/m/Y')
                                    .' · aviso '.PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($adjustment->charge_amount)).' · '.$adjustment->created_by_name)
                                .($adjustment->revert_reason ? '<br><span class="text-gray-600 dark:text-gray-400">Revertido: '.e((string) $adjustment->revert_reason).'</span>' : '')
                                .'</li>')->implode('')
                            .'</ul>')),
                ]),
        ]);
    }

    private static function calculation(CorporatePopulationUpdate $record): HtmlString
    {
        $adjustment = PopulationBillingTray::displayedAdjustment($record);

        if ($adjustment instanceof CorporateBillingAdjustment) {
            return PopulationBillingPreview::html((array) $adjustment->summary);
        }

        try {
            $plan = PopulationBillingAdjuster::plan($record);

            return PopulationBillingPreview::html(PopulationBillingAdjuster::summaryFromPlan($plan), $plan['blockers']);
        } catch (Throwable $exception) {
            report($exception);

            return new HtmlString('<p class="text-sm text-red-700 dark:text-red-300">No se pudo calcular: '.e($exception->getMessage()).'</p>');
        }
    }

    private static function chargeLabel(?CorporateBillingAdjustment $adjustment): string
    {
        if (! $adjustment instanceof CorporateBillingAdjustment) {
            return '—';
        }

        $amount = PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($adjustment->charge_amount));
        $charge = $adjustment->chargeCollection;

        return $charge !== null
            ? $amount.' · aviso '.$charge->collection_invoice_number.' ('.$charge->status.', vence '.$charge->next_payment_date.')'
            : $amount.' (no se emitió aviso)';
    }
}
