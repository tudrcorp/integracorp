<?php

declare(strict_types=1);

namespace App\Support\Affiliations\PersonIdentity;

use App\Enums\PersonIdentityFindingKind;

/**
 * Decide si dos registros son la misma persona. Dos reglas, las dos necesitan el nombre:
 *
 * 1. Misma cédula comparable y el mismo nombre (mismas palabras, en cualquier orden). Si un nombre
 *    solo contiene al otro, además la fecha de nacimiento no puede contradecirlo: así no se confunde
 *    a una madre «MARIA PEREZ» con su hija «MARIA PEREZ GOMEZ» registrada con la cédula de la madre.
 * 2. Distinta cédula (o ninguna) pero el mismo nombre y la misma fecha de nacimiento: el caso de la
 *    cédula de menor escrita de dos formas, o una cédula escolar frente a la del representante.
 *
 * La cédula sola no basta: hay hermanos y menores registrados con la cédula del representante.
 */
final class PersonIdentityMatcher
{
    public static function match(PersonIdentityRecord $a, PersonIdentityRecord $b): ?PersonIdentityFindingKind
    {
        if ($a->sameRecordAs($b)) {
            return null;
        }

        $relation = PersonIdentityNormalizer::nameRelation($a->nameKey, $b->nameKey);

        if ($relation === null) {
            return null;
        }

        $sameBirth = $a->birthDateKey !== null && $a->birthDateKey === $b->birthDateKey;
        $birthContradicts = $a->birthDateKey !== null && $b->birthDateKey !== null && ! $sameBirth;

        if ($a->documentKey !== null && $a->documentKey === $b->documentKey) {
            if ($relation === 'equal' || ! $birthContradicts) {
                return PersonIdentityFindingKind::SameDocument;
            }

            return null;
        }

        if ($relation === 'equal' && $sameBirth) {
            return PersonIdentityFindingKind::SameNameAndBirthDate;
        }

        return null;
    }

    /**
     * Por qué coinciden, en palabras del analista.
     */
    public static function reason(PersonIdentityFindingKind $kind): string
    {
        return match ($kind) {
            PersonIdentityFindingKind::SameDocument => 'la misma cédula (sin letras, espacios ni guiones) y el mismo nombre',
            PersonIdentityFindingKind::SameNameAndBirthDate => 'el mismo nombre y la misma fecha de nacimiento, aunque la cédula esté escrita distinto',
            PersonIdentityFindingKind::RepeatedName => 'el nombre cargado dos veces',
        };
    }
}
