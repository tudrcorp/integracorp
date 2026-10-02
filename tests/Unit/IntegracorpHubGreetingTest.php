<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\Integracorp\IntegracorpHubGreeting;
use Illuminate\Support\Carbon;

it('saluda según la hora del día', function (): void {
    $morning = Carbon::parse('2026-03-10 09:00:00');
    $afternoon = Carbon::parse('2026-03-10 15:00:00');
    $night = Carbon::parse('2026-03-10 21:00:00');

    expect(IntegracorpHubGreeting::salutation($morning))->toBe('Buenos días')
        ->and(IntegracorpHubGreeting::salutation($afternoon))->toBe('Buenas tardes')
        ->and(IntegracorpHubGreeting::salutation($night))->toBe('Buenas noches');
});

it('usa el primer nombre del usuario', function (): void {
    $user = new User([
        'name' => 'Gustavo Camacho',
        'email' => 'gcamacho@tudrencasa.com',
    ]);

    expect(IntegracorpHubGreeting::firstName($user))->toBe('Gustavo');
});

it('arma el mensaje de módulos según la cantidad', function (): void {
    expect(IntegracorpHubGreeting::modulesLead(0))->toContain('Cuando tengas acceso')
        ->and(IntegracorpHubGreeting::modulesLead(1))->toContain('espacio de trabajo')
        ->and(IntegracorpHubGreeting::modulesLead(8))->toContain('Elige el espacio de trabajo');
});

it('arma la etiqueta de módulos habilitados para el encabezado', function (): void {
    expect(IntegracorpHubGreeting::modulesCountLabel(0))->toBe('Sin módulos habilitados')
        ->and(IntegracorpHubGreeting::modulesCountLabel(1))->toBe('1 módulo habilitado')
        ->and(IntegracorpHubGreeting::modulesCountLabel(3))->toBe('3 módulos habilitados');
});
