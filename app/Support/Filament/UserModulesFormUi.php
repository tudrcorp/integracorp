<?php

declare(strict_types=1);

namespace App\Support\Filament;

use App\Models\Rol;
use Illuminate\Support\HtmlString;

final class UserModulesFormUi
{
    public static function stylesView(): string
    {
        return 'filament.business.users.partials.modules-form-styles';
    }

    /**
     * @return array<string, string>
     */
    public static function moduleOptions(): array
    {
        $options = [];

        foreach (Rol::query()->orderBy('name')->pluck('name') as $module) {
            if (! is_string($module) || trim($module) === '') {
                continue;
            }

            $options[$module] = self::optionLabel($module);
        }

        return $options;
    }

    public static function modulesIntroHtml(): HtmlString
    {
        return new HtmlString(
            '<div class="user-modules-intro rounded-lg border border-sky-200 bg-sky-50 px-4 py-3 dark:border-sky-500/20 dark:bg-sky-950/30">'
            .'<p class="text-sm font-semibold text-slate-900 dark:text-slate-100">¿Qué son los módulos?</p>'
            .'<p class="mt-1 text-sm leading-relaxed text-slate-600 dark:text-slate-300">'
            .'Cada módulo habilita un panel de INTEGRACORP (Negocios, Administración, Operaciones, etc.). '
            .'Después de elegirlos aquí, define las pantallas exactas en la pestaña <strong>Permisos</strong>.'
            .'</p>'
            .'</div>'
        );
    }

    public static function selectionSummaryHtml(mixed $departments): HtmlString
    {
        $selected = is_array($departments)
            ? array_values(array_filter($departments, fn (mixed $item): bool => is_string($item) && trim($item) !== ''))
            : [];

        $count = count($selected);

        if ($count === 0) {
            $message = 'Ningún módulo seleccionado. El usuario no podrá ingresar a paneles internos.';
            $class = 'user-modules-summary user-modules-summary--empty';
        } elseif ($count === 1) {
            $message = '1 módulo seleccionado: '.UserPermissionFormUi::moduleDisplayLabel($selected[0]).'.';
            $class = 'user-modules-summary user-modules-summary--active';
        } else {
            $labels = array_map(
                fn (string $module): string => UserPermissionFormUi::moduleDisplayLabel($module),
                $selected,
            );
            $message = $count.' módulos seleccionados: '.implode(' · ', $labels).'.';
            $class = 'user-modules-summary user-modules-summary--active';
        }

        return new HtmlString(
            '<div class="'.$class.' rounded-lg border px-4 py-3 text-sm">'.$message.'</div>'
        );
    }

    public static function permissionsHintHtml(): HtmlString
    {
        return new HtmlString(
            '<p class="user-modules-hint text-sm text-slate-500 dark:text-slate-400">'
            .'Tip: guarda los módulos y continúa en la pestaña <strong>Permisos</strong> para restringir el menú de cada panel.'
            .'</p>'
        );
    }

    private static function optionLabel(string $module): string
    {
        $title = UserPermissionFormUi::moduleDisplayLabel($module);
        $subtitle = UserPermissionFormUi::moduleMenuSubtitle($module);

        return "{$title} — {$subtitle}";
    }
}
