<?php

declare(strict_types=1);

namespace App\Support\Collections;

use App\Models\Collection;
use App\Models\CollectionObservation;
use App\Models\User;
use Illuminate\Support\Collection as SupportCollection;
use InvalidArgumentException;

/**
 * Bitácora de observaciones de cobranza de una afiliación.
 *
 * La nota se ata al **código de la afiliación**, no a la cuota: en «Cobranza Por
 * Mes» la fila es la próxima cuota pendiente y cambia al pagarse, y el analista
 * necesita seguir viendo lo que se gestionó antes. De la cuota se copian id,
 * vencimiento y monto para saber a qué se refería cada nota.
 */
final class CollectionObservationLog
{
    public static function record(Collection $collection, string $observation, User $user): CollectionObservation
    {
        $observation = trim($observation);
        $affiliationCode = trim((string) $collection->affiliation_code);

        if ($observation === '') {
            throw new InvalidArgumentException('Escriba la observación antes de guardarla.');
        }

        if (mb_strlen($observation) > CollectionObservation::MAX_LENGTH) {
            throw new InvalidArgumentException('La observación no puede superar '.CollectionObservation::MAX_LENGTH.' caracteres.');
        }

        if ($affiliationCode === '') {
            throw new InvalidArgumentException('Esta cuota no tiene código de afiliación: no se puede asociar la observación.');
        }

        return CollectionObservation::query()->create([
            'affiliation_code' => $affiliationCode,
            'affiliation_type' => CollectionReceivableReport::isCorporate($collection)
                ? CollectionObservation::TYPE_CORPORATE
                : CollectionObservation::TYPE_INDIVIDUAL,
            'collection_id' => $collection->id,
            'due_date' => CollectionReceivableReport::dueDate($collection)?->toDateString(),
            'amount' => CollectionReceivableReport::installmentAmount($collection),
            'observation' => $observation,
            'created_by' => $user->id,
            'created_by_name' => $user->name,
        ]);
    }

    /**
     * Notas de la afiliación, de la más reciente a la más antigua.
     *
     * @return SupportCollection<int, CollectionObservation>
     */
    public static function forAffiliation(?string $affiliationCode): SupportCollection
    {
        $affiliationCode = trim((string) $affiliationCode);

        if ($affiliationCode === '') {
            return collect();
        }

        return CollectionObservation::query()
            ->where('affiliation_code', $affiliationCode)
            ->latest('created_at')
            ->latest('id')
            ->get();
    }
}
