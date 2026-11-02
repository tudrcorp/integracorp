<?php

declare(strict_types=1);

uses(Tests\TestCase::class);

use App\Filament\Business\Resources\ProspectAgents\Pages\EditProspectAgent;
use App\Filament\Business\Resources\ProspectAgents\Schemas\ProspectAgentForm;
use App\Models\ProspectAgent;
use App\Models\ProspectAgentContact;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\View;
use Livewire\Livewire;

/**
 * @return array<string, mixed>
 */
function prospectContactRow(array $overrides = []): array
{
    return array_merge([
        'department' => null,
        'position' => null,
        'name' => null,
        'email' => null,
        'phone' => null,
        'local_phone' => null,
        'extensions' => null,
    ], $overrides);
}

function prospectWithContactsForFicha(): ProspectAgent
{
    $prospect = new ProspectAgent([
        'name' => 'Agencia Prueba Contactos',
        'phone_1' => '04120000000',
        'email' => 'agencia@ejemplo.com',
    ]);
    $prospect->id = 999999;

    $prospect->setRelation('state', null);
    $prospect->setRelation('city', null);
    $prospect->setRelation('country', null);
    $prospect->setRelation('prospect_agent_observations', collect());
    $prospect->setRelation('prospect_agent_tasks', collect());
    $prospect->setRelation('prospectAgentContacts', collect([
        new ProspectAgentContact([
            'department' => 'Comercial',
            'position' => 'Gerente comercial',
            'name' => 'Carlos Mendoza',
            'email' => 'carlos.mendoza@empresa-con-dominio-largo.com',
            'phone' => '04125678909',
            'local_phone' => '02129876543',
            'extensions' => '201, 204',
        ]),
        new ProspectAgentContact([
            'department' => 'Recursos Humanos',
            'name' => 'Ana Pérez',
        ]),
    ]));

    return $prospect;
}

it('declara los campos nuevos del contacto como asignables', function (): void {
    expect((new ProspectAgentContact)->getFillable())
        ->toContain('department')
        ->toContain('local_phone')
        ->toContain('extensions')
        ->toContain('name')
        ->toContain('position')
        ->toContain('phone')
        ->toContain('email');
});

it('exige el nombre cuando se llena cualquier otro dato del contacto', function (string $field, string $value): void {
    expect(ProspectAgentForm::contactHasData(prospectContactRow([$field => $value])))->toBeTrue();
})->with([
    'departamento' => ['department', 'Comercial'],
    'cargo' => ['position', 'Gerente'],
    'correo' => ['email', 'a@b.com'],
    'celular' => ['phone', '04125678909'],
    'teléfono local' => ['local_phone', '02129876543'],
    'extensiones' => ['extensions', '201'],
]);

it('no exige el nombre en una fila vacía o con solo espacios en blanco', function (): void {
    expect(ProspectAgentForm::contactHasData(prospectContactRow()))->toBeFalse()
        ->and(ProspectAgentForm::contactHasData(prospectContactRow(['department' => '   '])))->toBeFalse();
});

it('descarta filas vacías pero conserva la que solo trae nombre', function (): void {
    expect(ProspectAgentForm::contactHasData(prospectContactRow(), includeName: true))->toBeFalse()
        ->and(ProspectAgentForm::contactHasData(prospectContactRow(['name' => 'Ana']), includeName: true))->toBeTrue();
});

it('rotula cada contacto con nombre y departamento', function (): void {
    expect(ProspectAgentForm::contactItemLabel(['name' => 'Ana Pérez', 'department' => 'RRHH']))->toBe('Ana Pérez · RRHH')
        ->and(ProspectAgentForm::contactItemLabel(['name' => 'Ana Pérez']))->toBe('Ana Pérez')
        ->and(ProspectAgentForm::contactItemLabel(['department' => 'RRHH']))->toBe('Nuevo contacto');
});

it('permite cargar N contactos sin tope en el formulario', function (): void {
    $source = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentForm.php'));

    expect($source)
        ->not->toContain('->maxItems(')
        ->toContain("->label('Nombre y apellido')")
        ->toContain("->label('Teléfono celular')")
        ->toContain("->label('Teléfono local')")
        ->toContain("->label('Extensión(es)')");
});

