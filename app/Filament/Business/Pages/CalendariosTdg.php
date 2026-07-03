<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use App\Filament\Business\Pages\Concerns\InteractsWithCorporateCalendarShell;
use App\Filament\Business\Pages\Concerns\InteractsWithTdgHybridCalendar;
use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class CalendariosTdg extends Page
{
    use AuthorizesDepartmentNavigation;
    use InteractsWithCorporateCalendarShell;
    use InteractsWithTdgHybridCalendar {
        InteractsWithTdgHybridCalendar::calendarDayInteractionsEnabled insteadof InteractsWithCorporateCalendarShell;
        InteractsWithTdgHybridCalendar::getCalendarDaysProperty insteadof InteractsWithCorporateCalendarShell;
        InteractsWithTdgHybridCalendar::getCurrentWeekDaysProperty insteadof InteractsWithCorporateCalendarShell;
    }

    protected static ?string $navigationLabel = 'Calendarios TDG';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.business.pages.calendarios-tdg';

    public function mount(): void
    {
        $this->mountCorporateCalendarShell();
        $this->mountTdgHybridCalendar();
    }

    public function corporateCalendarHeading(): string
    {
        return 'Calendarios TDG';
    }
}
