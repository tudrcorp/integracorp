<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\PopulationBillingAdjustments\Actions;

use App\Filament\Administration\Resources\PopulationBillingAdjustments\PopulationBillingAdjustmentResource;
use App\Models\CorporateBillingAdjustment;
use App\Models\CorporatePopulationUpdate;
use App\Models\User;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingAdjuster;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingCalculator;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingPreview;
use App\Support\AffiliationCorporates\BillingAdjustments\PopulationBillingTray;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * Acciones de «Ajustes por población»: calcular y aplicar, revertir y descargar el aviso en PDF.
 * Las mismas en la tabla y en la ficha. Aplicar y revertir exigen el permiso de acción propio;
 * el servicio también lo verifica.
 */
final class PopulationBillingAdjustmentActions
{
    public static function apply(): Action
    {
        return Action::make('applyPopulationBillingAdjustment')
            ->label('Calcular y aplicar ajuste')
            ->icon(Heroicon::OutlinedCalculator)
            ->color('primary')
            ->modalHeading(fn (CorporatePopulationUpdate $record): string => 'Ajuste por población · '.($record->affiliationCorporate?->code ?? ''))
            ->modalDescription('Revise el cálculo antes de aplicar. Nada se guarda hasta pulsar «Aplicar ajuste».')
            ->modalIcon(Heroicon::OutlinedCalculator)
            ->modalWidth(Width::FiveExtraLarge)
            ->modalSubmitActionLabel('Aplicar ajuste')
            ->modalCancelActionLabel('Cancelar')
            ->visible(fn (CorporatePopulationUpdate $record): bool => PopulationBillingAdjuster::userCan()
                && PopulationBillingTray::state($record) === PopulationBillingTray::STATE_PENDING)
            ->authorize(fn (): bool => PopulationBillingAdjuster::userCan())
            ->fillForm(function (CorporatePopulationUpdate $record): array {
                $date = $record->applied_at?->format('d/m/Y');

                return [
                    'effective_date' => $date,
                    'fingerprint' => self::fingerprint($record, $date),
                    'reason' => '',
                    'confirmed' => false,
                ];
            })
            ->schema([
                DatePicker::make('effective_date')
                    ->label('Fecha efectiva del cambio de población')
                    ->helperText(fn (CorporatePopulationUpdate $record): string => 'Desde qué día valen las altas y bajas. Por defecto, el día en que se aplicó la población ('
                        .($record->applied_at?->format('d/m/Y') ?? '—').'). Se acepta hasta '.PopulationBillingAdjuster::EFFECTIVE_DATE_WINDOW_DAYS.' días antes o después.')
                    ->required()
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->format('d/m/Y')
                    ->closeOnDateSelection()
                    ->minDate(fn (CorporatePopulationUpdate $record) => $record->applied_at?->copy()->subDays(PopulationBillingAdjuster::EFFECTIVE_DATE_WINDOW_DAYS))
                    ->maxDate(fn (CorporatePopulationUpdate $record) => $record->applied_at?->copy()->addDays(PopulationBillingAdjuster::EFFECTIVE_DATE_WINDOW_DAYS))
                    ->live()
                    ->afterStateUpdated(fn (?string $state, Set $set, CorporatePopulationUpdate $record) => $set('fingerprint', self::fingerprint($record, $state))),
                Hidden::make('fingerprint'),
                Placeholder::make('preview')
                    ->label('Cálculo (vista previa, todavía no se guarda nada)')
                    ->content(function (Get $get, CorporatePopulationUpdate $record): HtmlString {
                        try {
                            $plan = PopulationBillingAdjuster::plan($record, $get('effective_date'));

                            return PopulationBillingPreview::html(PopulationBillingAdjuster::summaryFromPlan($plan), $plan['blockers']);
                        } catch (Throwable $exception) {
                            report($exception);

                            return new HtmlString('<p class="text-sm text-red-700 dark:text-red-300">No se pudo calcular: '.e($exception->getMessage()).'</p>');
                        }
                    })
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label('Motivo del ajuste')
                    ->required()
                    ->minLength(PopulationBillingAdjuster::REASON_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->placeholder('Ejemplo: inclusión de 24 personas según el archivo de MAPFRE del 21/10/2026, efectivas desde esa fecha.')
                    ->columnSpanFull(),
                Checkbox::make('confirmed')
                    ->label('Revisé el cálculo. Entiendo que se emite el aviso por ajuste y que cambian los montos de las cuotas por pagar mostradas.')
                    ->accepted()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (CorporatePopulationUpdate $record, array $data, Action $action): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                try {
                    $adjustment = PopulationBillingAdjuster::apply($record, $data['effective_date'] ?? null, (string) ($data['reason'] ?? ''), $user, (string) ($data['fingerprint'] ?? ''));
                } catch (RuntimeException $exception) {
                    self::failed('No se aplicó el ajuste', $exception->getMessage());
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se aplicó el ajuste', 'Ocurrió un error inesperado y se deshizo todo.');
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Ajuste aplicado')
                    ->body('Aviso por ajuste: '.PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($adjustment->charge_amount))
                        .'. Cuotas ajustadas: '.$adjustment->installments_adjusted.' ('.PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($adjustment->installment_delta)).' cada una). '
                        .'Puede ver o descargar el aviso en PDF desde esta ficha.')
                    ->success()
                    ->persistent()
                    ->send();

                $action->redirect(PopulationBillingAdjustmentResource::getUrl('view', array_filter([
                    'record' => $record,
                    'action' => $adjustment->charge_collection_id !== null ? 'previewPopulationBillingAdjustmentPdf' : null,
                ])));
            });
    }

