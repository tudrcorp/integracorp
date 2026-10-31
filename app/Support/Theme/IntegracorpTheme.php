<?php

declare(strict_types=1);

namespace App\Support\Theme;

use App\Models\SystemThemeChange;
use App\Models\User;
use App\Support\Filament\UserNavigationAccess;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use InvalidArgumentException;
use Throwable;

/**
 * Tema de Integracorp: el original o el del manual de marca.
 *
 * El tema de marca es una hoja encima de los temas de cada panel, sin tocarlos:
 * volver al original es no cargarla. Aplica a los siete paneles internos y nunca
 * a un login. La vista previa vive en la sesión del SUPERADMIN: solo la ve él.
 *
 * Si la tabla no está o la base falla, rige el original: la apariencia nunca
 * debe tumbar una pantalla.
 */
final class IntegracorpTheme
{
    public const ORIGINAL = 'original';

    public const BRAND = 'integracorp';

    public const PANELS = ['business', 'administration', 'marketing', 'operations', 'telemedicina', 'metrics', 'projects'];

    private const CACHE_KEY = 'integracorp-theme:active';

    private const PREVIEW_SESSION_KEY = 'integracorp_theme_preview';

    private const STYLESHEET = 'brand/integracorp/tema.css';

    public const FONTS_STYLESHEET = 'brand/integracorp/fonts.css';

    public static function isValid(string $theme): bool
    {
        return in_array($theme, [self::ORIGINAL, self::BRAND], true);
    }

    public static function label(string $theme): string
    {
        return $theme === self::BRAND ? 'Tema Integracorp' : 'Tema original';
    }

    public static function active(): string
    {
        try {
            return Cache::rememberForever(self::CACHE_KEY, static fn (): string => (string) (SystemThemeChange::query()->latest('id')->value('theme') ?? self::ORIGINAL));
        } catch (Throwable) {
            return self::ORIGINAL;
        }
    }

    public static function canManage(?User $user): bool
    {
        return $user instanceof User && UserNavigationAccess::isSuperAdmin($user);
    }

    public static function apply(string $theme, ?User $actor): void
    {
        if (! self::isValid($theme)) {
            throw new InvalidArgumentException('Tema desconocido.');
        }

        $previous = self::active();

        if ($previous === $theme) {
            return;
        }

        DB::transaction(static function () use ($theme, $previous, $actor): void {
            SystemThemeChange::query()->create([
                'theme' => $theme,
                'previous_theme' => $previous,
                'changed_by_user_id' => $actor?->id,
                'changed_by_name' => $actor?->name,
                'ip_address' => request()?->ip(),
            ]);
        });

        Cache::forget(self::CACHE_KEY);
        self::clearPreview();

        Log::info('SISTEMA: Tema de Integracorp cambiado.', [
            'audit' => 'system_theme',
            'theme' => $theme,
            'previous_theme' => $previous,
            'actor_user_id' => $actor?->id,
            'actor_name' => $actor?->name,
        ]);
    }

    public static function preview(): ?string
    {
        if (! self::canManage(self::currentUser())) {
            return null;
        }

        $theme = (string) session(self::PREVIEW_SESSION_KEY, '');

        return self::isValid($theme) ? $theme : null;
    }

    public static function startPreview(string $theme): void
    {
        if (self::isValid($theme)) {
            session()->put(self::PREVIEW_SESSION_KEY, $theme);
        }
    }

    public static function clearPreview(): void
    {
        session()->forget(self::PREVIEW_SESSION_KEY);
    }

    public static function current(): string
    {
        return self::preview() ?? self::active();
    }

    public static function stylesheet(): HtmlString
    {
        if (! self::appliesHere() || self::current() !== self::BRAND) {
            return new HtmlString('');
        }

        return new HtmlString('<link rel="stylesheet" href="'.e(asset(self::STYLESHEET)).'?v='.(int) @filemtime(public_path(self::STYLESHEET)).'">');
    }

    public static function previewBanner(): HtmlString
    {
        $preview = self::preview();

        if ($preview === null || ! self::appliesHere()) {
            return new HtmlString('');
        }

        $url = route('filament.'.Filament::getCurrentPanel()?->getId().'.pages.tema');

        return new HtmlString(
            '<div role="status" style="position:fixed;left:50%;bottom:.75rem;transform:translateX(-50%);z-index:60;display:flex;gap:.75rem;align-items:center;max-width:calc(100vw - 2rem);padding:.5rem 1rem;border-radius:999px;background:#0b1a28;color:#efe9df;font-size:.8125rem;box-shadow:0 6px 18px rgba(0,0,0,.3)">'
            .'<span>Vista previa: '.e(self::label($preview)).'. Solo tú la ves.</span>'
            .'<a href="'.e($url).'" style="color:#d29d45;font-weight:600;text-decoration:underline">Ir a Tema</a>'
            .'</div>'
        );
    }

    private static function appliesHere(): bool
    {
        if (request()->routeIs('filament.*.auth.*')) {
            return false;
        }

        return in_array(Filament::getCurrentPanel()?->getId(), self::PANELS, true);
    }

    private static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
