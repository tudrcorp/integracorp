<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

final class ProjectManagementActivityAppearance
{
    public const DEFAULT_COLOR = '#8B5CF6';

    /**
     * @return array<string, string>
     */
    public static function colorPresets(): array
    {
        return ProjectManagementProjectAppearance::colorPresets();
    }
}
