<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Models\UserWorkBreak;
use App\Models\UserWorkMeeting;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\Workday\WorkBreakException;
use App\Support\UserActivity\Workday\WorkBreaks;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use App\Support\UserActivity\Workday\WorkMeetings;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * Almuerzo y reuniones en la barra superior de los paneles internos.
 *
 * - «Salir a almorzar» → «En almuerzo 00:42 · Volver» (en naranja al pasar de la
 *   hora). Visible todo el día laboral; se declara a cualquier hora de la jornada.
 * - «Reunión» (con motivo obligatorio) → «En reunión 00:35 · Terminé». Puede
 *   cerrar sesión: al volver a entrar sigue «en reunión» hasta marcar el fin.
 * - Si vuelve a usar el sistema sin marcar el regreso o el fin, se le pregunta;
 *   «No, sigo» calla la pregunta unos minutos en todas sus pestañas.
 */
class WorkBreakToggle extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    private const SNOOZE_SESSION_KEY = 'work_break.prompt_snooze_until';

    private const MEETING_SNOOZE_SESSION_KEY = 'work_meeting.prompt_snooze_until';

    /** @var array<string, mixed> */
    public array $status = [];

    /** @var array<string, mixed> */
    public array $meeting = [];

    public int $snoozeUntil = 0;

    public int $meetingSnoozeUntil = 0;

    public ?string $panel = null;

    /**
     * Solo para quien tiene jornada; el resto no ve nada ni hace consultas.
     */
    public static function shouldRender(): bool
    {
        try {
            $user = Auth::user();

            return $user instanceof User && WorkdayConfig::enabled() && WorkdaySubjects::isSubject($user);
        } catch (Throwable) {
            return false;
        }
    }

    public function mount(): void
    {
        try {
            $this->panel = Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            $this->panel = null;
        }

        $this->refreshStatus();
    }

    public function refreshStatus(): void
    {
        $user = Auth::user();

        try {
            $this->status = $user instanceof User ? WorkBreaks::status($user) : [];
            $this->meeting = $user instanceof User ? WorkMeetings::status($user) : [];
        } catch (Throwable $exception) {
            report($exception);
            $this->status = [];
            $this->meeting = [];
        }

        $this->snoozeUntil = (int) session(self::SNOOZE_SESSION_KEY, 0);
        $this->meetingSnoozeUntil = (int) session(self::MEETING_SNOOZE_SESSION_KEY, 0);
    }

    public function startLunchAction(): Action
    {
        return Action::make('startLunch')
            ->label('Salir a almorzar')
            ->icon(Heroicon::OutlinedClock)
            ->color('info')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedClock)
            ->modalHeading('¿Sales a almorzar ahora?')
            ->modalDescription(fn (): string => 'Tienes '.WorkdayConfig::lunchMinutes().' minutos. El monitor te mostrará «En almuerzo» hasta que pulses «Volver del almuerzo». El tiempo de más se registra como exceso.'.(($this->meeting['open'] ?? false) ? ' Tu reunión en curso se dará por terminada.' : ''))
            ->modalSubmitActionLabel('Sí, salgo a almorzar')
            ->modalCancelActionLabel('Todavía no')
            ->action(function (): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                try {
                    $break = WorkBreaks::start($user, $this->panel);
                } catch (WorkBreakException $exception) {
                    Notification::make()->danger()->title('No se pudo iniciar el almuerzo')->body($exception->getMessage())->send();
                    $this->refreshStatus();

                    return;
                }

                session()->forget(self::SNOOZE_SESSION_KEY);
                $backAt = UserActivityClock::minuteOfDay($break->started_at) + WorkdayConfig::lunchMinutes();

                Notification::make()
                    ->success()
                    ->title('¡Buen provecho!')
                    ->body('Quedaste en almuerzo. Vuelve antes de las '.UserActivityClock::formatMinute($backAt).' y pulsa «Volver del almuerzo».')
                    ->send();

                $this->refreshStatus();
            });
    }

    public function endLunchAction(): Action
    {
        return Action::make('endLunch')
            ->label('Volver del almuerzo')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('success')
            ->action(fn () => $this->finishLunch(UserWorkBreak::END_MANUAL));
    }

    public function returnPromptAction(): Action
    {
        return Action::make('returnPrompt')
            ->modalIcon(Heroicon::OutlinedClock)
            ->modalIconColor('info')
            ->modalHeading('¿Ya volviste del almuerzo?')
            ->modalDescription(fn (): string => 'Estás usando el sistema y sigues marcado en almuerzo'.(isset($this->status['started_at']) ? ' desde las '.UserActivityClock::formatMinute(UserActivityClock::minuteOfDay(UserActivityClock::now()->setTimestamp((int) $this->status['started_at']))) : '').'. Si ya estás trabajando, confírmalo.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Sí, ya volví')
            ->modalCancelAction(false)
            ->extraModalFooterActions([
                Action::make('stillOnLunch')
                    ->label('No, sigo en almuerzo')
                    ->color('gray')
                    ->cancelParentActions()
                    ->action(fn () => $this->snoozePrompt()),
            ])
            ->action(fn () => $this->finishLunch(UserWorkBreak::END_PROMPT));
    }

    public function startMeetingAction(): Action
    {
        return Action::make('startMeeting')
            ->label('Entrar en reunión')
            ->icon(Heroicon::OutlinedUserGroup)
            ->color('primary')
            ->modalIcon(Heroicon::OutlinedUserGroup)
            ->modalIconColor('primary')
            ->modalHeading('¿Entras en una reunión?')
            ->modalDescription(fn (): string => 'Mientras estés en reunión no contará como inactividad, aunque cierres sesión. Al terminar, entra de nuevo y pulsa «Terminé la reunión».'.(($this->status['state'] ?? null) === 'on' ? ' Tu almuerzo en curso se dará por terminado.' : ''))
            ->modalWidth('md')
            ->modalSubmitActionLabel('Entrar en reunión')
            ->modalCancelActionLabel('Cancelar')
            ->form([
                TextInput::make('reason')
                    ->label('Motivo de la reunión')
                    ->placeholder('Por ejemplo: revisión de casos con la clínica')
                    ->helperText('Lo verá el administrador en el monitor de actividad.')
                    ->required()
                    ->minLength(WorkMeetings::REASON_MIN)
                    ->maxLength(WorkMeetings::REASON_MAX)
                    ->autofocus(),
            ])
            ->action(function (array $data): void {
                $user = Auth::user();

                if (! $user instanceof User) {
                    return;
                }

                try {
                    WorkMeetings::start($user, (string) ($data['reason'] ?? ''), $this->panel);
                } catch (WorkBreakException $exception) {
                    Notification::make()->danger()->title('No se pudo registrar la reunión')->body($exception->getMessage())->send();
                    $this->refreshStatus();

                    return;
                }

                session()->forget(self::MEETING_SNOOZE_SESSION_KEY);
                session()->forget(self::SNOOZE_SESSION_KEY);

                Notification::make()
                    ->success()
                    ->title('Quedaste en reunión')
                    ->body('Puedes cerrar sesión si lo necesitas. Al volver, pulsa «Terminé la reunión».')
                    ->send();

                $this->refreshStatus();
            });
    }

    public function endMeetingAction(): Action
    {
        return Action::make('endMeeting')
            ->label('Terminé la reunión')
            ->icon(Heroicon::OutlinedCheck)
            ->color('success')
            ->action(fn () => $this->finishMeeting(UserWorkMeeting::END_MANUAL));
    }

    public function meetingPromptAction(): Action
    {
        return Action::make('meetingPrompt')
            ->modalIcon(Heroicon::OutlinedUserGroup)
            ->modalIconColor('primary')
            ->modalHeading('¿Ya terminó tu reunión?')
            ->modalDescription(fn (): string => 'Sigues marcado en reunión'.(filled($this->meeting['reason'] ?? null) ? ' («'.$this->meeting['reason'].'»)' : '').'. Si ya terminó, confírmalo para que el monitor te vuelva a medir con normalidad.')
            ->modalWidth('md')
            ->modalSubmitActionLabel('Sí, ya terminó')
            ->modalCancelAction(false)
            ->extraModalFooterActions([
                Action::make('stillInMeeting')
                    ->label('No, sigo en reunión')
                    ->color('gray')
                    ->cancelParentActions()
                    ->action(fn () => $this->snoozeMeetingPrompt()),
            ])
            ->action(fn () => $this->finishMeeting(UserWorkMeeting::END_PROMPT));
    }

    public function snoozeMeetingPrompt(): void
    {
        $this->meetingSnoozeUntil = now()->addMinutes(WorkdayConfig::meetingPromptSnoozeMinutes())->getTimestamp();
        session()->put(self::MEETING_SNOOZE_SESSION_KEY, $this->meetingSnoozeUntil);
    }

    public function snoozePrompt(): void
    {
        $this->snoozeUntil = now()->addMinutes(WorkdayConfig::promptSnoozeMinutes())->getTimestamp();
        session()->put(self::SNOOZE_SESSION_KEY, $this->snoozeUntil);
    }

    public function render(): View
    {
        return view('livewire.work-break-toggle', [
            'graceSeconds' => WorkdayConfig::promptGraceMinutes() * 60,
        ]);
    }

    private function finishMeeting(string $reason): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $meeting = WorkMeetings::end($user, $reason);
        session()->forget(self::MEETING_SNOOZE_SESSION_KEY);
        $this->refreshStatus();

        if ($meeting === null) {
            return;
        }

        $minutes = max(0, (int) floor(($meeting->ended_at->getTimestamp() - $meeting->started_at->getTimestamp()) / 60));

        Notification::make()
            ->success()
            ->title('Reunión terminada')
            ->body('«'.$meeting->reason.'» · '.UserActivityClock::formatDuration($minutes).'. Hoy llevas '.UserActivityClock::formatDuration((int) ($this->meeting['today_minutes'] ?? 0)).' en reuniones.')
            ->send();
    }

    private function finishLunch(string $reason): void
    {
        $user = Auth::user();

        if (! $user instanceof User) {
            return;
        }

        $break = WorkBreaks::end($user, $reason);
        session()->forget(self::SNOOZE_SESSION_KEY);
        $this->refreshStatus();

        if ($break === null) {
            return;
        }

        $minutes = (int) ($this->status['minutes'] ?? 0);
        $excess = (int) ($this->status['excess'] ?? 0);

        Notification::make()
            ->success()
            ->title('¡De vuelta al trabajo!')
            ->body('Almorzaste '.UserActivityClock::formatDuration($minutes).($excess > 0 ? ': '.$excess.' min por encima de la hora.' : '.'))
            ->send();
    }
}
