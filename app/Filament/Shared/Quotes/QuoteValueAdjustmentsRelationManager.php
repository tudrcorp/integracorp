<?php

declare(strict_types=1);

namespace App\Filament\Shared\Quotes;

use App\Models\QuoteValueAdjustment;
use App\Models\User;
use App\Support\Quotes\Adjustments\QuoteValueAdjustments;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use RuntimeException;
use Throwable;

/**
 * «Historial de ajustes» de una cotización (individual o corporativa): qué cambió, en qué
 * afiliación y avisos, y su reverso.
 */
class QuoteValueAdjustmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'valueAdjustments';

    protected static ?string $title = 'Historial de ajustes';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return AdjustQuoteValuesAction::canUse() || $ownerRecord->valueAdjustments()->exists();
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Historial de ajustes de valores')
            ->description('Cada ajuste de tarifa o personas, con lo que cambió en la afiliación y sus avisos de cobro. El más reciente se revierte primero.')
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('Sin ajustes')
            ->emptyStateDescription('Use «Ajustar valores» arriba para corregir tarifas o personas de esta cotización.')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y h:i A')->sortable(),
                TextColumn::make('created_by_name')->label('Analista')->placeholder('—'),
                TextColumn::make('lines')
                    ->label('Cambios')
                    ->state(fn (QuoteValueAdjustment $record): string => collect((array) $record->lines)->map(static fn (array $line): string => $line['label'].': US$ '.$line['fee_before'].' × '.$line['persons_before'].' → US$ '.$line['fee_after'].' × '.$line['persons_after'])->implode(' | '))
                    ->wrap()
                    ->limit(160),
                TextColumn::make('effect')
                    ->label('Afiliación')
                    ->state(function (QuoteValueAdjustment $record): string {
                        $affiliation = (array) ($record->effects['affiliation'] ?? []);
                        if (! ($affiliation['cascade'] ?? false)) {
                            return 'Sin cambios en la afiliación';
                        }

                        return $affiliation['code'].': cuota US$ '.$affiliation['totals']['before']['total_amount'].' → '.$affiliation['totals']['after']['total_amount'].' · '.count((array) $affiliation['collections']).' aviso(s)';
                    })
                    ->wrap(),
                TextColumn::make('reason')->label('Motivo')->wrap()->limit(120),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => $state === QuoteValueAdjustment::STATUS_APPLIED ? 'success' : 'gray')
                    ->description(fn (QuoteValueAdjustment $record): ?string => $record->reverted_at !== null ? 'Revertido el '.$record->reverted_at->format('d/m/Y').' por '.$record->reverted_by_name : null),
            ])
            ->recordActions([
                Action::make('detail')
                    ->label('Ver detalle')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->modalHeading(fn (QuoteValueAdjustment $record): string => 'Ajuste #'.$record->getKey().' · '.$record->quote_code)
                    ->modalWidth(Width::FourExtraLarge)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Cerrar')
                    ->modalContent(fn (QuoteValueAdjustment $record) => view('filament.shared.quotes.value-adjustment-detail', ['adjustment' => $record])),
                Action::make('revert')
                    ->label('Revertir')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (QuoteValueAdjustment $record): bool => $record->isApplied() && AdjustQuoteValuesAction::canUse())
                    ->modalHeading(fn (QuoteValueAdjustment $record): string => 'Revertir el ajuste #'.$record->getKey())
                    ->modalDescription('La cotización, la afiliación y sus avisos POR PAGAR vuelven a como estaban. Si un aviso ajustado ya se pagó, la diferencia se compensa en el siguiente aviso pendiente del año.')
                    ->modalIcon(Heroicon::OutlinedArrowUturnLeft)
                    ->modalIconColor('danger')
                    ->modalWidth(Width::ThreeExtraLarge)
                    ->schema(fn (QuoteValueAdjustment $record): array => [
                        Placeholder::make('revert_preview')
                            ->hiddenLabel()
                            ->content(fn (): HtmlString => new HtmlString(view('filament.shared.quotes.value-adjustment-revert-preview', ['preview' => QuoteValueAdjustments::revertPreview($record)])->render())),
                        Textarea::make('reason')
                            ->label('Motivo del reverso')
                            ->placeholder('Ej.: La tarifa negociada no fue aprobada por la gerencia comercial.')
                            ->rows(2)
                            ->required()
                            ->minLength(QuoteValueAdjustments::REASON_MIN_LENGTH)
                            ->maxLength(1000),
                    ])
                    ->modalSubmitActionLabel('Revertir ajuste')
                    ->modalSubmitAction(fn (Action $action): Action => $action->color('danger'))
                    ->action(function (array $data, QuoteValueAdjustment $record, Action $action): void {
                        abort_unless(AdjustQuoteValuesAction::canUse(), 403);
                        $user = Auth::user();
                        abort_unless($user instanceof User, 403);

                        try {
                            QuoteValueAdjustments::revert($record, (string) ($data['reason'] ?? ''), $user);
                        } catch (RuntimeException $exception) {
                            Notification::make()->title('No se revirtió')->body($exception->getMessage())->danger()->persistent()->send();
                            $action->halt();

                            return;
                        } catch (Throwable $exception) {
                            report($exception);
                            Notification::make()->title('No se revirtió')->body('Error inesperado. No se cambió nada.')->danger()->send();
                            $action->halt();

                            return;
                        }

                        Notification::make()->title('Ajuste revertido')->body('La cotización, la afiliación y sus avisos POR PAGAR volvieron a los valores anteriores.')->success()->send();
                    }),
            ]);
    }
}
