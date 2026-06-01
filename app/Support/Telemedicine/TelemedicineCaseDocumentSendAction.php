<?php

declare(strict_types=1);

namespace App\Support\Telemedicine;

use App\Services\Telemedicine\TelemedicineCaseDocumentDeliveryService;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

final class TelemedicineCaseDocumentSendAction
{
    public static function make(): Action
    {
        return Action::make('sendCaseDocument')
            ->modalIcon('heroicon-o-paper-airplane')
            ->modalHeading(fn (array $arguments): string => match ($arguments['focus'] ?? 'both') {
                'whatsapp' => 'Enviar documento por WhatsApp',
                'email' => 'Enviar documento por correo',
                default => 'Enviar documento al paciente',
            })
            ->modalDescription('Indique teléfono, correo o ambos. El documento se enviará por cada canal completado.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Enviar documento')
            ->modalCancelActionLabel('Cancelar')
            ->fillForm(fn (array $arguments): array => [
                'phone' => (string) ($arguments['default_phone'] ?? ''),
                'email' => (string) ($arguments['default_email'] ?? ''),
            ])
            ->form(fn (array $arguments): array => [
                Placeholder::make('document_context')
                    ->hiddenLabel()
                    ->content(fn (): HtmlString => new HtmlString(
                        '<p class="text-sm text-gray-600 dark:text-gray-300"><span class="font-semibold text-gray-900 dark:text-white">'
                        .e((string) ($arguments['document_name'] ?? 'Documento'))
                        .'</span></p>'
                    ))
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->label('WhatsApp / Teléfono')
                    ->tel()
                    ->placeholder('Ej: 04127018390')
                    ->maxLength(30),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->email()
                    ->placeholder('paciente@ejemplo.com')
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data): void {
                $phone = trim((string) ($data['phone'] ?? ''));
                $email = trim((string) ($data['email'] ?? ''));

                if ($phone === '' && $email === '') {
                    Notification::make()
                        ->title('Datos incompletos')
                        ->body('Indique al menos un teléfono o un correo electrónico.')
                        ->danger()
                        ->send();

                    return;
                }

                $filePath = trim((string) ($arguments['file_path'] ?? ''));
                $documentName = trim((string) ($arguments['document_name'] ?? ''));
                $patientName = trim((string) ($arguments['patient_name'] ?? 'Paciente'));

                if ($filePath === '' || $documentName === '') {
                    Notification::make()
                        ->title('Documento no válido')
                        ->body('No se pudo identificar el archivo a enviar.')
                        ->danger()
                        ->send();

                    return;
                }

                if (! TelemedicineCaseDocumentDeliveryService::fileExists($filePath)) {
                    Notification::make()
                        ->title('Documento no disponible')
                        ->body('El archivo no se encuentra en el servidor.')
                        ->danger()
                        ->send();

                    return;
                }

                try {
                    $result = TelemedicineCaseDocumentDeliveryService::send(
                        $filePath,
                        $documentName,
                        $patientName !== '' ? $patientName : 'Paciente',
                        $phone !== '' ? $phone : null,
                        $email !== '' ? $email : null,
                    );

                    if (! $result['whatsapp_sent'] && ! $result['email_sent']) {
                        Notification::make()
                            ->title('No se pudo enviar el documento')
                            ->body('Revise el teléfono o correo indicado e intente nuevamente.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $channels = array_values(array_filter([
                        $result['whatsapp_sent'] ? 'WhatsApp' : null,
                        $result['email_sent'] ? 'correo electrónico' : null,
                    ]));

                    Notification::make()
                        ->title('Documento enviado')
                        ->body('El documento fue enviado por '.implode(' y ', $channels).'.')
                        ->success()
                        ->send();
                } catch (\Throwable $throwable) {
                    Log::error('TELEMEDICINA: Error al enviar documento del caso.', [
                        'message' => $throwable->getMessage(),
                        'file_path' => $filePath,
                        'document_name' => $documentName,
                    ]);

                    Notification::make()
                        ->title('Error al enviar')
                        ->body($throwable->getMessage())
                        ->danger()
                        ->send();
                }
            });
    }
}
