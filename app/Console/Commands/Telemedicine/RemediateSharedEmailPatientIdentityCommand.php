<?php

declare(strict_types=1);

namespace App\Console\Commands\Telemedicine;

use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\OperationCoordinationService;
use App\Models\TelemedicineCase;
use App\Models\TelemedicineConsultationPatient;
use App\Models\TelemedicineMedicalReport;
use App\Models\TelemedicinePatient;
use App\Services\AssociateAffiliateCorporateWithTelemedicinePatientService;
use App\Services\AssociateAffiliateWithTelemedicinePatientService;
use App\Support\Telemedicine\TelemedicineCaseIdentity;
use App\Support\Telemedicine\TelemedicinePatientIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

class RemediateSharedEmailPatientIdentityCommand extends Command
{
    protected $signature = 'telemedicine:remediate-shared-email-patient-identity
                            {--apply : Ejecuta la remediación (por defecto solo dry-run)}
                            {--document= : Cédula desplazada a restaurar (ej. 4128740)}';

    protected $description = 'Separa identidades de consultas/servicios cuya cédula no coincide con el paciente FK (p. ej. familiar sobrescrito por email compartido).';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');
        $filterDocument = TelemedicinePatientIdentity::normalizeDocument($this->option('document'));

        $mismatchedConsultations = TelemedicineConsultationPatient::query()
            ->with(['telemedicinePatient:id,full_name,nro_identificacion'])
            ->whereNotNull('nro_identificacion')
            ->where('nro_identificacion', '!=', '')
            ->orderBy('id')
            ->get()
            ->filter(function (TelemedicineConsultationPatient $consultation) use ($filterDocument): bool {
                $patient = $consultation->telemedicinePatient;
                $consultationDocument = TelemedicinePatientIdentity::normalizeDocument($consultation->nro_identificacion);

                if ($consultationDocument === '') {
                    return false;
                }

                if ($filterDocument !== '' && $consultationDocument !== $filterDocument) {
                    return false;
                }

                if ($patient === null) {
                    return true;
                }

                return ! TelemedicinePatientIdentity::documentsMatch(
                    $consultation->nro_identificacion,
                    $patient->nro_identificacion,
                );
            })
            ->values();

        if ($mismatchedConsultations->isEmpty()) {
            $this->info('No hay consultas con cédula distinta al paciente FK'.($filterDocument !== '' ? " para {$filterDocument}" : '').'.');

            return CommandAlias::SUCCESS;
        }

        $this->warn("Consultas inconsistentes: {$mismatchedConsultations->count()}".($apply ? ' (APLICANDO)' : ' (dry-run)'));

        $grouped = $mismatchedConsultations->groupBy(
            fn (TelemedicineConsultationPatient $consultation): string => TelemedicinePatientIdentity::normalizeDocument($consultation->nro_identificacion)
        );

        $failures = 0;

