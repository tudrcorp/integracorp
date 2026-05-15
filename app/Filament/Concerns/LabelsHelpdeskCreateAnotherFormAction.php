<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use Filament\Actions\Action;

trait LabelsHelpdeskCreateAnotherFormAction
{
    protected function getCreateAnotherFormAction(): Action
    {
        return parent::getCreateAnotherFormAction()
            ->label('Crear otro');
    }
}
