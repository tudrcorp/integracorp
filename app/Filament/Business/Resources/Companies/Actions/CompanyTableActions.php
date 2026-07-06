<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\Companies\Actions;

use App\Models\Company;
use App\Support\Companies\CompanyPaymentUploadService;
use App\Support\Companies\CompanyPaymentVoucherForm;
use App\Support\Companies\CompanyPublicRegistrationLinkSender;
use App\Support\SecurityAudit;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Log;

final class CompanyTableActions
{
    public static function uploadPaymentVoucherAction(): Action
    {
        return Action::make('uploadPaymentVoucher')
            ->label('Comprobante de Pago')
            ->color('info')
            ->icon(Heroicon::CloudArrowUp)
            ->modalWidth(Width::FourExtraLarge)
            ->form(CompanyPaymentVoucherForm::schema())
            ->action(function (Company $record, array $data): void {
                try {
                    $uploaded = CompanyPaymentUploadService::upload($record, $data, 'AGENTE');

                    if ($uploaded) {
                        Notification::make()
                            ->title('NOTIFICACION')
                            ->body('El comprobante de pago se ha registrado con exito')
                            ->icon('heroicon-m-user-plus')
                            ->iconColor('success')
                            ->success()
                            ->seconds(5)
                            ->send();
                    }

                    SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PAYMENT_VOUCHER_TABLE_ACTION', 'business.companies.table-upload-payment', [
                        'company_id' => $record->getKey(),
                        'uploaded' => (bool) $uploaded,
                        'payment_method' => $data['payment_method'] ?? null,
                    ]);
                } catch (\Throwable $throwable) {
                    Log::error('NEGOCIOS-EMPRESAS: Error al cargar comprobante de pago.', [
                        'company_id' => $record->getKey(),
                        'error' => $throwable->getMessage(),
                    ]);

                    SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PAYMENT_VOUCHER_TABLE_ACTION_FAILED', 'business.companies.table-upload-payment', [
                        'company_id' => $record->getKey(),
                        'payment_method' => $data['payment_method'] ?? null,
                        'error' => $throwable->getMessage(),
                    ]);

                    Notification::make()
                        ->title('¡ERROR!')
                        ->icon('heroicon-m-exclamation-triangle')
                        ->body('Hubo un error al registrar el comprobante de pago. Por favor, intente de nuevo.')
                        ->danger()
                        ->send();

                    throw $throwable;
                }
            });
    }

    public static function sendPublicRegistrationLinkAction(): Action
    {
        return Action::make('sendPublicRegistrationLink')
            ->label('Enviar enlace público')
            ->icon(Heroicon::PaperAirplane)
            ->color('warning')
            ->modalHeading('Enviar enlace público de registro')
            ->modalDescription('Comparta el enlace para que los responsables de la empresa registren asociados. Indique al menos correo electrónico o WhatsApp.')
            ->modalIcon(Heroicon::Link)
            ->modalIconColor('success')
            ->modalSubmitActionLabel('Enviar')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth(Width::ExtraLarge)
            ->fillForm(fn (Company $record): array => [
                'email' => $record->email,
                'phone' => $record->phone,
            ])
            ->form([
                Section::make('Destinatarios')
                    ->description('Puede usar solo correo, solo WhatsApp o ambos en el mismo envío.')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'lg' => 2,
                        ])->schema([
                            TextInput::make('email')
                                ->label('Correo electrónico')
                                ->email()
                                ->maxLength(255)
                                ->autocomplete('email')
                                ->prefixIcon(Heroicon::Envelope)
                                ->placeholder('ejemplo@empresa.com')
                                ->helperText('Correo válido donde recibirán el enlace.'),
                            TextInput::make('phone')
                                ->label('WhatsApp')
                                ->prefixIcon(Heroicon::Phone)
                                ->tel()
                                ->placeholder('04127018390 o +584121234567')
                                ->helperText('Número con WhatsApp. Venezuela: 0412… sin espacios. Extranjero: código de país (+58…, +1…).'),
                        ]),
                    ]),
            ])
            ->action(function (Company $record, array $data): void {
                $email = filled($data['email'] ?? null) ? (string) $data['email'] : null;
                $phone = filled($data['phone'] ?? null) ? (string) $data['phone'] : null;

                if ($email === null && $phone === null) {
                    SecurityAudit::log('AUDIT_BUSINESS_COMPANY_PUBLIC_LINK_SEND_FAILED', 'business.companies.send-public-link', [
                        'company_id' => $record->getKey(),
                        'reason' => 'missing_email_and_phone',
                    ]);

                    Notification::make()
                        ->title('NOTIFICACION')
                        ->body('La notificación no pudo enviarse porque no se proporcionaron datos de contacto (correo y/o teléfono).')
                        ->icon('heroicon-c-shield-exclamation')
                        ->color('warning')
                        ->send();

                    return;
                }

                if ($email !== null) {
                    $sent = CompanyPublicRegistrationLinkSender::sendEmail($record, $email);

                    Notification::make()
                        ->title($sent ? 'NOTIFICACION ENVIADA' : 'ENVIO FALLIDO')
                        ->body($sent
                            ? 'La notificación vía correo electrónico fue enviada con éxito.'
                            : 'La notificación vía correo electrónico no pudo enviarse.')
                        ->icon($sent ? 'heroicon-c-shield-check' : 'heroicon-c-shield-exclamation')
                        ->color($sent ? 'success' : 'danger')
                        ->send();
                }

                if ($phone !== null) {
                    $sent = CompanyPublicRegistrationLinkSender::sendWhatsApp($record, $phone);

                    Notification::make()
                        ->title($sent ? 'NOTIFICACION ENVIADA' : 'ENVIO FALLIDO')
                        ->body($sent
                            ? 'La notificación vía WhatsApp fue enviada con éxito.'
                            : 'La notificación vía WhatsApp no pudo enviarse. Verifique el número e intente de nuevo.')
                        ->icon($sent ? 'heroicon-c-shield-check' : 'heroicon-c-shield-exclamation')
                        ->color($sent ? 'success' : 'danger')
                        ->send();
                }
            });
    }
}
