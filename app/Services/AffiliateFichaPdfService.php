<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AffiliateFichaScope;
use App\Models\Affiliate;
use App\Models\AffiliateCorporate;
use App\Support\Affiliations\AffiliateFicha\AffiliateFichaData;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Throwable;

/**
 * PDF de la ficha del afiliado (individual o corporativo).
 *
 * Reunir los datos cuesta unas pocas consultas acotadas; lo caro es DomPDF.
 * Por eso el PDF se guarda en caché bajo la huella de los datos: mientras nada
 * cambie, la vista previa y la descarga sirven el mismo archivo al instante, y
 * cualquier cambio (un pago, una consulta) produce otra huella y otro PDF. No
 * hay que invalidar nada a mano.
 */
final class AffiliateFichaPdfService
{
    private const CACHE_PREFIX = 'affiliate_ficha_pdf:v1:';

    private const CACHE_TTL_SECONDS = 900;

    /**
     * @return array{binary: string, filename: string, data: array<string, mixed>}
     */
    public static function render(string $kind, Model $affiliate, AffiliateFichaScope $scope): array
    {
        $data = self::data($kind, $affiliate, $scope);
        $key = self::CACHE_PREFIX.hash('sha256', serialize($data));

        try {
            $encoded = Cache::get($key);
        } catch (Throwable $exception) {
            Log::warning('Ficha del afiliado: caché no disponible al leer.', ['error' => $exception->getMessage()]);
            $encoded = null;
        }

        $binary = is_string($encoded) ? base64_decode($encoded, true) : false;

        if (! is_string($binary) || $binary === '') {
            $binary = self::output($data);

            try {
                Cache::put($key, base64_encode($binary), self::CACHE_TTL_SECONDS);
            } catch (Throwable $exception) {
                Log::warning('Ficha del afiliado: caché no disponible al guardar.', ['error' => $exception->getMessage()]);
            }
        }

        return [
            'binary' => $binary,
            'filename' => self::filename($data),
            'data' => $data,
        ];
    }

    /**
     * El afiliado de la ficha, aunque su afiliación esté eliminada lógicamente.
     */
    public static function find(string $kind, int $affiliateId): ?Model
    {
        return match ($kind) {
            AffiliateFichaData::KIND_INDIVIDUAL => Affiliate::query()->withTrashed()->find($affiliateId),
            AffiliateFichaData::KIND_CORPORATE => AffiliateCorporate::query()->withTrashed()->find($affiliateId),
            default => null,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(string $kind, Model $affiliate, AffiliateFichaScope $scope): array
    {
        return match (true) {
            $kind === AffiliateFichaData::KIND_INDIVIDUAL && $affiliate instanceof Affiliate => AffiliateFichaData::forIndividual($affiliate, $scope),
            $kind === AffiliateFichaData::KIND_CORPORATE && $affiliate instanceof AffiliateCorporate => AffiliateFichaData::forCorporate($affiliate, $scope),
            default => throw new InvalidArgumentException('Tipo de afiliado desconocido para la ficha.'),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function output(array $data): string
    {
        $html = View::make('documents.affiliate-ficha', [
            'ficha' => $data,
            'logoDataUri' => AgentFichaPdfService::logoDataUri(),
            'generatedAt' => now()->timezone(config('app.timezone'))->format('d/m/Y h:i A'),
        ])->render();

        return (string) Pdf::loadHTML($html)
            ->setPaper('a4', 'portrait')
            ->setWarnings(false)
            ->setOptions([
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'defaultFont' => 'DejaVu Sans',
            ])
            ->output();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function filename(array $data): string
    {
        $document = preg_replace('/[^A-Za-z0-9]/', '', (string) ($data['document'] ?? '')) ?: 'afiliado';
        $scope = (string) ($data['scope'] ?? 'todo');

        return 'Ficha-afiliado-'.$document.'-'.$scope.'.pdf';
    }
}
