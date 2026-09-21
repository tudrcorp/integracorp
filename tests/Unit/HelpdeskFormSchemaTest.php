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
        ->toContain('disableOnEditUnlessRevertedResubmit')
        ->toContain('ticket_resubmit_intro')
        ->toContain("Tabs::make('helpdeskFormTabs')")
        ->toContain("Tab::make('Tipo de ticket')")
        ->toContain("Tab::make('Compromiso de atención')")
        ->toContain("Radio::make('ticket_type')")
        ->toContain('fi-helpdesk-ios-section')
        ->toContain('persistTabInQueryString')
        ->toContain('Creador del ticket')
        ->toContain("TextInput::make('created_by')")
        ->toContain('->dehydratedWhenHidden()')
        ->toContain("Select::make('status')")
        ->toContain("->hiddenOn('create')")
        ->toContain("Select::make('cc_colaboradores')")
        ->toContain('scrumProductOwnerInbox')
        ->toContain('Flujo según asignado');
});

it('el CC de helpdesk lista todos los colaboradores sin exigir usuario de sistema', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $src = file_get_contents($path);
    $methodStart = strpos($src, 'function rrhhColaboradorOptionsForHelpdeskMultiselect()');
    $nextMethod = strpos($src, 'function rrhhColaboradorOptionsForHelpdeskWorkGroups()', $methodStart ?: 0);
    $methodSrc = $methodStart === false || $nextMethod === false
        ? ''
        : substr($src, $methodStart, $nextMethod - $methodStart);

    expect($methodSrc)
        ->not->toContain("whereNotNull('user_id')")
        ->and($src)->toContain("Select::make('cc_colaboradores')")
        ->and($src)->toContain('->options(self::rrhhColaboradorOptionsForHelpdeskMultiselect())');
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

it('todos los paneles helpdesk envían el ticket al Product Owner Scrum cuando aplica', function (string $panel): void {
    $path = dirname(__DIR__, 2)."/app/Filament/{$panel}/Resources/Helpdesks/Schemas/HelpdeskForm.php";

    expect(file_get_contents($path))
        ->toContain('HelpdeskFormSchema::configure($schema, assigneesRequired: true, scrumProductOwnerInbox: true)');
})->with(['Business', 'Administration', 'Marketing', 'Operations']);

it('marca el campo de asignados como requerido en el schema compartido', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';

    expect(file_get_contents($path))
        ->toContain("Select::make('rrhhColaboradores')")
        ->toContain('->required($assigneesRequired)')
        ->toContain('->multiple(! $scrumProductOwnerInbox)')
        ->toContain('Seleccione uno o más colaboradores responsables de resolver el caso.');
});
