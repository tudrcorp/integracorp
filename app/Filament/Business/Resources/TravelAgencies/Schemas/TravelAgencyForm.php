<?php

namespace App\Filament\Business\Resources\TravelAgencies\Schemas;

use Filament\Forms\Components\TextInput;
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
            ]);
    }
}
