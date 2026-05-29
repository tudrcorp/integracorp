<?php

declare(strict_types=1);

namespace App\Support\Filament\Operations;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Set;

final class SupplierBeneficiaryBankingForm
{
    /**
     * @return array<string, string>
     */
    public static function venezuelanBankOptions(): array
    {
        return [
            'BANCO DE VENEZUELA' => 'BANCO DE VENEZUELA',
            'BANCO BICENTENARIO' => 'BANCO BICENTENARIO',
            'BANCO MERCANTIL' => 'BANCO MERCANTIL',
            'BANCO PROVINCIAL' => 'BANCO PROVINCIAL',
            'BANCO CARONI' => 'BANCO CARONI',
            'BANCO DEL CARIBE' => 'BANCO DEL CARIBE',
            'BANCO DEL TESORO' => 'BANCO DEL TESORO',
            'BANCO NACIONAL DE CREDITO' => 'BANCO NACIONAL DE CREDITO',
            'BANESCO' => 'BANESCO',
            'FONDO COMUN' => 'FONDO COMUN',
            'BANCO CANARIAS' => 'BANCO CANARIAS',
            'BANCO DEL SUR' => 'BANCO DEL SUR',
            'BANCO AGRICOLA DE VENEZUELA' => 'BANCO AGRICOLA DE VENEZUELA',
            'BANPLUS' => 'BANPLUS',
            'MI BANCO' => 'MI BANCO',
            'BANCAMIGA' => 'BANCAMIGA',
            'BANFANB' => 'BANFANB',
            'BANCARIBE' => 'BANCARIBE',
            'BANCO ACTIVO' => 'BANCO ACTIVO',
            'BANCO VENEZOLANO DE CREDITO' => 'BANCO VENEZOLANO DE CREDITO',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function internationalBankOptions(): array
    {
        return [
            'FACEBANK INTERNATIONAL' => 'FACEBANK INTERNATIONAL',
            'JPMORGAN CHASE & CO' => 'JPMORGAN CHASE & CO',
            'BANK OF AMERICA' => 'BANK OF AMERICA',
            'WELLS FARGO' => 'WELLS FARGO',
            'CITIBANK (CITIGROUP)' => 'CITIBANK (CITIGROUP)',
            'U.S. BANK' => 'U.S. BANK',
            'PNC FINANCIAL SERVICES' => 'PNC FINANCIAL SERVICES',
            'TRUIST FINANCIAL CORPORATION' => 'TRUIST FINANCIAL CORPORATION',
            'CAPITAL ONE' => 'CAPITAL ONE',
            'TD BANK (TORONTO-DOMINION BANK)' => 'TD BANK (TORONTO-DOMINION BANK)',
            'HSBC BANK USA' => 'HSBC BANK USA',
            'FIFTH THIRD BANK' => 'FIFTH THIRD BANK',
            'REGIONS FINANCIAL CORPORATION' => 'REGIONS FINANCIAL CORPORATION',
            'HUNTINGTON NATIONAL BANK' => 'HUNTINGTON NATIONAL BANK',
            'NAVY FEDERAL CREDIT UNION' => 'NAVY FEDERAL CREDIT UNION',
            'STATE EMPLOYEES CREDIT UNION (SECU)' => 'STATE EMPLOYEES CREDIT UNION (SECU)',
            'BANCO NACIONAL DE PANAMÁ (BNP)' => 'BANCO NACIONAL DE PANAMÁ (BNP)',
            'CAJA DE AHORROS' => 'CAJA DE AHORROS',
            'BANCO GENERAL' => 'BANCO GENERAL',
            'GLOBAL BANK' => 'GLOBAL BANK',
            'BANESCO PANAMÁ' => 'BANESCO PANAMÁ',
            'METROBANK' => 'METROBANK',
            'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)' => 'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)',
            'HSBC BANK PANAMÁ' => 'HSBC BANK PANAMÁ',
            'SCOTIABANK PANAMÁ' => 'SCOTIABANK PANAMÁ',
            'CITIBANK PANAMÁ' => 'CITIBANK PANAMÁ',
            'BANCO SANTANDER PANAMÁ' => 'BANCO SANTANDER PANAMÁ',
            'BANCO DAVIVIENDA PANAMÁ' => 'BANCO DAVIVIENDA PANAMÁ',
            'BANCO ALIADO' => 'BANCO ALIADO',
            'MULTIBANK' => 'MULTIBANK',
            'BANCAMIGA' => 'BANCAMIGA',
            'BANCO DEL TESORO' => 'BANCO DEL TESORO',
            'PROVINCIAL' => 'PROVINCIAL',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function localAccountTypeOptions(): array
    {
        return [
            'AHORRO' => 'AHORRO',
            'CORRIENTE' => 'CORRIENTE',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function internationalAccountTypeOptions(): array
    {
        return [
            'CUENTA DE CHEQUES (CHECKING ACCOUNT)' => 'CUENTA DE CHEQUES (CHECKING ACCOUNT)',
            'CUENTA DE AHORROS (SAVINGS ACCOUNT)' => 'CUENTA DE AHORROS (SAVINGS ACCOUNT)',
            'CUENTA CORRIENTE (CURRENT ACCOUNT)' => 'CUENTA CORRIENTE (CURRENT ACCOUNT)',
            'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)' => 'CUENTA DE DEPÓSITO A PLAZO FIJO (CERTIFICATE OF DEPOSIT - CD)',
            'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)' => 'CUENTA DE NEGOCIOS (BUSINESS ACCOUNT)',
            'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)' => 'CUENTA DE INVERSIÓN (INVESTMENT ACCOUNT)',
            'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)' => 'CUENTA DE RETIRO INDIVIDUAL (INDIVIDUAL RETIREMENT ACCOUNT - IRA)',
            'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)' => 'CUENTA DE FONDOS DE EMERGENCIA (EMERGENCY FUND ACCOUNT)',
            'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)' => 'CUENTA PARA MENORES (MINOR ACCOUNT / CUSTODIAL ACCOUNT)',
            'CUENTA CONJUNTA (JOINT ACCOUNT)' => 'CUENTA CONJUNTA (JOINT ACCOUNT)',
            'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)' => 'CUENTA EN MONEDA EXTRANJERA (CUENTA EN DÓLARES, EUROS, ETC.)',
            'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)' => 'CUENTA DE RETIRO (CUENTA DE JUBILACIÓN)',
            'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)' => 'CUENTA DE FIDEICOMISO (TRUST ACCOUNT)',
        ];
    }

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
            ->icon('heroicon-o-banknotes')
            ->extraAttributes(['class' => $sectionCardClass])
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema([
                        TextInput::make('local_beneficiary_name')
                            ->label('Nombre / razón social del beneficiario')
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255)
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('local_beneficiary_name', $state.toUpperCase());
                            JS)
                            ->live(onBlur: true),
                        TextInput::make('local_beneficiary_rif')
                            ->label('CI / RIF del beneficiario')
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255),
                        TextInput::make('local_beneficiary_phone_pm')
                            ->label('Teléfono pago móvil del beneficiario')
                            ->prefixIcon('heroicon-s-phone')
                            ->tel()
                            ->helperText('Formato: 04121234567, 04241869168')
                            ->mask('09999999999'),
                    ]),
                Fieldset::make('Cuenta nacional, moneda nacional (Bs.)')
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema([
                        TextInput::make('local_beneficiary_account_number')
                            ->label('Número de cuenta del beneficiario')
                            ->prefixIcon('heroicon-s-identification'),
                        Select::make('local_beneficiary_account_bank')
                            ->label('Banco del beneficiario')
                            ->prefixIcon('heroicon-s-building-library')
                            ->searchable()
                            ->options(self::venezuelanBankOptions()),
                        Select::make('local_beneficiary_account_type')
                            ->label('Tipo de cuenta del beneficiario')
                            ->prefixIcon('heroicon-s-identification')
                            ->options(self::localAccountTypeOptions()),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Fieldset::make('Cuenta nacional, moneda internacional (US$, EUR)')
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema([
                        TextInput::make('local_beneficiary_account_number_mon_inter')
                            ->label('Número de cuenta del beneficiario')
                            ->prefixIcon('heroicon-s-identification'),
                        Select::make('local_beneficiary_account_bank_mon_inter')
                            ->label('Banco del beneficiario')
                            ->prefixIcon('heroicon-s-building-library')
                            ->searchable()
                            ->options(self::venezuelanBankOptions()),
                        Select::make('local_beneficiary_account_type_mon_inter')
                            ->label('Tipo de cuenta del beneficiario')
                            ->prefixIcon('heroicon-s-identification')
                            ->options(self::localAccountTypeOptions()),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
            ])
            ->collapsible();
    }

    public static function extraBankingSection(string $sectionCardClass, string $innerCardClass): Section
    {
        return Section::make('Información bancaria extranjera (US$)')
            ->description('Datos bancarios para recibir pagos en moneda extranjera.')
            ->icon('heroicon-o-globe-alt')
            ->extraAttributes(['class' => $sectionCardClass])
            ->schema([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->extraAttributes(['class' => $innerCardClass])
                    ->schema([
                        TextInput::make('extra_beneficiary_name')
                            ->label('Nombre / razón social')
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255)
                            ->afterStateUpdatedJs(<<<'JS'
                                $set('extra_beneficiary_name', $state.toUpperCase());
                            JS)
                            ->live(onBlur: true),
                        TextInput::make('extra_beneficiary_ci_rif')
                            ->label('Nro. CI / RIF / ID / pasaporte')
                            ->prefixIcon('heroicon-s-identification')
                            ->numeric()
                            ->validationMessages([
                                'numeric' => 'Campo tipo numérico',
                            ])
                            ->maxLength(255),
                        TextInput::make('extra_beneficiary_account_number')
                            ->label('Número de cuenta')
                            ->prefixIcon('heroicon-s-identification')
                            ->numeric()
                            ->validationMessages([
                                'numeric' => 'Campo tipo numérico',
                            ])
                            ->live()
                            ->maxLength(255),
                        Select::make('extra_beneficiary_account_bank')
                            ->label('Banco')
                            ->prefixIcon('heroicon-s-building-library')
                            ->searchable()
                            ->preload()
                            ->options(self::internationalBankOptions()),
                        TextInput::make('extra_beneficiary_address')
                            ->label('Dirección')
                            ->prefixIcon('heroicon-s-map-pin')
                            ->maxLength(255)
                            ->columnSpan(['default' => 1, 'lg' => 2])
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('extra_beneficiary_address', strtoupper((string) $state));
                            })
                            ->live(onBlur: true),
                        Select::make('extra_beneficiary_account_type')
                            ->label('Tipo de cuenta')
                            ->prefixIcon('heroicon-s-identification')
                            ->searchable()
                            ->preload()
                            ->options(self::internationalAccountTypeOptions()),
                        TextInput::make('extra_beneficiary_route')
                            ->label('Ruta')
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('extra_beneficiary_route', strtoupper((string) $state));
                            })
                            ->live(onBlur: true),
                        TextInput::make('extra_beneficiary_swift')
                            ->label('SWIFT')
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('extra_beneficiary_swift', strtoupper((string) $state));
                            })
                            ->live(onBlur: true),
                        TextInput::make('extra_beneficiary_zelle')
                            ->label('Zelle')
                            ->prefixIcon('heroicon-s-identification')
                            ->maxLength(255)
                            ->afterStateUpdated(function (Set $set, ?string $state): void {
                                $set('extra_beneficiary_zelle', strtoupper((string) $state));
                            })
                            ->live(onBlur: true),
                    ]),
            ])
            ->collapsible();
    }
}
