<?php

declare(strict_types=1);

it('asigna created_by con el usuario de sesión al preparar la creación del ticket', function (): void {
    $path = dirname(__DIR__, 2).'/app/Filament/Concerns/PreparesHelpdeskColaboradorAssigneesOnCreate.php';
    $src = file_get_contents($path);

    expect($src)
        ->toContain("\$data['created_by'] = Auth::user()?->name;")
        ->toContain("\$data['created_by_user_id'] = Auth::id();")
        ->toContain("\$data['terms_accepted_at'] = now();")
        ->toContain("\$data['first_response_due_at']")
        ->and($src)->toContain("\$data['status'] ??= 'PENDIENTE POR INICIAR';");
});

it('dehydrata created_by aunque el campo esté oculto en create', function (): void {
    $path = dirname(__DIR__, 2).'/app/Support/HelpdeskFormSchema.php';
    $src = file_get_contents($path);

    expect($src)
        ->toContain("TextInput::make('created_by')")
        ->toContain("->hiddenOn('create')")
        ->toContain('->dehydratedWhenHidden()');
});
