<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\AffiliationCorporate;
use App\Support\CsvExportStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliationCorporateCsvExportService
{
    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'PRE-APROBADA' => 'Pre-aprobada',
            'ACTIVA' => 'Activa',
            'PENDIENTE' => 'Pendiente',
            'EXCLUIDO' => 'Excluido',
        ];
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'ID',
            'Código afiliación',
            'Estatus',
            'Código agencia',
            'Nombre agente',
            'Cliente corporativo',
            'RIF',
            'Teléfono',
            'Correo',
            'Nombre contacto',
            'CI contacto',
            'Teléfono contacto',
            'Correo contacto',
            'Dirección',
            'Ciudad',
            'Estado',
            'País',
            'Región',
            'Frecuencia de pago',
            'Fee anual',
            'Monto total',
            'Fecha activación',
            'Fecha vigencia',
            'Línea de servicio',
            'Unidad de negocio',
            'Fecha de registro',
        ];
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_corporate_ids?: list<int|string>|null}  $filters
     */
    public function streamCsv(array $filters, string $panel = 'business'): StreamedResponse
    {
        $filename = 'afiliaciones_corporativas_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($filters, $panel): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            self::query($filters, $panel)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (AffiliationCorporate $record) use ($handle): void {
                    fputcsv($handle, self::row($record));
                });

            fclose($handle);
        }, 200, self::downloadHeaders($filename));
    }

    /**
     * @return array<string, string>
     */
    public static function downloadHeaders(string $filename): array
    {
        return [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Pragma' => 'public',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ];
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_corporate_ids?: list<int|string>|null}  $filters
     */
    public static function query(array $filters, string $panel = 'business'): Builder
    {
        $query = AffiliationCorporate::query()->with([
            'agent:id,name,code_agent',
            'city:id,definition',
            'state:id,definition',
            'country:id,name',
            'region:id,definition',
            'businessLine:id,definition',
            'businessUnit:id,definition',
        ]);

        if ($panel === 'business' && Auth::user()?->is_accountManagers) {
            $query->where('ownerAccountManagers', Auth::id());
        }

        $affiliationCorporateIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliation_corporate_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        if ($affiliationCorporateIds !== []) {
            $query->whereIn('id', $affiliationCorporateIds);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private static function row(AffiliationCorporate $record): array
    {
        return [
            (string) $record->id,
            (string) ($record->code ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->code_agency ?? ''),
            (string) ($record->agent?->name ?? ''),
            (string) ($record->name_corporate ?? ''),
            (string) ($record->rif ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->full_name_contact ?? ''),
            (string) ($record->nro_identificacion_contact ?? ''),
            (string) ($record->phone_contact ?? ''),
            (string) ($record->email_contact ?? ''),
            (string) ($record->address ?? ''),
            (string) ($record->city?->definition ?? ''),
            (string) ($record->state?->definition ?? ''),
            (string) ($record->country?->name ?? ''),
            (string) ($record->region?->definition ?? ''),
            (string) ($record->payment_frequency ?? ''),
            (string) ($record->fee_anual ?? ''),
            (string) ($record->total_amount ?? ''),
            (string) ($record->activated_at ?? ''),
            (string) ($record->effective_date ?? ''),
            (string) ($record->businessLine?->definition ?? ''),
            (string) ($record->businessUnit?->definition ?? ''),
            (string) ($record->created_at ?? ''),
        ];
    }
}
