<?php

declare(strict_types=1);

namespace App\Filament\Telemedicina\Pages;

use App\Filament\Concerns\InteractsWithTelemedicineCaseBitacora;
use App\Support\Operations\TelemedicineCaseBitacora;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class BitacoraDeCaso extends Page
{
    use InteractsWithTelemedicineCaseBitacora;

    protected static ?string $navigationLabel = 'Bitácora de Caso';

    protected static ?string $title = 'Bitácora de Caso';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'GESTIÓN TELEMÉDICA';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'bitacora-de-caso';

    protected string $view = 'filament.operations.pages.bitacora-de-caso';

    protected function bitacoraScope(): string
    {
        return TelemedicineCaseBitacora::SCOPE_TELEMEDICINA;
    }
}
