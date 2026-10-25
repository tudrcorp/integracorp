<?php

namespace App\Filament\Operations\Resources\DoctorNurses\Pages;

use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use App\Models\DoctorNurse;
use App\Support\Operations\ProviderDeletion;
use App\Support\SecurityAudit;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditDoctorNurse extends EditRecord
{
    protected static string $resource = DoctorNurseResource::class;

    protected static ?string $title = 'Formulario de Edición del Proveedor Natural';

    /** @var array<string, array{old: mixed, new: mixed}> */
    private array $auditChanges = [];

    // estilos de botones
    private const TICKET_BUTTON_GRAY_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const TICKET_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const TICKET_BUTTON_DANGER_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::user()?->name;
        $this->auditChanges = $this->resolveChangedFields($data);

        return $data;
    }

    protected function afterSave(): void
    {
        SecurityAudit::log('AUDIT_OPERATIONS_DOCTOR_NURSE_UPDATED', 'operations.doctor-nurses.edit', [
            'doctor_nurse_id' => $this->record->id,
            'doctor_nurse_name' => $this->record->name,
            'updated_by' => Auth::user()?->name,
            'changed_fields' => array_keys($this->auditChanges),
            'changes' => $this->auditChanges,
            'changed_fields_count' => count($this->auditChanges),
        ]);
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->icon('heroicon-s-check-circle')
            ->success()
            ->title('Proveedor natural actualizado')
            ->body('El proveedor natural ha sido actualizado exitosamente.');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver Proveedor Natural')
                ->url(fn (DoctorNurse $record) => $this->getResource()::getUrl('view', ['record' => $record]))
                ->color('gray')
                ->icon('heroicon-o-eye')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_GRAY_CLASS,
                ]),
            /** Eliminación reversible, con la misma validación de servicios que la tabla. */
            ProviderDeletion::makeDeleteAction(ProviderDeletion::TYPE_DOCTOR_NURSE)
                ->label('Eliminar Proveedor Natural')
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_DANGER_CLASS,
                ])
                ->after(function (DoctorNurse $record): void {
                    if (DoctorNurse::query()->whereKey($record->getKey())->doesntExist()) {
                        $this->redirect(DoctorNurseResource::getUrl('index'));
                    }
                }),
        ];
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
