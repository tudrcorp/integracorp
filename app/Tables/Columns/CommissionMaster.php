<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class CommissionMaster extends Column
{
    protected string $view = 'tables.columns.commission-master';

    public function getNameCorporative(): string
    {
        $sum = $this->getRecord()->commission_agency_master_usd + $this->getRecord()->commission_agency_general_usd + $this->getRecord()->commission_agent_usd;
        return $sum;
    }
}