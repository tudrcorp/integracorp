<?php

declare(strict_types=1);

use App\Models\User;
use App\Support\TdgCalendarEditAuthorization;

it('autoriza a editar guardia oficinas solo a andreina reyes y katty domingues', function (): void {
    $andreina = new User([
        'name' => 'Andreina Reyes',
        'email' => 'otro-correo@tudrencasa.com',
    ]);
    $andreinaConAcento = new User([
        'name' => 'Andreína del C. Reyes Rodríguez',
        'email' => 'personal@example.com',
    ]);
    $katty = new User([
        'name' => 'Katy Dominguez',
        'email' => 'personal@example.com',
    ]);
    $kattyPorCorreo = new User([
        'name' => 'Katia Domingues',
        'email' => 'KDOMINGUES@tudrencasa.com',
    ]);
    $christopher = new User([
        'name' => 'Christopher Reyes',
        'email' => 'chreyes@tudrencasa.com',
    ]);
    $andreinaRamos = new User([
        'name' => 'Andreina Ramos',
        'email' => 'andreinanramosr@gmail.com',
    ]);

    expect(TdgCalendarEditAuthorization::allows($andreina))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allows($andreinaConAcento))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allows($katty))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allows($kattyPorCorreo))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allows($christopher))->toBeFalse()
        ->and(TdgCalendarEditAuthorization::allows($andreinaRamos))->toBeFalse()
        ->and(TdgCalendarEditAuthorization::allows(null))->toBeFalse();
});

it('autoriza a un superadministrador a editar guardia oficinas', function (): void {
    $superadmin = new User([
        'name' => 'Anthony Jesus Aular Guzman',
        'email' => 'aaular@tudrencasa.com',
        'departament' => ['SUPERADMIN', 'NEGOCIOS'],
    ]);
    $analista = new User([
        'name' => 'Anthony Jesus Aular Guzman',
        'email' => 'aaular@tudrencasa.com',
        'departament' => ['NEGOCIOS'],
    ]);

    expect(TdgCalendarEditAuthorization::allows($superadmin))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allows($analista))->toBeFalse()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards($superadmin))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards($analista))->toBeFalse();
});

it('autoriza a editar guardias operaciones solo a becky acosta', function (): void {
    $becky = new User([
        'name' => 'Becky Acosta',
        'email' => 'otro-correo@tudrencasa.com',
    ]);
    $beckyPorFicha = new User([
        'name' => 'Becky K, Acosta Quintero',
        'email' => 'personal@example.com',
    ]);
    $beckyPorCorreo = new User([
        'name' => 'Otra persona',
        'email' => 'BACOSTA@tudrencasa.com',
    ]);
    $nelson = new User([
        'name' => 'Nelson Acosta',
        'email' => 'nelsonacosta@test.com',
    ]);
    $fabiola = new User([
        'name' => 'Maria Fabiola Acosta',
        'email' => 'mfapservices@gmail.com',
    ]);
    $andreina = new User([
        'name' => 'Andreina Reyes',
        'email' => 'areyes@tudrencasa.com',
    ]);

    expect(TdgCalendarEditAuthorization::allowsOperationsGuards($becky))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards($beckyPorFicha))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards($beckyPorCorreo))->toBeTrue()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards($nelson))->toBeFalse()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards($fabiola))->toBeFalse()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards($andreina))->toBeFalse()
        ->and(TdgCalendarEditAuthorization::allowsOperationsGuards(null))->toBeFalse()
        ->and(TdgCalendarEditAuthorization::allows($becky))->toBeFalse();
});

it('protege en el servidor cada acción que modifica la guardia de oficinas', function (): void {
    $trait = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/Concerns/InteractsWithTdgHybridCalendar.php');
    $page = file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/CalendariosTdg.php');
    $modal = file_get_contents(dirname(__DIR__, 2).'/resources/views/filament/business/pages/partials/calendarios-tdg-day-modal.blade.php');

    expect($page)->toContain("return 'Guardia Oficinas';")
        ->toContain("return ['offices'];");
    expect(file_get_contents(dirname(__DIR__, 2).'/app/Filament/Business/Pages/GuardiasOperaciones.php'))
        ->toContain("return 'Guardias Operaciones';")
        ->toContain("return ['guards'];");
    expect($modal)
        ->toContain('corporateCalendarHeading()')
        ->toContain('canEditCurrentTdgCalendar()')
        ->toContain("in_array('guards', \$tdgCalendarWorkspaces, true)")
        ->toContain("in_array('offices', \$tdgCalendarWorkspaces, true)")
        ->toContain('Centro Lido, Farmadoc y Home Office.')
        ->toContain('Quien queda aquí trabaja desde casa y no asiste a ninguna oficina ese día.')
        ->toContain('tdgCalendarReadOnlyNotice()');
    expect($trait)
        ->toContain('allowsOperationsGuards')
        ->toContain('Solo Becky Acosta y los superadministradores pueden modificar Guardias Operaciones.')
        ->toContain('Solo Andreína Reyes, Katty Domingues y los superadministradores pueden editarla.')
        ->toContain('Solo Andreína Reyes, Katty Domingues y los superadministradores pueden modificar Guardia Oficinas.')
        ->toContain('Solo Becky Acosta y los superadministradores pueden editarla.');

    $guards = [
        'offices' => [
            'assignOfficeCollaborator',
            'removeOfficeCollaborator',
            'clearOfficeCollaborators',
            'replicateOfficeAssignmentsToSelectedDays',
            'notifyMonthOfficeAttendance',
        ],
        'guards' => [
            'assignGuardCollaborator',
            'clearGuardCollaborator',
            'replicateGuardAssignmentsToSelectedDays',
        ],
        'departments' => [
            'toggleDepartment',
            'assignDepartmentCollaborator',
            'removeDepartmentCollaborator',
            'clearDepartmentCollaborators',
            'replicateDepartmentAssignmentsToSelectedDays',
        ],
    ];

    foreach ($guards as $workspace => $methods) {
        foreach ($methods as $method) {
            expect($trait)->toMatch('/function '.$method.'\([^)]*\)\s*:\s*void\s*\{\s*if \(\$this->denyTdgWorkspaceEdit\(\''.$workspace.'\'\)\) \{\s*return;\s*\}/');
        }
    }

    expect($trait)->toMatch('/function saveDayAssignments\(\)\s*:\s*void\s*\{\s*if \(\$this->denyCurrentTdgCalendarEdit\(\)\) \{\s*return;\s*\}/');
    expect($trait)->toContain('function notifyMonthGuardAttendance(): void')
        ->toContain('denyGuardAttendanceNotify()')
        ->toContain('UserNavigationAccess::isSuperAdmin');
});
