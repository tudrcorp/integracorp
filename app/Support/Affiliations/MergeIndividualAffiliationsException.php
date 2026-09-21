<?php

declare(strict_types=1);

namespace App\Support\Affiliations;

use RuntimeException;

final class MergeIndividualAffiliationsException extends RuntimeException
{
    /**
     * @param  list<string>  $blockers
     */
    public static function fromBlockers(array $blockers): self
    {
        $unique = array_values(array_unique(array_filter($blockers)));

        return new self(
            $unique === []
                ? 'No se puede unificar el grupo familiar.'
                : implode("\n", $unique)
        );
    }
}
