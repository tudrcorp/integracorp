<?php

declare(strict_types=1);

namespace App\Filament\Business\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class GeneradorQrPersonalizado extends Page
{
    protected static ?string $navigationLabel = 'Generador QR personalizado';

    protected static ?string $title = 'Generador QR personalizado';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQrCode;

    protected static string|UnitEnum|null $navigationGroup = 'CONFIGURACIÓN';

    protected static ?int $navigationSort = 99;

    protected string $view = 'filament.business.pages.generador-qr-personalizado';
}
