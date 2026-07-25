<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineCases\Actions\ReverseTelemedicineCaseAction;
use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineCaseDocumentSendAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewTelemedicineCase extends ViewRecord
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Detalle de Caso';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back_to_cases_dashboard')
                ->label('Volver al dashboard de casos')
                ->button()
                ->icon(Heroicon::ArrowLeft)
                ->color('estandar')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('estandar'),
                ])
                ->url(route('filament.telemedicina.pages.dashboard')),
            ReverseTelemedicineCaseAction::make(
                afterReverse: fn (): mixed => redirect()->to(route('filament.telemedicina.pages.dashboard')),
            ),
            Action::make('returnToConsultation')
                ->label('Volver a Consulta')
                ->icon('heroicon-s-arrow-right')
                ->color('warning')
                ->extraAttributes([
                    'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                ])
                ->action(function () {
                    if (session()->has('historyCasesToDetails')) {
                        // retunr back page
                        session()->forget('historyCasesToDetails');
                        $patient = session()->get('patient');

                        return redirect()->route('filament.telemedicina.resources.telemedicine-consultation-patients.create', ['id' => $patient->id]);
                        // session()->forget('historyCasesToDetails');
                    }
                })
                ->hidden(function () {
                    return ! session()->has('historyCasesToDetails');
                }),
        ];
    }

    public function sendCaseDocumentAction(): Action
    {
        return TelemedicineCaseDocumentSendAction::make();
    }
}
