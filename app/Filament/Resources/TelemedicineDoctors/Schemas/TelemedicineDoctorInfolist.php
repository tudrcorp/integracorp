<?php

namespace App\Filament\Resources\TelemedicineDoctors\Schemas;

use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class TelemedicineDoctorInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('first_name'),
                TextEntry::make('last_name'),
                TextEntry::make('nro_identificacion'),
                TextEntry::make('email')
                    ->label('Email address'),
                TextEntry::make('code_cm'),
                TextEntry::make('code_mpps'),
                TextEntry::make('phone'),
                TextEntry::make('specialty'),
                TextEntry::make('address'),
                ImageEntry::make('image'),
                TextEntry::make('signature'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }
}
