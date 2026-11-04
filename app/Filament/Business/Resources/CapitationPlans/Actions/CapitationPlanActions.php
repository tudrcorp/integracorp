<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\CapitationPlans\Actions;

use App\Jobs\SendCapitationPlanReportJob;
use App\Models\CapitationPlan;
use App\Models\User;
use App\Services\CapitationPlanPdfService;
use App\Services\HelpdeskTicketAssigneeWhatsAppService;
use App\Support\Business\CapitationPlans\CapitationPlanWriter;
use App\Support\Filament\BusinessFilamentActionAccess;
use App\Support\Filament\BusinessFilamentActionPermissionRegistry;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Acciones de Planes de Capitado compartidas por la tabla, la ficha y la edición.
 */
final class CapitationPlanActions
{
    public static function canDelete(): bool
    {
        return BusinessFilamentActionAccess::userCan(BusinessFilamentActionPermissionRegistry::DELETE_CAPITATION_PLANS);
    }

    public static function delete(): Action
    {
        return Action::make('deleteCapitationPlan')
            ->label('Eliminar')
            ->icon(Heroicon::OutlinedTrash)
            ->color('danger')
            ->visible(fn (): bool => self::canDelete())
            ->modalIcon(Heroicon::OutlinedTrash)
            ->modalIconColor('danger')
            ->modalHeading('Eliminar plan de capitado')
            ->modalDescription(fn (CapitationPlan $record): string => 'El plan «'.$record->name.'» dejará de aparecer en la red y en los reportes. No se borra de la base de datos: queda guardado con su razón en la auditoría. Si solo dejó de aplicar, es mejor marcarlo como Inactivo.')
            ->modalWidth(Width::Large)
            ->schema([
                Textarea::make('reason')
                    ->label('Razón de la eliminación')
                    ->placeholder('Ej.: el proveedor rescindió el convenio el 30/10/2026 y se negoció un plan nuevo.')
                    ->helperText('Obligatoria. Quedará registrada junto con su nombre y la fecha.')
                    ->required()
                    ->minLength(CapitationPlanWriter::MIN_DELETION_REASON)
                    ->maxLength(CapitationPlanWriter::MAX_DELETION_REASON)
                    ->rows(4)
                    ->autofocus(),
            ])
            ->modalSubmitActionLabel('Sí, eliminar')
            ->action(function (CapitationPlan $record, array $data, mixed $livewire, Action $action): void {
                /** La visibilidad no basta: se vuelve a comprobar en el servidor. */
                if (! self::canDelete()) {
                    Notification::make()->title('Sin permiso')->body('No tiene permiso para eliminar planes de capitado.')->danger()->send();
                    $action->halt();
                }

                try {
                    CapitationPlanWriter::delete($record, (string) ($data['reason'] ?? ''), self::user());
                } catch (ValidationException $exception) {
                    Notification::make()->title('No se eliminó el plan')->body((string) collect($exception->errors())->flatten()->first())->danger()->send();
                    $action->halt();
                }

                Notification::make()->title('Plan eliminado')->body('La razón quedó registrada en la auditoría.')->success()->send();

                /** Desde la ficha o la edición se vuelve al listado: el plan ya no se puede abrir. */
                if ($livewire instanceof ViewRecord || $livewire instanceof EditRecord) {
                    $livewire->redirect($livewire::getResource()::getUrl('index'), navigate: true);
                }
            });
    }

    public static function pdf(): Action
    {
        return Action::make('capitationPlanPdf')
            ->label('Ficha PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->modalHeading('Ficha del plan en PDF')
            ->modalIcon(Heroicon::OutlinedDocumentText)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (CapitationPlan $record): ViewContract => self::previewView([(int) $record->getKey()], (string) $record->name))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->action(fn (): null => null);
    }

    public static function send(): Action
    {
        return Action::make('sendCapitationPlan')
            ->label('Enviar por correo o WhatsApp')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->modalHeading('Enviar plan de capitado')
            ->modalDescription(fn (CapitationPlan $record): string => 'Se enviará la ficha PDF del plan «'.$record->name.'». Los datos de contacto del proveedor vienen precargados; puede cambiarlos.')
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalWidth(Width::Large)
            ->fillForm(fn (CapitationPlan $record): array => self::defaultSendData(CapitationPlanPdfService::plans([(int) $record->getKey()])))
            ->schema(self::sendSchema())
            ->modalSubmitActionLabel('Enviar')
            ->action(fn (CapitationPlan $record, array $data, Action $action): mixed => self::dispatchSend([(int) $record->getKey()], $data, $action));
    }

