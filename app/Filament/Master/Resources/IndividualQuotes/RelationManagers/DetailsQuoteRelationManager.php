<?php

declare(strict_types=1);

namespace App\Filament\Master\Resources\IndividualQuotes\RelationManagers;

use App\Models\Agency;
use App\Models\IndividualQuote;
use App\Support\IndividualQuotes\IndividualQuotePurchase;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\Width;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

class DetailsQuoteRelationManager extends RelationManager
{
    protected static string $relationship = 'detailsQuote';

    public function table(Table $table): Table
    {
        return $table
            ->heading('DETALLES DE LA COTIZACIÓN')
            ->description('COBERTURAS, TARIFAS AGRUPADAS POR EL RANGO DE EDAD')
            ->recordTitleAttribute('individual_quote_id')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['plan:id,description', 'coverage:id,price', 'ageRange:id,range']))
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->sortable(),
                TextColumn::make('ageRange.range')
                    ->label('Rango de Edad')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Cobertura')
                    ->searchable()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('fee')
                    ->label('Tarifa individual')
                    ->alignCenter()
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_anual')
                    ->label('Total anual')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_biannual')
                    ->label('Total semestral')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Total trimestral')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_monthly')
                    ->label('Total Mensual')
                    ->alignCenter()
                    ->description(fn ($record): string => $record->total_persons.' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$')
                    ->hidden(fn (): bool => Agency::where('code', Auth::user()->code_agency)->first()->activate_monthly_frequency == 0),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verde',
                            'APROBADA' => 'success',
                            'EJECUTADA' => 'azul',
                        };
                    })
                    ->sortable(),
            ])
            ->defaultGroup('ageRange.range')
            ->filters([
                SelectFilter::make('coverage_id')
                    ->label('Lista de coberturas')
                    ->relationship('coverage', 'price')
                    ->attribute('sucursal_id'),
            ])
            ->headerActions([
                Action::make('buy_plan')
                    ->label('Comprar plan')
                    ->icon('heroicon-s-shopping-cart')
                    ->color('success')
                    ->hidden(fn (): bool => ! IndividualQuotePurchase::canPurchase($this->getOwnerRecord()))
                    ->requiresConfirmation()
                    ->modalHeading('COMPRAR PLAN')
                    ->modalIcon('heroicon-s-shopping-cart')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalDescription(new HtmlString(Blade::render(<<<'BLADE'
                                <div class="fi-section-header-description mt-5 mb-5">
                                    Felicitaciones!.
                                    <br>
                                Solo falta completar el formulario de pre-afiliación
                                </div>
                        BLADE)))
                    ->action(function () {
                        /** @var IndividualQuote $quote */
                        $quote = $this->getOwnerRecord();

                        try {
                            $url = IndividualQuotePurchase::start($quote, 'master');

                            Notification::make()
                                ->title('COTIZACION INDIVIDUAL APROBADA')
                                ->body('Nro.'.$quote->code.', puede proceder a realizar la pre-afiliación')
                                ->icon('heroicon-s-user-group')
                                ->iconColor('success')
                                ->persistent()
                                ->success()
                                ->send();

                            return redirect()->to($url);
                        } catch (\RuntimeException $exception) {
                            Notification::make()
                                ->title('No se puede comprar este plan')
                                ->body($exception->getMessage())
                                ->warning()
                                ->send();
                        } catch (\Throwable $th) {
                            Log::error('MASTER: falla al comprar plan desde la tabla de montos de la cotización individual', [
                                'individual_quote_id' => $quote->getKey(),
                                'exception' => $th,
                            ]);

                            Notification::make()
                                ->title('ERROR')
                                ->body('Ocurrió un error inesperado. Intenta nuevamente o contacta a soporte.')
                                ->icon('heroicon-s-x-circle')
                                ->iconColor('danger')
                                ->danger()
                                ->send();
                        }

                        return null;
                    }),
            ]);
    }
}
