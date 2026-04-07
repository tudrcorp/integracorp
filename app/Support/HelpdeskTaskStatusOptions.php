<?php

namespace App\Support;

use App\Models\HelpDesk;

final class HelpdeskTaskStatusOptions
{
    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        return [
            'PENDIENTE POR INICIAR' => 'Pendiente por iniciar',
            'EN PROCESO' => 'En proceso',
            'TERMINADO' => 'Terminado',
            'CANCELADO' => 'Cancelado',
        ];
    }

    /**
     * Opciones para el select de estado: en alta nunca incluye TERMINADO/CANCELADO.
     * En edición, TERMINADO y CANCELADO solo aparecen para quien creó la tarea;
     * si otro usuario edita y el estado ya es terminal, se mantiene visible ese valor.
     *
     * @return array<string, string>
     */
    public static function forSelect(?HelpDesk $record, ?string $currentUserName): array
    {
        $all = self::all();
        $nonTerminal = [
            'PENDIENTE POR INICIAR' => $all['PENDIENTE POR INICIAR'],
            'EN PROCESO' => $all['EN PROCESO'],
        ];

        if ($record === null) {
            return $nonTerminal;
        }

        $isCreator = $currentUserName !== null && $record->created_by === $currentUserName;

        if ($isCreator) {
            return $all;
        }

        $options = $nonTerminal;
        $status = $record->status;
        if (in_array($status, ['TERMINADO', 'CANCELADO'], true) && isset($all[$status])) {
            $options[$status] = $all[$status];
        }

        return $options;
    }

    /**
     * Evita que un usuario no creador guarde TERMINADO/CANCELADO salvo que ya estuviera en ese estado y no cambie a otro terminal distinto.
     */
    public static function sanitizeStatusForSave(HelpDesk $record, string $newStatus, ?string $currentUserName): string
    {
        $allowed = array_keys(self::forSelect($record, $currentUserName));

        return in_array($newStatus, $allowed, true) ? $newStatus : $record->status;
    }
}
