<?php

declare(strict_types=1);

namespace App\Filament\Business\Resources\ProspectAgents;

use App\Models\ProspectAgent;
use App\Models\RrhhColaborador;
use App\Models\User;
use App\Support\Filament\FilamentIosButton;
use App\Support\ProspectAgents\ProspectHelpdeskTicketCreator;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

/**
 * «Ticket de trabajo»: crea un ticket del Helpdesk (Solicitud de servicio) con unas
 * indicaciones para uno o varios colaboradores sobre los prospectos elegidos (acción masiva
 * de la tabla) o sobre el prospecto abierto (Ver). Se gestiona desde el Helpdesk.
 */
final class ProspectWorkTicketActions
{
    public static function bulk(): BulkAction
    {
        return BulkAction::make('workTicket')
            ->label('Ticket de trabajo')
            ->icon(Heroicon::OutlinedTicket)
            ->color('info')
            ->modalIcon(Heroicon::OutlinedTicket)
            ->modalHeading('Nuevo ticket de trabajo en el Helpdesk')
            ->modalDescription(fn (Collection $records): string => self::summary($records))
            ->modalSubmitActionLabel('Crear ticket')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth('2xl')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('info'),
            ]))
            ->schema(self::fields())
            ->action(function (Collection $records, array $data): void {
                self::run($records, $data);
            })
            ->deselectRecordsAfterCompletion();
    }

    public static function single(): Action
    {
        return Action::make('workTicket')
            ->label('Ticket de trabajo')
            ->icon(Heroicon::OutlinedTicket)
            ->color('info')
            ->visible(fn (ProspectAgent $record): bool => ! $record->trashed())
            ->modalIcon(Heroicon::OutlinedTicket)
            ->modalHeading('Nuevo ticket de trabajo en el Helpdesk')
            ->modalDescription(fn (ProspectAgent $record): string => self::summary(collect([$record])))
            ->modalSubmitActionLabel('Crear ticket')
            ->modalCancelActionLabel('Cancelar')
            ->modalWidth('2xl')
            ->modalSubmitAction(fn (Action $action): Action => $action->extraAttributes([
                'class' => FilamentIosButton::extraClassForFilamentColor('info'),
            ]))
            ->schema(self::fields())
            ->action(function (ProspectAgent $record, array $data): void {
                self::run(collect([$record]), $data);
            });
    }

    /**
     * @return array<int, Select|Textarea>
     */
    public static function fields(): array
    {
        return [
            Select::make('colaborador_ids')
                ->label('Asignar a')
                ->helperText('Una o varias personas. El Helpdesk les avisa por WhatsApp y correo, como con cualquier ticket.')
                ->multiple()
                ->options(fn (): array => self::colaboradorOptions())
                ->searchable()
                ->preload()
                ->native(false)
                ->required()
                ->minItems(1)
                ->validationMessages([
                    'required' => 'Elige al menos una persona.',
                    'min' => 'Elige al menos una persona.',
                ]),
            Select::make('priority')
                ->label('Prioridad')
                ->helperText('Define el SLA del Helpdesk: Alta 4 h para responder y 24 h para resolver; Media 24 h y 72 h; Baja 48 h y 120 h.')
                ->options(ProspectHelpdeskTicketCreator::priorityOptions())
                ->default(ProspectHelpdeskTicketCreator::DEFAULT_PRIORITY)
                ->selectablePlaceholder(false)
                ->native(false)
                ->required(),
            Textarea::make('instructions')
                ->label('Indicaciones de la actividad')
                ->placeholder('Ej. Llamar al gerente comercial, presentar el plan corporativo y enviar la propuesta por correo. Registrar en la bitácora la fecha de la reunión acordada.')
                ->helperText('Sé específico: qué hay que hacer, con quién y qué resultado se espera.')
                ->required()
                ->minLength(ProspectHelpdeskTicketCreator::MIN_INSTRUCTIONS_LENGTH)
                ->maxLength(ProspectHelpdeskTicketCreator::MAX_INSTRUCTIONS_LENGTH)
                ->validationMessages([
                    'required' => 'Escribe las indicaciones de la actividad.',
                    'min' => 'Las indicaciones deben tener al menos '.ProspectHelpdeskTicketCreator::MIN_INSTRUCTIONS_LENGTH.' caracteres.',
                ])
                ->rows(6)
                ->autosize(),
        ];
    }

    /**
     * @return array<int|string, string>
     */
    public static function colaboradorOptions(): array
    {
        return RrhhColaborador::query()
            ->whereRaw("LOWER(TRIM(COALESCE(status, ''))) = 'activo'")
            ->orderBy('fullName')
            ->pluck('fullName', 'id')
            ->map(fn (mixed $name): string => trim((string) $name) ?: 'Sin nombre')
            ->all();
    }

    /**
     * @param  Collection<int, ProspectAgent>  $records
     */
    public static function summary(Collection $records): string
    {
        $live = $records->filter(fn (ProspectAgent $record): bool => ! $record->trashed());
        $count = $live->count();

        if ($count === 0) {
            return 'Los prospectos seleccionados están eliminados: no se puede crear el ticket.';
        }

        $names = $live->take(5)->map(fn (ProspectAgent $record): string => trim((string) $record->name) ?: 'Prospecto #'.$record->getKey())->implode(', ');
        $rest = $count - min(5, $count);
        $omitted = $records->count() - $count;

        return 'Se creará un ticket en el Helpdesk (Solicitud de servicio) con '.$count.' '.($count === 1 ? 'prospecto' : 'prospectos').': '.$names
            .($rest > 0 ? ' y '.$rest.' más' : '').'.'
            .($omitted > 0 ? ' Se omiten '.$omitted.' eliminados.' : '');
    }

    /**
     * @param  Collection<int, ProspectAgent>  $records
     * @param  array<string, mixed>  $data
     */
    private static function run(Collection $records, array $data): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            Notification::make()->danger()->title('No se creó el ticket')->body('Tu sesión expiró. Vuelve a iniciar sesión.')->send();

            return;
        }

        try {
            $ticket = ProspectHelpdeskTicketCreator::create(
                $records->filter(fn (ProspectAgent $record): bool => ! $record->trashed())->map(fn ($model): mixed => $model->getKey())->values()->all(),
                (array) ($data['colaborador_ids'] ?? []),
                (string) ($data['instructions'] ?? ''),
                (string) ($data['priority'] ?? ProspectHelpdeskTicketCreator::DEFAULT_PRIORITY),
                $user,
            );
        } catch (InvalidArgumentException $exception) {
            Notification::make()->warning()->title('No se creó el ticket')->body($exception->getMessage())->send();

            return;
        } catch (Throwable $exception) {
            Log::error('Captación: no se pudo crear el ticket de trabajo en el Helpdesk', [
                'ids' => $records->map(fn ($model): mixed => $model->getKey())->values()->all(),
                'error' => $exception->getMessage(),
            ]);

            Notification::make()
                ->danger()
                ->title('No se creó el ticket')
                ->body('Inténtalo de nuevo; si el problema continúa, avisa a sistemas.')
                ->send();

            return;
        }

        $url = ProspectHelpdeskTicketCreator::ticketUrl($ticket);

        Notification::make()
            ->success()
            ->title('Ticket N.º '.$ticket->getKey().' creado en el Helpdesk')
            ->body('Quedó asignado y el Helpdesk avisó a los responsables. Se gestiona desde el Helpdesk.')
            ->actions($url !== null ? [
                Action::make('viewTicket')->label('Ver en el Helpdesk')->url($url),
            ] : [])
            ->send();
    }
}
