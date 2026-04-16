<?php

namespace App\Filament\Operations\Resources\Suppliers\Pages;

use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditSupplier extends EditRecord
{
    protected static string $resource = SupplierResource::class;

    protected static ?string $title = 'Editar Información del Proveedor';

    /** @var array<string, array{old: mixed, new: mixed}> */
    private array $auditChanges = [];

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()->name;
        $this->auditChanges = $this->resolveChangedFields($data);

        return $data;
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Proveedor Actualizado')
            ->body('El proveedor ha sido actualizado exitosamente.');
    }

    protected function afterSave(): void
    {
        SecurityAudit::log('AUDIT_OPERATIONS_SUPPLIER_UPDATED', 'operations.suppliers.edit', [
            'supplier_id' => $this->record->id,
            'supplier_name' => $this->record->name,
            'updated_by' => Auth::user()?->name,
            'changed_fields' => array_keys($this->auditChanges),
            'changes' => $this->auditChanges,
            'changed_fields_count' => count($this->auditChanges),
        ]);
    }

    /**
     * @param  array<string, mixed>  $incomingData
     * @return array<string, array{old: mixed, new: mixed}>
     */
    private function resolveChangedFields(array $incomingData): array
    {
        $changes = [];

        foreach ($incomingData as $field => $newValue) {
            if ($field === 'updated_at') {
                continue;
            }

            $oldValue = $this->record->getAttribute($field);
            $normalizedOld = $this->normalizeAuditValue($oldValue);
            $normalizedNew = $this->normalizeAuditValue($newValue);

            if ($normalizedOld === $normalizedNew) {
                continue;
            }

            $changes[$field] = [
                'old' => $normalizedOld,
                'new' => $normalizedNew,
            ];
        }

        return $changes;
    }

    private function normalizeAuditValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->normalizeAuditValue($item))
                ->values()
                ->all();
        }

        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return (string) $value;
    }
}
