<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\CompanyAssociate;
use App\Support\Telemedicine\TelemedicinePatientAssociationResolver;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssociateCompanyAssociateWithTelemedicinePatientService
{
    /**
     * Registra o actualiza un paciente de telemedicina a partir de un asociado de nuevos negocios.
     *
     * @return array{patient: \App\Models\TelemedicinePatient, was_recently_created: bool}
     */
    public static function run(CompanyAssociate $associate, ?string $createdBy = null, ?string $sexOverride = null): array
    {
        $associate->loadMissing(['company', 'state', 'city']);

        if ($associate->company === null) {
            throw ValidationException::withMessages([
                'associate' => ['No se encontró una empresa asociada a este registro de nuevos negocios.'],
            ]);
        }

        $countryId = $associate->state?->country_id
            ?? $associate->city?->country_id;
        $regionId = $associate->state?->region_id;
        $stateId = $associate->state_id ?? $associate->city?->state_id;
        $cityId = $associate->city_id;

        if (blank($countryId) || blank($regionId) || blank($stateId) || blank($cityId)) {
            throw ValidationException::withMessages([
                'associate' => ['El asociado no tiene ubicación completa (país, región, estado y ciudad). Complete esos datos antes de asociarlo como paciente.'],
            ]);
        }

        $emailKey = Str::lower(trim((string) ($associate->email ?? '')));
        $createdByName = $createdBy ?? Auth::user()?->name;
        $company = $associate->company;
        $sex = TelemedicinePatientIdentity::normalizeSex($sexOverride)
            ?? TelemedicinePatientIdentity::normalizeSex($associate->sex);

        $attributes = [
            'name_corporate' => $company->name,
            'plan_id' => null,
            'coverage_id' => null,
            'afilliation_id' => null,
            'afilliation_corporate_id' => null,
            'code_affiliation' => filled($associate->vaucher_ils) ? (string) $associate->vaucher_ils : null,
            'status_affiliation' => 'ACTIVO',
            'type_affiliation' => 'NUEVOS NEGOCIOS',
            'full_name' => $associate->full_name,
            'nro_identificacion' => $associate->identity_card,
            'birth_date' => $associate->birth_date,
            'sex' => $sex,
            'age' => $associate->age,
            'phone' => $associate->phone,
            'address' => $company->address,
            'city_id' => $cityId,
            'country_id' => $countryId,
            'region' => $regionId,
            'state_id' => $stateId,
            'email' => $emailKey !== '' ? $emailKey : ($associate->email ?? null),
            'phone_contact' => $associate->contact_phone ?? $company->phone,
            'email_contact' => filled($associate->contact_email)
                ? Str::lower(trim((string) $associate->contact_email))
                : (filled($company->email) ? Str::lower(trim((string) $company->email)) : null),
            'created_by' => $createdByName,
            'business_unit_id' => null,
            'business_line_id' => null,
            'supplier_id' => Auth::user()?->supplier_id,
        ];

        return DB::transaction(function () use ($associate, $sex, $attributes): array {
            TelemedicinePatientIdentity::persistCanonicalSexIfSourceMissing($associate, $sex);

            return TelemedicinePatientAssociationResolver::upsertByDocument($attributes);
        });
    }
}
