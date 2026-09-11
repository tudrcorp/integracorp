<?php

declare(strict_types=1);

namespace App\Filament\Operations\Pages;

use App\Filament\Concerns\AuthorizesDepartmentNavigation;
use App\Filament\Concerns\InteractsWithTelemedicineCaseBitacora;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class BitacoraDeCaso extends Page
{
    use AuthorizesDepartmentNavigation;
    use InteractsWithTelemedicineCaseBitacora;

    protected static ?string $navigationLabel = 'Bitácora de Caso';

    protected static ?string $title = 'Bitácora de Caso';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static string|UnitEnum|null $navigationGroup = 'TELEMEDICINA';

    protected static ?int $navigationSort = 4;

    protected static ?string $slug = 'bitacora-de-caso';

    protected string $view = 'filament.operations.pages.bitacora-de-caso';
}
