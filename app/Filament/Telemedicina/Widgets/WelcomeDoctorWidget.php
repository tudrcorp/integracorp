<?php

namespace App\Filament\Telemedicina\Widgets;

use App\Models\TelemedicineDoctor;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class WelcomeDoctorWidget extends Widget
{
    protected string $view = 'filament.telemedicina.widgets.welcome-doctor-widget';

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
        $doctorId = Auth::user()?->doctor_id;

        if ($doctorId === null) {
            return 'Panel de Telemedicina';
        }

        $doctor = TelemedicineDoctor::query()->find($doctorId);

        if ($doctor === null) {
            return 'Médico de Telemedicina';
        }

        $specialty = trim((string) ($doctor->specialty ?? ''));

        return $specialty !== '' ? $specialty : 'Médico de Telemedicina';
    }
}
