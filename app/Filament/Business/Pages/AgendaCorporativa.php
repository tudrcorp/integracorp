<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use BackedEnum;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class AgendaCorporativa extends Page
{
    protected static string|UnitEnum|null $navigationGroup = 'SOLICITUDES';

    protected static ?string $navigationLabel = 'Agenda Corporativa';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?int $navigationSort = 2;

    protected string $view = 'filament.business.pages.agenda-corporativa';

    public string $cursorMonth = '';

    public function mount(): void
    {
        $this->cursorMonth = now()->startOfMonth()->toDateString();
    }

    public function previousMonth(): void
    {
        $this->cursorMonth = $this->resolveCursor()->subMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->cursorMonth = $this->resolveCursor()->addMonth()->toDateString();
    }

    public function goToday(): void
    {
        $this->cursorMonth = now()->startOfMonth()->toDateString();
    }

    public function getMonthLabelProperty(): string
    {
        return (string) $this->resolveCursor()->translatedFormat('F Y');
    }

    /**
     * @return array<int, string>
     */
    public function getWeekdaysProperty(): array
    {
        return ['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarDaysProperty(): array
    {
        $cursor = $this->resolveCursor();
        $start = $cursor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $day = $start->copy();

        while ($day->lessThanOrEqualTo($end)) {
            $isCurrentMonth = $day->isSameMonth($cursor);
            $isToday = $day->isToday();

            $days[] = [
                'date' => $day->toDateString(),
                'day_number' => (int) $day->format('j'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $isToday,
                ...$this->buildDayVisuals($day, $isCurrentMonth),
            ];

            $day->addDay();
        }

        return $days;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildDayVisuals(Carbon $day, bool $isCurrentMonth): array
    {
        if (! $isCurrentMonth) {
            return [
                'task_primary' => null,
                'task_secondary' => null,
                'progress_width' => 0,
                'progress_tone' => 'none',
                'has_indicator' => false,
            ];
        }

        $dayNumber = (int) $day->format('j');
        $hasTask = $dayNumber % 2 === 0 || $dayNumber % 7 === 1;
        $hasSecondaryTask = $dayNumber % 3 === 0;

        $progressWidth = match (true) {
            $dayNumber % 5 === 0 => 90,
            $dayNumber % 4 === 0 => 72,
            $dayNumber % 3 === 0 => 58,
            $dayNumber % 2 === 0 => 42,
            default => 26,
        };

        $progressTone = match (true) {
            $dayNumber % 6 === 0 => 'amber',
            $dayNumber % 5 === 0 => 'neutral',
            default => 'cyan',
        };

        return [
            'task_primary' => $hasTask ? 'Tareas corporativas' : null,
            'task_secondary' => $hasSecondaryTask ? 'Seguimiento interno' : null,
            'progress_width' => $progressWidth,
            'progress_tone' => $progressTone,
            'has_indicator' => $dayNumber % 7 === 0 || $dayNumber % 9 === 0,
        ];
    }

    private function resolveCursor(): Carbon
    {
        return Carbon::parse($this->cursorMonth)->startOfMonth();
    }
}
