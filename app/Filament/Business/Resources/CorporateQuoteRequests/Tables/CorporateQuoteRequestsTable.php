<?php

namespace App\Filament\Business\Resources\CorporateQuoteRequests\Tables;


use App\Models\Agency;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\ActionGroup;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Auth;
use App\Models\CorporateQuoteRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Support\Enums\Alignment;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Database\Eloquent\Builder;

class CorporateQuoteRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            // ->query(CorporateQuoteRequest::query()->where('ownerAccountManagers', Auth::user()->id))
            ->query(function (Builder $query) {
                if (Auth::user()->is_accountManagers) {
                    return CorporateQuoteRequest::query()->where('ownerAccountManagers', Auth::user()->id);
                }
                return CorporateQuoteRequest::query();
            })
            ->defaultSort('id', 'desc')
            ->heading('TABLA DE SOLICITUDES DE COTIZACIONES CORPORATIVAS')
            ->description('Lista de solicitudes de cotizaciones corporativas')
            ->columns([
                TextColumn::make('code_agency')
                    ->prefix(function ($record) {
                        $agency_type = Agency::select('agency_type_id')
                            ->where('code', $record->code_agency)
                            ->with('typeAgency')
                            ->first();

                        return isset($agency_type) ? $agency_type->typeAgency->definition . ' - ' : 'MASTER - ';
                    })
                    ->alignCenter()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-s-building-library')
                    ->searchable(),

                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('warning')
                    ->searchable(),
                TextColumn::make('accountManager.name')
                    ->label('Account Manager')
                    ->icon('heroicon-o-shield-check')
                    ->badge()
                    ->color('warning'),
                TextColumn::make('agent_id')
                    ->label('Registrado por:')
                    ->prefix('AGT-000')
                    ->alignCenter()
                    ->badge()
                    ->color('azulOscuro')
                    ->searchable(),
                TextColumn::make('full_name')
                    ->label('Solicitada por:')
                    ->searchable(),
                TextColumn::make('rif')
                    ->label('Rif:')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('Telefono')
                    ->searchable(),
                TextColumn::make('state.definition')
                    ->label('Estado')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('region')
                    ->label('Región')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->color(function (string $state): string {
                        return match ($state) {
                            'PRE-APROBADA'  => 'verdeOpaco',
                            'PROCESADA'      => 'success',
                            'APROBADA'      => 'success',
                            'ANULADA'       => 'warning',
                            'DECLINADA'     => 'danger',
                            default => 'azul',
                        };
                    })
                    ->icon(function (mixed $state): ?string {
                        return match ($state) {
                            'PRE-APROBADA'  => 'heroicon-c-information-circle',
                            'APROBADA'      => 'heroicon-s-check-circle',
                            'PROCESADA'      => 'heroicon-s-check-circle',
                            'ANULADA'       => 'heroicon-s-exclamation-circle',
                            'DECLINADA'     => 'heroicon-c-x-circle',
                        };
                    })
                    ->searchable(),
                TextColumn::make('created_by')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('document')
                    ->alignment(Alignment::Center)
                    ->label('Archivo')
                    ->icon(function ($record) {
                        // Muestra un ícono si la imagen existe
                        return $record->document
                            ? 'heroicon-o-check-circle' // Ícono de "check" si la imagen existe
                            : 'heroicon-o-x-circle';   // Ícono de "x" si no existe
                    })
                    // ->iconPosition(IconPosition::After), // Posición del ícono
                    ->color(function ($record) {
                        // Color del ícono basado en la existencia de la imagen
                        return $record->document
                            ? 'success' // Verde si la imagen existe
                            : 'danger'; // Rojo si no existe
                    })
                    ->url(function ($record) {
                        return asset('storage/' . $record->document);
                    })
                    ->openUrlInNewTab()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    /**APROBAR */
                    Action::make('approve')
                        ->label('Ir a cotizar')
                        ->icon('heroicon-m-clipboard-document-check')
                        ->color('success')
                        ->requiresConfirmation()
                        ->modalHeading('APROBACIÓN DIRECTA DE COTIZACIÓN INDIVIDUAL')
                        ->modalWidth(Width::ExtraLarge)
                        ->modalIcon('heroicon-s-check-circle')
                        ->action(function (CorporateQuoteRequest $record) {
                            // dd($record);
                            // $record->update(['status' => 'APROBADA']);
                            // Notification::success('Cotización aprobada con exito!')->send();
                            return redirect()->route('filament.admin.resources.corporate-quotes.create', ['corporate_quote_request_id' => $record->id]);
                        })
                        ->hidden(fn(CorporateQuoteRequest $record): bool => $record->status == 'APROBADA'),
                ])->icon('heroicon-c-ellipsis-vertical')->color('azulOscuro')
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}