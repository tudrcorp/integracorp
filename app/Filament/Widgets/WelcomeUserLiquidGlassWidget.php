<?php

namespace App\Filament\Widgets;

use App\Models\RrhhColaborador;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class WelcomeUserLiquidGlassWidget extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.welcome-user-liquid-glass';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -3;

    /**
     * @return array{
     *     user: Authenticatable|null,
     *     name: string,
     *     avatar: string|null,
     *     greeting: string,
     *     date: string,
     *     role: string
     * }
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();
        $hour = (int) Carbon::now()->format('H');

        $greeting = match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        return [
            'user' => $user,
            'name' => $user !== null ? Filament::getUserName($user) : '',
            'avatar' => $user !== null ? Filament::getUserAvatarUrl($user) : null,
            'greeting' => $greeting,
            'date' => ucfirst(Carbon::now()->locale('es')->translatedFormat('l, d \d\e F \d\e Y')),
            'role' => $this->resolveRoleLabel(),
        ];
    }

    private function resolveRoleLabel(): string
    {
        $user = Auth::user();
        $panelContext = $this->panelContext();

        if ($user === null) {
            return $panelContext['fallback'];
        }

        $colaborador = RrhhColaborador::query()
            ->with('cargo')
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        $cargo = trim((string) ($colaborador?->cargo?->description ?? ''));

        if ($cargo !== '') {
            return $cargo;
        }

        $departaments = is_array($user->departament) ? $user->departament : [];

        if (in_array('SUPERADMIN', $departaments, true)) {
            return 'Superadmin';
        }

        if (in_array($panelContext['department'], $departaments, true)) {
            return $panelContext['label'];
        }

        return $panelContext['fallback'];
    }

    /**
     * @return array{department: string, label: string, fallback: string}
     */
    private function panelContext(): array
    {
        return match (Filament::getCurrentPanel()?->getId()) {
            'business' => [
                'department' => 'NEGOCIOS',
                'label' => 'Negocios',
                'fallback' => 'Panel de Negocios',
            ],
            'administration' => [
                'department' => 'ADMINISTRACION',
                'label' => 'Administración',
                'fallback' => 'Panel de Administración',
            ],
            'marketing' => [
                'department' => 'MARKETING',
                'label' => 'Marketing',
                'fallback' => 'Panel de Marketing',
            ],
            'projects' => [
                'department' => 'PROYECTOS',
                'label' => 'Proyectos',
                'fallback' => 'Panel de Proyectos',
            ],
            'operations' => [
                'department' => 'OPERACIONES',
                'label' => 'Operaciones',
                'fallback' => 'Panel de Operaciones',
            ],
            default => [
                'department' => '',
                'label' => 'Usuario',
                'fallback' => 'Panel interno',
            ],
        };
    }
}
