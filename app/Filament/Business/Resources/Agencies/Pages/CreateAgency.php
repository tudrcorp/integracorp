<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use App\Models\Agent;
use App\Models\User;
use App\Support\SecurityAudit;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CreateAgency extends CreateRecord
{
    protected static string $resource = AgencyResource::class;

    /**
     * Metodo que se ejecuta antes de crear un registro
     * Valida que el RIF y el correo electrónico no se encuentren registrados en la base de datos.
     */
    protected function beforeCreate(): void
    {

        $email = $this->data['email'];

        if (Agent::where('email', $email)->exists()) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_CREATE_FAILED', 'business.agencies.create', [
                'email' => $email,
                'reason' => 'email_exists_in_agents',
            ]);

            Notification::make()
                ->title('ERROR')
                ->body('El Correo electrónico ya se encuentra registrado en la tabla de agentes. Por favor intenta con otro correo electrónico')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();

            Log::warning('NEGOCIOS: El Usuario '.Auth::user()->name.' intento registrar una agencia con un correo electrónico ya existente en la tabla de agentes.');

            $this->halt();
        }

        if (User::where('email', $email)->exists()) {
            SecurityAudit::log('AUDIT_BUSINESS_AGENCY_CREATE_FAILED', 'business.agencies.create', [
                'email' => $email,
                'reason' => 'email_exists_in_users',
            ]);

            Notification::make()
                ->title('ERROR')
                ->body('El Correo electrónico ya se encuentra registrado en la tabla de usuarios. Por favor intenta con otro correo electrónico')
                ->icon('heroicon-m-tag')
                ->iconColor('danger')
                ->danger()
                ->send();

            Log::warning('NEGOCIOS: El Usuario '.Auth::user()->name.' intento registrar una agencia con un correo electrónico ya existente en la tabla de usuarios.');

            $this->halt();
        }

    }

    protected function afterCreate(): void
    {
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
