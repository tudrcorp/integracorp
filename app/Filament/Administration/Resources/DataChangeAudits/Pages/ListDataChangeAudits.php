<?php

declare(strict_types=1);

namespace App\Filament\Administration\Resources\DataChangeAudits\Pages;

use App\Filament\Administration\Resources\DataChangeAudits\DataChangeAuditResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListDataChangeAudits extends ListRecords
{
    protected static string $resource = DataChangeAuditResource::class;

    protected static ?string $title = 'Auditoría de cambios';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Todo lo que se crea, modifica, elimina o revierte en afiliaciones, cotizaciones, agencias, agentes, ventas, cobranza, comisiones, planes, tarifas y Nuevos Negocios, con el antes y el después de cada campo. Busque por código (p. ej. 000451), usuario o campo; abra un movimiento para ver su detalle, su contexto y la historia del registro.';
    }
}
