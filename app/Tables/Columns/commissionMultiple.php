<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class commissionMultiple extends Column
{
    protected string $view = 'tables.columns.commission-multiple';

    // public function getColorSuccess()
    // {
    //     dd($this->getRecord());
    //     if($this->getRecord()->commission_agency_master_usd > 0 || $this->getRecord()->commission_agency_master_ves > 0){
            
    //     }
    //     // return match ($this->getState()) {
    //     //     'Pendiente' => false,
    //     //     default => true
    //     // };
        
    // }

    // public function getColorWarning(): bool
    // {
    //     return false;
    // }
}