<?php

namespace App\Filament\Marketing\Resources\TravelAgencies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;

class TravelAgencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('status'),
                TextInput::make('fechaIngreso'),
                TextInput::make('representante'),
                TextInput::make('idRepresentante'),
                TextInput::make('FechaNacimientoRepresentante'),
                TextInput::make('name'),
                TextInput::make('typeIdentification'),
                TextInput::make('numberIdentification'),
                TextInput::make('userPortalWeb'),
                TextInput::make('aniversary'),
                TextInput::make('country'),
                TextInput::make('state'),
                TextInput::make('city'),
                TextInput::make('address'),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('phoneAdditional')
                    ->tel(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email(),
                TextInput::make('userInstagram'),
                TextInput::make('classification'),
                TextInput::make('comision')
                    ->numeric(),
                TextInput::make('montoCreditoAprobado')
                    ->numeric(),
                TextInput::make('nivel'),
                TextInput::make('agenteSuperiorNivel3'),
                TextInput::make('agenciaSuperiorNivel2'),
                TextInput::make('agenciaPpalNivel1'),
                TextInput::make('createdBy'),
                TextInput::make('updatedBy'),
                Textarea::make('logo')
                    ->columnSpanFull(),
                TextInput::make('nameSecundario'),
                TextInput::make('emailSecundario')
                    ->email(),
                TextInput::make('phoneSecundario')
                    ->tel(),
                TextInput::make('fechaNacimientoSecundario'),
                TextInput::make('local_beneficiary_name'),
                TextInput::make('local_beneficiary_rif'),
                TextInput::make('local_beneficiary_account_number'),
                TextInput::make('local_beneficiary_account_bank'),
                TextInput::make('local_beneficiary_account_type'),
                TextInput::make('local_beneficiary_phone_pm')
                    ->tel(),
                TextInput::make('extra_beneficiary_name'),
                TextInput::make('extra_beneficiary_ci_rif'),
                TextInput::make('extra_beneficiary_account_number'),
                TextInput::make('extra_beneficiary_account_bank'),
                TextInput::make('extra_beneficiary_account_type'),
                TextInput::make('extra_beneficiary_route'),
                TextInput::make('extra_beneficiary_zelle'),
                TextInput::make('extra_beneficiary_ach'),
                TextInput::make('extra_beneficiary_swift'),
                TextInput::make('extra_beneficiary_aba'),
                TextInput::make('extra_beneficiary_address'),
                TextInput::make('local_beneficiary_account_number_mon_inter'),
                TextInput::make('local_beneficiary_account_bank_mon_inter'),
                TextInput::make('local_beneficiary_account_type_mon_inter'),
            ]);
    }
}
