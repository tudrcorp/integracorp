<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Catálogo de bancos usado por los formularios que registran pagos.
 *
 * Las listas replican las que ya viven embebidas en los formularios de aliados
 * corporativos, agencias y agentes. Se centralizan aquí para que los módulos
 * nuevos no vuelvan a copiarlas; los formularios existentes se dejan intactos.
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
        ];
    }
}
