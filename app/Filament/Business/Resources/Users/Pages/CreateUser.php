<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Pages;

use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Filament\Business\Resources\Users\UserResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<int|string> IDs de permisos a sincronizar tras crear (desde el formulario). */
    protected array $pendingPermissionIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->pendingPermissionIds = UserForm::extractPermissionIdsFromState($data);

        foreach (UserForm::allPermissionFieldKeys() as $permissionFieldKey) {
            unset($data[$permissionFieldKey]);
        }
        unset($data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingPermissionIds === []) {
            return;
        }

        $pivotValues = [
            'created_by' => Auth::user()->name,
            'updated_by' => Auth::user()->name,
        ];
        $this->record->permissions()->syncWithPivotValues(
            $this->pendingPermissionIds,
            $pivotValues
        );
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        /** @var User $user */
        $user = $this->record;

        return Notification::make()
            ->success()
            ->icon(Heroicon::OutlinedUserPlus)
            ->title('Usuario registrado')
            ->body($this->createdNotificationBody($user))
            ->duration(6000);
    }

    private function createdNotificationBody(User $user): string
    {
        $lines = [
            (string) $user->name,
            (string) $user->email,
        ];

        $permissionCount = count($this->pendingPermissionIds);

        if ($permissionCount === 0) {
            $lines[] = 'Permisos de menú: configúralos cuando el usuario necesite acceso granular.';
        } elseif ($permissionCount === 1) {
            $lines[] = 'Permisos de menú: 1 pantalla asignada.';
        } else {
            $lines[] = "Permisos de menú: {$permissionCount} pantallas asignadas.";
        }

        return implode("\n", $lines);
    }
}
