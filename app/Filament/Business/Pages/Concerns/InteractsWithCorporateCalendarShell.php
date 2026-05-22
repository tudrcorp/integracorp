<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages\Concerns;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

trait InteractsWithCorporateCalendarShell
{
    public string $cursorMonth = '';

    public string $viewMode = 'month';

    public string $selectedWeekDate = '';

    public string $selectedDate = '';

    public function mountCorporateCalendarShell(): void
    {
        $this->cursorMonth = now()->startOfMonth()->toDateString();
        $this->selectedDate = now()->toDateString();
        $this->selectedWeekDate = now()->toDateString();
    }

    public function previousMonth(): void
    {
        $this->cursorMonth = $this->resolveCorporateCalendarCursor()->subMonth()->toDateString();
    }

    public function nextMonth(): void
    {
        $this->cursorMonth = $this->resolveCorporateCalendarCursor()->addMonth()->toDateString();
    }

    public function goToday(): void
    {
        $this->cursorMonth = now()->startOfMonth()->toDateString();
        $this->selectedDate = now()->toDateString();
        $this->selectedWeekDate = now()->toDateString();
    }

    public function getMonthLabelProperty(): string
    {
        return (string) $this->resolveCorporateCalendarCursor()->translatedFormat('F Y');
    }

    public function setWeekView(): void
    {
        $this->viewMode = 'week';
        $this->selectedWeekDate = now()->toDateString();
    }

    public function setMonthView(): void
    {
        $this->viewMode = 'month';
    }

    public function selectWeekDate(string $date): void
    {
        $targetDate = Carbon::parse($date);
        $startOfWeek = now()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = now()->endOfWeek(Carbon::SUNDAY);

        if ($targetDate->betweenIncluded($startOfWeek, $endOfWeek)) {
            $this->selectedWeekDate = $targetDate->toDateString();
        }
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
        $cursor = $this->resolveCorporateCalendarCursor();
        $start = $cursor->copy()->startOfMonth()->startOfWeek(Carbon::MONDAY);
        $end = $cursor->copy()->endOfMonth()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $day = $start->copy();

        while ($day->lessThanOrEqualTo($end)) {
            $isCurrentMonth = $day->isSameMonth($cursor);
            $isToday = $day->isToday();
            $isPastDate = $day->lt(now()->startOfDay());

            $days[] = [
                'date' => $day->toDateString(),
                'day_number' => (int) $day->format('j'),
                'is_current_month' => $isCurrentMonth,
                'is_today' => $isToday,
                'is_past_date' => $isPastDate,
                ...$this->resolveCorporateCalendarDayVisualsForDate($day, $isCurrentMonth),
            ];

            $day->addDay();
        }

        return $days;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCurrentWeekDaysProperty(): array
    {
        $baseDate = now();
        $startOfWeek = $baseDate->copy()->startOfWeek(Carbon::MONDAY);
        $endOfWeek = $baseDate->copy()->endOfWeek(Carbon::SUNDAY);

        $days = [];
        $cursor = $startOfWeek->copy();

        while ($cursor->lessThanOrEqualTo($endOfWeek)) {
            $dateKey = $cursor->toDateString();
            $days[] = [
                'date' => $dateKey,
                'day_label' => Str::upper($cursor->translatedFormat('D')),
                'day_number' => (int) $cursor->format('j'),
                'is_today' => $cursor->isToday(),
                'is_selected' => $dateKey === $this->selectedWeekDate,
                'activity_count' => 0,
                'social_platforms' => [],
                'social_badges' => [],
            ];

            $cursor->addDay();
        }

        return $days;
    }

    /**
     * @return Collection<int, mixed>
     */
    public function getWeekSelectedDayActivitiesProperty(): Collection
    {
        return collect();
    }

    public function corporateCalendarHeading(): string
    {
        return 'Agenda Corporativa';
    }

    public function calendarDayInteractionsEnabled(): bool
    {
        return true;
    }

    public function canManageSocialPublications(): bool
    {
        return false;
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveCorporateCalendarDayVisualsForDate(Carbon $day, bool $isCurrentMonth): array
    {
        return $this->buildCorporateCalendarDayVisuals($isCurrentMonth);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCorporateCalendarDayVisuals(bool $isCurrentMonth): array
    {
        if (! $isCurrentMonth) {
            return [
                'activity_count' => 0,
                'task_primary' => null,
                'task_secondary' => null,
                'avatars' => [],
                'progress_width' => 0,
                'progress_tone' => 'none',
                'has_indicator' => false,
                'social_platforms' => [],
                'social_badges' => [],
                'has_social_publications' => false,
            ];
        }

        return [
            'activity_count' => 0,
            'task_primary' => null,
            'task_secondary' => null,
            'avatars' => [],
            'progress_width' => 0,
            'progress_tone' => 'none',
            'has_indicator' => false,
            'social_platforms' => [],
            'social_badges' => [],
            'has_social_publications' => false,
        ];
    }

    private function resolveCorporateCalendarCursor(): Carbon
    {
        return Carbon::parse($this->cursorMonth)->startOfMonth();
    }
}
