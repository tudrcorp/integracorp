<?php

namespace App\Filament\Administration\Resources\RrhhColaboradors\Pages;

use App\Filament\Administration\Resources\RrhhColaboradors\RrhhColaboradorResource;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateRrhhColaborador extends CreateRecord
{
    protected static string $resource = RrhhColaboradorResource::class;

    protected static ?string $title = 'Nuevo Colaborador';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

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
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::user()?->name ?? ($data['created_by'] ?? '');
        $data['updated_by'] = Auth::user()?->name ?? ($data['updated_by'] ?? '');

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_ADMIN_RRHH_COLABORADOR_CREATE_FAILED', 'administration.rrhh-colaboradors.create', [
                'panel' => 'administration',
                'full_name' => $data['fullName'] ?? null,
                'email_corporativo' => $data['emailCorporativo'] ?? null,
                'error_message' => $th->getMessage(),
                'error_class' => $th::class,
                'error_file' => $th->getFile(),
                'error_line' => $th->getLine(),
            ], Auth::user());

            throw $th;
        }
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        SecurityAudit::log('AUDIT_ADMIN_RRHH_COLABORADOR_CREATED', 'administration.rrhh-colaboradors.create', [
            'panel' => 'administration',
            'colaborador_id' => $record->id,
            'full_name' => $record->fullName,
            'status' => $record->status,
            'departmento_id' => $record->departmento_id,
            'cargo_id' => $record->cargo_id,
            'created_by' => Auth::user()?->name,
        ], Auth::user());
    }
}
