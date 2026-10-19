<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use BackedEnum;
use Filament\Support\Icons\Heroicon;

class GuardiasOperaciones extends CalendariosTdg
{
    protected static ?string $navigationLabel = 'Guardias Operaciones';

    protected static ?string $title = 'Guardias Operaciones';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 4;

    /**
     * @return list<'guards'>
     */
    public function tdgCalendarWorkspaces(): array
    {
        return ['guards'];
    }

    public function corporateCalendarHeading(): string
    {
        return 'Guardias Operaciones';
    }
}
