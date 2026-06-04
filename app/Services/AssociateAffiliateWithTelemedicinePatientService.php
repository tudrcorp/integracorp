<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Affiliate;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssociateAffiliateWithTelemedicinePatientService
{
    /**
     * Registra o actualiza un paciente de telemedicina a partir de un afiliado individual.
     *
     * @return array{patient: TelemedicinePatient, was_recently_created: bool}
     */
    public static function run(Affiliate $affiliate, ?string $createdBy = null): array
    {
        $affiliate->loadMissing('affiliation');

        if ($affiliate->affiliation === null) {
            throw ValidationException::withMessages([
                'affiliate' => ['No se encontró una afiliación asociada a este afiliado.'],
            ]);
        }

        if ($affiliate->status !== 'ACTIVO') {
            throw ValidationException::withMessages([
                'affiliate' => ["El afiliado ({$affiliate->full_name}) no está activo."],
            ]);
        }

        $affiliation = $affiliate->affiliation;
        $emailTitular = Str::lower(trim((string) ($affiliation->email_ti ?? '')));
        $createdByName = $createdBy ?? Auth::user()?->name;

        $attributes = [
            'plan_id' => $affiliation->plan_id,
            'coverage_id' => $affiliation->coverage_id,
            'afilliation_id' => $affiliation->id,
            'code_affiliation' => $affiliation->code,
            'status_affiliation' => 'ACTIVO',
            'type_affiliation' => 'INDIVIDUAL',
            'full_name' => $affiliate->full_name,
            'nro_identificacion' => $affiliate->nro_identificacion,
            'birth_date' => $affiliate->birth_date,
            'sex' => $affiliate->sex,
            'age' => $affiliate->age,
            'phone' => $affiliate->phone,
            'address' => $affiliate->address,
            'city_id' => $affiliate->city_id,
            'country_id' => $affiliate->country_id,
            'region' => $affiliate->region,
            'state_id' => $affiliate->state_id,
            'email' => $emailTitular !== '' ? $emailTitular : ($affiliation->email_ti ?? null),
            'phone_contact' => $affiliation->phone_ti ?? null,
            'email_contact' => filled($affiliation->email_payer ?? null)
                ? Str::lower(trim((string) $affiliation->email_payer))
                : null,
            'created_by' => $createdByName,
            'business_unit_id' => $affiliation->business_unit_id == null ? '----' : $affiliation->business_unit_id,
            'business_line_id' => $affiliation->business_line_id == null ? '----' : $affiliation->business_line_id,
            'supplier_id' => Auth::user()?->supplier_id,
        ];

        $patient = $emailTitular !== ''
            ? TelemedicinePatient::updateOrCreate(['email' => $emailTitular], $attributes)
            : TelemedicinePatient::create($attributes);

        return [
            'patient' => $patient,
            'was_recently_created' => $patient->wasRecentlyCreated,
        ];
    }
}
