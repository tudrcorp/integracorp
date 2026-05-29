<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Support\Icons\Heroicon;

final class SupplierBeneficiaryBankingInfolist
{
    public static function bankingTab(string $sectionCardClass, string $innerCardClass): Tab
    {
        return Tab::make('Datos bancarios')
            ->icon('heroicon-o-building-library')
            ->schema([
                self::localBankingSection($sectionCardClass, $innerCardClass),
                self::extraBankingSection($sectionCardClass, $innerCardClass),
            ]);
    }

    public static function localBankingSection(string $sectionCardClass, string $innerCardClass): Section
    {
        return Section::make('Información bancaria local (VES)')
            ->description('Datos bancarios para recibir pagos en moneda nacional.')
            ->icon(Heroicon::OutlinedBanknotes)
            ->extraAttributes(['class' => $sectionCardClass])
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema(self::localBeneficiaryEntries()),
                Fieldset::make('Cuenta nacional, moneda nacional (Bs.)')
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema(self::localNationalCurrencyAccountEntries())
                    ->columns(3)
                    ->columnSpanFull(),
                Fieldset::make('Cuenta nacional, moneda internacional (US$, EUR)')
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema(self::localInternationalCurrencyAccountEntries())
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->columnSpanFull();
    }

    public static function extraBankingSection(string $sectionCardClass, string $innerCardClass): Section
    {
        return Section::make('Información bancaria extranjera (US$)')
            ->description('Datos bancarios para recibir pagos en moneda extranjera.')
            ->icon(Heroicon::OutlinedGlobeAlt)
            ->extraAttributes(['class' => $sectionCardClass])
            ->schema([
                Grid::make(['default' => 1, 'sm' => 2, 'lg' => 3])
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema(self::extraBeneficiaryEntries()),
            ])
            ->columnSpanFull();
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function localBeneficiaryEntries(): array
    {
        return [
            TextEntry::make('local_beneficiary_name')
                ->label('Nombre / razón social del beneficiario')
                ->icon(Heroicon::OutlinedIdentification)
                ->weight('medium')
                ->wrap()
                ->placeholder('—'),
            TextEntry::make('local_beneficiary_rif')
                ->label('CI / RIF del beneficiario')
                ->icon(Heroicon::OutlinedIdentification)
                ->badge()
                ->color('info')
                ->copyable()
                ->placeholder('—'),
            TextEntry::make('local_beneficiary_phone_pm')
                ->label('Teléfono pago móvil del beneficiario')
                ->icon(Heroicon::OutlinedDevicePhoneMobile)
                ->copyable()
                ->placeholder('—'),
        ];
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function localNationalCurrencyAccountEntries(): array
    {
        return [
            TextEntry::make('local_beneficiary_account_number')
                ->label('Número de cuenta del beneficiario')
                ->icon(Heroicon::OutlinedCreditCard)
                ->copyable()
                ->placeholder('—'),
            TextEntry::make('local_beneficiary_account_bank')
                ->label('Banco del beneficiario')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->badge()
                ->color('primary')
                ->placeholder('—'),
            TextEntry::make('local_beneficiary_account_type')
                ->label('Tipo de cuenta del beneficiario')
                ->icon(Heroicon::OutlinedTag)
                ->badge()
                ->color('gray')
                ->placeholder('—'),
        ];
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function localInternationalCurrencyAccountEntries(): array
    {
        return [
            TextEntry::make('local_beneficiary_account_number_mon_inter')
                ->label('Número de cuenta del beneficiario')
                ->icon(Heroicon::OutlinedCreditCard)
                ->copyable()
                ->placeholder('—'),
            TextEntry::make('local_beneficiary_account_bank_mon_inter')
                ->label('Banco del beneficiario')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->badge()
                ->color('primary')
                ->placeholder('—'),
            TextEntry::make('local_beneficiary_account_type_mon_inter')
                ->label('Tipo de cuenta del beneficiario')
                ->icon(Heroicon::OutlinedTag)
                ->badge()
                ->color('gray')
                ->placeholder('—'),
        ];
    }

    /**
     * @return array<int, TextEntry>
     */
    private static function extraBeneficiaryEntries(): array
    {
        return [
            TextEntry::make('extra_beneficiary_name')
                ->label('Nombre / razón social')
                ->icon(Heroicon::OutlinedIdentification)
                ->weight('medium')
                ->wrap()
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_ci_rif')
                ->label('Nro. CI / RIF / ID / pasaporte')
                ->icon(Heroicon::OutlinedIdentification)
                ->badge()
                ->color('info')
                ->copyable()
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_account_number')
                ->label('Número de cuenta')
                ->icon(Heroicon::OutlinedCreditCard)
                ->copyable()
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_account_bank')
                ->label('Banco')
                ->icon(Heroicon::OutlinedBuildingLibrary)
                ->badge()
                ->color('primary')
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_address')
                ->label('Dirección')
                ->icon(Heroicon::OutlinedMapPin)
                ->columnSpan(['default' => 1, 'lg' => 2])
                ->wrap()
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_account_type')
                ->label('Tipo de cuenta')
                ->icon(Heroicon::OutlinedTag)
                ->badge()
                ->color('gray')
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_route')
                ->label('Ruta')
                ->icon(Heroicon::OutlinedArrowPath)
                ->copyable()
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_swift')
                ->label('SWIFT')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->copyable()
                ->placeholder('—'),
            TextEntry::make('extra_beneficiary_zelle')
                ->label('Zelle')
                ->icon(Heroicon::OutlinedCurrencyDollar)
                ->copyable()
                ->placeholder('—'),
        ];
    }
}
