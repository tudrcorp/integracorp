<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\OperationCoordinationServices\Tables\OperationCoordinationServicesTable;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Icons\Heroicon;

final class OperationServiceOrderCoveredPricingFormFields
{
    /**
     * Precio de la orden para ítems cubiertos cuando ya hay proveedor seleccionado
     * (natural/jurídico convenido) o cuando el no convenido es natural (orden directa).
     */
    public static function shouldShow(Get $get): bool
    {
        // En el wizard de la tabla, el precio viene de la cotización asociada.
        if ((bool) $get('create_associated_quote')) {
            return false;
        }

        if ((bool) $get('register_unregistered_provider')) {
            return mb_strtolower(trim((string) $get('unregistered_provider_type'))) === 'natural';
        }

        return filled($get('doctor_nurse_id')) || filled($get('supplier_id'));
    }

    /**
     * @return array<int, mixed>
     */
    public static function components(): array
    {
        return [
            Section::make('Precio del servicio')
                ->description('Indique el precio en dólares. El equivalente en bolívares se calcula con la tasa BCV del día.')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->iconColor('success')
                ->visible(fn (Get $get): bool => self::shouldShow($get))
                ->schema([
                    Grid::make(3)
                        ->schema([
                            TextInput::make('service_order_bcv_rate')
                                ->label('Tasa BCV del día')
                                ->prefix('Bs.')
                                ->numeric()
                                ->readOnly()
                                ->dehydrated()
                                ->default(fn (): ?float => OperationCoordinationServicesTable::referenciaTasaBcvDesdeApi())
                                ->helperText('Referencia automática desde API BCV.')
                                ->required(fn (Get $get): bool => self::shouldShow($get)),
                            TextInput::make('service_order_price_usd')
                                ->label('Precio en dólares')
                                ->prefix('US$')
                                ->numeric()
                                ->minValue(0.01)
                                ->live(debounce: 400)
                                ->required(fn (Get $get): bool => self::shouldShow($get))
                                ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                    self::syncVesFromUsd($get, $set, $state);
                                }),
                            TextInput::make('service_order_price_ves')
                                ->label('Precio en bolívares')
                                ->prefix('Bs.')
                                ->numeric()
                                ->readOnly()
                                ->dehydrated()
                                ->helperText('Calculado automáticamente (USD × tasa BCV).')
                                ->required(fn (Get $get): bool => self::shouldShow($get)),
                        ])
                        ->columnSpanFull(),
                ])
                ->columnSpanFull(),
        ];
    }

    public static function syncVesFromUsd(Get $get, Set $set, mixed $usdState): void
    {
        $rate = OperationCoordinationServicesTable::decimalOrNull($get('service_order_bcv_rate'));
        $usd = OperationCoordinationServicesTable::decimalOrNull($usdState);

        if ($rate === null || $usd === null) {
            $set('service_order_price_ves', null);

            return;
        }

        $set('service_order_price_ves', round($usd * $rate, 4));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{currency: string, tasa_bcv: float, total_amount_usd: float, total_amount_ves: float}|null
     */
    public static function pricingPayloadFromData(array $data): ?array
    {
        $usd = OperationCoordinationServicesTable::decimalOrNull($data['service_order_price_usd'] ?? null);
        $rate = OperationCoordinationServicesTable::decimalOrNull(
            $data['service_order_bcv_rate'] ?? OperationCoordinationServicesTable::referenciaTasaBcvDesdeApi()
        );
        $ves = OperationCoordinationServicesTable::decimalOrNull($data['service_order_price_ves'] ?? null);

        if ($usd === null || $usd <= 0 || $rate === null || $rate <= 0) {
            return null;
        }

        return [
            'currency' => 'USD',
            'tasa_bcv' => $rate,
            'total_amount_usd' => $usd,
            'total_amount_ves' => $ves ?? round($usd * $rate, 4),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function requiresPricing(array $data): bool
    {
        if ((bool) ($data['register_unregistered_provider'] ?? false)) {
            return mb_strtolower(trim((string) ($data['unregistered_provider_type'] ?? ''))) === 'natural';
        }

        return filled($data['doctor_nurse_id'] ?? null) || filled($data['supplier_id'] ?? null);
    }

    /**
     * Mensaje de validación si falta precio/tasa; null si es válido o no aplica.
     *
     * @param  array<string, mixed>  $data
     */
    public static function validationMessage(array $data): ?string
    {
        if (! self::requiresPricing($data)) {
            return null;
        }

        if (self::pricingPayloadFromData($data) === null) {
            return 'Indique el precio en dólares (mayor a cero). El sistema calculará el equivalente en bolívares con la tasa BCV.';
        }

        return null;
    }
}
