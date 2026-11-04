<?php

declare(strict_types=1);

namespace App\Support\Operations\QualityControl;

use App\Enums\QualityControlAffiliateType;
use App\Enums\QualityControlRating;
use App\Enums\QualityControlService;
use App\Models\QualityControlSurvey;
use App\Support\SecurityAudit;
use Carbon\CarbonImmutable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta, edición y baja de encuestas desde el formulario de Operaciones.
 *
 * El enlace al afiliado se resuelve aquí, en el servidor, a partir de la cédula: nunca se
 * toma un `affiliate_id` enviado por el navegador.
 */
final class QualityControlSurveyWriter
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function create(QualityControlAffiliateType $type, array $data, ?int $userId): QualityControlSurvey
    {
        $respondedAt = CarbonImmutable::now();
        $attributes = self::attributes($type, $data);

        try {
            $survey = DB::transaction(fn (): QualityControlSurvey => QualityControlSurvey::query()->create([
                ...$attributes,
                'affiliate_type' => $type->value,
                'responded_at' => $respondedAt,
                'source' => QualityControlSurvey::SOURCE_FORM,
                'dedupe_hash' => QualityControlSurveyKeys::dedupeHash($type, $respondedAt, $attributes['patient_document']),
                'created_by' => $userId,
                'updated_by' => $userId,
            ]));
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'data.patient_document' => 'Ya se registró una encuesta de esta cédula en este mismo segundo. Revise el listado antes de volver a guardar.',
            ]);
        }

        SecurityAudit::log('AUDIT_QUALITY_CONTROL_SURVEY_CREATED', 'operations.quality-control.create', [
            'survey_id' => $survey->getKey(),
            'affiliate_type' => $type->value,
            'affiliate_id' => $survey->affiliate_id,
            'patient_document' => $survey->patient_document,
        ]);

        return $survey;
    }

    /**
     * La marca temporal y el origen no se editan: son la prueba de cuándo y cómo llegó la respuesta.
     *
     * @param  array<string, mixed>  $data
     */
    public static function update(QualityControlSurvey $survey, array $data, ?int $userId): QualityControlSurvey
    {
        $type = $survey->affiliate_type;
        $attributes = self::attributes($type, $data);
        $before = $survey->only(array_keys($attributes));

        try {
            DB::transaction(function () use ($survey, $attributes, $type, $userId): void {
                $survey->fill([
                    ...$attributes,
                    'dedupe_hash' => QualityControlSurveyKeys::dedupeHash($type, $survey->responded_at, $attributes['patient_document']),
                    'updated_by' => $userId,
                ])->save();
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'data.patient_document' => 'Ya existe otra encuesta de esta cédula con la misma marca temporal.',
            ]);
        }

        SecurityAudit::log('AUDIT_QUALITY_CONTROL_SURVEY_UPDATED', 'operations.quality-control.update', [
            'survey_id' => $survey->getKey(),
            'affiliate_type' => $type->value,
            'changes' => self::diff($before, $survey->only(array_keys($attributes))),
        ]);

        return $survey;
    }

    public static function delete(QualityControlSurvey $survey): void
    {
        $survey->delete();

        SecurityAudit::log('AUDIT_QUALITY_CONTROL_SURVEY_DELETED', 'operations.quality-control.delete', [
            'survey_id' => $survey->getKey(),
            'affiliate_type' => $survey->affiliate_type->value,
            'patient_document' => $survey->patient_document,
            'responded_at' => $survey->responded_at?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Normaliza y valida lo que llega del formulario (la validación de Filament ya corrió;
     * esto cubre a quien llame sin pasar por la pantalla).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributes(QualityControlAffiliateType $type, array $data): array
    {
        $document = mb_strtoupper(Str::squish((string) ($data['patient_document'] ?? '')));
        $name = mb_strtoupper(Str::squish((string) ($data['patient_name'] ?? '')));
        $errors = [];

        if ($document === '' || mb_strlen($document) > 60) {
            $errors['data.patient_document'] = 'Escriba la cédula del paciente (máximo 60 caracteres).';
        }

        if ($name === '') {
            $errors['data.patient_name'] = 'Escriba el nombre completo del paciente.';
        }

        $serviceDate = filled($data['service_date'] ?? null) ? CarbonImmutable::parse((string) $data['service_date']) : null;

        if ($serviceDate !== null && $serviceDate->isAfter(CarbonImmutable::today())) {
            $errors['data.service_date'] = 'La fecha de servicio no puede ser futura.';
        }

        $ratings = [];
        foreach (array_keys(QualityControlSurvey::RATING_FIELDS) as $field) {
            $rating = $data[$field] ?? null;
            $rating = $rating instanceof QualityControlRating ? $rating : QualityControlRating::tryFrom((string) $rating);

            if ($rating === null) {
                $errors['data.'.$field] = 'Elija una calificación.';
            }

            $ratings[$field] = $rating?->value;
        }

        $services = array_values(array_unique(array_filter(
            array_map(fn (mixed $value): ?string => QualityControlService::tryFrom((string) $value)?->value, (array) ($data['services'] ?? [])),
        )));

        if ($services === []) {
            $errors['data.services'] = 'Marque al menos un servicio recibido.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        $match = QualityControlAffiliateLookup::find($type, $document);
        $phone = Str::squish((string) ($data['contact_phone'] ?? ''));
        $observations = trim((string) ($data['observations'] ?? ''));

        return [
            'affiliate_id' => $match['id'] ?? null,
            'service_date' => $serviceDate?->format('Y-m-d'),
            'patient_document' => $document,
            'patient_document_key' => QualityControlSurveyKeys::documentKey($document),
            'patient_name' => mb_substr($name, 0, 255),
            'contact_phone' => $phone !== '' ? mb_substr($phone, 0, 60) : null,
            ...$ratings,
            'observations' => $observations !== '' ? $observations : null,
            'services' => $services,
        ];
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private static function diff(array $before, array $after): array
    {
        $changes = [];
        $plain = fn (mixed $value): mixed => match (true) {
            $value instanceof \BackedEnum => $value->value,
            $value instanceof \DateTimeInterface => $value->format('Y-m-d'),
            default => $value,
        };

        foreach ($after as $key => $value) {
            $from = $plain($before[$key] ?? null);
            $to = $plain($value);

            if ($from !== $to) {
                $changes[$key] = ['from' => $from, 'to' => $to];
            }
        }

        return $changes;
    }
}
