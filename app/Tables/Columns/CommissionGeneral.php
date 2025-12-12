<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class CommissionGeneral extends Column
{
    protected string $view = 'tables.columns.commission-general';

    public function getNameCorporative(): string
    {
        $sum = $this->getRecord()->commission_agency_master_ves + $this->getRecord()->commission_agency_general_ves + $this->getRecord()->commission_agent_ves;
        return $sum;
    }
}