<?php

declare(strict_types=1);

namespace App\Filament\Shared\Theme\Pages;

use App\Models\SystemThemeChange;
use App\Models\User;
use App\Support\Theme\IntegracorpTheme;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

/**
 * Elegir el tema de Integracorp, solo SUPERADMIN. Cada panel la expone con una
 * subclase vacía, para tenerla a mano mientras se revisa la vista previa.
 */
abstract class ThemeSettingsPage extends Page
{
    protected static ?string $navigationLabel = 'Tema';

    protected static ?string $title = 'Tema';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?int $navigationSort = 99;

    protected static ?string $slug = 'tema';

    protected string $view = 'filament.shared.theme.settings';

    public static function canAccess(): bool
    {
        return IntegracorpTheme::canManage(self::currentUser());
    }

    public static function shouldRegisterNavigation(): bool
    {
        return self::canAccess();
    }

    public function previewTheme(string $theme): void
    {
        abort_unless(self::canAccess() && IntegracorpTheme::isValid($theme), 403);

        IntegracorpTheme::startPreview($theme);
        $this->reloadPage();
    }

    public function stopPreview(): void
    {
        abort_unless(self::canAccess(), 403);

        IntegracorpTheme::clearPreview();
        $this->reloadPage();
    }

    public function applyThemeAction(): Action
    {
        return Action::make('applyTheme')
            ->label('Aplicar a todos')
            ->color('danger')
            ->visible(fn (): bool => self::canAccess())
            ->requiresConfirmation()
            ->modalHeading(fn (array $arguments): string => '¿Aplicar el '.IntegracorpTheme::label((string) ($arguments['theme'] ?? '')).' a todos?')
            ->modalDescription('Cambia la apariencia de Negocios, Administración, Marketing, Operaciones, Telemedicina, Métricas y Proyectos para todos los usuarios. Lo verán al recargar o cambiar de página. No toca ningún login y puedes volver atrás cuando quieras.')
            ->modalSubmitActionLabel('Sí, aplicar')
            ->action(function (array $arguments): void {
                abort_unless(self::canAccess(), 403);

                $theme = (string) ($arguments['theme'] ?? '');

                if (! IntegracorpTheme::isValid($theme)) {
                    Notification::make()->title('Ese tema no existe')->danger()->send();

                    return;
                }

                try {
                    IntegracorpTheme::apply($theme, self::currentUser());
                } catch (\Throwable $exception) {
                    report($exception);
                    Notification::make()->title('No se pudo cambiar el tema')->body('Inténtalo de nuevo. La apariencia actual no cambió.')->danger()->persistent()->send();

                    return;
                }

                Notification::make()->title(IntegracorpTheme::label($theme).' aplicado a todos')->success()->send();
                $this->reloadPage();
            });
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        try {
            $history = SystemThemeChange::query()->latest('id')->limit(10)->get();
        } catch (\Throwable) {
            $history = collect();
        }

        return [
            'active' => IntegracorpTheme::active(),
            'preview' => IntegracorpTheme::preview(),
            'history' => $history,
            'fontsUrl' => asset(IntegracorpTheme::FONTS_STYLESHEET),
            'originalPanels' => [
                ['Negocios', '#4c566a'], ['Administración', '#f59e0b'], ['Marketing', '#17335e'], ['Operaciones', '#2d89ca'],
                ['Telemedicina', '#005ca9'], ['Métricas', '#14b8a6'], ['Proyectos', '#f59e0b'],
            ],
            'brandColors' => [
                ['Navy', '#0F2233', 'Fondos y tarjetas en modo oscuro; texto en modo claro · 60 %'],
                ['Navy profundo', '#0B1A28', 'Fondo de página en modo oscuro · 25 %'],
                ['Marfil', '#EFE9DF', 'Fondo en modo claro y texto sobre navy · 10 %'],
                ['Ocre', '#D29D45', 'Acción principal, enlaces y selección · 5 %'],
            ],
        ];
    }

    /**
     * Recarga completa: la hoja del tema se decide al dibujar el `<head>`.
     */
    private function reloadPage(): void
    {
        $this->redirect(static::getUrl());
    }

    private static function currentUser(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }
}
