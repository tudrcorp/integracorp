<?php

namespace App\Filament\Agents\Resources\Affiliations\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Actions\CreateAction;
use Filament\Support\Enums\Alignment;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Storage;
use Filament\Resources\RelationManagers\RelationManager;
use App\Filament\Agents\Resources\Affiliations\AffiliationResource;

class PaidMembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'paid_memberships';

    protected static ?string $title = 'Pagos registrados';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('affiliation_id')
            ->columns([
                TextColumn::make('plan.description')
                    ->label('Plan')
                    ->badge()
                    ->color('azulOscuro')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('coverage.price')
                    ->label('Covertura')
                    ->badge()
                    ->color('azulOscuro')
                    ->suffix(' US$')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('payment_frequency')
                    ->label('Frecuencia de pago')
                    ->badge()
                    ->color('azulOscuro'),
                TextColumn::make('total_amount')
                    ->label('Pago total')
                    ->numeric()
                    ->suffix(' US$'),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable()
                    ->label('Metodo de pago'),
                TextColumn::make('payment_method')
                    ->badge()
                    ->searchable()
                    ->label('Metodo de pago'),
                TextColumn::make('payment_method_usd')
                    ->label('Pago multiple')
                    ->prefix('US$: ')
                    ->description(function ($record) {
                        return $record->payment_method_ves != 'N/A' ? 'VES: ' . $record->payment_method_ves : 'VES: N/A';
                    })
                    ->searchable(),
                TextColumn::make('bank_usd')
                    ->searchable()
                    ->label('Banco')
                    ->description(function ($record) {
                        return $record->bank_ves != 'N/A' ? $record->bank_ves : 'N/A';
                    }),
                TextColumn::make('reference_payment_usd')
                    ->label('Referencia de pago')
                    ->prefix('Ref(Zelle): ')
                    ->description(function ($record) {
                        return $record->reference_payment_ves != 'N/A' ?  'Ref(VES): ' . $record->reference_payment_ves : 'Ref(VES): N/A';
                    })
                    ->searchable(),
                IconColumn::make('document_ves')
                    ->alignment(Alignment::Center)
                    ->label('Comprobante')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document_ves != 'N/A'
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document_ves != 'N/A'
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->document_ves);
                    })
                    ->openUrlInNewTab(),
                IconColumn::make('document_usd')
                    ->alignment(Alignment::Center)
                    ->label('Comprobante')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document_usd != 'N/A'
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document_usd != 'N/A'
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->document_usd);
                    })
                    ->openUrlInNewTab(),
                TextColumn::make('pay_amount_usd')
                    ->label('Pago registrado')
                    ->suffix(' US$')
                    ->description(function ($record) {
                        return $record->pay_amount_ves != 'N/A' ? $record->pay_amount_ves . ' VES' : 'N/A';
                    }),
                TextColumn::make('tasa_bcv')
                    ->label('Tasa BCV')
                    ->suffix(' VES')
                    ->numeric(),
                TextColumn::make('payment_date')
                    ->label('Pagado desde'),
                TextColumn::make('prox_payment_date')
                    ->label('Pagado hasta'),
                TextColumn::make('renewal_date')
                    ->label('Renovacion')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('created_at')
                    ->label('Cargado el:')
                    ->badge()
                    ->dateTime('d/m/Y'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function ($record) {
                        return $record->status == 'APROBADO' ? 'success' : 'warning';
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('details')
                    ->label('Ver Observaciones')
                    ->icon('fontisto-info')
                    ->color('primary')
                    ->modalHeading('Observaciones del pago')
                    ->modalIcon('fontisto-info')
                    ->modalWidth(Width::ExtraLarge)
                    ->modalSubmitAction(false)
                    ->button()
                    ->form([
                        Textarea::make('observations_payment')
                            ->label('Observaciones')
                            ->disabled()
                            ->autoSize()
                            ->default(fn($record) => $record->observations_payment)
                            ->required(),
                    ]),
            ])
            ->headerActions([
                // CreateAction::make(),
            ]);
    }
}
