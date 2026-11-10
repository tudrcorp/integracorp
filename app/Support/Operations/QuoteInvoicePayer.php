<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\AffiliationCorporate;
use App\Models\Company;
use App\Models\CompanyAssociate;
use App\Models\OperationCoordinationService;
use App\Models\OperationMedicationInvoice;
use App\Models\TelemedicinePatient;
use App\Support\Telemedicine\TelemedicinePatientDisplayName;
use App\Support\Telemedicine\TelemedicinePatientPlanBridge;

/**
 * Responsable de pago de la factura que nace de una cotización (10/11/2026).
 *
 * - Paciente: nombre y apellido, cédula y dirección. La dirección sale de la ficha del paciente
 *   o, si está vacía, de la de su afiliado; si tampoco hay, el analista la escribe.
 * - Grupo corporativo: razón social, RIF y dirección de la afiliación corporativa del paciente.
 *   Para un asociado de Nuevos Negocios, su empresa. Un afiliado individual no tiene grupo:
 *   la opción queda deshabilitada con el motivo.
 *
 * Los datos se congelan en la cotización al crearla y la factura los copia al aprobarla.
 */
final class QuoteInvoicePayer
{
    public const ADDRESS_MAX = 500;

    /** @var array<int, array<string, mixed>> Memoria por request: el formulario pregunta varias veces en un mismo render. */
    private static array $memo = [];

    /**
     * @return array{
     *     patient: array{name: string, identification: string|null, address: string|null},
     *     corporate: array{name: string, identification: string|null, address: string|null}|null,
     *     corporate_unavailable_reason: string|null
     * }
     */
    public static function options(OperationCoordinationService $record): array
    {
        $id = (int) $record->getKey();

        if ($id <= 0) {
            return self::resolve($record);
        }

        return self::$memo[$id] ??= self::resolve($record);
    }

    /**
     * @return array{name: string, identification: string|null, address: string|null}|null
     */
    public static function payer(OperationCoordinationService $record, ?string $type): ?array
    {
        $options = self::options($record);

        return match ($type) {
            OperationMedicationInvoice::PAYER_PATIENT => $options['patient'],
            OperationMedicationInvoice::PAYER_CORPORATE => $options['corporate'],
            default => null,
        };
    }

    /**
     * Datos a congelar en la cotización. La dirección escrita por el analista solo se usa cuando
     * la ficha no trae una.
     *
     * @return array{invoice_payer_type: string, invoice_payer_name: string, invoice_payer_identification: string|null, invoice_payer_address: string}
     *
     * @throws MedicationInvoiceException
     */
    public static function snapshot(OperationCoordinationService $record, ?string $type, mixed $manualAddress): array
    {
        if (! in_array($type, [OperationMedicationInvoice::PAYER_PATIENT, OperationMedicationInvoice::PAYER_CORPORATE], true)) {
            throw new MedicationInvoiceException('Indique quién es el responsable de pago de la factura.');
        }

        $resolved = self::resolve($record);
        $payer = $type === OperationMedicationInvoice::PAYER_PATIENT ? $resolved['patient'] : $resolved['corporate'];

        if ($payer === null) {
            throw new MedicationInvoiceException($resolved['corporate_unavailable_reason'] ?? 'El paciente no pertenece a un grupo corporativo.');
        }

        $address = $payer['address'] ?? self::clean($manualAddress);

        if ($address === null) {
            throw new MedicationInvoiceException('Escriba la dirección del responsable de pago: su ficha no tiene una.');
        }

        return [
            'invoice_payer_type' => $type,
            'invoice_payer_name' => $payer['name'],
            'invoice_payer_identification' => $payer['identification'],
            'invoice_payer_address' => mb_substr($address, 0, self::ADDRESS_MAX),
        ];
    }

    public static function flush(): void
    {
        self::$memo = [];
    }

