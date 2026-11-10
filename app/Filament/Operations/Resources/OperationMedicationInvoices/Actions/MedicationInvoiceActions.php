<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\OperationMedicationInvoices\Actions;

use App\Http\Controllers\MedicationInvoicePdfController;
use App\Jobs\SendMedicationInvoicePdfJob;
use App\Models\OperationMedicationInvoice;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use App\Support\Operations\MedicationInvoiceException;
use App\Support\Operations\MedicationInvoiceLifecycle;
use App\Support\Telemedicine\TelemedicinePatientDeliveryEmail;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

/**
 * Registrar pago y anular una factura de medicamentos. Cada acción exige su permiso también
 * al ejecutarse, no solo para mostrarse.
 */
final class MedicationInvoiceActions
{
    public static function canRegisterPayment(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::REGISTER_MEDICATION_INVOICE_PAYMENT);
    }

    public static function canAnnul(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::ANNUL_MEDICATION_INVOICE);
    }

    public const MAX_EMAILS = 5;

    public static function previewPdf(): Action
    {
        return Action::make('preview_medication_invoice_pdf')
            ->label('Ver PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('info')
            ->visible(fn (OperationMedicationInvoice $record): bool => MedicationInvoicePdfController::userCanView($record))
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Factura '.$record->invoice_number)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (OperationMedicationInvoice $record): ViewContract => View::make('filament.operations.operation-service-orders.pdf-preview', [
                'pdfPreviewUrl' => route('operations.medication-invoices.pdf.preview', ['operationMedicationInvoice' => $record]),
                'pdfDownloadUrl' => route('operations.medication-invoices.pdf.download', ['operationMedicationInvoice' => $record]),
                'documentLabel' => 'Factura a paciente',
                'documentTitle' => $record->invoice_number.' · '.$record->patient_name,
            ]))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->action(fn () => null);
    }

    public static function canSend(OperationMedicationInvoice $record): bool
    {
        return $record->status !== OperationMedicationInvoice::STATUS_ANNULLED && MedicationInvoicePdfController::userCanView($record);
    }

    public static function emailPdf(): Action
    {
        return Action::make('email_medication_invoice_pdf')
            ->label('Enviar por correo')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (OperationMedicationInvoice $record): bool => self::canSend($record))
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Enviar la factura '.$record->invoice_number.' por correo')
            ->modalDescription('El PDF va adjunto. Se propone el correo del paciente; puede cambiarlo o agregar otros.')
            ->modalIcon(Heroicon::OutlinedEnvelope)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Enviar')
            ->fillForm(fn (OperationMedicationInvoice $record): array => ['emails' => array_values(array_filter([self::patientEmail($record)]))])
            ->schema([
                TagsInput::make('emails')
                    ->label('Correos')
                    ->placeholder('Escriba un correo y pulse Enter')
                    ->helperText('Hasta '.self::MAX_EMAILS.' correos.')
                    ->required()
                    ->nestedRecursiveRules(['email:rfc'])
                    ->rules(['array', 'max:'.self::MAX_EMAILS])
                    ->validationMessages([
                        'required' => 'Escriba al menos un correo.',
                        'max' => 'Puede enviar a un máximo de '.self::MAX_EMAILS.' correos.',
                        'email' => 'Hay un correo con formato inválido.',
                    ]),
            ])
            ->action(function (OperationMedicationInvoice $record, array $data): void {
                abort_unless(self::canSend($record), 404);

                self::dispatchSend($record, SendMedicationInvoicePdfJob::CHANNEL_EMAIL, self::cleanEmails($data['emails'] ?? []));
            });
    }

    public static function whatsAppPdf(): Action
    {
        return Action::make('whatsapp_medication_invoice_pdf')
            ->label('Enviar por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (OperationMedicationInvoice $record): bool => self::canSend($record))
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Enviar la factura '.$record->invoice_number.' por WhatsApp')
            ->modalDescription('El PDF llega como documento por un enlace que vence en 48 horas. Se propone el teléfono del paciente; puede cambiarlo.')
            ->modalIcon(Heroicon::OutlinedChatBubbleLeftRight)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Enviar')
            ->fillForm(fn (OperationMedicationInvoice $record): array => ['phone' => self::patientPhone($record)])
            ->schema([
                TextInput::make('phone')
                    ->label('Teléfono de WhatsApp')
                    ->tel()
                    ->placeholder('Ej.: 0414-1234567')
                    ->required()
                    ->maxLength(30)
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        if (HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp((string) $value) === null) {
                            $fail('El teléfono no es válido para WhatsApp.');
                        }
                    }),
            ])
            ->action(function (OperationMedicationInvoice $record, array $data): void {
                abort_unless(self::canSend($record), 404);

                $phone = (string) HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp((string) ($data['phone'] ?? ''));

                self::dispatchSend($record, SendMedicationInvoicePdfJob::CHANNEL_WHATSAPP, $phone !== '' ? [$phone] : []);
            });
    }

    /**
     * @param  list<string>  $recipients
     */
    public static function dispatchSend(OperationMedicationInvoice $record, string $channel, array $recipients): void
    {
        if ($recipients === [] || in_array('', $recipients, true)) {
            Notification::make()->title('Revise el destino')->body('No hay un correo o teléfono válido para enviar.')->warning()->send();

            return;
        }

        SendMedicationInvoicePdfJob::dispatch($channel, $recipients, (int) Auth::id(), (int) $record->getKey())->afterCommit();

        Notification::make()
            ->title($channel === SendMedicationInvoicePdfJob::CHANNEL_EMAIL ? 'Enviando por correo' : 'Enviando por WhatsApp')
            ->body('La factura '.$record->invoice_number.' se está enviando a '.implode(', ', $recipients).'. Quedará en la bitácora; si algo falla, le avisaremos en la campana de notificaciones.')
            ->success()
            ->send();
    }

    /**
     * @return list<string>
     */
    public static function cleanEmails(mixed $emails): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $email): string => mb_strtolower(trim((string) $email)),
            is_array($emails) ? $emails : [],
        ), fn (string $email): bool => filter_var($email, FILTER_VALIDATE_EMAIL) !== false)));
    }

    /**
     * Correo propio del paciente (nunca el de contacto: ese es de un familiar o representante).
     */
    public static function patientEmail(OperationMedicationInvoice $record): ?string
    {
        return TelemedicinePatientDeliveryEmail::for($record->telemedicinePatient);
    }

    public static function patientPhone(OperationMedicationInvoice $record): ?string
    {
        $phone = trim((string) ($record->telemedicinePatient?->phone ?? ''));

        return $phone !== '' ? $phone : null;
    }

    public static function registerPayment(): Action
    {
        return Action::make('register_medication_invoice_payment')
            ->label('Registrar pago')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (OperationMedicationInvoice $record): bool => $record->isPendingPayment() && self::canRegisterPayment())
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Registrar pago de la factura '.$record->invoice_number)
            ->modalDescription(fn (OperationMedicationInvoice $record): string => 'Pago total de US$ '.number_format((float) $record->total_usd, 2, '.', ',')
                .' (Bs. '.number_format((float) $record->total_ves, 2, '.', ',').' a la tasa de emisión). La factura y su cuenta por cobrar quedan COBRADAS y ya no podrán anularse.')
            ->modalIcon(Heroicon::OutlinedBanknotes)
            ->modalSubmitActionLabel('Registrar pago')
            ->schema([
                DatePicker::make('paid_at')
                    ->label('Fecha del pago')
                    ->native(false)
                    ->displayFormat('d/m/Y')
                    ->default(now())
                    ->maxDate(now())
                    ->minDate(fn (OperationMedicationInvoice $record) => $record->issued_at?->copy()->startOfDay())
                    ->required(),
                Select::make('payment_method')
                    ->label('Forma de pago')
                    ->options(MedicationInvoiceLifecycle::paymentMethodOptions())
                    ->native(false)
                    ->required()
                    ->live(),
                TextInput::make('payment_reference')
                    ->label('Referencia')
                    ->maxLength(120)
                    ->required(fn (Get $get): bool => MedicationInvoiceLifecycle::paymentMethodRequiresReference($get('payment_method')))
                    ->helperText('Número de la transferencia, pago móvil, punto de venta o Zelle. No hace falta en efectivo.'),
                Textarea::make('payment_notes')
                    ->label('Observaciones (opcional)')
                    ->rows(2)
                    ->maxLength(2000),
            ])
            ->action(function (OperationMedicationInvoice $record, array $data, Action $action): void {
                if (! self::canRegisterPayment()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para registrar pagos de facturas a pacientes.')->danger()->send();
                    $action->halt();
                }

                try {
                    $paid = MedicationInvoiceLifecycle::registerPayment($record, $data);
                } catch (MedicationInvoiceException $exception) {
                    Notification::make()->title('No se registró el pago')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Pago registrado')
                    ->body('La factura '.$paid->invoice_number.' y su cuenta por cobrar quedaron COBRADAS.')
                    ->success()
                    ->send();
            });
    }

    public static function annul(): Action
    {
        return Action::make('annul_medication_invoice')
            ->label('Anular factura')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('danger')
            ->visible(fn (OperationMedicationInvoice $record): bool => $record->isPendingPayment() && self::canAnnul())
            ->requiresConfirmation()
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Anular la factura '.$record->invoice_number)
            ->modalDescription(fn (OperationMedicationInvoice $record): string => $record->isMedications() && ! $record->isFromQuote()
                ? 'La existencia vuelve al almacén del que salió, los medicamentos recetados vuelven a PENDIENTE para gestionarlos de nuevo y la cuenta por cobrar queda ANULADA. No se puede deshacer.'
                : 'La cuenta por cobrar queda ANULADA. La orden de servicio sigue activa: si el servicio ya no se realizará, cancélela o anúlela después. No se puede anular si la orden ya está FINALIZADA. No se puede deshacer.')
            ->modalSubmitActionLabel('Sí, anular')
            ->schema([
                Textarea::make('reason')
                    ->label('Motivo de la anulación')
                    ->placeholder('Ej.: el paciente no retiró el medicamento; se cargó el producto equivocado…')
                    ->rows(3)
                    ->minLength(10)
                    ->maxLength(2000)
                    ->required(),
            ])
            ->action(function (OperationMedicationInvoice $record, array $data, Action $action): void {
                if (! self::canAnnul()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para anular facturas a pacientes.')->danger()->send();
                    $action->halt();
                }

                try {
                    $annulled = MedicationInvoiceLifecycle::annul($record, (string) ($data['reason'] ?? ''));
                } catch (MedicationInvoiceException $exception) {
                    Notification::make()->title('No se anuló la factura')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                Notification::make()
                    ->title('Factura anulada')
                    ->body('La factura '.$annulled->invoice_number.' quedó ANULADA, la existencia volvió al inventario y los medicamentos pueden gestionarse de nuevo.')
                    ->success()
                    ->send();
            });
    }
}
