<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\SystemAuditTraces\Pages;

use App\Filament\Business\Resources\SystemAuditTraces\SystemAuditTraceResource;
use Filament\Resources\Pages\ListRecords;

class ListSystemAuditTraces extends ListRecords
{
    protected static string $resource = SystemAuditTraceResource::class;
}
