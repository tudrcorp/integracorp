<?php

namespace App\Filament\Agents\Resources\Affiliations\RelationManagers;

use Filament\Tables\Table;
use Filament\Actions\CreateAction;
use Filament\Support\Enums\Alignment;
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
                TextColumn::make('currency')
                    ->searchable()
                    ->label('Metodo de pago'),
                TextColumn::make('reference_payment')
                    ->searchable()
                    ->label('Referencia'),
                IconColumn::make('document_usd')
                    ->alignment(Alignment::Center)
                    ->label('Comprobante')
                    ->icon(function ($record) {
                // Muestra un ícono si la imagen existe
                return $record->document_usd
                // return Storage::disk('local')->files($record->document_usd)
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                // Color del ícono basado en la existencia de la imagen
                return $record->document_usd
                // return Storage::disk('local')->files($record->document_usd)
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        // return Storage::disk('local')->files($record->document_usd);
                        return asset('storage/' . $record->document_usd);
                    })
                    ->openUrlInNewTab(),
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
            ->headerActions([
                // CreateAction::make(),
            ]);
    }
}