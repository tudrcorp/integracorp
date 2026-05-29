<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Filament\Projects\Pages\Kanban;
use Livewire\Livewire;

final class ProjectsPanelHelpdeskTicketsTicker
{
    public static function shouldDisplay(): bool
    {
        return ! Livewire::current() instanceof Kanban;
    }
}
