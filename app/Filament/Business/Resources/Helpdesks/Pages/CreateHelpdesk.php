<?php

namespace App\Filament\Business\Resources\Helpdesks\Pages;

use App\Filament\Business\Resources\Helpdesks\HelpdeskResource;
use App\Models\RrhhColaborador;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateHelpdesk extends CreateRecord
{
    protected static string $resource = HelpdeskResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['status'] ??= 'PENDIENTE POR INICIAR';

        $myColaboradorId = RrhhColaborador::query()->where('user_id', Auth::id())->value('id');
        if ($myColaboradorId !== null && ($data['rrhh_colaborador_id'] ?? null) == $myColaboradorId) {
            $data['rrhh_colaborador_id'] = null;
        }

        return $data;
    }
}
