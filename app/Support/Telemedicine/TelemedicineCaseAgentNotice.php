<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Models\Affiliation;
use App\Models\AffiliationCorporate;
use App\Models\Agency;
use App\Models\Agent;
use App\Models\TelemedicineCase;
use App\Models\TelemedicinePatient;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\PatientAge;
use Illuminate\Support\Carbon;

/**
 * Aviso por WhatsApp al dueño comercial de la afiliación cuando se registra un
 * caso nuevo de telemedicina (Centro de notificaciones → «Nuevo caso al agente
 * de la afiliación»).
 *
 * El dueño es el agente de la afiliación (individual o corporativa); si no
 * tiene agente, o el agente está inactivo o sin WhatsApp válido, se avisa a la
 * agencia. El mensaje lleva solo identificación del paciente, de su afiliación
 * y del caso: nada clínico, porque lo recibe personal comercial.
 */
final class TelemedicineCaseAgentNotice
{
    public const RECIPIENT_AGENT = 'agent';

    public const RECIPIENT_AGENCY = 'agency';

    private const ACTIVE_STATUS = 'ACTIVO';

    /**
     * @return array{
     *     case_id: int,
     *     case_code: string,
     *     registered_at: string,
     *     registered_by: string,
     *     patient_name: string,
     *     patient_document: string,
     *     patient_age: string,
     *     patient_phone: string,
     *     affiliation_type: string|null,
     *     affiliation_code: string,
     *     company: string,
     *     plan: string,
     *     owner: array{kind: string, name: string, code: string, phone: string}|null,
     * }|null  null si el paciente no pertenece a ninguna afiliación
     */
    public static function payload(TelemedicineCase $case): ?array
    {
        $patient = $case->relationLoaded('telemedicinePatient')
            ? $case->telemedicinePatient
            : TelemedicinePatient::query()->find($case->telemedicine_patient_id);

        if (! $patient instanceof TelemedicinePatient) {
            return null;
        }

        $affiliation = self::affiliationOf($patient);

        if ($affiliation === null) {
            return null;
        }

        $registeredAt = $case->created_at instanceof Carbon ? $case->created_at : now();

        return [
            'case_id' => (int) $case->id,
            'case_code' => self::text($case->code),
            'registered_at' => $registeredAt->timezone(config('app.timezone'))->format('d/m/Y h:i A'),
            'registered_by' => self::text($case->assigned_by),
            'patient_name' => self::text(TelemedicinePatientDisplayName::fromPatient($patient)),
            'patient_document' => self::text($patient->nro_identificacion),
            'patient_age' => PatientAge::label($patient->birth_date, $patient->age),
            'patient_phone' => self::text($patient->phone),
            'affiliation_type' => $affiliation instanceof AffiliationCorporate ? 'Corporativa' : 'Individual',
            'affiliation_code' => self::text($affiliation->code ?? $patient->code_affiliation),
            'company' => $affiliation instanceof AffiliationCorporate ? self::text($affiliation->name_corporate ?? $patient->name_corporate) : '',
            'plan' => self::text(TelemedicinePatientPlanBridge::plan($patient)?->description),
            'owner' => self::ownerOf($affiliation),
        ];
    }

    public static function affiliationOf(TelemedicinePatient $patient): Affiliation|AffiliationCorporate|null
    {
        if ((int) ($patient->afilliation_id ?? 0) > 0) {
            $affiliation = Affiliation::query()->find($patient->afilliation_id);

            if ($affiliation instanceof Affiliation) {
                return $affiliation;
            }
        }

        if ((int) ($patient->afilliation_corporate_id ?? 0) > 0) {
            return AffiliationCorporate::query()->find($patient->afilliation_corporate_id);
        }

        return null;
    }

