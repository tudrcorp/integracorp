<?php

declare(strict_types=1);

namespace App\Support;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Set;
use InvalidArgumentException;

/**
 * Catálogo de bancos usado por los formularios que registran pagos.
 *
 * Catálogo compartido para selects de banco en agencias, agentes y pagos.
 * Permite buscar, elegir de la lista o agregar un banco manual (guardado en mayúsculas).
 */
final class BankCatalog
{
    /**
     * @return array<string, string>
     */
    public static function national(): array
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
    public static function international(): array
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
            'BANCO NACIONAL DE PANAMÁ (BNP)' => 'BANCO NACIONAL DE PANAMÁ (BNP)',
            'CAJA DE AHORROS' => 'CAJA DE AHORROS',
            'BANCO GENERAL' => 'BANCO GENERAL',
            'GLOBAL BANK' => 'GLOBAL BANK',
            'BANESCO PANAMÁ' => 'BANESCO PANAMÁ',
            'METROBANK' => 'METROBANK',
            'BANCAMIGA' => 'BANCAMIGA',
            'BANCO DEL TESORO' => 'BANCO DEL TESORO',
            'PROVINCIAL' => 'PROVINCIAL',
            'STATE EMPLOYEES CREDIT UNION (SECU)' => 'STATE EMPLOYEES CREDIT UNION (SECU)',
            'EL BANCO MERCANTIL PANAMÁ' => 'EL BANCO MERCANTIL PANAMÁ',
            'ENCORE BANK' => 'ENCORE BANK',
            'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)' => 'BANCO LATINOAMERICANO DE COMERCIO EXTERIOR (BLADEX)',
            'HSBC BANK PANAMÁ' => 'HSBC BANK PANAMÁ',
            'SCOTIABANK PANAMÁ' => 'SCOTIABANK PANAMÁ',
            'CITIBANK PANAMÁ' => 'CITIBANK PANAMÁ',
            'BANCO SANTANDER PANAMÁ' => 'BANCO SANTANDER PANAMÁ',
            'BANCO DAVIVIENDA PANAMÁ' => 'BANCO DAVIVIENDA PANAMÁ',
            'BANCO ALIADO' => 'BANCO ALIADO',
            'MULTIBANK' => 'MULTIBANK',
        ];
    }

    public static function normalizeStoredBank(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value !== '' ? mb_strtoupper($value, 'UTF-8') : null;
    }

    public static function configureNationalSelect(Select $select): Select
    {
        return self::configureSearchableSelectWithManualOption($select, self::national());
    }

    public static function configureInternationalSelect(Select $select): Select
    {
        return self::configureSearchableSelectWithManualOption($select, self::international());
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function configureSearchableSelectWithManualOption(Select $select, array $options): Select
    {
        return $select
            ->options($options)
            ->searchable()
            ->getOptionLabelUsing(fn ($value): ?string => is_string($value) && $value !== '' ? $value : null)
            ->dehydrateStateUsing(fn (mixed $state): ?string => self::normalizeStoredBank($state))
            ->createOptionModalHeading('Agregar banco')
            ->createOptionForm([
                TextInput::make('name')
                    ->label('Nombre del banco')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (Set $set, ?string $state): void {
                        $set('name', self::normalizeStoredBank($state) ?? '');
                    }),
            ])
            ->createOptionUsing(function (array $data): string {
                $name = self::normalizeStoredBank($data['name'] ?? null);

                if ($name === null) {
                    throw new InvalidArgumentException('Indica el nombre del banco.');
                }

                return $name;
            });
    }
}
