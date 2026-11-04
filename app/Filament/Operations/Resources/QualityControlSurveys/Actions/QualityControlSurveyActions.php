<?php

declare(strict_types=1);

namespace App\Filament\Operations\Resources\QualityControlSurveys\Actions;

use App\Filament\Operations\Resources\QualityControlSurveys\QualityControlSurveyResource;
use App\Jobs\SendQualityControlPdfJob;
use App\Models\QualityControlSurvey;
use App\Models\QualityControlSurveyNote;
use App\Models\User;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Operations\QualityControl\QualityControlSurveyNotes;
use Filament\Actions\Action;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

/**
 * Acciones de una encuesta de Quality Control (fila de la tabla y ficha): nota de
 * seguimiento y PDF (ver, enviar por correo, enviar por WhatsApp).
 */
final class QualityControlSurveyActions
{
    public const MAX_EMAILS = 10;

    /**
     * @param  class-string<QualityControlSurveyResource>  $resource
     */
    public static function addNote(string $resource): Action
    {
        return Action::make('addFollowUpNote')
            ->label('Nota de seguimiento')
            ->icon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->color('primary')
            ->visible(fn (QualityControlSurvey $record): bool => $resource::canAddNotes() && $resource::canViewSurvey($record))
            ->modalIcon(Heroicon::OutlinedChatBubbleBottomCenterText)
            ->modalHeading(fn (QualityControlSurvey $record): string => 'Nota de seguimiento · '.$record->patient_name)
            ->modalDescription('Registre lo que se hizo con esta encuesta: a quién se llamó, qué se acordó, a dónde se escaló. La nota queda con su autor, fecha y hora, y no se puede editar ni borrar.')
            ->modalWidth(Width::TwoExtraLarge)
            ->modalSubmitActionLabel('Guardar nota')
            ->schema([
                Textarea::make('note')
                    ->label('Nota')
                    ->placeholder('Ej.: se llamó a la paciente, confirma demora de 3 horas; se escaló a coordinación de medicamentos.')
                    ->rows(5)
                    ->required()
                    ->minLength(QualityControlSurveyNote::MIN_LENGTH)
                    ->maxLength(QualityControlSurveyNote::MAX_LENGTH)
                    ->validationMessages([
                        'required' => 'Escriba la nota.',
                        'min' => 'La nota debe tener al menos '.QualityControlSurveyNote::MIN_LENGTH.' caracteres.',
                        'minLength' => 'La nota debe tener al menos '.QualityControlSurveyNote::MIN_LENGTH.' caracteres.',
                    ]),
            ])
            ->successNotificationTitle('Nota de seguimiento guardada')
            ->action(function (QualityControlSurvey $record, array $data) use ($resource): void {
                abort_unless($resource::canAddNotes() && $resource::canViewSurvey($record), 403);

                $user = Auth::user();
                QualityControlSurveyNotes::add($record, (string) ($data['note'] ?? ''), $user instanceof User ? $user : null);
            });
    }