    public static function bulkPdf(): BulkAction
    {
        return BulkAction::make('capitationPlansPdf')
            ->label('Reporte PDF')
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->modalHeading('Reporte de planes de capitado en PDF')
            ->modalIcon(Heroicon::OutlinedDocumentText)
            ->modalWidth(Width::SevenExtraLarge)
            ->modalContent(fn (Collection $records): ViewContract => self::previewView(
                array_map('intval', $records->modelKeys()),
                $records->count() === 1 ? (string) $records->first()->name : $records->count().' planes seleccionados',
            ))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Cerrar')
            ->action(fn (): null => null);
    }

    public static function bulkSend(): BulkAction
    {
        return BulkAction::make('sendCapitationPlans')
            ->label('Enviar por correo o WhatsApp')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('primary')
            ->modalHeading('Enviar reporte de planes de capitado')
            ->modalDescription(fn (Collection $records): string => 'Se enviará un solo PDF con '.($records->count() === 1 ? 'el plan seleccionado' : 'los '.$records->count().' planes seleccionados').'. Si todos son del mismo proveedor, sus datos de contacto vienen precargados.')
            ->modalIcon(Heroicon::OutlinedPaperAirplane)
            ->modalWidth(Width::Large)
            ->fillForm(fn (Collection $records): array => $records->count() <= CapitationPlanPdfService::MAX_PLANS
                ? self::defaultSendData(CapitationPlanPdfService::plans($records->modelKeys()))
                : self::defaultSendData(new Collection))
            ->schema(self::sendSchema())
            ->modalSubmitActionLabel('Enviar')
            ->deselectRecordsAfterCompletion()
            ->action(fn (Collection $records, array $data, BulkAction $action): mixed => self::dispatchSend(array_map('intval', $records->modelKeys()), $data, $action));
    }

    /**
     * @param  list<int>  $ids
     */
    private static function previewView(array $ids, string $label): ViewContract
    {
        $blocked = count($ids) > CapitationPlanPdfService::MAX_PLANS
            ? 'Un reporte admite hasta '.CapitationPlanPdfService::MAX_PLANS.' planes y seleccionó '.count($ids).'. Reduzca la selección o use los filtros.'
            : null;

        sort($ids);
        $query = ['ids' => implode(',', $ids)];

        return View::make('filament.business.capitation-plans.pdf-preview-modal', [
            'pdfPreviewUrl' => $blocked === null ? route('business.capitation-plans.pdf', $query) : null,
            'pdfDownloadUrl' => $blocked === null ? route('business.capitation-plans.pdf', [...$query, 'download' => 1]) : null,
            'documentLabel' => $label,
            'blockedMessage' => $blocked,
        ]);
    }

