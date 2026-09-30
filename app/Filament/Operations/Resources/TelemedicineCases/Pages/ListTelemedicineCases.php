<?php

namespace App\Filament\Operations\Resources\TelemedicineCases\Pages;

use App\Filament\Operations\Resources\TelemedicineCases\TelemedicineCaseResource;
use App\Support\Operations\OperationsListHeaderCounts;
use App\Support\Telemedicine\TelemedicineUrgentPriorities;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListTelemedicineCases extends ListRecords
{
    protected static string $resource = TelemedicineCaseResource::class;

    protected static ?string $title = 'Gestión de Casos';

    public function getHeading(): string|Htmlable
    {
        [$urgentCondition, $urgentBindings] = TelemedicineUrgentPriorities::sqlCondition();

        $summary = OperationsListHeaderCounts::aggregate($this->getTable()->getQuery(), [
            'assigned' => ['telemedicine_cases.status = ?', ['ASIGNADO']],
            'follow_up' => ['telemedicine_cases.status = ?', ['EN SEGUIMIENTO']],
            'urgent' => [$urgentCondition, $urgentBindings],
            'today' => ['telemedicine_cases.created_at >= ?', [OperationsListHeaderCounts::startOfToday()]],
        ]);

        return new HtmlString(view('filament.operations.partials.list-header', [
            'icon' => 'heroicon-o-clipboard-document-list',
            'eyebrow' => 'Telemedicina · Casos',
            'title' => 'Gestión de casos',
            'total' => $summary['total'],
            'totalHint' => 'Casos activos (sin alta médica) que puede consultar',
            'description' => 'Casos de telemedicina en curso. Abra uno para ver el detalle, registrar seguimientos o reasignarlo; al dar el alta médica sale de esta lista.',
            'stats' => [
                ['label' => 'Por atender', 'value' => $summary['assigned'], 'icon' => 'heroicon-m-bell-alert', 'tone' => 'warning', 'hint' => 'Casos recién asignados, pendientes de iniciar seguimiento'],
                ['label' => 'En seguimiento', 'value' => $summary['follow_up'], 'icon' => 'heroicon-m-arrow-path', 'tone' => 'info', 'hint' => 'Casos con consultas en curso'],
                ['label' => 'Urgentes', 'value' => $summary['urgent'], 'icon' => 'heroicon-m-bolt', 'tone' => 'danger', 'hint' => 'Casos con prioridad Urgencia, Emergencia o Crítico'],
                ['label' => 'Nuevos hoy', 'value' => $summary['today'], 'icon' => 'heroicon-m-sparkles', 'tone' => 'primary', 'hint' => 'Casos abiertos hoy'],
            ],
        ])->render());
    }

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
