<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\AffiliateCorporate;
use App\Support\CsvExportStream;
use App\Support\SecurityAudit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AffiliateCorporateCsvExportService
{
    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'PRE-AFILIADO' => 'Pre-afiliado',
            'ACTIVO' => 'Activo',
            'INACTIVO' => 'Inactivo',
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
            'Cliente corporativo',
            'Nombres',
            'Apellidos',
            'Identificación',
            'Plan',
            'Estatus',
            'Teléfono',
            'Correo',
            'Fecha de nacimiento',
            'Edad',
            'Sexo',
            'Contacto de emergencia',
            'Teléfono emergencia',
            'Línea de servicio',
            'Unidad de negocio',
            'Cobertura (USD)',
            'Fecha de registro',
        ];
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_corporate_ids?: list<int|string>|null}  $filters
     */
    public function exportAndAudit(array $filters, string $panel, string $auditEvent, string $auditRoute): StreamedResponse
    {
        SecurityAudit::log($auditEvent, $auditRoute, [
            'plan_id' => $filters['plan_id'] ?? null,
            'status' => $filters['status'] ?? null,
            'affiliation_corporate_ids_count' => count((array) ($filters['affiliation_corporate_ids'] ?? [])),
            'exported_by_user_id' => Auth::id(),
            'panel' => $panel,
        ]);

        return $this->streamCsv($filters, $panel);
    }

    /**
     * @param  array{plan_id?: int|string|null, status?: string|null}  $filters
     */
    public function streamCsv(array $filters, string $panel = 'business'): StreamedResponse
    {
        $filename = 'afiliados_corporativos_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($filters, $panel): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            self::query($filters, $panel)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (AffiliateCorporate $record) use ($handle): void {
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
     * @param  array{plan_id?: int|string|null, status?: string|null, affiliation_corporate_ids?: list<int|string>|null, affiliate_corporate_ids?: list<int|string>|null}  $filters
     */
    public static function query(array $filters, string $panel = 'business'): Builder
    {
        $query = AffiliateCorporate::query()->with([
            'plan:id,description',
            'coverage:id,price',
            'affiliationCorporate:id,code,name_corporate,ownerAccountManagers,business_line_id,business_unit_id',
            'affiliationCorporate.businessLine:id,definition',
            'affiliationCorporate.businessUnit:id,definition',
        ]);

        if ($panel === 'business' && Auth::user()?->is_accountManagers) {
            $query->whereHas(
                'affiliationCorporate',
                fn (Builder $affiliationQuery): Builder => $affiliationQuery->where('ownerAccountManagers', Auth::id()),
            );
        }

        $affiliateCorporateIds = array_values(array_filter(
            array_map('intval', (array) ($filters['affiliate_corporate_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        if ($affiliateCorporateIds !== []) {
            $query->whereIn('id', $affiliateCorporateIds);
        } else {
            $affiliationCorporateIds = array_values(array_filter(
                array_map('intval', (array) ($filters['affiliation_corporate_ids'] ?? [])),
                fn (int $id): bool => $id > 0,
            ));

            if ($affiliationCorporateIds !== []) {
                $query->whereIn('affiliation_corporate_id', $affiliationCorporateIds);
            }
        }

        if (filled($filters['plan_id'] ?? null)) {
            $query->where('plan_id', (int) $filters['plan_id']);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private static function row(AffiliateCorporate $record): array
    {
        return [
            (string) $record->id,
            (string) ($record->affiliationCorporate?->code ?? ''),
            (string) ($record->affiliationCorporate?->name_corporate ?? ''),
            (string) ($record->first_name ?? ''),
            (string) ($record->last_name ?? ''),
            (string) ($record->nro_identificacion ?? ''),
            (string) ($record->plan?->description ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->email ?? ''),
            (string) ($record->birth_date ?? ''),
            (string) ($record->age ?? ''),
            (string) ($record->sex ?? ''),
            (string) ($record->full_name_emergency ?? ''),
            (string) ($record->phone_emergency ?? ''),
            (string) ($record->affiliationCorporate?->businessLine?->definition ?? ''),
            (string) ($record->affiliationCorporate?->businessUnit?->definition ?? ''),
            (string) ($record->coverage?->price ?? ''),
            (string) ($record->created_at ?? ''),
        ];
    }
}
