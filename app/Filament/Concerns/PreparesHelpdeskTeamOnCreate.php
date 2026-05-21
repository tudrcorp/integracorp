<?php

declare(strict_types=1);

namespace App\Filament\Concerns;

use App\Support\HelpdeskTeamMembersPayload;
use Filament\Notifications\Notification;

trait PreparesHelpdeskTeamOnCreate
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareHelpdeskTeamForCreate(array $data): array
    {
        $teamName = trim((string) ($data['team'] ?? ''));
        $teamColaboradorIds = $data['team_colaborador_ids'] ?? [];
        unset($data['team_colaborador_ids']);

        if (! is_array($teamColaboradorIds)) {
            $teamColaboradorIds = [];
        }

        $teamColaboradorIds = array_values(array_unique(array_map(
            static fn (mixed $value): int => (int) $value,
            array_filter($teamColaboradorIds, static fn (mixed $value): bool => filled($value))
        )));

        if ($teamName === '' && $teamColaboradorIds === []) {
            $data['team'] = null;
            $data['team_members'] = null;

            return $data;
        }

        if ($teamName === '' || count($teamColaboradorIds) < 2) {
            Notification::make()
                ->title('Equipo incompleto')
                ->body('Indique el nombre del equipo y seleccione al menos dos colaboradores.')
                ->icon('heroicon-m-user-group')
                ->iconColor('danger')
                ->danger()
                ->send();
            $this->halt();
        }

        $members = HelpdeskTeamMembersPayload::fromColaboradorIds($teamColaboradorIds);

        if (count($members) < 2) {
            Notification::make()
                ->title('Colaboradores no válidos')
                ->body('Debe seleccionar al menos dos colaboradores activos en el directorio.')
                ->icon('heroicon-m-user-group')
                ->iconColor('danger')
                ->danger()
                ->send();
            $this->halt();
        }

        $data['team'] = $teamName;
        $data['team_members'] = $members;

        return $data;
    }
}
