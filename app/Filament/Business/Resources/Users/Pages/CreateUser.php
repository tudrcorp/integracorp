<?php

namespace App\Filament\Business\Resources\Users\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use App\Filament\Business\Resources\Users\UserResource;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     *   #attributes: array:16 [▼
            "name" => "asd"
            "email" => "ag@t.com"
            "departament" => "NEGOCIOS"
            "password" => "$2y$12$pNT4ZG.ivCG.DWL00rsrmeEi2S8fcFoaM2XVY2.qPW//kGz/AXawq"
            "is_admin" => false
            "is_agent" => false
            "is_subagent" => false
            "is_agency" => false
            "is_doctor" => false
            "is_designer" => false
            "is_accountManagers" => true
            "is_superAdmin" => false
            "is_business_admin" => true
            "updated_at" => "2025-11-13 11:19:53"
            "created_at" => "2025-11-13 11:19:53"
            "id" => 333
        ]
     */
    protected function afterCreate(): void
    {
        try {

            dd($this->getRecord());

            
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