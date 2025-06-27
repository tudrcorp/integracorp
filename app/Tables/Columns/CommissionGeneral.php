<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class CommissionGeneral extends Column
{
    protected string $view = 'tables.columns.commission-general';

    public function getNameCorporative(): string
    {
        if($this->getRecord()->generalNameAgency->agency_type_id == 3){
            return $this->getRecord()->generalNameAgency->name_corporative;
        }else{
            return '----';
        }

    }
}