        foreach ($grouped as $document => $consultations) {
            $sample = $consultations->first();
            $this->line("- CI {$document} · {$sample?->full_name} · consultas: ".$consultations->pluck('id')->implode(', '));

            if (! $apply) {
                continue;
            }

            try {
                DB::transaction(function () use ($document, $consultations, $sample): void {
                    $targetPatient = $this->resolveOrCreatePatientForDocument((string) $document, $sample);

                    foreach ($consultations as $consultation) {
                        $previousPatientId = (int) $consultation->telemedicine_patient_id;

                        $consultation->telemedicine_patient_id = $targetPatient->id;
                        $consultation->full_name = $targetPatient->full_name;
                        $consultation->nro_identificacion = $targetPatient->nro_identificacion;
                        $consultation->save();

                        OperationCoordinationService::query()
                            ->where('telemedicine_consultation_patient_id', $consultation->id)
                            ->update([
                                'telemedicine_patient_id' => $targetPatient->id,
                                'patient' => $targetPatient->full_name,
                                'ci_patient' => $targetPatient->nro_identificacion,
                                'birth_date_patient' => $targetPatient->birth_date,
                                'age_patient' => $targetPatient->age,
                                'phone_holder' => $targetPatient->phone,
                                'updated_at' => now(),
                            ]);

                        if (class_exists(TelemedicineMedicalReport::class)) {
                            TelemedicineMedicalReport::query()
                                ->where('telemedicine_consultation_patient_id', $consultation->id)
                                ->update([
                                    'telemedicine_patient_id' => $targetPatient->id,
                                    'updated_at' => now(),
                                ]);
                        }

                        $case = TelemedicineCase::query()->find($consultation->telemedicine_case_id);

                        if ($case !== null) {
                            $caseHasOtherIdentity = TelemedicineConsultationPatient::query()
                                ->where('telemedicine_case_id', $case->id)
                                ->whereKeyNot($consultation->id)
                                ->get()
                                ->contains(function (TelemedicineConsultationPatient $other) use ($targetPatient): bool {
                                    return filled($other->nro_identificacion)
                                        && ! TelemedicinePatientIdentity::documentsMatch(
                                            $other->nro_identificacion,
                                            $targetPatient->nro_identificacion,
                                        );
                                });

                            if (! $caseHasOtherIdentity) {
                                $case->forceFill(TelemedicineCaseIdentity::enforceOnAttributes([
                                    'telemedicine_patient_id' => $targetPatient->id,
                                    'patient_name' => $targetPatient->full_name,
                                ], $targetPatient))->save();
                            }
                        }

                        Log::warning('RemediateSharedEmailPatientIdentity: consulta reasignada', [
                            'consultation_id' => $consultation->id,
                            'from_patient_id' => $previousPatientId,
                            'to_patient_id' => $targetPatient->id,
                            'document' => $document,
                        ]);
                    }
                });
            } catch (Throwable $exception) {
                $failures++;
                $this->error("Error remediando CI {$document}: {$exception->getMessage()}");
                Log::error('RemediateSharedEmailPatientIdentity falló', [
                    'document' => $document,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        if (! $apply) {
            $this->comment('Dry-run finalizado. Ejecute con --apply para corregir.');

            return CommandAlias::SUCCESS;
        }

        if ($failures > 0) {
            $this->warn("Remediación terminada con {$failures} error(es). Revise el log y reintente.");

            return CommandAlias::FAILURE;
        }

        $this->info('Remediación aplicada.');

        return CommandAlias::SUCCESS;
    }

    private function resolveOrCreatePatientForDocument(string $document, ?TelemedicineConsultationPatient $sample): TelemedicinePatient
    {
        $existing = TelemedicinePatient::query()
            ->where('nro_identificacion', $document)
            ->first();

        if ($existing !== null) {
            return $existing;
        }

        if (Schema::hasTable('affiliate_corporates')) {
            $corporate = AffiliateCorporate::query()
                ->where('nro_identificacion', $document)
                ->first();

            if ($corporate !== null) {
                return AssociateAffiliateCorporateWithTelemedicinePatientService::run(
                    $corporate,
                    'Remediación identidad telemedicina',
                )['patient'];
            }
        }

        if (Schema::hasTable('affiliates')) {
            $individual = Affiliate::query()
                ->where('nro_identificacion', $document)
                ->first();

            if ($individual !== null) {
                return AssociateAffiliateWithTelemedicinePatientService::run(
                    $individual,
                    'Remediación identidad telemedicina',
                )['patient'];
            }
        }

        $sourcePatientId = $sample?->telemedicine_patient_id;
        $sourcePatient = filled($sourcePatientId)
            ? TelemedicinePatient::query()->find($sourcePatientId)
            : null;

        $placeholderEmail = 'remediation+'.Str::lower($document).'@telemedicine.local';

        return TelemedicinePatient::query()->create([
            'full_name' => filled($sample?->full_name) ? (string) $sample->full_name : 'PACIENTE '.$document,
            'nro_identificacion' => $document,
            'birth_date' => $sourcePatient?->birth_date ?: '01/01/1900',
            'sex' => $sample?->sex ?: ($sourcePatient?->sex ?: 'NO ESPECIFICADO'),
            'age' => $sample?->age ?: ($sourcePatient?->age ?: 0),
            'phone' => $sample?->phone_ppal ?: ($sourcePatient?->phone ?: '0000000000'),
            'email' => $sourcePatient?->email ?: $placeholderEmail,
            'address' => $sample?->address ?: ($sourcePatient?->address ?: 'NO ESPECIFICADO'),
            'city_id' => $sourcePatient?->city_id ?: '1',
            'country_id' => $sourcePatient?->country_id ?: '189',
            'region' => $sourcePatient?->region ?: 'NO ESPECIFICADO',
            'state_id' => $sourcePatient?->state_id ?: '1',
            'plan_id' => $sourcePatient?->plan_id,
            'coverage_id' => $sourcePatient?->coverage_id,
            'afilliation_id' => $sourcePatient?->afilliation_id,
            'afilliation_corporate_id' => $sourcePatient?->afilliation_corporate_id,
            'code_affiliation' => $sourcePatient?->code_affiliation,
            'status_affiliation' => $sourcePatient?->status_affiliation ?: 'ACTIVO',
            'type_affiliation' => $sourcePatient?->type_affiliation ?: 'CORPORATIVO',
            'name_corporate' => $sourcePatient?->name_corporate,
            'business_unit_id' => $sourcePatient?->business_unit_id,
            'business_line_id' => $sourcePatient?->business_line_id,
            'phone_contact' => $sourcePatient?->phone_contact,
            'email_contact' => $sourcePatient?->email_contact,
            'created_by' => 'Remediación identidad telemedicina',
        ]);
    }
}
