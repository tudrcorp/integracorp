<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class CommissionAgent extends Column
{
    protected string $view = 'tables.columns.commission-agent';

    public function getResultBool(): bool
    {
        if($this->getRecord()->commission_agency_master_tdec > 0 || $this->getRecord()->commission_agency_general_tdec > 0){
            return true;
        } else {
            return false;
        }

    }

    public function getColorSuccess(): string
    {
        return match ($this->getResultBool()) {
            true => '#529471',
            false =>'#E8EBEA',
        };

    }

}