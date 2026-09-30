<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use App\Filament\Operations\Resources\Affiliates\AffiliateResource;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Models\User;
use App\Support\RunReportMessageFormatter;
use Illuminate\Support\Str;
use Throwable;

/**
 * Aviso al equipo de Afiliaciones cuando Operaciones actualiza un afiliado,
 * individual o corporativo. El payload se arma en el request (con los nombres de catálogo ya
 * resueltos) para que el job solo envíe.
 */
final class AffiliateUpdateNotificationMessage
{
    /**
     * @return array{
     *     kind: string,
     *     kind_label: string,
     *     affiliate_id: int,
     *     affiliate_name: string,
     *     company: string|null,
     *     affiliate_document: string,
     *     relationship: string,
     *     affiliation_code: string,
     *     plan: string,
     *     status: string,
     *     source: string,
     *     source_label: string,
     *     changes: list<array{field: string, label: string, before: string, after: string}>,
     *     telemedicine_synced: bool,
     *     age_range_warning: string|null,
     *     titular_note: string|null,
     *     updated_by: string,
     *     updated_by_email: string,
     *     updated_at: string,
     *     url: string|null
     * }
     */
    public static function payload(AffiliatePersonalDataUpdateResult $result, ?User $actor, string $source): array
    {
        $affiliate = $result->affiliate;
        $isCorporate = $affiliate instanceof AffiliateCorporate;

        $affiliate->loadMissing($isCorporate
            ? ['affiliationCorporate:id,code,name_corporate', 'plan:id,description']
            : ['affiliation:id,code', 'plan:id,description']);

        $changes = [];

        foreach ($result->changes as $field => $change) {
            $changes[] = [
                'field' => $field,
                'label' => $change['label'],
                'before' => AffiliatePersonalDataUpdater::displayValue($field, $change['before']),
                'after' => AffiliatePersonalDataUpdater::displayValue($field, $change['after']),
            ];
        }

        /** La afiliación individual guarda copia de los datos del titular; la corporativa no. */
        $isTitular = ! $isCorporate && mb_strtoupper(trim((string) $affiliate->relationship)) === 'TITULAR';
        $touchesTitularData = array_intersect(array_keys($result->changes), ['full_name', 'nro_identificacion', 'phone', 'email', 'address']) !== [];

        return [
            'kind' => AffiliatePersonalDataUpdater::kindOf($affiliate),
            'kind_label' => $isCorporate ? 'corporativo' : 'individual',
            'affiliate_id' => (int) $affiliate->getKey(),
            'affiliate_name' => self::value(self::displayName($affiliate)),
            'company' => $isCorporate ? self::value($affiliate->affiliationCorporate?->name_corporate) : null,
            'affiliate_document' => self::value($affiliate->nro_identificacion),
            'relationship' => self::value($affiliate->relationship),
            'affiliation_code' => self::value($isCorporate ? $affiliate->affiliationCorporate?->code : $affiliate->affiliation?->code),
            'plan' => self::value($affiliate->plan?->description),
            'status' => self::value($affiliate->status),
            'source' => $source,
            'source_label' => self::sourceLabel($source),
            'changes' => $changes,
            'telemedicine_synced' => $result->telemedicineSynced,
            'age_range_warning' => $result->ageRangeWarning,
            'titular_note' => $isTitular && $touchesTitularData
                ? 'Es el TITULAR: los datos del titular guardados en la afiliación no se modificaron. Actualícelos si corresponde.'
                : null,
            'updated_by' => self::value($actor?->name),
            'updated_by_email' => self::value($actor?->email),
            'updated_at' => now()->timezone((string) config('app.timezone'))->format('d/m/Y H:i'),
            'url' => self::affiliateUrl($affiliate),
        ];
    }

    public static function sourceLabel(string $source): string
    {
        return match ($source) {
            AffiliatePersonalDataUpdater::SOURCE_MAPS_ADDRESS => 'Dirección fijada con el mapa',
            default => 'Edición de datos personales',
        };
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function whatsappBody(array $payload): string
    {
        $lines = [
            '*AFILIADO ACTUALIZADO · OPERACIONES*',
            '',
            'Operaciones actualizó los datos personales de un afiliado '.($payload['kind_label'] ?? 'individual').'.',
            '',
            '*Afiliado*',
            '• Nombre: '.self::value($payload['affiliate_name'] ?? null),
            '• Cédula: '.self::value($payload['affiliate_document'] ?? null),
            '• Parentesco: '.self::value($payload['relationship'] ?? null),
            '• Afiliación: '.self::value($payload['affiliation_code'] ?? null),
            ...(filled($payload['company'] ?? null) ? ['• Empresa: '.$payload['company']] : []),
            '• Plan: '.self::value($payload['plan'] ?? null),
            '',
            '*Cambios ('.count($payload['changes'] ?? []).')*',
        ];

        foreach ($payload['changes'] ?? [] as $change) {
            $lines[] = '• '.$change['label'].': '.$change['before'].' → *'.$change['after'].'*';
        }

        $lines[] = '';
        $lines[] = '*Registro*';
        $lines[] = '• Origen: '.self::value($payload['source_label'] ?? null);
        $lines[] = '• Analista: '.self::value($payload['updated_by'] ?? null);
        $lines[] = '• Fecha: '.self::value($payload['updated_at'] ?? null);
        $lines[] = '• Paciente de telemedicina: '.(($payload['telemedicine_synced'] ?? false) ? 'actualizado' : 'sin cambios (no vinculado o sin datos a replicar)');

        if (filled($payload['age_range_warning'] ?? null)) {
            $lines[] = '';
            $lines[] = '⚠️ *TARIFA:* '.$payload['age_range_warning'];
        }

        if (filled($payload['titular_note'] ?? null)) {
            $lines[] = '';
            $lines[] = '⚠️ *TITULAR:* '.$payload['titular_note'];
        }

        if (filled($payload['url'] ?? null)) {
            $lines[] = '';
            $lines[] = 'Ver afiliado: '.$payload['url'];
        }

        return RunReportMessageFormatter::truncateForWhatsAppCaption(implode("\n", $lines));
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function emailSubject(array $payload): string
    {
        return 'Afiliado '.($payload['kind_label'] ?? 'individual').' actualizado por Operaciones · '
            .self::value($payload['affiliate_name'] ?? null)
            .' · '.self::value($payload['affiliation_code'] ?? null)
            .' · INTEGRACORP';
    }

    public static function displayName(Affiliate|AffiliateCorporate $affiliate): string
    {
        if ($affiliate instanceof AffiliateCorporate) {
            return trim(Str::squish((string) $affiliate->first_name).' '.Str::squish((string) $affiliate->last_name));
        }

        return Str::squish((string) $affiliate->full_name);
    }

    private static function affiliateUrl(Affiliate|AffiliateCorporate $affiliate): ?string
    {
        try {
            return $affiliate instanceof AffiliateCorporate
                ? AffiliateCorporateResource::getUrl('view', ['record' => $affiliate], panel: 'operations')
                : AffiliateResource::getUrl('view', ['record' => $affiliate], panel: 'operations');
        } catch (Throwable) {
            return null;
        }
    }

    private static function value(mixed $value): string
    {
        return filled($value) ? (string) $value : '—';
    }
}
