<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AccountManagers\Pages\ListAccountManagers;
use App\Models\AccountManager;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('business');
});

it('muestra los account managers en la tabla', function (): void {
    $user = User::factory()->create([
        'email' => 'negocios-am-test@tudrencasa.com',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
    ]);

    $accountManager = AccountManager::query()->create([
        'user_id' => 9_001,
        'full_name' => 'Ejecutivo Prueba UI',
        'phone' => '+584121234567',
        'address' => 'Av. Principal, Caracas',
        'email' => 'ejecutivo-prueba-'.uniqid('', true).'@example.com',
    ]);

    Livewire::actingAs($user)
        ->test(ListAccountManagers::class)
        ->assertCanSeeTableRecords([$accountManager]);
});
