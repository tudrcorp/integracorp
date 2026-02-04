<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Models\ProspectAgentTask;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Fieldset;

class CreateProspectAgent extends CreateRecord
{
    protected static string $resource = ProspectAgentResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
