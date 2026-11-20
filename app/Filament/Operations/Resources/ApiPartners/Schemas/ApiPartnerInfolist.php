<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\ApiPartners\Schemas;

use App\Enums\ApiPartnerAbility;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Models\ApiPartner;
use App\Support\ApiPartners\ApiPartnerSupplierOptions;
use App\Support\ApiPartners\ApiPartnerToken;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;

class ApiPartnerInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Aliado')
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('name')->label('Nombre')->weight(FontWeight::SemiBold),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->state(fn (ApiPartner $record): string => $record->status())
                            ->badge()
                            ->color(fn (string $state): string => ApiPartner::statusColor($state)),
                        TextEntry::make('contact_name')
                            ->label('Contacto')
                            ->placeholder('—')
                            ->helperText(fn (ApiPartner $record): ?string => $record->contact_email),
                        TextEntry::make('supplier_id')
                            ->label('Proveedor jurídico vinculado')
                            ->state(fn (ApiPartner $record): ?string => $record->supplier_id !== null ? ApiPartnerSupplierOptions::label($record->supplier_id) : null)
                            ->placeholder('Sin vincular')
                            ->url(fn (ApiPartner $record): ?string => $record->supplier !== null && ! $record->supplier->trashed() && SupplierResource::canAccess()
                                ? SupplierResource::getUrl('view', ['record' => $record->supplier_id])
                                : null)
                            ->color(fn (ApiPartner $record): ?string => $record->supplier_id !== null ? 'primary' : null)
                            ->visible(fn (): bool => ApiPartner::supplierColumnExists())
                            ->columnSpanFull(),
                    ]),
                Section::make('Credencial')
                    ->description('El token completo solo se muestra al generarlo. Aquí se ve el prefijo para identificarlo.')
                    ->icon(Heroicon::OutlinedKey)
                    ->columns(['default' => 1, 'md' => 3])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('token_prefix')
                            ->label('Token')
                            ->state(fn (ApiPartner $record): string => ApiPartnerToken::displayPrefix($record->hasToken() ? $record->token_prefix : null))
                            ->fontFamily('mono'),
                        TextEntry::make('token_issued_at')
                            ->label('Generado')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Nunca')
                            ->helperText(fn (ApiPartner $record): ?string => $record->tokenIssuedBy?->name),
                        TextEntry::make('last_used_at')
                            ->label('Último uso')
                            ->since()
                            ->placeholder('Nunca')
                            ->helperText(fn (ApiPartner $record): ?string => $record->last_used_ip !== null ? 'IP '.$record->last_used_ip : null),
                    ]),
                Section::make('Acceso')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->columns(['default' => 1, 'md' => 2])
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('abilities')
                            ->label('Permisos')
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => ApiPartnerAbility::tryFrom($state)?->label() ?? $state)
                            ->placeholder('Sin permisos'),
                        TextEntry::make('rate_limit_per_minute')
                            ->label('Límite')
                            ->suffix(' peticiones por minuto'),
                        TextEntry::make('expires_at')
                            ->label('Válido hasta')
                            ->dateTime('d/m/Y H:i')
                            ->placeholder('Sin vencimiento'),
                        TextEntry::make('endpoint')
                            ->label('Dirección del API')
                            ->state(fn (): string => url('/api/v1/aliados/medicamentos-no-cubiertos'))
                            ->copyable()
                            ->fontFamily('mono')
                            ->helperText('Encabezado: Authorization: Bearer <token>')
                            ->columnSpanFull(),
                        TextEntry::make('notes')
                            ->label('Notas internas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
