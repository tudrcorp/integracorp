<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\TelemedicinePanelEvents\Pages;

use App\Enums\TelemedicinePanelEventCategory as Category;
use App\Enums\TelemedicinePanelEventLevel as Level;
use App\Filament\Operations\Resources\TelemedicinePanelEvents\TelemedicinePanelEventResource;
use App\Support\Telemedicine\PanelLog\TelemedicinePanelEventQueries;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class ListTelemedicinePanelEvents extends ListRecords
{
    protected static string $resource = TelemedicinePanelEventResource::class;

    public function getSubheading(): string|Htmlable|null
    {
        return 'Lo que hacen los médicos en el panel de Telemedicina y lo que les sale mal, en vivo. Se actualiza cada 10 segundos y guarda 90 días. Abra un evento para ver la secuencia completa de ese médico.';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        return [
            'problems' => Tab::make('Problemas')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('level', [Level::Warning->value, Level::Error->value])),
            'no_response' => Tab::make('Pulsó y no pasó nada')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('category', Category::NoResponse->value)),
            'invisible' => Tab::make('Mensajes que no vio')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('event', 'validacion_invisible')),
            'browser' => Tab::make('Navegador')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('category', Category::Browser->value)),
            'abandoned' => Tab::make('Consultas sin guardar')
                ->modifyQueryUsing(fn (Builder $query): Builder => TelemedicinePanelEventQueries::abandonedConsultationForms($query)),
            'all' => Tab::make('Todo'),
        ];
    }

    public function getDefaultActiveTab(): string|int|null
    {
        return 'problems';
    }
}
