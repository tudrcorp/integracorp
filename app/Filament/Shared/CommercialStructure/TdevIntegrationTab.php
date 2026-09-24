<?php

declare(strict_types=1);

namespace App\Filament\Shared\CommercialStructure;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

final class TdevIntegrationTab
{
    public const LABEL = 'Integración TuDrEnViajes';

    /**
     * @param  list<string>|null  $saleRules
     * @param  list<string>|null  $renewalRules
     */
    public static function agent(bool $lockCommissions = false, ?array $saleRules = null, ?array $renewalRules = null): Tab
    {
        return self::make('agent', $lockCommissions, $saleRules, $renewalRules);
    }

    /**
     * @param  list<string>|null  $saleRules
     * @param  list<string>|null  $renewalRules
     */
    public static function agency(bool $lockCommissions = false, ?array $saleRules = null, ?array $renewalRules = null): Tab
    {
        return self::make('agency', $lockCommissions, $saleRules, $renewalRules);
    }

    /**
     * @param  list<string>|null  $saleRules
     * @param  list<string>|null  $renewalRules
     */
    private static function make(string $owner, bool $lockCommissions, ?array $saleRules, ?array $renewalRules): Tab
    {
        $sale = self::percentField('commission_tdev', 'Comisión TDEV', $lockCommissions, $saleRules, true);
        $renewal = self::percentField('commission_tdev_renewal', 'Comisión renovación TDEV', $lockCommissions, $renewalRules, false);
        $identifier = $owner === 'agency' ? TdevExternalIdField::agency() : TdevExternalIdField::agent();
        $credit = TextInput::make('amount_asign_credit_tdev')
            ->label('Crédito asignado TDEV')
            ->numeric()
            ->prefix('US$')
            ->step(0.01)
            ->minValue(0)
            ->default(0)
            ->helperText('No es obligatorio. Si se deja vacío, queda en 0.00.');

        if ($lockCommissions) {
            $credit->disabled()->dehydrated();
        }

        return Tab::make(self::LABEL)
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->schema([
                Section::make('Tu Dr en Viajes')
                    ->description('Identificador, usuario, comisiones y crédito de la integración TDEV.')
                    ->icon(Heroicon::OutlinedPaperAirplane)
                    ->schema([
                        Grid::make(['default' => 1, 'lg' => 2])
                            ->schema([
                                Toggle::make('tdev')
                                    ->label('TDEV activo')
                                    ->inline(false)
                                    ->onIcon('heroicon-s-check')
                                    ->onColor('success')
                                    ->disabled($lockCommissions)
                                    ->dehydrated(),
                                TextInput::make('user_tdev')
                                    ->label('Usuario de Tu Doctor en Viajes (TDEV)')
                                    ->prefixIcon(Heroicon::OutlinedIdentification)
                                    ->maxLength(255),
                                $sale,
                                $renewal,
                                $identifier,
                                $credit,
                            ]),
                    ]),
            ]);
    }

    /**
     * @param  list<string>|null  $rules
     */
    private static function percentField(string $name, string $label, bool $lockCommissions, ?array $rules, bool $live): TextInput
    {
        $field = TextInput::make($name)
            ->label($label)
            ->helperText('Valor en porcentaje. Use punto como separador decimal.')
            ->prefix('%')
            ->numeric();

        if ($lockCommissions) {
            return $field->disabled()->dehydrated();
        }

        if ($live) {
            $field->live(onBlur: true);
        }

        if ($rules !== null) {
            $field->rules($rules);
        }

        return $field;
    }
}