    /**
     * @param  class-string<QualityControlSurveyResource>  $resource
     */
    public static function previewPdf(string $resource): Action
    {
        return Action::make('previewSurveyPdf')
            ->label('Ver PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('info')
            ->visible(fn (QualityControlSurvey $record): bool => $resource::canViewSurvey($record))
            ->modalHeading(fn (QualityControlSurvey $record): string => 'Encuesta de '.$record->patient_name)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (QualityControlSurvey $record): ViewContract => self::previewView(
                route('operations.quality-control.survey.pdf.preview', ['qualityControlSurvey' => $record]),
                route('operations.quality-control.survey.pdf.download', ['qualityControlSurvey' => $record]),
                'Encuesta de Quality Control',
                $record->patient_name,
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->action(fn () => null);
    }

    /**
     * @param  class-string<QualityControlSurveyResource>  $resource
     */
    public static function emailPdf(string $resource): Action
    {
        return Action::make('emailSurveyPdf')
            ->label('Enviar PDF por correo')
            ->icon(Heroicon::OutlinedEnvelope)
            ->color('gray')
            ->visible(fn (QualityControlSurvey $record): bool => $resource::canViewSurvey($record))
            ->modalHeading('Enviar PDF de la encuesta por correo')
            ->modalDescription('El PDF va adjunto. Escriba a quién se envía: nada se completa con los datos del paciente.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Enviar')
            ->schema([self::emailsField()])
            ->action(function (QualityControlSurvey $record, array $data) use ($resource): void {
                abort_unless($resource::canViewSurvey($record), 403);

                self::dispatch(SendQualityControlPdfJob::CHANNEL_EMAIL, self::cleanEmails($data['emails'] ?? []), $record->affiliate_type->value, surveyId: (int) $record->getKey());
            });
    }

    /**
     * @param  class-string<QualityControlSurveyResource>  $resource
     */
    public static function whatsAppPdf(string $resource): Action
    {
        return Action::make('whatsappSurveyPdf')
            ->label('Enviar PDF por WhatsApp')
            ->icon(Heroicon::OutlinedChatBubbleLeftRight)
            ->color('success')
            ->visible(fn (QualityControlSurvey $record): bool => $resource::canViewSurvey($record))
            ->modalHeading('Enviar PDF de la encuesta por WhatsApp')
            ->modalDescription('El PDF llega como documento por un enlace que vence en 48 horas. Escriba el teléfono: nada se completa con los datos del paciente.')
            ->modalWidth(Width::Large)
            ->modalSubmitActionLabel('Enviar')
            ->schema([self::phoneField()])
            ->action(function (QualityControlSurvey $record, array $data) use ($resource): void {
                abort_unless($resource::canViewSurvey($record), 403);

                self::dispatch(SendQualityControlPdfJob::CHANNEL_WHATSAPP, [self::normalizedPhone($data['phone'] ?? '')], $record->affiliate_type->value, surveyId: (int) $record->getKey());
            });
    }

    public static function previewView(string $previewUrl, string $downloadUrl, string $label, string $title): ViewContract
    {
        return View::make('filament.operations.operation-service-orders.pdf-preview', [
            'pdfPreviewUrl' => $previewUrl,
            'pdfDownloadUrl' => $downloadUrl,
            'documentLabel' => $label,
            'documentTitle' => $title,
        ]);
    }

    public static function emailsField(): TagsInput
    {
        return TagsInput::make('emails')
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
            ]);
    }

    public static function phoneField(): TextInput
    {
        return TextInput::make('phone')
            ->label('Teléfono de WhatsApp')
            ->tel()
            ->placeholder('Ej.: 0414-1234567')
            ->required()
            ->maxLength(30)
            ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail): void {
                if (HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp((string) $value) === null) {
                    $fail('El teléfono no es válido para WhatsApp.');
                }
            });
    }

    /**
     * @param  list<string>  $recipients
     * @param  list<int>  $reportIds
     */
    public static function dispatch(string $channel, array $recipients, string $affiliateType, ?int $surveyId = null, array $reportIds = [], string $reportLabel = ''): void
    {
        if ($recipients === [] || in_array('', $recipients, true)) {
            Notification::make()->title('Revise el destino')->body('No hay un correo o teléfono válido para enviar.')->warning()->send();

            return;
        }

        SendQualityControlPdfJob::dispatch($channel, $recipients, (int) Auth::id(), $affiliateType, $surveyId, $reportIds, $reportLabel)->afterCommit();

        Notification::make()
            ->title($channel === SendQualityControlPdfJob::CHANNEL_EMAIL ? 'Enviando por correo' : 'Enviando por WhatsApp')
            ->body('El PDF se está enviando a '.implode(', ', $recipients).'. Si algo falla, le avisaremos en la campana de notificaciones.')
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

    public static function normalizedPhone(mixed $phone): string
    {
        return (string) HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp((string) $phone);
    }
}
