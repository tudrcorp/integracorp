<?php

namespace App\Filament\Business\Resources\AccountManagers\RelationManagers;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Models\Agency;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AgenciesRelationManager extends RelationManager
{
    protected static string $relationship = 'agencies';

    protected static ?string $title = 'Agencias de corretaje';

    protected static string|BackedEnum|null $icon = 'heroicon-o-building-library';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name_corporative')
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['typeAgency']))
            ->defaultSort('created_at', 'desc')
            ->heading('Agencias')
            ->description('Agencias Master y General asignadas a este account manager.')
            ->emptyStateHeading('Sin agencias asignadas')
            ->emptyStateDescription('Aún no hay agencias vinculadas a este ejecutivo.')
            ->striped()
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->icon('heroicon-m-qr-code')
                    ->formatStateUsing(function (?string $state, Agency $record): string {
                        $type = $record->typeAgency?->definition ?? '';
                        $code = $state ?? '';

                        return $type !== '' ? "{$type} · {$code}" : $code;
                    })
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name_corporative')
                    ->label('Razón social')
                    ->icon('heroicon-m-building-office-2')
                    ->weight(FontWeight::SemiBold)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->extraCellAttributes(fn (): array => [
                        'class' => 'min-w-44 sm:min-w-56',
                    ]),
                TextColumn::make('rif')
                    ->label('RIF')
                    ->icon('heroicon-m-document-text')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('ci_responsable')
                    ->label('CI responsable')
                    ->icon('heroicon-m-identification')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->icon('heroicon-m-map-pin')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Agency $record): string => (string) $record->address)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('email')
                    ->label('Correo')
                    ->icon('heroicon-m-envelope')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->tooltip(fn (Agency $record): string => (string) $record->email),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-phone')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->icon('heroicon-m-signal')
                    ->badge()
                    ->color(fn (mixed $state): string => match ($state) {
                        'ACTIVO' => 'success',
                        'INACTIVO' => 'danger',
                        'POR REVISION' => 'warning',
                        default => 'gray',
                    })
                    ->sortable()
                    ->searchable(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->icon('heroicon-m-calendar-days')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options([
                        'ACTIVO' => 'Activo',
                        'INACTIVO' => 'Inactivo',
                        'POR REVISION' => 'Por revisión',
                    ])
                    ->native(false),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver ficha')
                    ->icon('heroicon-m-arrow-top-right-on-square')
                    ->url(fn (Agency $record): string => AgencyResource::getUrl('view', ['record' => $record], true, 'business')),
            ]);
    }
}
