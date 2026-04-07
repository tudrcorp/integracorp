<?php

namespace App\Filament\Operations\Resources\OperationCoordinationServices\Pages;

use App\Filament\Operations\Resources\OperationCoordinationServices\OperationCoordinationServiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewOperationCoordinationService extends ViewRecord
{
    protected static string $resource = OperationCoordinationServiceResource::class;

    protected static ?string $title = 'Ficha Técnica del Servicio de Coordinación';

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->extraAttributes([
                    'class' => self::GRAY_BUTTON_CLASS,
                ])
                ->url(OperationCoordinationServiceResource::getUrl()),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        $operationCoordinationService = $this->getRecord();
        $status = (string) ($operationCoordinationService->status ?? '');
        $badgeStyle = $this->badgeStyleForStatus($status);
        $referenceNumber = (string) ($operationCoordinationService->reference_number ?? '—');
        $patientId = (string) ($operationCoordinationService->ci_patient ?? '—');
        $patientName = (string) ($operationCoordinationService->patient ?? 'Paciente no definido');

        return new \Illuminate\Support\HtmlString(
            '<div style="display:flex;flex-direction:column;font-family:-apple-system,BlinkMacSystemFont,\'SF Pro Text\',\'Segoe UI\',Roboto,Helvetica,Arial,sans-serif;gap:12px;padding:12px 0;">'.
                // Título principal
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white mb-2">'.
                'Detalles del Servicio de Coordinación'.
                '</span>'.
                // Nombre paciente
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-white mb-2">'.
                'Paciente: '.$patientName.
                '</span>'.
                // Metadatos
                '<div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:2px;">'.
                '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;background:linear-gradient(180deg,#f4f8ff 0%,#e7efff 100%);color:#1f2937;font-size:.78rem;font-weight:700;border:1px solid #d7e3ff;box-shadow:0 1px 2px rgba(15,23,42,.06),inset 0 1px 0 rgba(255,255,255,.9);">'.
                'Referencia: '.$referenceNumber.
                '</span>'.
                '<span style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;border-radius:9999px;background:linear-gradient(180deg,#f8fafc 0%,#ecf1f7 100%);color:#1f2937;font-size:.78rem;font-weight:700;border:1px solid #dde5ef;box-shadow:0 1px 2px rgba(15,23,42,.06),inset 0 1px 0 rgba(255,255,255,.9);">'.
                'C.I. paciente: '.$patientId.
                '</span>'.
                '</div>'.
                // Estatus
                '<div style="display:flex;align-items:center;margin-top:2px;">'.
                '<span style="background:linear-gradient(180deg,'.$badgeStyle['bg'].' 0%,'.$badgeStyle['bg'].' 100%);color:#ffffff;padding:8px 16px;border-radius:9999px;font-size:.8rem;font-weight:800;letter-spacing:.02em;display:inline-flex;align-items:center;gap:6px;box-shadow:'.$badgeStyle['shadow'].',inset 0 1px 0 rgba(255,255,255,.25);border:1px solid rgba(255,255,255,.24);">'.
                '<span style="font-size:10px;opacity:.95;">●</span> '.$status.
                '</span>'.
                '</div>'.
                '</div>'
        );
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'EN GESTION' => [
                'bg' => '#ffc107',
                'shadow' => '0 4px 12px rgba(255, 193, 7, 0.35)',
            ],
            'CANCELADA' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            'FINALIZADO' => [
                'bg' => '#28cd41',
                'shadow' => '0 4px 12px rgba(40, 205, 65, 0.35)',
            ],
            'PENDIENTE' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'PENDIENTE POR RESULTADOS' => [
                'bg' => '#ffcc00',
                'shadow' => '0 4px 12px rgba(255, 204, 0, 0.35)',
            ],
            'NOVEDAD ADMON' => [
                'bg' => '#ff3b30',
                'shadow' => '0 4px 12px rgba(255, 59, 48, 0.35)',
            ],
            default => [
                'bg' => '#8e8e93',
                'shadow' => '0 4px 12px rgba(142, 142, 147, 0.35)',
            ],
        };
    }
}
