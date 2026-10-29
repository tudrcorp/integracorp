<?php

declare(strict_types=1);

namespace App\Enums;

use App\Filament\Operations\Resources\CorporateAllies\CorporateAllyResource;
use App\Filament\Operations\Resources\DoctorNurses\DoctorNurseResource;
use App\Filament\Operations\Resources\Suppliers\SupplierResource;
use App\Models\CorporateAlly;
use App\Models\DoctorNurse;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * Los tres tipos de proveedor de la red: jurídicos (clínicas y centros), naturales (médicos,
 * enfermeros) y aliados corporativos.
 */
enum ProviderNetworkType: string
{
    case Juridico = 'juridico';
    case Natural = 'natural';
    case Aliado = 'aliado';

    public static function fromModel(Model $model): self
    {
        return match (true) {
            $model instanceof Supplier => self::Juridico,
            $model instanceof DoctorNurse => self::Natural,
            $model instanceof CorporateAlly => self::Aliado,
            default => throw new \InvalidArgumentException('El registro no es un proveedor.'),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Juridico => 'Jurídico',
            self::Natural => 'Natural',
            self::Aliado => 'Aliado corporativo',
        };
    }

    public function plural(): string
    {
        return match ($this) {
            self::Juridico => 'Jurídicos',
            self::Natural => 'Naturales',
            self::Aliado => 'Aliados corporativos',
        };
    }

    /**
     * Token de color de la serie en el dashboard.
     */
    public function color(): string
    {
        return match ($this) {
            self::Juridico => 'var(--ops-series1)',
            self::Natural => 'var(--ops-series2)',
            self::Aliado => 'var(--ops-violet)',
        };
    }

    /**
     * @return class-string<Supplier|DoctorNurse|CorporateAlly>
     */
    public function modelClass(): string
    {
        return match ($this) {
            self::Juridico => Supplier::class,
            self::Natural => DoctorNurse::class,
            self::Aliado => CorporateAlly::class,
        };
    }

    public function viewUrl(int $id): ?string
    {
        try {
            return match ($this) {
                self::Juridico => SupplierResource::getUrl('view', ['record' => $id], panel: 'operations'),
                self::Natural => DoctorNurseResource::getUrl('view', ['record' => $id], panel: 'operations'),
                self::Aliado => CorporateAllyResource::getUrl('view', ['record' => $id], panel: 'operations'),
            };
        } catch (Throwable) {
            return null;
        }
    }
}
