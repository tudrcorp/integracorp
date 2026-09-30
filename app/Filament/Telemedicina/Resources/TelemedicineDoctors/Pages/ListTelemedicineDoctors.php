<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Models\TelemedicineDoctor;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ListTelemedicineDoctors extends ListRecords
{
    protected static string $resource = TelemedicineDoctorResource::class;

    protected static ?string $title = 'Mi Perfil';

    public function getHeading(): string|Htmlable
    {
        $doctorId = Auth::user()?->doctor_id;

        $doctor = filled($doctorId)
            ? TelemedicineDoctor::query()
                ->select(['id', 'full_name', 'specialty', 'status', 'image', 'signature', 'code_cm', 'code_mpps'])
                ->find($doctorId)
            : null;

        return new HtmlString(view('filament.telemedicina.doctors.profile-list-header', [
            'fullName' => $doctor?->full_name,
            'specialty' => filled($doctor?->specialty) ? (string) $doctor->specialty : null,
            'status' => filled($doctor?->status) ? (string) $doctor->status : null,
            'checklist' => $doctor instanceof TelemedicineDoctor ? self::profileChecklist($doctor) : [],
        ])->render());
    }

    /**
     * Datos que aparecen en recetas, informes y órdenes; si falta alguno, el documento sale incompleto.
     *
     * @return list<array{label: string, done: bool}>
     */
    public static function profileChecklist(TelemedicineDoctor $doctor): array
    {
        return [
            ['label' => 'Firma digital', 'done' => filled($doctor->signature)],
            ['label' => 'CM', 'done' => filled($doctor->code_cm)],
            ['label' => 'MPPS', 'done' => filled($doctor->code_mpps)],
            ['label' => 'Foto de perfil', 'done' => filled($doctor->image)],
        ];
    }
}
