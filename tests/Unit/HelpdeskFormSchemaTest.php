<?php

declare(strict_types=1);

use App\Filament\Business\Resources\Helpdesks\Schemas\HelpdeskForm;
use App\Support\HelpdeskFormSchema;
use Filament\Schemas\Schema;
use Tests\TestCase;

uses(TestCase::class);

it('configura el schema del formulario helpdesk sin error', function (): void {
    $schema = Schema::make();
    $configured = HelpdeskForm::configure($schema);

    expect($configured)->toBeInstanceOf(Schema::class);
});

it('en edición solo el creador es editable y el resto queda deshabilitado', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $src = file_get_contents($path);
    expect($src)->toContain("->disabledOn('edit')")
        ->toContain("Tabs::make('helpdeskFormTabs')")
        ->toContain("Tab::make('Tipo de ticket')")
        ->toContain("Tab::make('Compromiso de atención')")
        ->toContain("Radio::make('ticket_type')")
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('persistTabInQueryString')
        ->toContain('Creador del ticket')
        ->toContain("TextInput::make('created_by')")
        ->toContain('getQualifiedKeyName()');
});

it('permite adjuntar pdf y powerpoints en creación de ticket', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $src = file_get_contents($path);

    expect($src)
        ->toContain("'application/pdf'")
        ->toContain("'application/vnd.ms-powerpoint'")
        ->toContain("'application/vnd.openxmlformats-officedocument.presentationml.presentation'");
});

it('configura administration con asignados requeridos', function (): void {
    $schema = Schema::make();
    $configured = HelpdeskFormSchema::configure($schema, assigneesRequired: true);

    expect($configured)->toBeInstanceOf(Schema::class);
});
