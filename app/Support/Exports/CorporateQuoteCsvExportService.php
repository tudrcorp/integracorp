<?php

declare(strict_types=1);

namespace App\Support\Exports;

use App\Models\CorporateQuote;
use App\Support\CsvExportStream;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class CorporateQuoteCsvExportService
{
    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            'ACTIVA' => 'Activa',
            'APROBADA' => 'Aprobada',
            'PROCESADA' => 'Procesada',
            'ANULADA' => 'Anulada',
            'DECLINADA' => 'Declinada',
        ];
    }

    /**
     * @return list<string>
     */
    public static function headers(): array
    {
        return [
            'ID',
            'Código',
            'Estatus',
            'Código agencia',
            'Nombre agente',
            'Account Manager',
            'Cliente corporativo',
            'RIF',
            'Teléfono',
            'Correo',
            'Tipo de plan',
            'Región',
            'Días contrato',
            'Fecha de registro',
        ];
    }

    /**
     * @param  array{status?: string|null, corporate_quote_ids?: list<int|string>|null}  $filters
     */
    public function streamCsv(array $filters, string $panel = 'business'): StreamedResponse
    {
        $filename = 'cotizaciones_corporativas_'.now()->format('Y-m-d_His').'.csv';

        return new StreamedResponse(function () use ($filters, $panel): void {
            $handle = CsvExportStream::openOutput();

            if ($handle === false) {
                return;
            }

            fputcsv($handle, self::headers());

            self::query($filters, $panel)
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (CorporateQuote $record) use ($handle): void {
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
     * @param  array{status?: string|null, corporate_quote_ids?: list<int|string>|null}  $filters
     */
    public static function query(array $filters, string $panel = 'business'): Builder
    {
        $query = CorporateQuote::query()->with([
            'accountManager:id,name',
            'agent:id,name',
        ]);

        if ($panel === 'business' && Auth::user()?->is_accountManagers) {
            $query->where('ownerAccountManagers', Auth::id());
        }

        $corporateQuoteIds = array_values(array_filter(
            array_map('intval', (array) ($filters['corporate_quote_ids'] ?? [])),
            fn (int $id): bool => $id > 0,
        ));

        if ($corporateQuoteIds !== []) {
            $query->whereIn('id', $corporateQuoteIds);
        }

        if (filled($filters['status'] ?? null)) {
            $query->where('status', (string) $filters['status']);
        }

        return $query;
    }

    /**
     * @return list<string>
     */
    private static function row(CorporateQuote $record): array
    {
        return [
            (string) $record->id,
            (string) ($record->code ?? ''),
            (string) ($record->status ?? ''),
            (string) ($record->code_agency ?? ''),
            (string) ($record->agent?->name ?? ''),
            (string) ($record->accountManager?->name ?? '—'),
            (string) ($record->full_name ?? ''),
            (string) ($record->rif ?? ''),
            (string) ($record->phone ?? ''),
            (string) ($record->email ?? ''),
            self::planLabel($record->plan),
            (string) ($record->region ?? ''),
            (string) ($record->count_days ?? ''),
            (string) ($record->created_at ?? ''),
        ];
    }

    private static function planLabel(mixed $plan): string
    {
        return match (true) {
            $plan === '1' || $plan === 1 => 'Plan Inicial',
            $plan === '2' || $plan === 2 => 'Plan Ideal',
            $plan === '3' || $plan === 3 => 'Plan Especial',
            $plan === 'CM' => 'MultiPlan',
            $plan === null, $plan === '' => '—',
            default => (string) $plan,
        };
    }
}
