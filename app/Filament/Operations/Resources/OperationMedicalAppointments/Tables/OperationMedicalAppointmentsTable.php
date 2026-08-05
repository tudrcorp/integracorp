<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationMedicalAppointments\Tables;

use App\Jobs\SendNotificacionWhatsApp;
use App\Mail\OperationServiceOrderPdfMail;
use App\Models\OperationMedicalAppointment;
use App\Models\OperationServiceOrder;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Filament\FilamentIosButton;
use App\Support\Filament\Operations\OperationsSupplierScope;
use App\Support\Operations\MedicalAppointmentManager;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\View;
use InvalidArgumentException;
use Throwable;

final class OperationMedicalAppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->heading('Citas médicas')
            ->description('Citas acordadas con el paciente al generar órdenes de servicio presenciales.')
            ->defaultSort('appointment_at', 'desc')
            ->modifyQueryUsing(function (Builder $query): Builder {
                return $query
                    ->with([
                        'telemedicinePatient:id,full_name,nro_identificacion',
                        'telemedicineCase:id,code',
                        'supplier:id,name,razon_social,correo_principal,personal_phone,local_phone',
                        'operationServiceOrder:id,order_number,service_type,supplier_external',
                        'operationCoordinationService:id,patient,ci_patient,reference_number',
                    ])
                    ->whereHas(
                        'operationCoordinationService',
                        fn (Builder $coordinationQuery): Builder => OperationsSupplierScope::applyCoordinationListScope($coordinationQuery)
                    );
            })
            ->columns([
                TextColumn::make('appointment_at')
                    ->label('Fecha / hora cita')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->weight(FontWeight::SemiBold),
                TextColumn::make('patient')
                    ->label('Paciente')
                    ->state(fn (OperationMedicalAppointment $record): string => (string) (
                        $record->telemedicinePatient?->full_name
                        ?? $record->operationCoordinationService?->patient
                        ?? '—'
                    ))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner
                                ->whereHas('telemedicinePatient', fn (Builder $q): Builder => $q->where('full_name', 'like', "%{$search}%"))
                                ->orWhereHas('operationCoordinationService', fn (Builder $q): Builder => $q->where('patient', 'like', "%{$search}%"));
                        });
                    })
                    ->badge()
                    ->color('gray'),
                TextColumn::make('patient_ci')
                    ->label('Cédula')
                    ->state(fn (OperationMedicalAppointment $record): string => (string) (
                        filled($record->telemedicinePatient?->nro_identificacion)
                            ? $record->telemedicinePatient->nro_identificacion
                            : (filled($record->operationCoordinationService?->ci_patient)
                                ? $record->operationCoordinationService->ci_patient
                                : '—')
                    ))
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $inner) use ($search): void {
                            $inner
                                ->whereHas('telemedicinePatient', fn (Builder $q): Builder => $q->where('nro_identificacion', 'like', "%{$search}%"))
                                ->orWhereHas('operationCoordinationService', fn (Builder $q): Builder => $q->where('ci_patient', 'like', "%{$search}%"));
                        });
                    })
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('telemedicineCase.code')
                    ->label('Caso')
                    ->state(fn (OperationMedicalAppointment $record): string => filled($record->telemedicineCase?->code)
                        ? mb_strtoupper((string) $record->telemedicineCase->code)
                        : '—')
                    ->badge()
                    ->color('info')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('telemedicineCase', fn (Builder $q): Builder => $q->where('code', 'like', "%{$search}%"));
                    }),
                TextColumn::make('supplier_label')
                    ->label('Proveedor')
                    ->state(fn (OperationMedicalAppointment $record): string => MedicalAppointmentManager::supplierLabel($record))
                    ->badge()
                    ->color('success'),
                TextColumn::make('operationServiceOrder.order_number')
                    ->label('Orden')
                    ->badge()
                    ->color('primary')
                    ->searchable(),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match (mb_strtoupper(trim((string) $state))) {
                        'RESCHEDULED' => 'REPROGRAMADA',
                        default => 'AGENDADA',
                    })
                    ->color(fn (?string $state): string => match (mb_strtoupper(trim((string) $state))) {
                        'RESCHEDULED' => 'warning',
                        default => 'success',
                    }),
            ])
            ->recordActions([
                Action::make('reschedule')
                    ->label('Cambiar fecha')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->color('warning')
                    ->modalHeading('Reprogramar cita médica')
                    ->modalDescription('Indique la nueva fecha/hora y el motivo. Se notificará al proveedor por correo y WhatsApp.')
                    ->modalWidth(Width::Large)
                    ->modalSubmitActionLabel('Guardar y notificar')
                    ->fillForm(fn (OperationMedicalAppointment $record): array => [
                        'appointment_at' => $record->appointment_at,
                        'email' => $record->supplier_notify_email
                            ?? $record->supplier?->correo_principal,
                        'phone' => $record->supplier_notify_phone
                            ?? $record->supplier?->personal_phone
                            ?? $record->supplier?->local_phone,
                        'reason' => null,
                    ])
                    ->form([
                        DateTimePicker::make('appointment_at')
                            ->label('Nueva fecha y hora')
                            ->seconds(false)
                            ->native(false)
                            ->required(),
                        TextInput::make('email')
                            ->label('Correo del proveedor')
                            ->email()
                            ->required()
                            ->maxLength(255),
                        TextInput::make('phone')
                            ->label('Teléfono WhatsApp del proveedor')
                            ->tel()
                            ->required()
                            ->maxLength(30),
                        Textarea::make('reason')
                            ->label('Motivo del cambio')
                            ->required()
                            ->minLength(10)
                            ->rows(3)
                            ->helperText('Mínimo 10 caracteres. Quedará en la bitácora del caso.'),
                    ])
                    ->action(function (OperationMedicalAppointment $record, array $data): void {
                        try {
                            MedicalAppointmentManager::reschedule($record, $data);
                            Notification::make()
                                ->title('Cita reprogramada')
                                ->body('Se actualizó la orden, se registró en bitácora y se notificó al proveedor.')
                                ->success()
                                ->send();
                        } catch (InvalidArgumentException|Throwable $e) {
                            Notification::make()
                                ->title('No se pudo reprogramar')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    })
                    ->button()
                    ->extraAttributes([
                        'x-on:click.stop' => '',
                        'class' => FilamentIosButton::extraClassForFilamentColor('warning'),
                    ]),
                ActionGroup::make([
                    Action::make('preview_order_pdf')
                        ->label('Vista previa OS')
                        ->icon(Heroicon::OutlinedDocumentMagnifyingGlass)
                        ->color('info')
                        ->modalHeading('Orden de servicio en PDF')
                        ->modalWidth(Width::SevenExtraLarge)
                        ->modalContent(function (OperationMedicalAppointment $record): ViewContract {
                            $order = self::requireOrder($record);

                            return View::make('filament.operations.operation-service-orders.pdf-preview', [
                                'pdfPreviewUrl' => route('operations.operation-service-orders.pdf.preview', ['operationServiceOrder' => $order]),
                                'pdfDownloadUrl' => route('operations.operation-service-orders.pdf', ['operationServiceOrder' => $order]),
                            ]);
                        })
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('Cerrar')
                        ->action(fn () => null),
                    Action::make('download_order_pdf')
                        ->label('Descargar OS')
                        ->icon(Heroicon::OutlinedArrowDownTray)
                        ->color('gray')
                        ->url(fn (OperationMedicalAppointment $record): string => route(
                            'operations.operation-service-orders.pdf',
                            ['operationServiceOrder' => self::requireOrder($record)]
                        ))
                        ->openUrlInNewTab(),
                    Action::make('email_order_pdf')
                        ->label('Enviar OS por correo')
                        ->icon(Heroicon::OutlinedEnvelope)
                        ->color('success')
                        ->form([
                            TextInput::make('email')
                                ->label('Correo del destinatario')
                                ->email()
                                ->required()
                                ->default(fn (OperationMedicalAppointment $record): ?string => $record->supplier_notify_email
                                    ?? $record->supplier?->correo_principal)
                                ->maxLength(255),
                        ])
                        ->action(function (OperationMedicalAppointment $record, array $data): void {
                            Mail::to($data['email'])->send(new OperationServiceOrderPdfMail(self::requireOrder($record)));

                            Notification::make()
                                ->success()
                                ->title('Correo enviado')
                                ->body('Se adjuntó el PDF de la orden de servicio.')
                                ->send();
                        }),
                    Action::make('whatsapp_order')
                        ->label('Enviar OS por WhatsApp')
                        ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                        ->color('success')
                        ->form([
                            TextInput::make('phone')
                                ->label('Teléfono WhatsApp')
                                ->tel()
                                ->required()
                                ->default(fn (OperationMedicalAppointment $record): ?string => $record->supplier_notify_phone
                                    ?? $record->supplier?->personal_phone
                                    ?? $record->supplier?->local_phone)
                                ->maxLength(30),
                        ])
                        ->action(function (OperationMedicalAppointment $record, array $data): void {
                            $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp((string) $data['phone']);

                            if ($normalized === null) {
                                Notification::make()
                                    ->title('Teléfono inválido')
                                    ->body('Indique un teléfono válido para WhatsApp.')
                                    ->danger()
                                    ->send();

                                return;
                            }

                            $order = self::requireOrder($record);
                            $previewUrl = route('operations.operation-service-orders.pdf.preview', ['operationServiceOrder' => $order]);
                            $caption = "📄 *Orden de servicio*\n"
                                .'N.º: '.($order->order_number ?? '#'.$order->id)."\n"
                                .'Paciente: '.($record->telemedicinePatient?->full_name
                                    ?? $record->operationCoordinationService?->patient
                                    ?? '—')."\n"
                                .'Cita: '.MedicalAppointmentManager::formatAppointmentAt($record->appointment_at)."\n"
                                .'Vista previa: '.$previewUrl;

                            SendNotificacionWhatsApp::dispatch(
                                Auth::id(),
                                $caption,
                                $normalized,
                                null,
                                [
                                    'panel' => 'operations',
                                    'context' => 'medical_appointment_order_whatsapp',
                                    'entity_id' => $record->id,
                                ],
                            );

                            Notification::make()
                                ->success()
                                ->title('WhatsApp encolado')
                                ->body('Se enviará la notificación de la orden al proveedor.')
                                ->send();
                        }),
                ])
                    ->label('Orden de servicio')
                    ->icon(Heroicon::OutlinedDocumentText)
                    ->color('info')
                    ->button()
                    ->extraAttributes([
                        'x-on:click.stop' => '',
                        'class' => FilamentIosButton::extraClassForFilamentColor('info'),
                    ]),
            ]);
    }

    private static function requireOrder(OperationMedicalAppointment $record): OperationServiceOrder
    {
        $order = $record->operationServiceOrder;

        if (! $order instanceof OperationServiceOrder) {
            throw new InvalidArgumentException('La cita no tiene orden de servicio asociada.');
        }

        return $order;
    }
}
