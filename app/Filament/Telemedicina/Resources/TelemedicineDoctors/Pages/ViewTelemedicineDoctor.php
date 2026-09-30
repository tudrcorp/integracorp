<?php

namespace App\Filament\Telemedicina\Resources\TelemedicineDoctors\Pages;

use App\Filament\Telemedicina\Resources\TelemedicineDoctors\TelemedicineDoctorResource;
use App\Models\TelemedicineDoctor;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use Throwable;

class ViewTelemedicineDoctor extends ViewRecord
{
    protected static string $resource = TelemedicineDoctorResource::class;

    /**
     * Texto plano para la pestaña del navegador; el encabezado visual va en getHeading().
     */
    public function getTitle(): string|Htmlable
    {
        $doctor = $this->getRecord();

        return $doctor instanceof TelemedicineDoctor && filled($doctor->full_name)
            ? 'Perfil · Dr(a). '.$doctor->full_name
            : 'Perfil del médico';
    }

    public function getHeading(): string|Htmlable
    {
        $doctor = $this->getRecord();

        if (! $doctor instanceof TelemedicineDoctor) {
            return 'Perfil del médico';
        }

        $doctor->loadMissing('supplier:id,name');

        if (! array_key_exists('telemedicine_consultation_patients_count', $doctor->getAttributes())) {
            $doctor->loadCount('telemedicineConsultationPatients');
        }

        return new HtmlString(view('filament.telemedicina.doctors.doctor-header', [
            'fullName' => $doctor->full_name,
            'photoUrl' => self::photoUrl($doctor->image),
            'specialty' => filled($doctor->specialty) ? (string) $doctor->specialty : null,
            'status' => filled($doctor->status) ? (string) $doctor->status : null,
            'consultationsCount' => (int) $doctor->telemedicine_consultation_patients_count,
            'details' => self::details($doctor),
        ])->render());
    }

    /**
     * @return list<array{label: string, value: string}>
     */
    public static function details(TelemedicineDoctor $doctor): array
    {
        return array_values(array_filter([
            filled($doctor->nro_identificacion) ? ['label' => 'Cédula', 'value' => (string) $doctor->nro_identificacion] : null,
            filled($doctor->code_cm) ? ['label' => 'CM', 'value' => (string) $doctor->code_cm] : null,
            filled($doctor->code_mpps) ? ['label' => 'MPPS', 'value' => (string) $doctor->code_mpps] : null,
            filled($doctor->code) ? ['label' => 'Código', 'value' => (string) $doctor->code] : null,
            filled($doctor->supplier?->name)
                ? ['label' => 'Proveedor', 'value' => trim(preg_replace('/\s+/u', ' ', (string) $doctor->supplier->name))]
                : (filled($doctor->managed_by) ? ['label' => 'Gestiona', 'value' => (string) $doctor->managed_by] : null),
        ]));
    }

    public static function photoUrl(mixed $path): ?string
    {
        $path = is_string($path) ? trim($path) : '';

        if ($path === '') {
            return null;
        }

        try {
            return Storage::disk(config('filament.default_filesystem_disk', 'public'))->url($path);
        } catch (Throwable) {
            return null;
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regresar')
                ->label('Regresar')
                ->button()
                ->icon('heroicon-s-arrow-left')
                ->color('gray')
                ->url(TelemedicineDoctorResource::getUrl('index')),
            EditAction::make(),
        ];
    }
}
