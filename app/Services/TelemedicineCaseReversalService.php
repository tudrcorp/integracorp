<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\TelemedicineCase;
use App\Support\SecurityAudit;
use App\Support\Telemedicine\TelemedicineCaseReversalNotifier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Throwable;

final class TelemedicineCaseReversalService
{
    /**
     * @return array<string, mixed>
     *
     * @throws Throwable
     */
    public function reverse(TelemedicineCase $case, string $reversalNote): array
    {
        $note = trim($reversalNote);

        if ($note === '') {
            throw new \InvalidArgumentException('La nota del reverso es obligatoria.');
        }

        $status = mb_strtoupper(trim((string) $case->status));

        if (in_array($status, ['ALTA MEDICA', 'EN SEGUIMIENTO'], true)) {
            throw new \InvalidArgumentException('Los casos en seguimiento o con alta médica no pueden ser reversados.');
        }

        $case->loadMissing(['telemedicineDoctor', 'telemedicinePatient', 'priority']);

        $user = Auth::user();
        $reversedAt = now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i:s');

        $payload = [
            'case_id' => (int) $case->id,
            'case_code' => (string) $case->code,
            'patient_name' => (string) ($case->patient_name ?? $case->telemedicinePatient?->full_name ?? '—'),
            'patient_phone' => $case->patient_phone,
            'patient_address' => $case->patient_address,
            'patient_age' => $case->patient_age !== null ? (string) $case->patient_age : null,
            'patient_sex' => $case->patient_sex,
            'reason' => $case->reason,
            'status' => $case->status,
            'managed_by' => $case->managed_by,
            'assigned_by' => $case->assigned_by,
            'doctor_name' => $case->telemedicineDoctor?->full_name,
            'doctor_id' => $case->telemedicine_doctor_id,
            'priority' => $case->priority?->name,
            'reversed_by' => (string) ($user?->name ?? 'SISTEMA'),
            'reversed_by_user_id' => $user?->id,
            'reversal_note' => $note,
            'reversed_at' => $reversedAt,
            'telemedicine_patient_id' => $case->telemedicine_patient_id,
            'supplier_id' => $case->supplier_id,
        ];

        DB::transaction(function () use ($case, $payload): void {
            SecurityAudit::log('AUDIT_TELEMEDICINE_CASE_REVERSAL_STARTED', 'telemedicina.cases.reverse', [
                'telemedicine_case_id' => $payload['case_id'],
                'telemedicine_case_code' => $payload['case_code'],
                'telemedicine_patient_id' => $payload['telemedicine_patient_id'],
                'doctor_id' => $payload['doctor_id'],
                'reversal_note' => $payload['reversal_note'],
            ]);

            $case->delete();

            SecurityAudit::log('AUDIT_TELEMEDICINE_CASE_REVERSED', 'telemedicina.cases.reverse', [
                'telemedicine_case_id' => $payload['case_id'],
                'telemedicine_case_code' => $payload['case_code'],
                'telemedicine_patient_id' => $payload['telemedicine_patient_id'],
                'doctor_id' => $payload['doctor_id'],
                'reversed_by' => $payload['reversed_by'],
                'reversal_note' => $payload['reversal_note'],
            ]);
        });

        TelemedicineCaseReversalNotifier::notify($payload);

        return $payload;
    }
}
