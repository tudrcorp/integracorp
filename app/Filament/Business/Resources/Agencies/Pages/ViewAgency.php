<?php

namespace App\Filament\Business\Resources\Agencies\Pages;

use App\Filament\Business\Resources\Agencies\AgencyResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewAgency extends ViewRecord
{
    protected static string $resource = AgencyResource::class;

    /**
     * Idéntico a Crear Ticket / Crear Nuevo Paciente: .ticket-btn-ios en theme.css (verde, sombras iOS, hover).
     */
    private const TICKET_BUTTON_CLASS = 'ticket-btn-ios shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const PRIMARY_BUTTON_CLASS = 'aviso-btn-ios-primary shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const WARNING_BUTTON_CLASS = 'aviso-btn-ios-warning shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    public function getTitle(): string|Htmlable
    {
        $agency = $this->getRecord();

        $code = (string) ($agency->code ?? 'Sin código');
        $name = (string) ($agency->name_corporative ?? 'Sin razón social');
        $status = strtoupper((string) ($agency->status ?? 'SIN ESTADO'));
        $email = (string) ($agency->email ?? 'Sin correo');
        $phone = (string) ($agency->phone ?? 'Sin teléfono');
        $badgeStyle = $this->badgeStyleForStatus($status);

        return new HtmlString(
            '<div style="display:flex;flex-direction:column;gap:6px;padding:10px 0;">'
            .'<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-white">'
            .'Agencia: '.e($code)
            .'</span>'
            .'<span class="text-2xl font-bold tracking-tight text-gray-900 dark:text-white">'
            .e($name)
            .'</span>'
            .'<div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;">'
            .'<span style="background-color: '.$badgeStyle['bg'].';color:#fff;padding:5px 14px;border-radius:999px;font-size:.78rem;font-weight:700;box-shadow:'.$badgeStyle['shadow'].';">'
            .e($status)
            .'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">📧 '.e($email).'</span>'
            .'<span class="text-sm text-gray-600 dark:text-gray-300">📞 '.e($phone).'</span>'
            .'</div>'
            .'</div>'
        );
    }

    /**
     * @return array{bg: string, shadow: string}
     */
    private function badgeStyleForStatus(string $status): array
    {
        return match ($status) {
            'ACTIVO' => ['bg' => '#16a34a', 'shadow' => '0 8px 20px rgba(22,163,74,.35)'],
            'POR REVISION' => ['bg' => '#f59e0b', 'shadow' => '0 8px 20px rgba(245,158,11,.35)'],
            'INACTIVO' => ['bg' => '#dc2626', 'shadow' => '0 8px 20px rgba(220,38,38,.35)'],
            default => ['bg' => '#6b7280', 'shadow' => '0 8px 20px rgba(107,114,128,.35)'],
        };
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color(self::PRIMARY_BUTTON_CLASS)
                ->extraAttributes([
                    'class' => self::TICKET_BUTTON_CLASS,
                ]),
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('warning')
                ->url(AgencyResource::getUrl())
                ->extraAttributes([
                    'class' => self::WARNING_BUTTON_CLASS,
                ]),
        ];
    }
}
