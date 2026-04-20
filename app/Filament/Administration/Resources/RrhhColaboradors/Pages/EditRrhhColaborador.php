<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Pages;

use App\Filament\Administration\Resources\RrhhColaboradors\RrhhColaboradorResource;
use App\Models\RrhhColaborador;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditRrhhColaborador extends EditRecord
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected static ?string $title = 'Editar Colaborador';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    protected array $auditChanges = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(RrhhColaboradorResource::getUrl('index'))
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ], merge: true),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon('heroicon-m-trash')
                ->color('danger')
                ->extraAttributes([
                    'class' => self::IOS_DANGER_BUTTON_CLASS,
                ], merge: true),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var RrhhColaborador $record */
        $record = $this->getRecord();

        $data['updated_by'] = Auth::user()?->name ?? ($data['updated_by'] ?? '');

        $trackedFields = [
            'fullName',
            'departmento_id',
            'cargo_id',
            'cedula',
            'sexo',
            'fechaNacimiento',
            'fechaIngreso',
            'telefono',
            'telefonoCorporativo',
            'emailCorporativo',
            'emailAlternativo',
            'emailPersonal',
            'direccion',
            'nroHijos',
            'nroHijoDependiente',
            'tallaCamisa',
            'banck_id',
            'nroCta',
            'codigoCta',
            'tipoCta',
            'status',
            'avatar',
            'sueldo',
            'updated_by',
        ];

        $changes = [];
        foreach ($trackedFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $oldValue = $record->getAttribute($field);
            $newValue = $data[$field];

            if ((string) $oldValue === (string) $newValue) {
                continue;
            }

            $changes[$field] = [
                'old' => $oldValue,
                'new' => $newValue,
            ];
        }

        $this->auditChanges = $changes;

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_ADMIN_RRHH_COLABORADOR_UPDATE_FAILED', 'administration.rrhh-colaboradors.edit', [
                'panel' => 'administration',
                'colaborador_id' => $record->getKey(),
                'full_name' => $record->fullName ?? null,
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
            ], Auth::user());

            throw $th;
        }
    }

    protected function afterSave(): void
    {
        /** @var RrhhColaborador $record */
        $record = $this->getRecord();

        SecurityAudit::log('AUDIT_ADMIN_RRHH_COLABORADOR_UPDATED', 'administration.rrhh-colaboradors.edit', [
            'panel' => 'administration',
            'colaborador_id' => $record->id,
            'full_name' => $record->fullName,
            'status' => $record->status,
            'changed_fields' => $this->auditChanges,
            'changed_fields_count' => count($this->auditChanges),
            'updated_by' => Auth::user()?->name,
        ], Auth::user());
    }
}
