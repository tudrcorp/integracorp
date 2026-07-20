<?php

namespace App\Filament\Operations\Widgets;

use App\Filament\Widgets\WelcomeUserLiquidGlassWidget;

/**
 * Alias de compatibilidad para cachés / referencias al widget anterior.
 */
class WelcomeOperationsWidget extends WelcomeUserLiquidGlassWidget
{
    protected static bool $isDiscovered = false;
}
