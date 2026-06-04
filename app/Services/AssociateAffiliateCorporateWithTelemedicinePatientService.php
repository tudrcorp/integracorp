<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AffiliateCorporate;
use App\Models\TelemedicinePatient;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssociateAffiliateCorporateWithTelemedicinePatientService
{
    /**
     * Registra o actualiza un paciente de telemedicina a partir de un afiliado corporativo.
     *
     * @return array{patient: TelemedicinePatient, was_recently_created: bool}
     */
    public static function run(AffiliateCorporate $member, ?string $createdBy = null): array
    {
        $member->loadMissing('affiliationCorporate');

        if ($member->affiliationCorporate === null) {
            throw ValidationException::withMessages([
                'affiliate' => ['No se encontró una afiliación corporativa asociada a este afiliado.'],
            ]);
        }

        if ($member->status !== 'ACTIVO') {
            $displayName = trim("{$member->first_name} {$member->last_name}");

            throw ValidationException::withMessages([
                'affiliate' => ["El afiliado corporativo ({$displayName}) no está activo."],
            ]);
        }

        $affiliation = $member->affiliationCorporate;
        $emailKey = Str::lower(trim((string) ($member->email ?? '')));
        $createdByName = $createdBy ?? Auth::user()?->name;

        $attributes = [
            'name_corporate' => $affiliation->name_corporate,
            'plan_id' => $member->plan_id,
            'coverage_id' => $member->coverage_id,
            'afilliation_corporate_id' => $affiliation->id,
            'code_affiliation' => $affiliation->code,
            'status_affiliation' => 'ACTIVO',
            'type_affiliation' => 'CORPORATIVO',
            'full_name' => $member->first_name,
            'nro_identificacion' => $member->nro_identificacion,
            'birth_date' => $member->birth_date,
            'sex' => $member->sex,
            'age' => $member->age,
            'phone' => $member->phone,
            'address' => $member->address,
            'city_id' => $affiliation->city_id,
            'country_id' => $affiliation->country_id,
            'region' => $affiliation->region_id,
            'state_id' => $affiliation->state_id,
            'email' => $emailKey !== '' ? $emailKey : ($member->email ?? null),
            'phone_contact' => $affiliation->phone,
            'email_contact' => $affiliation->email,
            'created_by' => $createdByName,
            'business_unit_id' => $affiliation->business_unit_id == null ? null : $affiliation->business_unit_id,
            'business_line_id' => $affiliation->business_line_id == null ? null : $affiliation->business_line_id,
            'supplier_id' => Auth::user()?->supplier_id,
        ];

        $patient = $emailKey !== ''
            ? TelemedicinePatient::updateOrCreate(['email' => $emailKey], $attributes)
            : TelemedicinePatient::create($attributes);

        return [
            'patient' => $patient,
            'was_recently_created' => $patient->wasRecentlyCreated,
        ];
    }
}
