<?php

namespace App\Filament\Business\Resources\BusinessAppointments\Pages;

use App\Filament\Business\Resources\BusinessAppointments\BusinessAppointmentsResource;
use App\Models\BusinessAppointmentObservation;
use App\Models\BusinessAppointments;
use App\Support\Filament\RecordPageHeader;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Fieldset;

class ViewBusinessAppointments extends ViewRecord
{
    protected static string $resource = BusinessAppointmentsResource::class;

    protected static ?string $pollingInterval = '5s';

    private const IOS_BUTTON_BASE = ' shrink-0 inline-flex items-center justify-center gap-2 rounded-full px-4 py-2 text-sm font-semibold tracking-tight transition-all duration-200 active:scale-[0.98]';

    private const IOS_GRAY_BUTTON_CLASS = 'ticket-btn-ios-gray'.self::IOS_BUTTON_BASE;

    private const IOS_SUCCESS_BUTTON_CLASS = 'aviso-btn-ios-success'.self::IOS_BUTTON_BASE;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon('heroicon-o-arrow-left')
                ->color('gray')
                ->url(BusinessAppointmentsResource::getUrl())
                ->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ]),
            Action::make('notes')
                ->label('Agregar Notas/Observaciones')
                ->icon('heroicon-o-document-text')
                ->color('success')
                ->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ])
                ->modal()
                ->modalHeading('Agregar Notas/Observaciones')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_SUCCESS_BUTTON_CLASS,
                ]))
                ->modalCancelAction(fn (Action $action): Action => $action->extraAttributes([
                    'class' => self::IOS_GRAY_BUTTON_CLASS,
                ]))
                ->form([
                    Fieldset::make('Formulario de Notas')
                        ->schema([
                            Textarea::make('observations')
                                ->label('Notas')
                                ->autosize()
                                ->required(),
                        ])->columns(1),
                ])
                ->action(function ($data, $record) {

                    try {

                        BusinessAppointmentObservation::create([
                            'business_appointment_id' => $record->id,
                            'observation' => $data['observations'],
                            'created_by' => auth()->user()->name,
                        ]);

                        Notification::make()
                            ->title('Notas agregadas correctamente')
                            ->success()
                            ->send();

                        return $this->redirect(BusinessAppointmentsResource::getUrl('view', ['record' => $record->id]));

                    } catch (\Exception $e) {
                        dd($e);
                        Notification::make()
                            ->title('Error al agregar notas')
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    public function getTitle(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Cita '.(trim((string) $this->getRecord()->legal_name) ?: '');
    }

    /**
     * Encabezado del sistema ({@see RecordPageHeader}): estado de la cita con sus
     * colores de siempre (pendiente y reagendada en ámbar, atendida en verde,
     * cancelada en rojo) y los datos de contacto.
     */
    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        /** @var BusinessAppointments $appointment */
        $appointment = $this->getRecord();

        [$label, $tone] = match (mb_strtolower(trim((string) ($appointment->status ?: 'pendiente')))) {
            'atendida' => ['ATENDIDA', RecordPageHeader::TONE_SUCCESS],
            'cancelada' => ['CANCELADA', RecordPageHeader::TONE_DANGER],
            'reagendada' => ['REAGENDADA', RecordPageHeader::TONE_WARNING],
            default => ['PENDIENTE', RecordPageHeader::TONE_WARNING],
        };

        $observations = $appointment->businessAppointmentObservations()->count();

        return RecordPageHeader::render(
            eyebrow: 'Agenda de Negocios · Cita',
            title: trim((string) $appointment->legal_name) ?: 'Sin nombre',
            status: RecordPageHeader::tag($label, $tone),
            chips: [
                $observations > 0 ? RecordPageHeader::tag($observations === 1 ? '1 observación' : $observations.' observaciones', RecordPageHeader::TONE_NEUTRAL) : null,
            ],
            facts: [
                'Teléfono' => $appointment->phone,
                'Correo' => $appointment->email,
                'Registrada el' => $appointment->created_at?->format('d/m/Y'),
                'Agendada por' => $appointment->created_by,
            ],
        );
    }
}
