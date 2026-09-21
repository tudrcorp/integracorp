<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Filament\Shared\CommercialStructure\Concerns\SyncsReferidorAssignments;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateAgency extends CreateRecord
{
    use SyncsReferidorAssignments;

    protected static string $resource = AgencyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $this->captureReferidorAssignments($data);
    }

    protected function afterCreate(): void
    {
        $this->persistCapturedReferidorAssignments();

        try {

            $record = $this->getRecord();

            // Si el usuario logueado es un administrador de cuentas
            if (Auth::user()->is_accountManagers) {
                // Actualizo el registro y le agrego el id del administrador de cuenta que realizo el registro
                $record->ownerAccountManagers = Auth::user()->id;
                $record->save();
            }

            if (filled($record->email)) {
                $record->sendCartaBienvenida($record->code, $record->name_corporative, $record->email);
            }

            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_CREATED', 'business.agencies.create', [
                'agency_id' => $record->id,
                'agency_code' => $record->code,
                'agency_name' => $record->name_corporative,
                'agency_email' => $record->email,
                'owner_account_manager_id' => $record->ownerAccountManagers,
            ]);

        } catch (\Throwable $th) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_CREATE_FAILED', 'business.agencies.create', [
                'error' => $th->getMessage(),
                'agency_email' => $this->data['email'] ?? null,
            ]);

            Notification::make()
                ->title('ERROR')
                ->body($th->getMessage())
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();
        }
    }
}
