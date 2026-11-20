<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Tables;

use App\Models\ApiPartner;
use App\Support\ApiPartners\ApiPartnerToken;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ApiPartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->modifyQueryUsing(fn (Builder $query): Builder => ApiPartner::supplierColumnExists() ? $query->with('supplier:id,name,deleted_at') : $query)
            ->searchDebounce('350ms')
            ->columns([
                TextColumn::make('name')
                    ->label('Aliado')
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::SemiBold)
                    ->description(fn (ApiPartner $record): ?string => $record->contact_email),
                TextColumn::make('supplier.name')
                    ->label('Proveedor vinculado')
                    ->formatStateUsing(fn (?string $state): string => \Illuminate\Support\Str::squish((string) $state))
                    ->placeholder('Sin vincular')
                    ->wrap()
                    ->visible(fn (): bool => ApiPartner::supplierColumnExists()),
                TextColumn::make('status')
                    ->label('Estado')
                    ->state(fn (ApiPartner $record): string => $record->status())
                    ->badge()
                    ->color(fn (string $state): string => ApiPartner::statusColor($state)),
                TextColumn::make('token_prefix')
                    ->label('Token')
                    ->state(fn (ApiPartner $record): string => ApiPartnerToken::displayPrefix($record->hasToken() ? $record->token_prefix : null))
                    ->fontFamily('mono')
                    ->color('gray'),
                TextColumn::make('last_used_at')
                    ->label('Último uso')
                    ->since()
                    ->sortable()
                    ->placeholder('Nunca'),
                TextColumn::make('rate_limit_per_minute')
                    ->label('Límite')
                    ->suffix(' / min')
                    ->alignEnd(),
                TextColumn::make('expires_at')
                    ->label('Válido hasta')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->placeholder('Sin vencimiento'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->emptyStateHeading('Ningún aliado tiene acceso al API')
            ->emptyStateDescription('Crea un aliado, guarda y genera su token desde la ficha.');
    }
}
