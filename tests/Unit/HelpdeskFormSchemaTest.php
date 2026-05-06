<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Helpdesks\Schemas\HelpdeskForm;
use Filament\Schemas\Schema;

it('configura el schema del formulario helpdesk sin error', function (): void {
    $schema = Schema::make();
    $configured = HelpdeskForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('en edición solo el creador es editable y el resto queda deshabilitado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Schemas/HelpdeskForm.php';
    $src = file_get_contents($path);
    expect($src)->toContain("->disabledOn('edit')")
        ->toContain('Creador del ticket')
        ->toContain("TextInput::make('created_by')")
        ->toContain('getQualifiedKeyName()');
});

it('permite adjuntar pdf y powerpoints en creación de ticket', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Business/Resources/Helpdesks/Schemas/HelpdeskForm.php';
    $src = file_get_contents($path);

    expect($src)
        ->toContain("'application/pdf'")
        ->toContain("'application/vnd.ms-powerpoint'")
        ->toContain("'application/vnd.openxmlformats-officedocument.presentationml.presentation'");
});