    /**
     * @return array{
     *     patient: array{name: string, identification: string|null, address: string|null},
     *     corporate: array{name: string, identification: string|null, address: string|null}|null,
     *     corporate_unavailable_reason: string|null
     * }
     */
    private static function resolve(OperationCoordinationService $record): array
    {
        $record->loadMissing('telemedicinePatient');
        $patient = $record->telemedicinePatient;

        $patientName = TelemedicinePatientDisplayName::forCoordination($record);
        $patientData = [
            'name' => $patientName !== '' && $patientName !== '—' ? $patientName : (string) ($patient?->full_name ?? '—'),
            'identification' => self::clean($patient?->nro_identificacion),
            'address' => $patient instanceof TelemedicinePatient ? self::patientAddress($patient) : null,
        ];

        if (! $patient instanceof TelemedicinePatient) {
            return ['patient' => $patientData, 'corporate' => null, 'corporate_unavailable_reason' => 'El servicio no tiene un paciente asociado.'];
        }

        $type = mb_strtoupper(trim((string) $patient->type_affiliation));
        $corporate = match (true) {
            $type === 'NUEVOS NEGOCIOS' => self::associateCompany($patient),
            default => self::corporateAffiliation($patient),
        };

        $reason = null;

        if ($corporate === null) {
            $reason = match (true) {
                $type === 'INDIVIDUAL' => 'El paciente es afiliado individual: no pertenece a un grupo corporativo.',
                $type === 'NUEVOS NEGOCIOS' => 'No se encontró la empresa de Nuevos Negocios del paciente.',
                $type === 'CORPORATIVO' => 'No se encontró la afiliación corporativa del paciente.',
                default => 'El paciente no está vinculado a un grupo corporativo.',
            };
        }

        return ['patient' => $patientData, 'corporate' => $corporate, 'corporate_unavailable_reason' => $reason];
    }

    private static function patientAddress(TelemedicinePatient $patient): ?string
    {
        $address = self::clean($patient->address);

        if ($address !== null) {
            return $address;
        }

        $affiliate = TelemedicinePatientPlanBridge::linkedAffiliate($patient)
            ?? TelemedicinePatientPlanBridge::linkedAffiliateCorporate($patient);

        return self::clean($affiliate?->address);
    }

    /**
     * @return array{name: string, identification: string|null, address: string|null}|null
     */
    private static function corporateAffiliation(TelemedicinePatient $patient): ?array
    {
        $affiliation = filled($patient->afilliation_corporate_id)
            ? AffiliationCorporate::query()->withTrashed()->find($patient->afilliation_corporate_id)
            : null;

        if (! $affiliation instanceof AffiliationCorporate) {
            $linked = TelemedicinePatientPlanBridge::linkedAffiliateCorporate($patient);
            $affiliation = filled($linked?->affiliation_corporate_id)
                ? AffiliationCorporate::query()->withTrashed()->find($linked->affiliation_corporate_id)
                : null;
        }

        if (! $affiliation instanceof AffiliationCorporate || self::clean($affiliation->name_corporate) === null) {
            return null;
        }

        return [
            'name' => (string) self::clean($affiliation->name_corporate),
            'identification' => self::rif($affiliation->document_type_rif, $affiliation->rif),
            'address' => self::clean($affiliation->address),
        ];
    }

    /**
     * @return array{name: string, identification: string|null, address: string|null}|null
     */
    private static function associateCompany(TelemedicinePatient $patient): ?array
    {
        $company = null;
        $identification = self::clean($patient->nro_identificacion);

        if ($identification !== null) {
            $company = CompanyAssociate::query()
                ->where('identity_card', $identification)
                ->whereNotNull('company_id')
                ->orderByRaw("CASE WHEN UPPER(COALESCE(status, '')) = 'ANULADO' THEN 1 ELSE 0 END")
                ->latest('id')
                ->first()
                ?->company;
        }

        if (! $company instanceof Company && self::clean($patient->name_corporate) !== null) {
            $company = Company::query()
                ->whereRaw('UPPER(TRIM(name)) = ?', [mb_strtoupper((string) self::clean($patient->name_corporate))])
                ->latest('id')
                ->first();
        }

        if (! $company instanceof Company || self::clean($company->name) === null) {
            return null;
        }

        return [
            'name' => (string) self::clean($company->name),
            'identification' => self::rif(null, $company->rif),
            'address' => self::clean($company->address),
        ];
    }

    private static function rif(?string $type, mixed $number): ?string
    {
        $number = self::clean($number);

        if ($number === null) {
            return null;
        }

        $type = mb_strtoupper((string) self::clean($type));

        if ($type === '' || preg_match('/^[VEJGP]/i', $number) === 1) {
            return mb_strtoupper($number);
        }

        return $type.'-'.$number;
    }

    private static function clean(mixed $value): ?string
    {
        $text = trim(preg_replace('/\s+/u', ' ', (string) $value) ?? '');

        return $text === '' ? null : $text;
    }
}
