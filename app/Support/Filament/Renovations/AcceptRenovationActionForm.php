<?php

declare(strict_types=1);

namespace App\Support\Filament\Renovations;

use App\Models\AgeRange;
use App\Models\Plan;
use App\Models\Renovation;
use App\Support\AffiliationAffiliateFeeCalculator;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;

final class AcceptRenovationActionForm
{
    private const CARD_CLASS = 'rounded-2xl border border-slate-200/90 bg-gradient-to-br from-slate-50 via-white to-slate-50 p-4 shadow-sm dark:border-white/10 dark:from-slate-900/90 dark:via-slate-950/95 dark:to-slate-900/90';

    private const PREVIEW_CARD_CLASS = 'rounded-2xl border border-emerald-200/90 bg-gradient-to-br from-emerald-50/90 via-white to-emerald-50/40 p-4 shadow-sm dark:border-emerald-800/40 dark:from-emerald-950/40 dark:via-slate-950/95 dark:to-emerald-950/20';

    /**
     * @param  Collection<int, Renovation>  $records
     * @return array<int, \Filament\Schemas\Components\Component|\Filament\Forms\Components\Component>
     */
    public static function schema(Collection $records): array
    {
        /** @var Renovation|null $reference */
        $reference = $records->first()?->loadMissing(['plan', 'coverage', 'affiliation']);

        return [
            Section::make('Resumen')
                ->description('Revise qué registros se procesarán al confirmar.')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->schema([
                    Placeholder::make('selection_summary')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => self::selectionSummaryHtml($records, $reference))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->compact(),

            Section::make('Modo de aceptación')
                ->description('Por defecto se aplica la propuesta generada por el sistema. Active la configuración manual solo si el cliente acordó otras condiciones.')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->schema([
                    Toggle::make('manual_commercial_config')
                        ->label('Configurar manualmente plan, cobertura, rango de edad y frecuencia')
                        ->helperText('El costo del afiliado y su familia se calculará automáticamente según su selección.')
                        ->live()
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->compact(),

            Section::make('Propuesta del sistema')
                ->description('Condiciones proyectadas en la renovación.')
                ->icon(Heroicon::OutlinedSparkles)
                ->visible(fn (Get $get): bool => ! (bool) $get('manual_commercial_config'))
                ->schema([
                    Placeholder::make('automatic_proposal_summary')
                        ->hiddenLabel()
                        ->content(fn (): HtmlString => self::automaticProposalHtml($reference))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->compact(),

            Section::make('Configuración comercial')
                ->description('Defina las condiciones acordadas con el cliente.')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->visible(fn (Get $get): bool => (bool) $get('manual_commercial_config'))
                ->schema([
                    Grid::make(['default' => 1, 'md' => 2])
                        ->schema([
                            Select::make('plan_id')
                                ->label('Plan')
                                ->options(fn (): array => Plan::query()->orderBy('description')->pluck('description', 'id')->all())
                                ->default(fn (): ?int => $reference?->plan_id)
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->live()
                                ->required(fn (Get $get): bool => (bool) $get('manual_commercial_config'))
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('age_range_id', null);
                                    $set('coverage_id', null);
                                }),
                            Select::make('age_range_id')
                                ->label('Rango de edad (titular)')
                                ->options(fn (Get $get): array => filled($get('plan_id'))
                                    ? AgeRange::query()
                                        ->where('plan_id', $get('plan_id'))
                                        ->orderBy('range')
                                        ->pluck('range', 'id')
                                        ->all()
                                    : [])
                                ->default(fn (): ?int => $reference?->age_range_id)
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->live()
                                ->required(fn (Get $get): bool => (bool) $get('manual_commercial_config'))
                                ->afterStateUpdated(fn (Set $set): mixed => $set('coverage_id', null)),
                            Select::make('coverage_id')
                                ->label('Cobertura')
                                ->options(function (Get $get): array {
                                    if (blank($get('plan_id')) || blank($get('age_range_id'))) {
                                        return [];
                                    }

                                    if ((int) $get('plan_id') === AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID) {
                                        return [];
                                    }

                                    $ageRange = AgeRange::query()
                                        ->with('fees')
                                        ->find($get('age_range_id'));

                                    if ($ageRange === null) {
                                        return [];
                                    }

                                    return collect($ageRange->fees)
                                        ->mapWithKeys(fn ($fee): array => [
                                            (int) $fee->coverage_id => (string) ($fee->coverage ?? 'Cobertura #'.$fee->coverage_id),
                                        ])
                                        ->all();
                                })
                                ->default(fn (): ?int => $reference?->coverage_id)
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->live()
                                ->required(fn (Get $get): bool => (bool) $get('manual_commercial_config')
                                    && (int) ($get('plan_id') ?? 0) !== AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID)
                                ->visible(fn (Get $get): bool => (int) ($get('plan_id') ?? 0) !== AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID),
                            Select::make('payment_frequency')
                                ->label('Frecuencia de pago')
                                ->options([
                                    'ANUAL' => 'Anual',
                                    'SEMESTRAL' => 'Semestral',
                                    'TRIMESTRAL' => 'Trimestral',
                                ])
                                ->default(fn (): string => (string) ($reference?->payment_frequency ?? 'ANUAL'))
                                ->native(false)
                                ->live()
                                ->required(fn (Get $get): bool => (bool) $get('manual_commercial_config')),
                        ]),
                    Placeholder::make('calculated_cost_preview')
                        ->label('Costo calculado')
                        ->content(fn (Get $get): HtmlString => self::costPreviewHtml($get, $records, $reference))
                        ->columnSpanFull(),
                ])
                ->columnSpanFull()
                ->compact(),
        ];
    }

    /**
     * @param  Collection<int, Renovation>  $records
     */
    private static function selectionSummaryHtml(Collection $records, ?Renovation $reference): HtmlString
    {
        $count = $records->count();
        $countLabel = $count === 1 ? '1 renovación seleccionada' : "{$count} renovaciones seleccionadas";

        $code = e((string) ($reference?->code_affiliation ?? '—'));
        $titular = e((string) ($reference?->affiliation?->full_name_ti ?? '—'));
        $renewalDate = $reference?->date_renewal?->format('d/m/Y') ?? '—';

        $extra = $count > 1
            ? '<p class="mt-2 text-xs text-slate-500 dark:text-slate-400">Se muestra la primera selección como referencia. Cada afiliación se procesará con sus propios familiares.</p>'
            : '';

        $cardClass = self::CARD_CLASS;

        return new HtmlString(<<<HTML
            <div class="{$cardClass}">
                <p class="text-sm font-semibold text-slate-900 dark:text-slate-100">{$countLabel}</p>
                <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-3">
                    <div><dt class="text-slate-500 dark:text-slate-400">Código</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{$code}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Titular</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{$titular}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Renovación</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{$renewalDate}</dd></div>
                </dl>
                {$extra}
            </div>
        HTML);
    }

    private static function automaticProposalHtml(?Renovation $reference): HtmlString
    {
        if ($reference === null) {
            return new HtmlString('<p class="text-sm text-slate-500 dark:text-slate-400">No hay datos de referencia.</p>');
        }

        $plan = e((string) ($reference->plan?->description ?? '—'));
        $coverage = $reference->coverage_id
            ? 'US$ '.number_format((float) ($reference->coverage?->price ?? 0), 2)
            : 'Plan inicial';
        $annual = 'US$ '.number_format((float) $reference->subtotal_anual, 2);
        $frequency = e((string) ($reference->payment_frequency ?? 'ANUAL'));
        $persons = (string) $reference->total_persons;
        $cardClass = self::CARD_CLASS;

        return new HtmlString(<<<HTML
            <div class="{$cardClass}">
                <dl class="grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-3">
                    <div><dt class="text-slate-500 dark:text-slate-400">Plan proyectado</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{$plan}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Cobertura</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{$coverage}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Frecuencia</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{$frequency}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Anual familia</dt><dd class="font-semibold text-emerald-700 dark:text-emerald-300">{$annual}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Personas</dt><dd class="font-medium text-slate-900 dark:text-slate-100">{$persons}</dd></div>
                </dl>
            </div>
        HTML);
    }

    /**
     * @param  Collection<int, Renovation>  $records
     */
    private static function costPreviewHtml(Get $get, Collection $records, ?Renovation $reference): HtmlString
    {
        $planId = (int) ($get('plan_id') ?? 0);
        $ageRangeId = (int) ($get('age_range_id') ?? 0);
        $coverageId = filled($get('coverage_id') ?? null) ? (int) $get('coverage_id') : null;
        $frequency = (string) ($get('payment_frequency') ?? '');

        if ($planId <= 0 || $ageRangeId <= 0 || $frequency === '') {
            return new HtmlString('<p class="text-sm text-slate-500 dark:text-slate-400">Complete plan, rango de edad y frecuencia para ver el cálculo.</p>');
        }

        if ($planId !== AffiliationAffiliateFeeCalculator::INITIAL_PLAN_ID && $coverageId === null) {
            return new HtmlString('<p class="text-sm text-amber-600 dark:text-amber-400">Seleccione la cobertura para calcular el costo.</p>');
        }

        if ($reference === null) {
            return new HtmlString('<p class="text-sm text-slate-500 dark:text-slate-400">—</p>');
        }

        $preview = app(RenovationManualAcceptancePricing::class)->previewFromRenovation(
            $reference->loadMissing('affiliation.affiliates'),
            $planId,
            $coverageId,
            $ageRangeId,
            $frequency,
        );

        if ($preview === null) {
            return new HtmlString('<p class="text-sm text-danger-600 dark:text-danger-400">No se encontró tarifa para la combinación seleccionada.</p>');
        }

        $periodLabel = match ($frequency) {
            'SEMESTRAL' => 'Pago semestral',
            'TRIMESTRAL' => 'Pago trimestral',
            default => 'Pago anual',
        };

        $periodAmount = match ($frequency) {
            'SEMESTRAL' => $preview['subtotal_biannual'],
            'TRIMESTRAL' => $preview['subtotal_quarterly'],
            default => $preview['subtotal_anual'],
        };

        $titular = number_format($preview['titular_annual'], 2);
        $family = number_format($preview['subtotal_anual'], 2);
        $period = number_format($periodAmount, 2);
        $persons = (string) $preview['total_persons'];

        $note = $records->count() > 1
            ? '<p class="mt-3 text-xs text-emerald-800/80 dark:text-emerald-200/80">Vista previa de la primera afiliación. Cada registro recalculará montos según sus familiares.</p>'
            : '';

        $previewCardClass = self::PREVIEW_CARD_CLASS;

        return new HtmlString(<<<HTML
            <div class="{$previewCardClass}">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800 dark:text-emerald-200">Vista previa del costo</p>
                <dl class="mt-3 grid gap-3 text-sm sm:grid-cols-2 lg:grid-cols-4">
                    <div><dt class="text-slate-500 dark:text-slate-400">Titular anual</dt><dd class="text-lg font-semibold text-slate-900 dark:text-slate-100">US$ {$titular}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Familia anual</dt><dd class="text-lg font-semibold text-emerald-700 dark:text-emerald-300">US$ {$family}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">{$periodLabel}</dt><dd class="text-lg font-semibold text-slate-900 dark:text-slate-100">US$ {$period}</dd></div>
                    <div><dt class="text-slate-500 dark:text-slate-400">Personas</dt><dd class="text-lg font-semibold text-slate-900 dark:text-slate-100">{$persons}</dd></div>
                </dl>
                {$note}
            </div>
        HTML);
    }
}
