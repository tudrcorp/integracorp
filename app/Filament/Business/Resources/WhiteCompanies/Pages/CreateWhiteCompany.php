<?php

namespace App\Filament\Business\Resources\WhiteCompanies\Pages;

use App\Models\User;
use App\Models\Agency;
use Illuminate\Support\Facades\Hash;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Business\Resources\WhiteCompanies\WhiteCompanyResource;
use App\Models\Configuration;

class CreateWhiteCompany extends CreateRecord
{
    protected static string $resource = WhiteCompanyResource::class;

    protected static ?string $title = 'Formulario de Registro de Empresas Aliadas';

    protected function afterCreate(): void
    {

        try {

            // dd($this->getRecord(), $this->data);

            //1.- Regitro la empresa como agencia en la tabla de agencias
            $new_agency = new Agency();
            $new_agency->code                       = $this->data['code_agency'];
            $new_agency->rif                        = $this->data['rif'];
            $new_agency->name_corporative           = $this->data['name'];
            $new_agency->address                    = $this->data['address'];
            $new_agency->phone                      = $this->data['phone'];
            $new_agency->email                      = $this->data['email'];
            $new_agency->owner_code                 = $this->data['code_agency'];
            $new_agency->country_id                 = $this->data['country_id'];
            $new_agency->state_id                   = $this->data['state_id'];
            $new_agency->city_id                    = $this->data['city_id'];
            $new_agency->commission_tdec            = $this->data['commission_tdec'];
            $new_agency->commission_tdec_renewal    = $this->data['commission_tdec_renewal'];
            $new_agency->commission_tdev            = $this->data['commission_tdev'];
            $new_agency->commission_tdev_renewal    = $this->data['commission_tdev_renewal'];
            $new_agency->agency_type_id             = $this->data['agency_type'] == 'MASTER' ? 1 : 3;
            $new_agency->status                     = 'ACTIVO';
            $new_agency->save();


            //2.- Regitro el usuario en la tabla de usuario
            $user = new User();
            $user->name                  = $this->data['name'];
            $user->email                 = $this->data['email_administrador'];
            $user->password              = Hash::make($this->data['password']);
            $user->is_agency             = true;
            $user->code_agency           = $this->data['code_agency'];
            $user->agency_type           = $this->data['agency_type'];
            $user->is_whiteCompanyAdmin  = $this->data['is_whiteCompanyAdmin'];
            $user->white_company_id      = $this->getRecord()->id;
            $user->status                = 'ACTIVO';
            $user->save();

            //3.- Registro la configuracion predefinida para la nueva empresa blanca
            $new_white_company_config = new Configuration();
            $new_white_company_config->white_company_id     = $this->getRecord()->id;
            $new_white_company_config->white_company_name   = $this->data['name'];
            $new_white_company_config->email                = $this->data['email'];
            $new_white_company_config->rif                  = $this->data['rif'];
            $new_white_company_config->save();

            
        } catch (\Throwable $th) {
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