<?php

namespace App\Filament\Business\Resources\Affiliations\Pages;

use App\Filament\Business\Resources\Affiliations\AffiliationResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Model;

class ViewAffiliation extends ViewRecord
{
    protected static string $resource = AffiliationResource::class;
    

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $affiliate = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $affiliate->full_name_ti ?? 'Sin Nombre';

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">' .
                // Título Principal Resaltado
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                'Afiliación Nro: ' . $affiliate->code .
                '</span>' .

                // Subtítulo (Nombre del Paciente)
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                'Titular: ' . $fullName .
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