    /**
     * @return array<int, mixed>
     */
    private static function sendSchema(): array
    {
        return [
            CheckboxList::make('channels')
                ->label('¿Por dónde lo envía?')
                ->options([
                    SendCapitationPlanReportJob::CHANNEL_EMAIL => 'Correo electrónico',
                    SendCapitationPlanReportJob::CHANNEL_WHATSAPP => 'WhatsApp',
                ])
                ->columns(2)
                ->required()
                ->live()
                ->validationMessages(['required' => 'Elija al menos un canal.']),
            TextInput::make('email')
                ->label('Correo del destinatario')
                ->email()
                ->maxLength(150)
                ->placeholder('proveedor@ejemplo.com')
                ->visible(fn (Get $get): bool => in_array(SendCapitationPlanReportJob::CHANNEL_EMAIL, (array) $get('channels'), true))
                ->required(fn (Get $get): bool => in_array(SendCapitationPlanReportJob::CHANNEL_EMAIL, (array) $get('channels'), true)),
            TextInput::make('phone')
                ->label('WhatsApp del destinatario')
                ->tel()
                ->maxLength(25)
                ->placeholder('0414 123 4567 o +58 414 123 4567')
                ->helperText('Con o sin código de país; se normaliza antes de enviar.')
                ->visible(fn (Get $get): bool => in_array(SendCapitationPlanReportJob::CHANNEL_WHATSAPP, (array) $get('channels'), true))
                ->required(fn (Get $get): bool => in_array(SendCapitationPlanReportJob::CHANNEL_WHATSAPP, (array) $get('channels'), true))
                ->rules([
                    fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (filled($value) && ! self::isValidWhatsAppNumber((string) $value)) {
                            $fail('El número de WhatsApp no es válido. Use 0414 123 4567 o +58 414 123 4567.');
                        }
                    },
                ]),
            Textarea::make('message')
                ->label('Mensaje (opcional)')
                ->placeholder('Ej.: Le compartimos el plan acordado en la reunión del lunes.')
                ->maxLength(500)
                ->rows(3),
        ];
    }

    /**
     * @param  Collection<int, CapitationPlan>  $plans
     * @return array<string, mixed>
     */
    private static function defaultSendData(Collection $plans): array
    {
        $recipient = CapitationPlanPdfService::suggestedRecipient($plans);

        return [
            'channels' => [SendCapitationPlanReportJob::CHANNEL_EMAIL],
            'email' => $recipient['email'],
            'phone' => $recipient['phone'],
            'message' => null,
        ];
    }

    /**
     * Encola un job por canal. El PDF se arma en la cola: el analista no espera el envío.
     *
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $data
     */
    private static function dispatchSend(array $ids, array $data, Action $action): null
    {
        if ($ids === [] || count($ids) > CapitationPlanPdfService::MAX_PLANS) {
            Notification::make()
                ->title('No se envió')
                ->body('Seleccione entre 1 y '.CapitationPlanPdfService::MAX_PLANS.' planes.')
                ->danger()
                ->send();
            $action->halt();
        }

        $channels = array_values(array_intersect(
            [SendCapitationPlanReportJob::CHANNEL_EMAIL, SendCapitationPlanReportJob::CHANNEL_WHATSAPP],
            (array) ($data['channels'] ?? []),
        ));
        $message = filled($data['message'] ?? null) ? mb_substr(trim((string) $data['message']), 0, 500) : null;
        $userId = self::user()?->getKey();
        $queued = [];

        try {
            foreach ($channels as $channel) {
                $recipient = $channel === SendCapitationPlanReportJob::CHANNEL_EMAIL
                    ? trim((string) ($data['email'] ?? ''))
                    : trim((string) ($data['phone'] ?? ''));

                if ($recipient === '') {
                    continue;
                }

                SendCapitationPlanReportJob::dispatch($ids, $channel, $recipient, $message, $userId !== null ? (int) $userId : null);
                $queued[] = $channel === SendCapitationPlanReportJob::CHANNEL_EMAIL ? 'correo a '.$recipient : 'WhatsApp al '.$recipient;
            }
        } catch (Throwable $exception) {
            Log::error('CapitationPlanActions: no se pudo encolar el envío', ['ids' => $ids, 'error' => $exception->getMessage()]);
        }

        if ($queued === []) {
            Notification::make()->title('No se pudo programar el envío')->body('Intente de nuevo. Si persiste, avise a sistemas.')->danger()->send();
            $action->halt();
        }

        Notification::make()
            ->title('Envío en curso')
            ->body('Se está preparando el PDF para: '.implode(' y ', $queued).'. Le avisaremos en la campana cuando salga.')
            ->success()
            ->send();

        return null;
    }

    /**
     * El normalizador del hub acepta cualquier cadena con dígitos; aquí se exige un número
     * internacional plausible (10 a 15 dígitos, E.164) antes de encolar.
     */
    public static function isValidWhatsAppNumber(string $phone): bool
    {
        $normalized = HelpdeskTicketAssigneeWhatsAppService::normalizePhoneForWhatsApp($phone);

        if ($normalized === null) {
            return false;
        }

        $digits = strlen((string) preg_replace('/\D+/', '', $normalized));

        return $digits >= 10 && $digits <= 15;
    }

    private static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
