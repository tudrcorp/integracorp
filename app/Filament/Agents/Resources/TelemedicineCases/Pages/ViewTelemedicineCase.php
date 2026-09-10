<?php

declare(strict_types=1);

namespace App\Filament\Agents\Resources\TelemedicineCases\Pages;

use App\Filament\Agents\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Shared\CommercialTelemedicine\CommercialTelemedicinePanel;
use App\Models\TelemedicineCase;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewTelemedicineCase extends ViewRecord
{
    protected static string $resource = TelemedicineCaseResource::class;

    public function getTitle(): string|Htmlable
    {
        $case = $this->getRecord();

        if ($case instanceof TelemedicineCase && filled($case->code)) {
            return 'Caso '.$case->code;
        }

        return 'Detalle del caso';
    }

    protected function getHeaderActions(): array
    {
        $case = $this->getRecord();
        $actions = [];

        $actions[] = Action::make('back_to_cases')
            ->label('Volver al listado')
            ->icon(Heroicon::OutlinedArrowLeft)
            ->color('gray')
            ->url(TelemedicineCaseResource::getUrl('index'))
            ->visible(fn (): bool => CommercialTelemedicinePanel::canViewCases());

        if ($case instanceof TelemedicineCase && $case->telemedicine_patient_id && CommercialTelemedicinePanel::canViewPatients()) {
            $actions[] = Action::make('view_patient')
                ->label('Ver paciente')
                ->icon(Heroicon::OutlinedUser)
                ->color('gray')
                ->url(CommercialTelemedicinePanel::patientViewUrl((int) $case->telemedicine_patient_id));
        }

        return $actions;
    }
}
