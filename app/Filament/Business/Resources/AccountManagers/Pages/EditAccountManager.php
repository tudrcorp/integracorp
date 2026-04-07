<?php

namespace App\Filament\Business\Resources\AccountManagers\Pages;

use App\Filament\Business\Resources\AccountManagers\AccountManagerResource;
use App\Filament\Business\Resources\AccountManagers\Widgets\StatsOverviewCountAgentAgency;
use App\Models\AccountManager;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class EditAccountManager extends EditRecord
{
    protected static string $resource = AccountManagerResource::class;

    public function getTitle(): string|Htmlable
    {
        /** @var AccountManager $record */
        $record = $this->getRecord();

        return sprintf('Productividad · %s', $record->full_name);
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return [
            'default' => 1,
            'md' => 2,
            'xl' => 2,
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar ejecutivo')
                ->icon('heroicon-m-trash')
                ->requiresConfirmation()
                ->modalHeading('¿Eliminar este account manager?')
                ->modalDescription('Se eliminará el registro. Verifica dependencias en agencias y agentes antes de continuar.')
                ->modalSubmitActionLabel('Sí, eliminar'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StatsOverviewCountAgentAgency::class,
        ];
    }
}
