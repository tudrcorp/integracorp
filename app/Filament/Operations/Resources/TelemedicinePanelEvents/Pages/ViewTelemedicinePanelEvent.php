<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePanelEvents\Pages;

use App\Filament\Operations\Resources\TelemedicinePanelEvents\TelemedicinePanelEventResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTelemedicinePanelEvent extends ViewRecord
{
    protected static string $resource = TelemedicinePanelEventResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Evento del panel de Telemedicina';
    }
}
