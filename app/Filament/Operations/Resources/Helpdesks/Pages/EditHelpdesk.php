<?php

namespace App\Filament\Operations\Resources\Helpdesks\Pages;

use App\Filament\Operations\Resources\Helpdesks\HelpdeskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHelpdesk extends EditRecord
{
    protected static string $resource = HelpdeskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
