<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class CommissionMaster extends Column
{
    protected string $view = 'tables.columns.commission-master';

    public function getNameCorporative(): string
    {
        return isset($this->getRecord()->ownerNameAgency->name_corporative) ? $this->getRecord()->ownerNameAgency->name_corporative : '----';
    }
}