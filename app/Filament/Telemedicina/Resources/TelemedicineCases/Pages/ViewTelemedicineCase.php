<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineCases\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineCases\Actions\ReverseTelemedicineCaseAction;
use App\Filament\Telemedicina\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Filament\Telemedicina\Resources\TelemedicineConsultationPatients\Pages\CreateTelemedicineConsultationPatient;
use App\Models\TelemedicineCase;
use App\Support\Filament\FilamentIosButton;
use App\Support\Telemedicine\TelemedicineCaseDocumentSendAction;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewTelemedicineCase extends ViewRecord
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Detalle de Caso';

    /**
     * Texto plano para la pestaña del navegador; el encabezado visual va en getHeading().
     */
    public function getTitle(): string|Htmlable
    {
        $case = $this->getRecord();

        return $case instanceof TelemedicineCase && filled($case->code)
            ? 'Caso '.$case->code.(filled($case->patient_name) ? ' · '.$case->patient_name : '')
            : 'Detalle de Caso';
    }

    public function getHeading(): string|Htmlable
    {
        $case = $this->getRecord();

        if (! $case instanceof TelemedicineCase) {
            return 'Detalle de Caso';
        }

        $case->loadMissing([
            'telemedicinePatient:id,full_name,nro_identificacion,age,sex',
            'telemedicineDoctor:id,full_name',
            'priority:id,name',
        ]);

        if (! array_key_exists('consultations_count', $case->getAttributes())) {
            $case->loadCount('consultations');
        }

        $openedAt = $case->created_at?->timezone(config('app.timezone'));

        return new HtmlString(view('filament.telemedicina.cases.case-header', [
            'caseCode' => $case->code,
            'status' => $case->status,
            'priority' => $case->priority?->name,
            'managedBy' => $case->managed_by,
            'patientName' => $case->telemedicinePatient?->full_name ?? $case->patient_name,
            'patientDetails' => self::patientDetails($case),
            'doctorName' => $case->telemedicineDoctor?->full_name,
            'reason' => filled($case->reason) ? trim(strip_tags((string) $case->reason)) : null,
            'openedAt' => $openedAt?->format('d/m/Y h:i A'),
            'openedAtHuman' => $openedAt?->locale('es')->diffForHumans(),
            'consultationsCount' => (int) $case->consultations_count,
        ])->render());
    }

    /**
     * @return list<string>
     */
    public static function patientDetails(TelemedicineCase $case): array
    {
        $patient = $case->telemedicinePatient;
        $document = CreateTelemedicineConsultationPatient::formatPatientDocument($patient?->nro_identificacion);
        $age = $patient?->age ?? $case->patient_age;
        $sex = $patient?->sex ?? $case->patient_sex;

        return array_values(array_filter([
            $document,
            filled($age) && is_numeric($age) ? ((int) $age).' '.((int) $age === 1 ? 'año' : 'años') : null,
            filled($sex) ? (string) $sex : null,
        ]));
    }

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
                        session()->forget('historyCasesToDetails');
                        $patient = session()->get('patient');
                        $case = session()->get('case');

                        if ($patient) {
                            return redirect()->to(\App\Support\Telemedicine\ConsultationCreateRoute::url($patient, $case instanceof \App\Models\TelemedicineCase ? $case : null));
                        }
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
