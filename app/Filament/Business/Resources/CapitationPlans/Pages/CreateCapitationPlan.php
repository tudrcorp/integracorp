<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Pages;

use App\Filament\Business\Resources\CapitationPlans\CapitationPlanResource;
use App\Models\User;
use App\Support\Business\CapitationPlans\CapitationPlanWriter;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateCapitationPlan extends CreateRecord
{
    protected static string $resource = CapitationPlanResource::class;

    protected static ?string $title = 'Nuevo plan de capitado';

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $user = Auth::user();

        return CapitationPlanWriter::save(null, $data, $user instanceof User ? $user : null);
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Plan de capitado creado';
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
