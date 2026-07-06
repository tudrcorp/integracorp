<?php

declare(strict_types=1);

namespace App\Support\Filament;

use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

use function Filament\Support\generate_icon_html;

final class UserRoleFormUi
{
    private const GROUP_SHELL_CLASS = 'user-role-group-shell overflow-hidden rounded-lg border shadow-[0_10px_32px_-14px_rgba(15,23,42,0.12)] ring-1 ring-slate-200/55 dark:border-white/10 dark:ring-white/[0.04] dark:shadow-[0_10px_32px_-14px_rgba(0,0,0,0.5)]';

    private const TOGGLE_CARD_CLASS = 'user-role-toggle-card h-full';

    /**
     * @var array<string, string>
     */
    private const ROLE_DESCRIPTIONS = [
        'is_agent' => 'Perfil vinculado a un agente comercial.',
        'is_subagent' => 'Operación como subagente en la red.',
        'is_agency' => 'Acceso con perfil de agencia.',
        'is_accountManagers' => 'Gestión de cartera y cuentas clave.',
        'is_superAdmin' => 'Acceso total a todos los módulos.',
        'is_business_admin' => 'Administración del módulo de negocios.',
    ];

    public static function stylesView(): string
    {
        return 'filament.business.users.partials.role-form-styles';
    }

    public static function groupShellClass(string $groupLabel): string
    {
        return self::GROUP_SHELL_CLASS.' user-role-group--'.self::groupSlug($groupLabel);
    }

    public static function togglesGridClass(): string
    {
        return 'user-role-toggles-grid';
    }

    public static function toggleCardClass(string $groupLabel, string $field): string
    {
        return self::TOGGLE_CARD_CLASS
            .' user-role-toggle-card--'.self::groupSlug($groupLabel)
            .' user-role-toggle-card--'.self::fieldSlug($field);
    }

    /**
     * @param  array{field: string, label: string, icon: Heroicon}  $role
     */
    public static function toggleLabelHtml(array $role): HtmlString
    {
        $label = e($role['label']);
        $description = e(self::ROLE_DESCRIPTIONS[$role['field']] ?? '');
        $icon = generate_icon_html($role['icon'], size: IconSize::Large)?->toHtml() ?? '';

        return new HtmlString(
            '<span class="user-role-toggle-copy">'
            .'<span class="user-role-toggle-icon" aria-hidden="true">'.$icon.'</span>'
            .'<span class="user-role-toggle-text">'
            .'<span class="user-role-toggle-title">'.$label.'</span>'
            .($description !== '' ? '<span class="user-role-toggle-description">'.$description.'</span>' : '')
            .'</span>'
            .'</span>'
        );
    }

    private static function groupSlug(string $groupLabel): string
    {
        return match ($groupLabel) {
            'Perfiles comerciales' => 'comerciales',
            'Perfiles administrativos' => 'administrativos',
            default => strtolower(str_replace([' ', '-'], '_', $groupLabel)),
        };
    }

    private static function fieldSlug(string $field): string
    {
        return str_replace('_', '-', $field);
    }
}