    public static function revert(): Action
    {
        return Action::make('revertPopulationBillingAdjustment')
            ->label('Revertir ajuste')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading('Revertir ajuste por población')
            ->modalDescription('Las cuotas vuelven a su monto anterior y el aviso por ajuste queda ANULADO. Es todo o nada.')
            ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Revertir ahora')
            ->modalCancelActionLabel('Cancelar')
            ->visible(fn (CorporatePopulationUpdate $record): bool => PopulationBillingAdjuster::userCan()
                && PopulationBillingTray::appliedAdjustment($record) instanceof CorporateBillingAdjustment)
            ->authorize(fn (): bool => PopulationBillingAdjuster::userCan())
            ->schema([
                Placeholder::make('revert_preview')
                    ->hiddenLabel()
                    ->content(function (CorporatePopulationUpdate $record): HtmlString {
                        $adjustment = PopulationBillingTray::appliedAdjustment($record);

                        if (! $adjustment instanceof CorporateBillingAdjustment) {
                            return new HtmlString('<p class="text-sm">No hay un ajuste aplicado.</p>');
                        }

                        $blocker = PopulationBillingAdjuster::revertBlocker($adjustment);

                        if ($blocker !== null) {
                            return new HtmlString('<p class="text-sm font-semibold text-red-700 dark:text-red-300">No se puede revertir: '.e($blocker).'</p>');
                        }

                        $charge = $adjustment->chargeCollection;

                        return new HtmlString('<ul class="list-disc space-y-1 pl-5 text-sm">'
                            .($charge !== null ? '<li>El aviso por ajuste <strong>'.e((string) $charge->collection_invoice_number).'</strong> ('.e(PopulationBillingCalculator::money(PopulationBillingAdjuster::cents($charge->total_amount))).') queda ANULADO.</li>' : '')
                            .'<li>'.(int) $adjustment->installments_adjusted.' '.((int) $adjustment->installments_adjusted === 1 ? 'cuota vuelve' : 'cuotas vuelven').' a su monto anterior ('.e(PopulationBillingCalculator::money(-PopulationBillingAdjuster::cents($adjustment->installment_delta))).' cada una).</li>'
                            .'<li>La población no cambia. Después puede volver a calcular el ajuste con otra fecha.</li></ul>');
                    })
                    ->columnSpanFull(),
                Textarea::make('reason')
                    ->label('Motivo de la reversión')
                    ->required()
                    ->minLength(PopulationBillingAdjuster::REASON_MIN_LENGTH)
                    ->maxLength(2000)
                    ->rows(3)
                    ->columnSpanFull(),
                Checkbox::make('confirmed')
                    ->label('Entiendo que el aviso por ajuste se anula y las cuotas vuelven a su monto anterior.')
                    ->accepted()
                    ->required()
                    ->columnSpanFull(),
            ])
            ->action(function (CorporatePopulationUpdate $record, array $data, Action $action): void {
                $user = Auth::user();
                $adjustment = PopulationBillingTray::appliedAdjustment($record);

                if (! $user instanceof User || ! $adjustment instanceof CorporateBillingAdjustment) {
                    return;
                }

                try {
                    PopulationBillingAdjuster::revert($adjustment, (string) ($data['reason'] ?? ''), $user);
                } catch (RuntimeException $exception) {
                    self::failed('No se revirtió el ajuste', $exception->getMessage());
                    $action->halt();

                    return;
                } catch (Throwable $exception) {
                    report($exception);
                    self::failed('No se revirtió el ajuste', 'Ocurrió un error inesperado y se deshizo todo.');
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Ajuste revertido')
                    ->body('Las cuotas volvieron a su monto anterior y el aviso por ajuste quedó ANULADO.')
                    ->success()
                    ->send();

                $record->unsetRelation('billingAdjustments');
            });
    }

    /**
     * Vista previa del aviso en una ventana, con spinner mientras carga. El PDF lo sirve
     * {@see \App\Http\Controllers\PopulationBillingAdjustmentPdfController}: desde el disco al
     * instante, o generado en ese momento si el job todavía no lo dejó listo.
     */
    public static function previewPdf(): Action
    {
        return Action::make('previewPopulationBillingAdjustmentPdf')
            ->label('Ver aviso PDF')
            ->icon(Heroicon::OutlinedEye)
            ->color('gray')
            ->visible(fn (CorporatePopulationUpdate $record): bool => self::pdfAdjustment($record) instanceof CorporateBillingAdjustment)
            ->modalHeading(fn (CorporatePopulationUpdate $record): string => 'Aviso por ajuste · '.(self::pdfAdjustment($record)?->chargeCollection?->collection_invoice_number ?? ''))
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->modalContent(function (CorporatePopulationUpdate $record): View {
                $adjustment = self::pdfAdjustment($record);

                return view('filament.administration.population-billing-adjustments.pdf-preview', [
                    'previewUrl' => self::pdfUrl($adjustment),
                    'downloadUrl' => self::pdfUrl($adjustment, download: true),
                    'reverted' => $adjustment !== null && ! $adjustment->isApplied(),
                ]);
            });
    }

    /**
     * Descarga directa: un enlace normal, sin pasar por Livewire ni esperar a la cola.
     */
    public static function downloadPdf(): Action
    {
        return Action::make('downloadPopulationBillingAdjustmentPdf')
            ->label('Descargar aviso PDF')
            ->icon(Heroicon::OutlinedDocumentArrowDown)
            ->color('gray')
            ->visible(fn (CorporatePopulationUpdate $record): bool => self::pdfAdjustment($record) instanceof CorporateBillingAdjustment)
            ->url(fn (CorporatePopulationUpdate $record): string => self::pdfUrl(self::pdfAdjustment($record), download: true));
    }

    public static function pdfUrl(?CorporateBillingAdjustment $adjustment, bool $download = false): string
    {
        if (! $adjustment instanceof CorporateBillingAdjustment) {
            return '#';
        }

        return route('administration.population-billing-adjustments.pdf', array_filter([
            'adjustment' => $adjustment->getKey(),
            'download' => $download ? 1 : null,
        ]));
    }

    /**
     * El ajuste con aviso emitido que se muestra (aplicado o, si no hay, el último revertido).
     */
    private static function pdfAdjustment(CorporatePopulationUpdate $record): ?CorporateBillingAdjustment
    {
        $adjustment = PopulationBillingTray::displayedAdjustment($record);

        return $adjustment instanceof CorporateBillingAdjustment && $adjustment->charge_collection_id !== null ? $adjustment : null;
    }

    private static function fingerprint(CorporatePopulationUpdate $record, ?string $date): string
    {
        try {
            return (string) PopulationBillingAdjuster::plan($record, $date)['fingerprint'];
        } catch (Throwable) {
            return '';
        }
    }

    private static function failed(string $title, string $body): void
    {
        Notification::make()
            ->title($title)
            ->body($body.' No se cambió ningún dato.')
            ->danger()
            ->persistent()
            ->send();
    }
}
