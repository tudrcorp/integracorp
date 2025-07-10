<?php

namespace App\Tables\Columns;

use Filament\Tables\Columns\Column;

class CommissionGeneral extends Column
{
    protected string $view = 'tables.columns.commission-general';

    public function getNameCorporative(): string
    {
        return isset($this->getRecord()->generalNameAgency->name_corporative) ? $this->getRecord()->generalNameAgency->name_corporative : '----';

    }
}