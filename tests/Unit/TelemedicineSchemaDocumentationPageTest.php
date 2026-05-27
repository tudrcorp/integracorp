<?php

declare(strict_types=1);

use App\Support\Telemedicine\TelemedicineSchemaDocumentation;
use Illuminate\Support\Facades\URL;

uses(Tests\TestCase::class);

test('la documentacion publica del esquema de telemedicina responde correctamente', function () {
    $temporarySignedUrl = URL::temporarySignedRoute(
        'telemedicine.schema.documentation',
        now()->addHours(12),
    );

    $response = $this->get($temporarySignedUrl);

    $response->assertSuccessful();
    $response->assertSee('Esquema de datos · Telemedicina', false);
    $response->assertSee('logoNewPdf.png', false);
    $response->assertSee('telemedicine_cases', false);
    $response->assertSee('Diagrama entidad-relación', false);
    $response->assertSee('erDiagram', false);
    $response->assertSee('telemedicine_patients', false);
});

test('la documentacion publica rechaza url sin firma', function () {
    $this->get(route('telemedicine.schema.documentation'))
        ->assertForbidden();
});

test('el soporte de documentacion expone nueve tablas y relaciones', function () {
    expect(TelemedicineSchemaDocumentation::tables())->toHaveCount(9)
        ->and(TelemedicineSchemaDocumentation::relationships())->not->toBeEmpty()
        ->and(TelemedicineSchemaDocumentation::mermaidErDiagram())->toContain('erDiagram')
        ->and(TelemedicineSchemaDocumentation::mermaidErDiagram())->toContain('telemedicine_consultation_patients');
});
