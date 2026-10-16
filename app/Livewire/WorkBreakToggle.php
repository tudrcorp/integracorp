<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\User;
use App\Models\UserWorkBreak;
use App\Support\UserActivity\UserActivityClock;
use App\Support\UserActivity\Workday\WorkBreakException;
use App\Support\UserActivity\Workday\WorkBreaks;
use App\Support\UserActivity\Workday\WorkdayConfig;
use App\Support\UserActivity\Workday\WorkdaySubjects;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

/**
 * Botón de almuerzo en la barra superior de los paneles internos.
 *
 * «Salir a almorzar» → «En almuerzo 00:42 · Volver» (en naranja al pasar de la
 * hora). Si el colaborador vuelve a usar el sistema sin marcar el regreso, se le
 * pregunta «¿Ya volviste del almuerzo?»; «No, sigo» calla la pregunta unos minutos
 * en todas sus pestañas.
 */
class WorkBreakToggle extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    private const SNOOZE_SESSION_KEY = 'work_break.prompt_snooze_until';

    /** @var array<string, mixed> */
    public array $status = [];

    public int $snoozeUntil = 0;

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
        } catch (Throwable $exception) {
            report($exception);
            $this->status = [];
        }

        $this->snoozeUntil = (int) session(self::SNOOZE_SESSION_KEY, 0);
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
            ->modalDescription('Tienes '.WorkdayConfig::lunchMinutes().' minutos. El monitor te mostrará «En almuerzo» hasta que pulses «Volver del almuerzo». El tiempo de más se registra como exceso.')
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
