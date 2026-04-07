<?php

declare(strict_types=1);

use App\Filament\Business\Resources\AccountManagers\Pages\EditAccountManager;
use App\Models\AccountManager;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('business');
});

it('carga la página de edición con el título del ejecutivo', function (): void {
    $user = User::factory()->create([
        'email' => 'negocios-edit-am@tudrencasa.com',
        'departament' => ['NEGOCIOS'],
        'status' => 'ACTIVO',
    ]);

    $accountManager = AccountManager::query()->create([
        'user_id' => 9_002,
        'full_name' => 'Ejecutivo Edición UI',
        'phone' => '+584129998887',
        'address' => 'Centro, Caracas',
        'email' => 'ejecutivo-edit-'.uniqid('', true).'@example.com',
    ]);

    Livewire::actingAs($user)
        ->test(EditAccountManager::class, ['record' => $accountManager->getRouteKey()])
        ->assertOk()
        ->assertSee('Productividad · Ejecutivo Edición UI');
});
