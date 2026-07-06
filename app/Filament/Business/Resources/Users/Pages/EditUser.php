<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Users\Pages;

use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Filament\Business\Resources\Users\UserResource;
use App\Models\User;
use App\Support\Filament\UserCredentialSynchronizer;
use App\Support\Filament\UserFormPermissionOptions;
use App\Support\Filament\UserPageHeader;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<int|string> IDs de permisos a sincronizar tras guardar (desde el formulario). */
    protected array $pendingPermissionIds = [];

    protected ?string $originalEmailBeforeSave = null;

    protected bool $passwordWasChanged = false;

    public function getTitle(): string|Htmlable
    {
        /** @var User $user */
        $user = $this->getRecord();

        return UserPageHeader::make($user, context: 'edit');
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();

        foreach (UserForm::getPermissionAssignableModules() as $module) {
            $modulePermissionIds = $record->permissions()
                ->where('permissions.module', $module)
                ->pluck('permissions.id')
                ->map(fn (mixed $id): int => (int) $id)
                ->all();

            foreach (UserFormPermissionOptions::groupedPermissionsForModule($module) as $navigationGroup => $permissions) {
                $groupPermissionIds = $permissions
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->all();

                $data[UserForm::permissionGroupFieldKey($module, $navigationGroup)] = array_values(
                    array_intersect($modulePermissionIds, $groupPermissionIds),
                );
            }
        }

        return $data;
    }

    /**
     * Filament llama a mutateFormDataBeforeSave antes de guardar (no mutateFormDataBeforeUpdate).
     * Los CheckboxList se guardan en el estado Livewire ($this->data); usamos eso para obtener los IDs.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->originalEmailBeforeSave = (string) $this->record->email;

        if (filled($data['password'] ?? null)) {
            $this->passwordWasChanged = true;
            $data['password'] = Hash::make((string) $data['password']);
        } else {
            $this->passwordWasChanged = false;
            unset($data['password']);
        }

        unset($data['password_confirmation']);

        $data['updated_by'] = Auth::user()->name;

        $state = array_merge(
            $data,
            $this->form->getState() ?? [],
            is_array($this->data ?? null) ? $this->data : [],
        );
        if (isset($state['form']) && is_array($state['form'])) {
            $state = array_merge($state, $state['form']);
        }

        $this->pendingPermissionIds = UserForm::extractPermissionIdsFromState($state);
        foreach (UserForm::allPermissionFieldKeys() as $permissionFieldKey) {
            unset($data[$permissionFieldKey]);
        }
        unset($data['permissions']);

        return $data;
    }

    protected function afterSave(): void
    {
        $pivotValues = [
            'created_by' => Auth::user()->name,
            'updated_by' => Auth::user()->name,
        ];
        $this->record->permissions()->syncWithPivotValues(
            $this->pendingPermissionIds,
            $pivotValues
        );

        $originalEmail = $this->originalEmailBeforeSave ?? (string) $this->record->email;
        $emailChanged = $originalEmail !== (string) $this->record->email;

        if ($emailChanged || $this->passwordWasChanged) {
            UserCredentialSynchronizer::syncRelatedRecordsAndAudit(
                user: $this->record->fresh(),
                originalEmail: $originalEmail,
                emailChanged: $emailChanged,
                passwordChanged: $this->passwordWasChanged,
            );
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotification(): ?Notification
    {
        /** @var User $user */
        $user = $this->getRecord();

        return Notification::make()
            ->success()
            ->icon(Heroicon::OutlinedCheckCircle)
            ->title('Usuario actualizado')
            ->body($this->savedNotificationBody($user))
            ->duration(6000);
    }

    private function savedNotificationBody(User $user): string
    {
        $lines = [
            (string) $user->name,
            (string) $user->email,
        ];

        $permissionCount = count($this->pendingPermissionIds);

        if ($permissionCount === 0) {
            $lines[] = 'Permisos de menú: ninguno asignado en los módulos seleccionados.';
        } elseif ($permissionCount === 1) {
            $lines[] = 'Permisos de menú: 1 pantalla asignada.';
        } else {
            $lines[] = "Permisos de menú: {$permissionCount} pantallas asignadas.";
        }

        $emailChanged = ($this->originalEmailBeforeSave ?? (string) $user->email) !== (string) $user->email;

        if ($emailChanged && $this->passwordWasChanged) {
            $lines[] = 'Credenciales actualizadas y sincronizadas en perfiles vinculados.';
        } elseif ($emailChanged) {
            $lines[] = 'Correo actualizado y sincronizado en perfiles vinculados.';
        } elseif ($this->passwordWasChanged) {
            $lines[] = 'Contraseña actualizada correctamente.';
        }

        return implode("\n", $lines);
    }
}
