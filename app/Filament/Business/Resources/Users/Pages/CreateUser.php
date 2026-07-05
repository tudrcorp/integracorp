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
}
