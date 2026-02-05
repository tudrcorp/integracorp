<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Pages;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewBusinessAppointments extends ViewRecord
{
    protected static string $resource = BusinessAppointmentsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(BusinessAppointmentsResource::getUrl()),
        ];
    }


    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $businessAppointment = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $businessAppointment->legal_name ?? 'Sin Nombre';

        // Lógica de colores basada en el estatus
        // Pendiente: Amarillo (#ffcc00)
        // Atendida: Verde (#28cd41)
        // Cancelada: Rojo (#ff3b30)
        // Reagendada: Amarillo (#ffcc00)

        $status = strtolower($businessAppointment->status ?? 'pendiente');

        $statusConfig = match ($status) {
            'atendida'   => ['color' => '#28cd41', 'label' => 'Atendida'],
            'cancelada'  => ['color' => '#ff3b30', 'label' => 'Cancelada'],
            'reagendada' => ['color' => '#ffcc00', 'label' => 'Reagendada'],
            default      => ['color' => '#ffcc00', 'label' => 'Pendiente'], // 'pendiente' u otros
        };

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">' .
                // Título Principal
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-2">' .
                'Información Principal' .
                '</span>' .

                // Nombre del Paciente/Cita
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-2">' .
                'Cita: ' . $fullName .
                '</span>' .

                // Estatus Dinámico Estilo iOS
                '<div style="display: flex; align-items: center; margin-top: 8px;">' .
                '<span style="' .
                'background-color: ' . $statusConfig['color'] . '; ' .
                'color: ' . ($statusConfig['color'] === '#ffcc00' ? '#000000' : '#ffffff') . '; ' . // Texto negro si es amarillo para legibilidad
                'padding: 6px 16px; ' .
                'border-radius: 50px; ' .
                'font-size: 0.8rem; ' .
                'font-weight: 700; ' .
                'display: inline-flex; ' .
                'align-items: center; ' .
                'gap: 6px; ' .
                'box-shadow: 0 4px 12px ' . $statusConfig['color'] . '59; ' . // 35% opacidad en el shadow
                'border: 1px solid rgba(255, 255, 255, 0.2);' .
                '">' .
                '<span style="font-size: 10px;">●</span>' . $statusConfig['label'] .
                '</span>' .
                '</div>' .
                '</div>'
        );
    }
}
