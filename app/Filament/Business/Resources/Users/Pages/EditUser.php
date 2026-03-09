<?php

namespace App\Filament\Business\Resources\Users\Pages;

use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Filament\Business\Resources\Users\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Editar Usuario';

    /** @var list<int|string> IDs de permisos a sincronizar tras guardar (desde el formulario). */
    protected array $pendingPermissionIds = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        foreach (UserForm::getDepartamentModules() as $module) {
            $data["permissions_{$module}"] = $record->permissions()
                ->where('permissions.module', $module)
                ->pluck('permissions.id')
                ->toArray();
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

        $data['updated_by'] = Auth::user()->name;

        $state = array_merge(
            $data,
            $this->form->getState() ?? [],
            is_array($this->data ?? null) ? $this->data : []
        );
        if (isset($state['form']) && is_array($state['form'])) {
            $state = array_merge($state, $state['form']);
        }

        $this->pendingPermissionIds = $this->extractPermissionIdsFromState($state);
        foreach (UserForm::getDepartamentModules() as $module) {
            unset($data["permissions_{$module}"]);
        }
        unset($data['permissions']);

        return $data;
    }

    /**
     * Extrae los IDs de permisos de un array de estado (data o form state).
     *
     * @param  array<string, mixed>  $state
     * @return list<int>
     */
    private function extractPermissionIdsFromState(array $state): array
    {
        $permissionIds = [];
        foreach (UserForm::getDepartamentModules() as $module) {
            $key = "permissions_{$module}";
            $value = $state[$key] ?? null;
            if (is_array($value) && $value !== []) {
                foreach ($value as $id) {
                    $permissionIds[] = (int) $id;
                }
            }
        }

        return array_values(array_unique($permissionIds));
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
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function mergePermissionsFromModuleFields(array $data): array
    {
        $permissionIds = [];
        foreach (UserForm::getDepartamentModules() as $module) {
            $key = "permissions_{$module}";
            if (! empty($data[$key]) && is_array($data[$key])) {
                $permissionIds = array_merge($permissionIds, $data[$key]);
                unset($data[$key]);
            }
        }
        $data['permissions'] = array_values(array_unique($permissionIds));

        return $data;
    }
}
