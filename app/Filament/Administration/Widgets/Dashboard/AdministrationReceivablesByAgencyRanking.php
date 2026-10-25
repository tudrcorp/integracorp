<?php

declare(strict_types=1);

namespace App\Filament\Administration\Widgets\Dashboard;

use App\Filament\Metrics\Widgets\Corporate\ReceivablesByAgencyRanking;

class AdministrationReceivablesByAgencyRanking extends ReceivablesByAgencyRanking
{
    protected int|string|array $columnSpan = ['default' => 'full', 'xl' => 4];
}
