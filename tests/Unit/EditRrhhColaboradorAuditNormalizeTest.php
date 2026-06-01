<?php

declare(strict_types=1);

use App\Filament\Administration\Resources\RrhhColaboradors\Pages\EditRrhhColaborador;
use Illuminate\Support\Carbon;

it('normaliza valores de auditoría sin convertir arrays a string', function (): void {
    $page = new EditRrhhColaborador;
    $method = new ReflectionMethod($page, 'normalizeAuditValue');
    $method->setAccessible(true);

    expect($method->invoke($page, ['doc-1.pdf', 'doc-2.pdf']))
        ->toBe(['doc-1.pdf', 'doc-2.pdf']);

    expect($method->invoke($page, Carbon::parse('1990-05-15')))
        ->toBe('1990-05-15');

    expect($method->invoke($page, '  avatar.png  '))
        ->toBe('avatar.png');
});

it('EditRrhhColaborador no compara cambios con cast string directo', function (): void {
    $contents = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Administration/Resources/RrhhColaboradors/Pages/EditRrhhColaborador.php');

    expect($contents)
        ->toContain('normalizeAuditValue')
        ->not->toContain('(string) $oldValue === (string) $newValue');
});
