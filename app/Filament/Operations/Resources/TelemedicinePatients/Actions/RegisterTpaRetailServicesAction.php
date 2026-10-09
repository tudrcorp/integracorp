<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePatients\Actions;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use App\Filament\Operations\Resources\TelemedicinePatients\TelemedicinePatientResource;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Models\TelemedicinePatientSpecialty;
use Filament\Actions\Action;
use Illuminate\Support\Facades\Auth;

/**
 * Entrada al registro de servicios TPA/RETAIL desde la ficha del paciente.
 *
 * El registro vive en la página {@see \App\Filament\Operations\Resources\TelemedicinePatients\Pages\RegisterRetailServices}
 * (regla en {@see \App\Support\Operations\RetailServiceRegistration}). Esta clase
 * conserva las utilidades de servicios standalone que usan Coordinación de
 * Servicios, estadísticas y el registro directo.
 */
class RegisterTpaRetailServicesAction
{
    private const NOT_COVERED = 'NO CUBIERTO';

    /**
     * Servicios de alto nivel (sin catálogo de ítems) seleccionables por el analista.
     *
     * @var list<string>
     */
    private const STANDALONE_SPECIFIC_SERVICES = [
        'TELEMEDICINA',
        'AMD (ASISTENCIA MEDICA DOMICILIARIA)',
        'TRASLADO EN AMBULANCIA',
        'CONSULTA ONLINE CON MEDICO ESPECIALISTA',
        'URGEN CARE',
        'APS',
        'INGRESO A CLINICA',
        'LECTURA DE RESULTADOS (LABORATORIO(S))',
        'LECTURA DE RESULTADOS (IMAGENOLOGIA)',
    ];

    public static function make(): Action
    {
        return Action::make('register_tpa_retail_services')
            ->label('Registrar servicios RETAIL')
            ->icon('heroicon-o-clipboard-document-check')
            ->color('success')
            ->url(fn (TelemedicinePatient $record): string => TelemedicinePatientResource::getUrl('retail', ['record' => $record]));
    }

    public static function medicalServicesIndexUrl(?TelemedicineCase $case = null): string
    {
        $url = OperationCoordinationServiceResource::getUrl('index', [
            'tab' => 'pendiente',
        ]);

        if (! $case instanceof TelemedicineCase) {
            return $url;
        }

        $groupTitle = self::caseGroupTitle($case);

        if ($groupTitle === '') {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').http_build_query([
            'expand_group' => $groupTitle,
        ]);
    }

    public static function caseGroupTitle(TelemedicineCase $case): string
    {
        $code = mb_strtoupper(trim((string) ($case->code ?? '')));

        if ($code === '') {
            return '';
        }

        $patientName = trim((string) ($case->patient_name ?? ''));

        return $patientName !== '' ? $code.' · '.$patientName : $code;
    }

    /**
     * @return list<string>
     */
    public static function standaloneSpecificServices(): array
    {
        return self::STANDALONE_SPECIFIC_SERVICES;
    }

    /**
     * @return array<string, string>
     */
    public static function standaloneServiceOptions(): array
    {
        return collect(self::STANDALONE_SPECIFIC_SERVICES)
            ->mapWithKeys(static fn (string $service): array => [$service => $service])
            ->all();
    }

    public static function isStandaloneSpecificService(?string $specificService): bool
    {
        $specificService = trim((string) $specificService);

        return $specificService !== ''
            && in_array($specificService, self::STANDALONE_SPECIFIC_SERVICES, true);
    }

    /**
     * Servicio sin catálogo de ítems (ambulancia, ingreso a clínica, AMD…) que
     * se gestiona con un único ítem «Servicio». Lo crean TPA/RETAIL y el registro
     * directo de servicios médicos ({@see \App\Support\Operations\DirectServiceRegistration}).
     */
    public static function isTpaRetailStandaloneCoordination(OperationCoordinationService $record): bool
    {
        $createdByStandaloneFlow = mb_strtoupper(trim((string) $record->servicie)) === 'TPA/RETAIL'
            || filled($record->direct_service_registration_id);

        return $createdByStandaloneFlow
            && self::isStandaloneSpecificService($record->specific_service);
    }

    /**
     * Garantiza un ítem gestionable (no cubierto) para cotizar el servicio standalone.
     */
    public static function ensureStandaloneManagementItem(OperationCoordinationService $record): void
    {
        if (! self::isTpaRetailStandaloneCoordination($record)) {
            return;
        }

        $specificService = trim((string) $record->specific_service);

        $exists = $record->telemedicinePatientSpecialties()
            ->where('specialty', $specificService)
            ->exists();

        if ($exists) {
            return;
        }

        TelemedicinePatientSpecialty::query()->create([
            'telemedicine_patient_id' => $record->telemedicine_patient_id,
            'telemedicine_case_id' => $record->telemedicine_case_id,
            'telemedicine_doctor_id' => $record->telemedicine_doctor_id,
            'telemedicine_consultation_patient_id' => $record->telemedicine_consultation_patient_id,
            'type' => self::NOT_COVERED,
            'specialty' => $specificService,
            'assigned_by' => Auth::id(),
            'status' => 'PENDIENTE',
            'operation_coordination_service_id' => $record->id,
        ]);
    }
}
