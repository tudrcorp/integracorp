<?php

declare(strict_types=1);

use App\Models\RrhhColaborador;
use Illuminate\Support\Carbon;

it('calcula años cumplidos desde birth_date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-05-14 12:00:00', 'UTC'));

    expect(RrhhColaborador::completedYearsFromBirthDate('1990-05-15'))->toBe(35)
        ->and(RrhhColaborador::completedYearsFromBirthDate('1990-05-14'))->toBe(36)
        ->and(RrhhColaborador::completedYearsFromBirthDate(null))->toBeNull()
        ->and(RrhhColaborador::completedYearsFromBirthDate(''))->toBeNull();

    Carbon::setTestNow();
});
