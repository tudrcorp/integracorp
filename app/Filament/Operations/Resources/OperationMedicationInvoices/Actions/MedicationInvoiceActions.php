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
use App\Support\Operations\MedicationInvoicePayerChange;
use App\Support\Operations\QuoteInvoicePayer;
use App\Support\Telemedicine\TelemedicinePatientDeliveryEmail;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
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

    public static function canSetNumber(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::SET_MEDICATION_INVOICE_NUMBER);
    }

    public static function canChangePayer(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::CHANGE_MEDICATION_INVOICE_PAYER);
    }

    public const MAX_EMAILS = 5;

    public static function previewPdf(): Action
    {
        return Action::make('preview_medication_invoice_pdf')
            ->label('Ver PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('info')
            ->visible(fn (OperationMedicationInvoice $record): bool => MedicationInvoicePdfController::userCanView($record))
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Factura '.$record->displayNumber())
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (OperationMedicationInvoice $record): ViewContract => View::make('filament.operations.operation-service-orders.pdf-preview', [
                'pdfPreviewUrl' => route('operations.medication-invoices.pdf.preview', ['operationMedicationInvoice' => $record]),
                'pdfDownloadUrl' => route('operations.medication-invoices.pdf.download', ['operationMedicationInvoice' => $record]),
                'documentLabel' => 'Factura a paciente',
                'documentTitle' => $record->displayNumber().' · '.$record->patient_name,
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
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Enviar la factura '.$record->displayNumber().' por correo')
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
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Enviar la factura '.$record->displayNumber().' por WhatsApp')
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
            ->body('La factura '.$record->displayNumber().' se está enviando a '.implode(', ', $recipients).'. Quedará en la bitácora; si algo falla, le avisaremos en la campana de notificaciones.')
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

    /**
     * Registrar (o corregir) el número de factura a mano. Desde entonces reemplaza al FAC-… en
     * pantalla, PDF, envíos y reportes; el interno queda en la ficha y en la bitácora.
     */
    public static function setNumber(): Action
    {
        return Action::make('set_medication_invoice_number')
            ->label(fn (OperationMedicationInvoice $record): string => $record->hasManualNumber() ? 'Corregir N.º de factura' : 'Registrar N.º de factura')
            ->icon(Heroicon::OutlinedHashtag)
            ->color('primary')
            ->visible(fn (OperationMedicationInvoice $record): bool => $record->status !== OperationMedicationInvoice::STATUS_ANNULLED && self::canSetNumber())
            ->modalHeading(fn (OperationMedicationInvoice $record): string => $record->hasManualNumber()
                ? 'Corregir el número de la factura '.$record->displayNumber()
                : 'Registrar el número de la factura')
            ->modalDescription(fn (OperationMedicationInvoice $record): string => 'Desde ahora será el número que se muestra en la factura, su PDF, los envíos, los reportes y la cuenta por cobrar. '
                .'El número interno '.$record->invoice_number.' queda en la ficha y en la bitácora.')
            ->modalIcon(Heroicon::OutlinedHashtag)
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel(fn (OperationMedicationInvoice $record): string => $record->hasManualNumber() ? 'Corregir' : 'Registrar')
            ->fillForm(fn (OperationMedicationInvoice $record): array => ['number' => $record->manual_invoice_number])
            ->schema([
                TextInput::make('number')
                    ->label('N.º de factura')
                    ->placeholder('Ej.: 00012345')
                    ->helperText('Tal como aparece en la factura. No puede repetirse con el de otra factura.')
                    ->required()
                    ->maxLength(OperationMedicationInvoice::MANUAL_NUMBER_MAX)
                    ->autofocus()
                    ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                        $problem = MedicationInvoiceLifecycle::manualNumberProblem(MedicationInvoiceLifecycle::normalizeManualNumber($value));

                        if ($problem !== null) {
                            $fail($problem);
                        }
                    }),
                Textarea::make('reason')
                    ->label('Motivo de la corrección')
                    ->placeholder('Ej.: se transcribió mal el número; la factura se reimprimió…')
                    ->rows(2)
                    ->minLength(5)
                    ->maxLength(500)
                    ->required()
                    ->visible(fn (OperationMedicationInvoice $record): bool => $record->hasManualNumber()),
            ])
            ->action(function (OperationMedicationInvoice $record, array $data, Action $action): void {
                if (! self::canSetNumber()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para registrar el número de las facturas a pacientes.')->danger()->send();
                    $action->halt();
                }

                $hadNumber = $record->hasManualNumber();

                try {
                    $updated = MedicationInvoiceLifecycle::setManualNumber($record, $data['number'] ?? '', $data['reason'] ?? null);
                } catch (MedicationInvoiceException $exception) {
                    Notification::make()->title('No se guardó el número')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                $record->setRawAttributes($updated->getAttributes(), true);

                Notification::make()
                    ->title($hadNumber ? 'Número corregido' : 'Número registrado')
                    ->body('La factura ahora se identifica como '.$updated->displayNumber().'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * Cambiar a nombre de quién va la factura: el paciente o su grupo corporativo. Los datos salen
     * de la ficha vigente; si la ficha no tiene dirección, el analista la escribe aquí.
     */
    public static function changePayer(): Action
    {
        return Action::make('change_medication_invoice_payer')
            ->label('Cambiar a nombre de quién va')
            ->icon(Heroicon::OutlinedUserCircle)
            ->color('primary')
            ->visible(fn (OperationMedicationInvoice $record): bool => MedicationInvoicePayerChange::isChangeable($record) && self::canChangePayer())
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'A nombre de quién va la factura '.$record->displayNumber())
            ->modalDescription(fn (OperationMedicationInvoice $record): string => 'Hoy va a nombre de '.mb_strtolower((string) OperationMedicationInvoice::payerLabel(MedicationInvoicePayerChange::currentType($record)))
                .': '.MedicationInvoicePayerChange::currentName($record).'. Los datos se toman de la ficha vigente y se ven de inmediato en el PDF y los envíos.')
            ->modalIcon(Heroicon::OutlinedUserCircle)
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(fn (OperationMedicationInvoice $record): array => ['payer_type' => MedicationInvoicePayerChange::currentType($record)])
            ->schema([
                Radio::make('payer_type')
                    ->label('La factura va a nombre de')
                    ->options(OperationMedicationInvoice::payerOptions())
                    ->descriptions(fn (OperationMedicationInvoice $record): array => self::payerDescriptions($record))
                    ->disableOptionWhen(fn (string $value, OperationMedicationInvoice $record): bool => $value === OperationMedicationInvoice::PAYER_CORPORATE
                        && (MedicationInvoicePayerChange::options($record)['corporate'] ?? null) === null)
                    ->required()
                    ->live(),
                Textarea::make('address')
                    ->label('Dirección del responsable')
                    ->helperText('Su ficha no tiene dirección. Escríbala tal como debe salir en la factura.')
                    ->rows(2)
                    ->maxLength(QuoteInvoicePayer::ADDRESS_MAX)
                    ->required(fn (Get $get, OperationMedicationInvoice $record): bool => self::payerNeedsAddress($record, $get('payer_type')))
                    ->visible(fn (Get $get, OperationMedicationInvoice $record): bool => self::payerNeedsAddress($record, $get('payer_type'))),
                Textarea::make('reason')
                    ->label('Motivo del cambio')
                    ->placeholder('Ej.: la empresa asume el pago; el paciente pidió la factura a su nombre…')
                    ->rows(2)
                    ->minLength(MedicationInvoicePayerChange::REASON_MIN)
                    ->maxLength(MedicationInvoicePayerChange::REASON_MAX)
                    ->required(),
            ])
            ->action(function (OperationMedicationInvoice $record, array $data, Action $action): void {
                if (! self::canChangePayer()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para cambiar a nombre de quién van las facturas a pacientes.')->danger()->send();
                    $action->halt();
                }

                try {
                    $updated = MedicationInvoicePayerChange::change($record, $data['payer_type'] ?? null, $data['reason'] ?? '', $data['address'] ?? null);
                } catch (MedicationInvoiceException $exception) {
                    Notification::make()->title('No se cambió el responsable')->body($exception->getMessage())->danger()->send();
                    $action->halt();

                    return;
                }

                $record->setRawAttributes($updated->getAttributes(), true);

                Notification::make()
                    ->title('Factura actualizada')
                    ->body('La factura '.$updated->displayNumber().' ahora va a nombre de '.$updated->payer_name.'.')
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<string, string>
     */
    public static function payerDescriptions(OperationMedicationInvoice $record): array
    {
        $options = MedicationInvoicePayerChange::options($record);

        if ($options === null) {
            return [];
        }

        $describe = static fn (array $payer, string $idLabel): string => $payer['name']
            .' · '.$idLabel.': '.($payer['identification'] ?? '—')
            .' · Dirección: '.($payer['address'] ?? 'sin dirección en la ficha');

        return [
            OperationMedicationInvoice::PAYER_PATIENT => $describe($options['patient'], 'C.I.'),
            OperationMedicationInvoice::PAYER_CORPORATE => $options['corporate'] !== null
                ? $describe($options['corporate'], 'RIF')
                : (string) $options['corporate_unavailable_reason'],
        ];
    }

    public static function payerNeedsAddress(OperationMedicationInvoice $record, mixed $type): bool
    {
        $options = MedicationInvoicePayerChange::options($record);

        if ($options === null || ! is_string($type)) {
            return false;
        }

        $payer = $type === OperationMedicationInvoice::PAYER_PATIENT ? $options['patient'] : $options['corporate'];

        if ($payer === null || filled($payer['address'])) {
            return false;
        }

        return ! (MedicationInvoicePayerChange::currentType($record) === $type && filled($record->payer_address));
    }

    public static function registerPayment(): Action
    {
        return Action::make('register_medication_invoice_payment')
            ->label('Registrar pago')
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('success')
            ->visible(fn (OperationMedicationInvoice $record): bool => $record->isPendingPayment() && self::canRegisterPayment())
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Registrar pago de la factura '.$record->displayNumber())
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
                    ->body('La factura '.$paid->displayNumber().' y su cuenta por cobrar quedaron COBRADAS.')
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
            ->modalHeading(fn (OperationMedicationInvoice $record): string => 'Anular la factura '.$record->displayNumber())
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
                    ->body('La factura '.$annulled->displayNumber().' quedó ANULADA, la existencia volvió al inventario y los medicamentos pueden gestionarse de nuevo.')
                    ->success()
                    ->send();
            });
    }
}