it('valida las extensiones como números separados por coma', function (string $value, bool $valid): void {
    expect((bool) preg_match('/^[0-9]+(\s*[,\/]\s*[0-9]+)*$/', $value))->toBe($valid);
})->with([
    'una' => ['201', true],
    'varias con coma' => ['201, 204', true],
    'con barra' => ['201/204', true],
    'letras' => ['ext 201', false],
    'coma colgante' => ['201,', false],
]);

it('muestra las siete columnas en el infolist en el orden pedido', function (): void {
    $source = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentInfolist.php'));

    $order = [
        "TableColumn::make('Departamento')",
        "TableColumn::make('Cargo')",
        "TableColumn::make('Nombre y apellido')",
        "TableColumn::make('Correo electrónico')",
        "TableColumn::make('Teléfono celular')",
        "TableColumn::make('Teléfono local')",
        "TableColumn::make('Extensión(es)')",
    ];

    $positions = array_map(fn (string $needle): int|false => strpos($source, $needle), $order);

    expect($positions)->each->not->toBeFalse();
    expect($positions)->toBe(collect($positions)->sort()->values()->all());
});

it('imprime los contactos completos en la ficha PDF', function (): void {
    $html = View::make('documents.prospect-agent-ficha', [
        'prospect' => prospectWithContactsForFicha(),
        'logoDataUri' => '',
        'generatedAt' => now(),
    ])->render();

    expect($html)
        ->toContain('Departamento')
        ->toContain('Teléfono local')
        ->toContain('Extensión(es)')
        ->toContain('Comercial')
        ->toContain('02129876543')
        ->toContain('201, 204')
        ->toContain('Recursos Humanos')
        ->toContain('Contactos secundarios');

    $binary = Pdf::loadHTML($html)->setPaper('a4', 'portrait')->output();

    expect($binary)->toStartWith('%PDF');
});

it('muestra los contactos en su propia pestaña Contactos Secundarios, justo después de Contacto', function (): void {
    $source = file_get_contents(app_path('Filament/Business/Resources/ProspectAgents/Schemas/ProspectAgentInfolist.php'));

    $contactTab = strpos($source, "Tab::make('Contacto')");
    $secondaryTab = strpos($source, "Tab::make('Contactos Secundarios')");
    $locationTab = strpos($source, "Tab::make('Ubicación')");
    $repeatable = strpos($source, "RepeatableEntry::make('prospectAgentContacts')");

    expect($contactTab)->not->toBeFalse()
        ->and($secondaryTab)->toBeGreaterThan($contactTab)
        ->and($locationTab)->toBeGreaterThan($secondaryTab)
        ->and($repeatable)->toBeGreaterThan($secondaryTab)
        ->and($repeatable)->toBeLessThan($locationTab);
});

it('al editar muestra plegados los contactos guardados y abierto el recién agregado', function (): void {
    DB::beginTransaction();

    try {
        Filament::setCurrentPanel('business');

        $analyst = User::query()
            ->where('email', 'like', '%@tudrencasa.com')
            ->where('status', 'ACTIVO')
            ->get()
            ->first(fn (User $user): bool => in_array('SUPERADMIN', (array) ($user->departament ?? []), true));

        $prospect = ProspectAgent::query()->has('prospectAgentContacts')->first();

        if ($analyst === null || $prospect === null) {
            $this->markTestSkipped('No hay un analista SUPERADMIN o un prospecto con contactos en la base.');
        }

        $this->actingAs($analyst);

        $saved = $prospect->prospectAgentContacts()->count();

        $collapsedItems = fn (string $html, string $state): int => preg_match_all('/isCollapsed:\s+'.$state.'\s/', $html);

        $page = Livewire::test(EditProspectAgent::class, ['record' => $prospect->getKey()]);

        $expandedBefore = $collapsedItems($page->html(), 'false');

        expect($collapsedItems($page->html(), 'true'))->toBe($saved);

        $page->call('mountAction', 'add', [], ['schemaComponent' => 'form.prospectAgentContacts'])
            ->call('callMountedAction');

        expect($collapsedItems($page->html(), 'true'))->toBe($saved)
            ->and($collapsedItems($page->html(), 'false'))->toBe($expandedBefore + 1);
    } finally {
        DB::rollBack();
    }
});
