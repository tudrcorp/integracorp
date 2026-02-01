<?php

namespace App\Filament\Operations\Resources\AffiliateCorporates\Pages;

use App\Filament\Operations\Resources\AffiliateCorporates\AffiliateCorporateResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAffiliateCorporate extends ViewRecord
{
    protected static string $resource = AffiliateCorporateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(AffiliateCorporateResource::getUrl())
        ];
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $affiliate = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $affiliate->first_name ?? 'Sin Nombre';

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">' .
                // Título Principal Resaltado
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                'Ficha del Afiliado Corporativo' .
                '</span>' .

                // Subtítulo (Nombre del Paciente)
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                $fullName .
                '</span>' .

                // Estatus Estilo Badge iOS Resaltado
                '<div style="display: flex; align-items: center; margin-top: 8px;">' .
                '<span style="' .
                'background-color: #28cd41; ' . // Verde iOS vibrante
                'color: #ffffff; ' .
                'padding: 6px 16px; ' .
                'border-radius: 50px; ' .
                'font-size: 0.8rem; ' .
                'font-weight: 700; ' .
                'display: inline-flex; ' .
                'align-items: center; ' .
                'gap: 6px; ' .
                'box-shadow: 0 4px 12px rgba(40, 205, 65, 0.35); ' .
                'border: 1px solid rgba(255, 255, 255, 0.2);' .
                '">' .
                '<span style="font-size: 10px;">●</span> ACTIVO' .
                '</span>' .
                '</div>' .
                '</div>'
        );
    }
}
