<?php

declare(strict_types=1);

use App\Filament\Marketing\Resources\MassNotifications\Schemas\MassNotificationFolderForm;

it('expone un esquema de formulario para crear carpeta', function (): void {
    $components = MassNotificationFolderForm::createComponents();

    expect($components)->not->toBeEmpty()
        ->and($components[0])->toBeInstanceOf(\Filament\Schemas\Components\Section::class);
});
