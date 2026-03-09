<?php

namespace App\Filament\Business\Resources\Users\Pages;

use App\Filament\Business\Resources\Users\Schemas\UserForm;
use App\Filament\Business\Resources\Users\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /** @var list<int|string> IDs de permisos a sincronizar tras crear (desde el formulario). */
    protected array $pendingPermissionIds = [];

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = $this->mergePermissionsFromModuleFields($data);
        $this->pendingPermissionIds = array_values(array_unique($data['permissions'] ?? []));
        unset($data['permissions']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->pendingPermissionIds !== []) {
            $pivotValues = [
                'created_by' => Auth::user()->name,
                'updated_by' => Auth::user()->name,
            ];
            $this->record->permissions()->syncWithPivotValues(
                array_map('intval', $this->pendingPermissionIds),
                $pivotValues
            );
        }
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
