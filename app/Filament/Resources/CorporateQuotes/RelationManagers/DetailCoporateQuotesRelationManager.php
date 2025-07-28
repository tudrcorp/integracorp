<?php

namespace App\Filament\Resources\CorporateQuotes\RelationManagers;

use Carbon\Carbon;
use App\Models\Fee;
use App\Models\Collection;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\CreateAction;
use Filament\Actions\ImportAction;
use Illuminate\Support\Facades\Log;
use App\Models\DetailCorporateQuote;
use Filament\Actions\BulkActionGroup;
use Illuminate\Validation\Rules\File;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use App\Http\Controllers\UtilsController;
use App\Models\CorporateQuoteRequestData;
use Filament\Tables\Filters\SelectFilter;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\RelationManagers\RelationManager;
use App\Http\Controllers\DetailCorporateQuotesController;
use App\Filament\Imports\CorporateQuoteRequestDataImporter;
use App\Filament\Resources\CorporateQuotes\CorporateQuoteResource;

class DetailCoporateQuotesRelationManager extends RelationManager
{
    protected static string $relationship = 'detailCoporateQuotes';

    protected static ?string $title = 'CALCULO DE COTIZACIÓN';

    protected static ?string $relatedResource = CorporateQuoteResource::class;

    public function table(Table $table): Table
    {
        return $table
            ->heading('DETALLE DE COTIZACION')
            ->description('Lista de detalles de planes y coberturas con sus tarifas, agrupas por rango de edades')
            ->recordTitleAttribute('individual_quote_id')
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
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_biannual')
                    ->label('Total semestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('subtotal_quarterly')
                    ->label('Total trimestral')
                    ->alignCenter()
                    ->description(fn($record): string => $record->total_persons . ' personas')
                    ->numeric(decimalPlaces: 0)
                    ->suffix(' UD$'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA' => 'verde',
                            'APROBADA' => 'success',
                            'EJECUTADA' => 'azul',
                            'ACTIVA-PENDIENTE' => 'azul',
                        };
                    })
                    ->sortable(),
            ])
            //agrupar por planes y por coberturas
            ->defaultGroup('ageRange.range')
            ->filters([
                SelectFilter::make('plan_id')
                    ->label('Lista de planes')
                    ->multiple()
                    ->preload()
                    ->relationship('plan', 'description')
                    ->attribute('sucursal_id'),
                SelectFilter::make('coverage_id')
                    ->label('Lista de coberturas')
                    ->multiple()
                    ->preload()
                    ->relationship('coverage', 'price')
                    ->attribute('sucursal_id'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    BulkAction::make('increase')
                        ->label('Incremento')
                        ->color('success')
                        ->icon('heroicon-c-receipt-percent')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            TextInput::make('increase')
                                ->helperText('El porcentaje debe ser un valor entre 0 y 100. Este aumento se aplica sobre todas las coberturas seleccionadas.')
                                ->label('Porcentaje(%) de Incremento')
                                ->numeric()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {

                            if ($data['increase'] > 100 || $data['increase'] < 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('El porcentaje debe ser un valor entre 0 y 100')
                                    ->send();
                                return;
                            }

                            foreach ($records as $record) {
                                $record->subtotal_anual     = DetailCorporateQuotesController::coverage_increase($record->subtotal_anual, $data['increase']);
                                $record->subtotal_quarterly = DetailCorporateQuotesController::coverage_increase($record->subtotal_quarterly, $data['increase']);
                                $record->subtotal_biannual  = DetailCorporateQuotesController::coverage_increase($record->subtotal_biannual, $data['increase']);
                                $record->subtotal_monthly   = DetailCorporateQuotesController::coverage_increase($record->subtotal_monthly, $data['increase']);
                                $record->save();
                            }

                            Notification::make()
                                ->success()
                                ->title('Subtotal Actualizado')
                                ->send();
                        }),
                    BulkAction::make('discount')
                        ->label('Descuento')
                        ->color('warning')
                        ->icon('heroicon-c-receipt-percent')
                        ->requiresConfirmation()
                        ->deselectRecordsAfterCompletion()
                        ->form([
                            TextInput::make('discount')
                                ->helperText('El porcentaje debe ser un valor entre 0 y 100. Este descuento se aplica sobre todas las coberturas seleccionadas.')
                                ->label('Porcentaje(%) de Descuento')
                                ->numeric()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {

                            if ($data['discount'] > 100 || $data['discount'] < 0) {
                                Notification::make()
                                    ->warning()
                                    ->title('El porcentaje debe ser un valor entre 0 y 100')
                                    ->send();
                                return;
                            }

                            foreach ($records as $record) {
                                $record->subtotal_anual     = DetailCorporateQuotesController::coverage_discount($record->subtotal_anual, $data['discount']);
                                $record->subtotal_quarterly = DetailCorporateQuotesController::coverage_discount($record->subtotal_quarterly, $data['discount']);
                                $record->subtotal_biannual  = DetailCorporateQuotesController::coverage_discount($record->subtotal_biannual, $data['discount']);
                                $record->subtotal_monthly   = DetailCorporateQuotesController::coverage_discount($record->subtotal_monthly, $data['discount']);
                                $record->save();
                            }

                            Notification::make()
                                ->success()
                                ->title('Subtotal Actualizado')
                                ->send();
                        })
                ]),
            ]);
    }

    public static function getFee(Get $get, Set $set): void
    {
        if ($get('age_range_id') == null || $get('coverage_id') == null) {
            $set('total_persons', '');
            $set('subtotal', '');
            return;
        }

        $fee = Fee::select('price', 'coverage_id')
            ->where('coverage_id', $get('coverage_id'))
            ->where('age_range_id', $get('age_range_id'))
            ->first();

        Log::info($fee, ['rango' => $get('age_range_id'), 'coverage' => $get('coverage_id')]);
        $calculo = $get('total_persons') * $fee->price;
        $set('subtotal', number_format($calculo, 2, '.', ''));
    }
}