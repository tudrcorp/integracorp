<?php

declare(strict_types=1);

it('define las tablas del sistema de gestión de proyectos según el documento técnico', function (): void {
    $path = dirname(__DIR__, 2).'/database/migrations/2026_05_27_221909_create_project_management_system_tables.php';
    $contents = file_get_contents($path);

    expect($contents)->toBeString()
        ->and($contents)->toContain("Schema::create('departments'")
        ->and($contents)->toContain("Schema::create('groups'")
        ->and($contents)->toContain("Schema::create('projects'")
        ->and($contents)->toContain("Schema::create('subprojects'")
        ->and($contents)->toContain("Schema::create('project_assignments'")
        ->and($contents)->toContain("Schema::create('activities'")
        ->and($contents)->toContain("Schema::create('notes_logs'")
        ->and($contents)->toContain("Schema::create('documents'")
        ->and($contents)->toContain('numericMorphs(\'assignable\')')
        ->and($contents)->toContain('numericMorphs(\'executor\')')
        ->and($contents)->toContain('numericMorphs(\'notable\')')
        ->and($contents)->toContain('numericMorphs(\'documentable\')')
        ->and($contents)->toContain('project_assignable_unique')
        ->and($contents)->toContain('cascadeOnDelete()')
        ->and($contents)->toContain('unsignedBigInteger(\'file_size\')')
        ->and($contents)->toContain("if (! Schema::hasTable('notes_logs'))")
        ->and($contents)->toContain("if (! Schema::hasTable('documents'))");
});
