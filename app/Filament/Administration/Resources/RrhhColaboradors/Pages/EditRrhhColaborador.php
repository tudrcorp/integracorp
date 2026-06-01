<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Pages;

use App\Filament\Administration\Resources\RrhhColaboradors\RrhhColaboradorResource;
use App\Filament\Administration\Resources\RrhhColaboradors\Schemas\RrhhColaboradorForm;
use App\Models\RrhhColaborador;
use App\Support\SecurityAudit;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditRrhhColaborador extends EditRecord
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected static ?string $title = 'Editar Colaborador';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_DANGER_BUTTON_CLASS = 'aviso-btn-ios-danger shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_PRIMARY_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    /**
     * @var array<string, array{old:mixed,new:mixed}>
     */
    protected array $auditChanges = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (empty($data['birth_date'] ?? null) && ! empty($data['fechaNacimiento'] ?? null)) {
            $data['birth_date'] = RrhhColaborador::normalizeBirthDateInput($data['fechaNacimiento']);
        }

        $data = self::normalizeBirthDateFormData($data);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('update_avatar')
                ->label('Foto de perfil')
                ->icon('heroicon-m-camera')
                ->color('primary')
                ->modalHeading('Foto de perfil')
                ->modalDescription('Sube o recorta la imagen del colaborador. Se mostrará en listados, asignaciones y firmas internas.')
                ->modalIcon('heroicon-m-user-circle')
                ->modalWidth(Width::TwoExtraLarge)
                ->fillForm(fn (): array => [
                    'avatar' => $this->getRecord()->avatar,
                ])
                ->form([
                    RrhhColaboradorForm::avatarUploadField(),
                ])
                ->action(function (array $data): void {
                    /** @var RrhhColaborador $record */
                    $record = $this->getRecord();

                    $normalizedOld = $this->normalizeAuditValue($record->avatar);
                    $normalizedNew = $this->normalizeAuditValue($this->resolveAvatarPath($data['avatar'] ?? null));

                    if ($normalizedOld === $normalizedNew) {
                        Notification::make()
                            ->title('Sin cambios en la foto')
                            ->body('La imagen seleccionada es la misma que la actual.')
                            ->info()
                            ->send();

                        return;
                    }

                    try {
                        $record->update([
                            'avatar' => $normalizedNew,
                            'updated_by' => Auth::user()?->name ?? $record->updated_by,
                        ]);
                    } catch (\Throwable $th) {
                        SecurityAudit::log('AUDIT_ADMIN_RRHH_COLABORADOR_AVATAR_UPDATE_FAILED', 'administration.rrhh-colaboradors.edit', [
                            'panel' => 'administration',
                            'colaborador_id' => $record->getKey(),
                            'full_name' => $record->fullName ?? null,
                            'error_message' => $th->getMessage(),
                            'error_class' => $th::class,
                        ], Auth::user());

                        throw $th;
                    }

                    SecurityAudit::log('AUDIT_ADMIN_RRHH_COLABORADOR_AVATAR_UPDATED', 'administration.rrhh-colaboradors.edit', [
                        'panel' => 'administration',
                        'colaborador_id' => $record->id,
                        'full_name' => $record->fullName,
                        'changed_fields' => [
                            'avatar' => [
                                'old' => $normalizedOld,
                                'new' => $normalizedNew,
                            ],
                        ],
                        'updated_by' => Auth::user()?->name,
                    ], Auth::user());

                    Notification::make()
                        ->title('Foto de perfil actualizada')
                        ->success()
                        ->send();
                })
                ->extraAttributes([
                    'class' => self::IOS_PRIMARY_BUTTON_CLASS,
                ], merge: true),
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

        $data = self::normalizeBirthDateFormData($data);

        $trackedFields = [
            'fullName',
            'departmento_id',
            'cargo_id',
            'cedula',
            'sexo',
            'birth_date',
            'age',
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
            'sueldo',
            'documents',
            'updated_by',
        ];

        $changes = [];
        foreach ($trackedFields as $field) {
            if (! array_key_exists($field, $data)) {
                continue;
            }

            $oldValue = $record->getAttribute($field);
            $newValue = $data[$field];

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

        $this->auditChanges = $changes;

        return $data;
    }

    private function normalizeAuditValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return collect($value)
                ->map(fn (mixed $item): mixed => $this->normalizeAuditValue($item))
                ->values()
                ->all();
        }

        if ($value instanceof CarbonInterface) {
            return $value->format('Y-m-d');
        }

        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return (string) $value;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function normalizeBirthDateFormData(array $data): array
    {
        if (! array_key_exists('birth_date', $data)) {
            return $data;
        }

        $normalized = RrhhColaborador::normalizeBirthDateInput($data['birth_date']);
        $data['birth_date'] = $normalized;
        $data['age'] = RrhhColaborador::completedYearsFromBirthDate($normalized);

        return $data;
    }

    private function resolveAvatarPath(mixed $avatar): ?string
    {
        if ($avatar === null || $avatar === '') {
            return null;
        }

        if (is_string($avatar)) {
            return trim($avatar);
        }

        if (is_array($avatar)) {
            foreach ($avatar as $path) {
                if (is_string($path) && $path !== '') {
                    return trim($path);
                }
            }
        }

        return null;
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
