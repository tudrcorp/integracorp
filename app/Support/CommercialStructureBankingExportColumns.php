<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * Columnas de datos bancarios nacionales e internacionales para exportes
 * de agencias y agentes (mismos nombres de atributos en ambos modelos).
 */
final class CommercialStructureBankingExportColumns
{
    /**
     * @return list<string>
     */
    public static function csvHeaders(): array
    {
        return [
            'Nat. nombre beneficiario',
            'Nat. RIF beneficiario',
            'Nat. nº cuenta',
            'Nat. banco',
            'Nat. tipo de cuenta',
            'Nat. teléfono pago móvil',
            'Nat. nº cuenta moneda extranjera (inter)',
            'Nat. banco (inter)',
            'Nat. tipo de cuenta (inter)',
            'Int. nombre beneficiario',
            'Int. CI/RIF',
            'Int. nº cuenta',
            'Int. banco',
            'Int. tipo de cuenta',
            'Int. ruta',
            'Int. Zelle',
            'Int. ACH',
            'Int. SWIFT',
            'Int. ABA',
            'Int. dirección',
        ];
    }

    /**
     * @return list<string>
     */
    public static function valuesFromModel(Model $record): array
    {
        return [
            self::stringCell($record->getAttribute('local_beneficiary_name')),
            self::stringCell($record->getAttribute('local_beneficiary_rif')),
            self::stringCell($record->getAttribute('local_beneficiary_account_number')),
            self::stringCell($record->getAttribute('local_beneficiary_account_bank')),
            self::stringCell($record->getAttribute('local_beneficiary_account_type')),
            self::stringCell($record->getAttribute('local_beneficiary_phone_pm')),
            self::stringCell($record->getAttribute('local_beneficiary_account_number_mon_inter')),
            self::stringCell($record->getAttribute('local_beneficiary_account_bank_mon_inter')),
            self::stringCell($record->getAttribute('local_beneficiary_account_type_mon_inter')),
            self::stringCell($record->getAttribute('extra_beneficiary_name')),
            self::stringCell($record->getAttribute('extra_beneficiary_ci_rif')),
            self::stringCell($record->getAttribute('extra_beneficiary_account_number')),
            self::stringCell($record->getAttribute('extra_beneficiary_account_bank')),
            self::stringCell($record->getAttribute('extra_beneficiary_account_type')),
            self::stringCell($record->getAttribute('extra_beneficiary_route')),
            self::stringCell($record->getAttribute('extra_beneficiary_zelle')),
            self::stringCell($record->getAttribute('extra_beneficiary_ach')),
            self::stringCell($record->getAttribute('extra_beneficiary_swift')),
            self::stringCell($record->getAttribute('extra_beneficiary_aba')),
            self::stringCell($record->getAttribute('extra_beneficiary_address')),
        ];
    }

    private static function stringCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        return trim((string) $value);
    }
}