    /**
     * Agente activo con WhatsApp válido; si no, la agencia de la afiliación.
     *
     * @return array{kind: string, name: string, code: string, phone: string}|null
     */
    public static function ownerOf(Affiliation|AffiliationCorporate $affiliation): ?array
    {
        $agent = (int) ($affiliation->agent_id ?? 0) > 0 ? Agent::query()->find($affiliation->agent_id) : null;

        if ($agent instanceof Agent && self::isActive($agent->status)) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($agent->phone);

            if ($phone !== null) {
                return [
                    'kind' => self::RECIPIENT_AGENT,
                    'name' => self::text($agent->name),
                    // Los agentes antiguos no tienen código: no se inventa uno.
                    'code' => trim((string) ($agent->code_agent ?? '')),
                    'phone' => $phone,
                ];
            }
        }

        $agencyCode = trim((string) ($affiliation->code_agency ?? ''));
        $agency = $agencyCode !== '' ? Agency::query()->where('code', $agencyCode)->first() : null;

        if ($agency instanceof Agency && self::isActive($agency->status)) {
            $phone = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($agency->phone);

            if ($phone !== null) {
                return [
                    'kind' => self::RECIPIENT_AGENCY,
                    'name' => self::text($agency->name_corporative),
                    'code' => $agencyCode,
                    'phone' => $phone,
                ];
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function ownerBody(array $payload): string
    {
        $owner = is_array($payload['owner'] ?? null) ? $payload['owner'] : null;
        $greeting = $owner !== null ? 'Hola, *'.$owner['name'].'*.' : 'Hola.';

        return implode("\n", [
            '*Tu Doctor en Casa · Nuevo caso registrado*',
            '',
            $greeting,
            'Le informamos que se registró un caso de telemedicina para uno de sus afiliados.',
            '',
            ...self::detailLines($payload),
            '',
            'Nuestro equipo médico ya está atendiendo al paciente. Este mensaje es informativo y se envía una sola vez por caso.',
        ]);
    }

    /**
     * Copia de control: el mismo detalle, con a quién se le avisó.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function copyBody(array $payload): string
    {
        $owner = is_array($payload['owner'] ?? null) ? $payload['owner'] : null;

        $notified = $owner !== null
            ? 'Se avisó '.self::ownerLabel($owner).'.'
            : '⚠️ No se avisó a nadie: la afiliación no tiene agente ni agencia activos con WhatsApp válido.';

        return implode("\n", [
            '*Copia · Nuevo caso al agente de la afiliación*',
            '',
            $notified,
            '',
            ...self::detailLines($payload),
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function detailLines(array $payload): array
    {
        $lines = [
            '*Datos del caso*',
            TelemedicineDocumentCaseCode::LABEL.': '.($payload['case_code'] ?? '—'),
            'Fecha de registro: '.($payload['registered_at'] ?? '—'),
            'Registrado por: '.($payload['registered_by'] ?? '—'),
            '',
            '*Datos del paciente*',
            'Nombre: '.($payload['patient_name'] ?? '—'),
            'Cédula: '.($payload['patient_document'] ?? '—'),
            'Edad: '.($payload['patient_age'] ?? '—'),
            'Teléfono: '.($payload['patient_phone'] ?? '—'),
            '',
            '*Afiliación*',
            'Tipo: '.($payload['affiliation_type'] ?? '—'),
            'Código: '.($payload['affiliation_code'] ?? '—'),
        ];

        if (filled($payload['company'] ?? null)) {
            $lines[] = 'Empresa: '.$payload['company'];
        }

        $lines[] = 'Plan: '.($payload['plan'] ?? '—');

        return $lines;
    }

    /**
     * «al agente *Nombre* (AGT-000527)» / «a la agencia *Nombre* (TDG-135)».
     *
     * @param  array{kind: string, name: string, code: string, phone: string}  $owner
     */
    public static function ownerLabel(array $owner): string
    {
        $label = ($owner['kind'] === self::RECIPIENT_AGENT ? 'al agente' : 'a la agencia').' *'.$owner['name'].'*';

        return $owner['code'] !== '' ? $label.' ('.$owner['code'].')' : $label;
    }

    private static function isActive(mixed $status): bool
    {
        return mb_strtoupper(trim((string) $status)) === self::ACTIVE_STATUS;
    }

    private static function text(mixed $value): string
    {
        $text = is_scalar($value) ? trim((string) $value) : '';

        return $text === '' ? '—' : $text;
    }
}
