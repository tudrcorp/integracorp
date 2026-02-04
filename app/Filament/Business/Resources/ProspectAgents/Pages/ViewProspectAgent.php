<?php

namespace App\Filament\Business\Resources\ProspectAgents\Pages;

use App\Filament\Business\Resources\ProspectAgents\ProspectAgentResource;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentObservation;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;

class ViewProspectAgent extends ViewRecord
{
    protected static string $resource = ProspectAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(ProspectAgentResource::getUrl()),
            EditAction::make()
                ->label('Editar')
                ->icon('heroicon-o-pencil')
                ->color('primary'),
            Action::make('notes')
                ->label('Agregar Notas')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->modal()
                ->modalHeading('Agregar Notas')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->form([
                    Fieldset::make('Formulario de Notas')
                    ->schema([
                        TextInput::make('created_by')
                            ->label('Creado por')
                            ->disabled()
                            ->dehydrated()
                            ->default(auth()->user()->name),
                        Textarea::make('observations')
                            ->label('Notas')
                            ->autosize()
                            ->required(),
                    ])->columns(1),
                ])
                ->action(function ($data, $record) {

                    try {

                        ProspectAgentObservation::create([
                            'prospect_agent_id' => $record->id,
                            'observation' => $data['observations'],
                            'created_by' => $data['created_by'],
                        ]);

                        Notification::make()
                            ->title('Notas agregadas correctamente')
                            ->success()
                            ->send();

                        $this->redirectMethod($record->id);    

                    } catch (\Exception $e) {

                        Notification::make()
                            ->title('Error al agregar notas')
                            ->danger()
                            ->send();
                    }

                }),
        ];
    }

    /**
     * Corrección del error de redirección.
     * getUrl() requiere un array ['record' => $id] como segundo parámetro.
     */
    public function redirectMethod($recordId): void
    {
        $this->redirect(ProspectAgentResource::getUrl('view', ['record' => $recordId]));
    }

    public function getTitle(): string | \Illuminate\Contracts\Support\Htmlable
    {
        $prospectAgent = $this->getRecord();

        // Definimos el nombre del afiliado de forma segura
        $fullName = $prospectAgent->name ?? 'Sin Nombre';

        return new \Illuminate\Support\HtmlString(
            '<div style="display: flex; flex-direction: column; font-family: -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; gap: 2px; padding: 12px 0;">' .
                // Título Principal Resaltado
                '<span class="text-sm font-bold uppercase tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                'Informacion Principal'. 
                '</span>' .

                // Subtítulo (Nombre del Paciente)
                '<span class="text-3xl font-bold tracking-tight text-gray-900 dark:text-gray-100 mb-2 dark:text-white">' .
                'Prospecto: ' . $fullName .
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
                '<span style="font-size: 10px;">●</span>' . $prospectAgent->status .
                '</span>' .
                '</div>' .
                '</div>'
        );
    }
}
