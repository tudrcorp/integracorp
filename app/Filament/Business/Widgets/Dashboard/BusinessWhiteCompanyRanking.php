<?php

declare(strict_types=1);

namespace App\Filament\Business\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\WhiteCompanyRanking;

/**
 * Empresas aliadas, a la medida de la grilla del Dashboard de Negocios.
 */
class BusinessWhiteCompanyRanking extends WhiteCompanyRanking
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];
}
