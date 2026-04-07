<?php

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Models\RrhhColaborador;
use App\Support\HelpdeskTaskStatusOptions;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditHelpdesk extends EditRecord
{
    protected static string $resource = HelpdeskResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;

        if (isset($data['status'])) {
            $data['status'] = HelpdeskTaskStatusOptions::sanitizeStatusForSave(
                $this->getRecord(),
                $data['status'],
                Auth::user()?->name,
            );
        }

        $myColaboradorId = RrhhColaborador::query()->where('user_id', Auth::id())->value('id');
        if ($myColaboradorId !== null && ($data['rrhh_colaborador_id'] ?? null) == $myColaboradorId) {
            $data['rrhh_colaborador_id'] = null;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            // DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
