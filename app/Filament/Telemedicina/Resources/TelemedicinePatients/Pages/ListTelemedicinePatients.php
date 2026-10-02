<?php

namespace App\Filament\Telemedicina\Resources\TelemedicinePatients\Pages;

use App\Filament\Telemedicina\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\TelemedicineCase;
use App\Support\Telemedicine\TelemedicineCaseFilamentListQuery;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ListTelemedicinePatients extends ListRecords
{
    protected static string $resource = TelemedicinePatientResource::class;

    protected static ?string $title = 'Ficha del paciente';

    public function getHeading(): string|Htmlable
    {
        $doctorId = Auth::user()?->doctor_id;

        return new HtmlString(view('filament.telemedicina.patients.list-header', [
            'linked' => filled($doctorId),
            'summary' => self::summary(filled($doctorId) ? (int) $doctorId : null),
        ])->render());
    }

    /**
     * Conteos del encabezado en una sola consulta, con el mismo alcance que la tabla:
     * casos del equipo de guardia del médico, sin «PACIENTE DE ALTA» y respetando el scope que oculta los eliminados.
     *
     * @return array{patients: int, assigned: int, follow_up: int, discharged: int}
     */
    public static function summary(?int $doctorId): array
    {
        $empty = ['patients' => 0, 'assigned' => 0, 'follow_up' => 0, 'discharged' => 0];

        if ($doctorId === null) {
            return $empty;
        }

        $row = TelemedicineCaseFilamentListQuery::constrainToDoctorTeamCases(TelemedicineCase::query(), $doctorId)
            ->where('status', '!=', 'PACIENTE DE ALTA')
            ->whereHas('telemedicinePatient')
            ->toBase()
            ->selectRaw(
                'COUNT(DISTINCT telemedicine_patient_id) AS patients_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS assigned_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS follow_up_count, '
                .'SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS discharged_count',
                ['ASIGNADO', 'EN SEGUIMIENTO', 'ALTA MEDICA'],
            )
            ->first();

        return [
            'patients' => (int) ($row->patients_count ?? 0),
            'assigned' => (int) ($row->assigned_count ?? 0),
            'follow_up' => (int) ($row->follow_up_count ?? 0),
            'discharged' => (int) ($row->discharged_count ?? 0),
        ];
    }
}
