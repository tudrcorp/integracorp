<?php

declare(strict_types=1);

namespace App\Enums;

enum CreditReconciliationEntityType: string
{
    case WhiteCompany = 'white_company';
    case Agency = 'agency';
    case Agent = 'agent';

    public function label(): string
    {
        return match ($this) {
            self::WhiteCompany => 'Empresa aliada',
            self::Agency => 'Agencia',
            self::Agent => 'Agente',
        };
    }
}
