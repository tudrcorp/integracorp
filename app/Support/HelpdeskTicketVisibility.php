<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\HelpDesk;
use App\Models\RrhhColaborador;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

final class HelpdeskTicketVisibility
{
    public static function canViewGlobalQueue(?Authenticatable $user = null): bool
    {
        if (func_num_args() === 0) {
            $user = self::currentUserOrNull();
        }

        if ($user === null) {
            return false;
        }

        return HelpdeskUserAccess::hasSystemsDepartment($user)
            || HelpdeskUserAccess::hasSuperAdminDepartment($user)
            || HelpdeskBusinessScrumRoles::isProductOwnerUser($user);
    }

    /**
     * @param  Builder<HelpDesk>  $query
     * @return Builder<HelpDesk>
     */
    public static function constrainVisible(Builder $query, ?Authenticatable $user = null): Builder
    {
        if (func_num_args() < 2) {
            $user = self::currentUserOrNull();
        }

        if (! $user instanceof User) {
            return $query->whereRaw('0 = 1');
        }

        if (HelpdeskBusinessScrumRoles::isProductOwnerUser($user)) {
            return $query;
        }

        if (self::canViewGlobalQueue($user)) {
            return self::hideProductOwnerInboxUnlessMine($query, $user);
        }

        return self::constrainToMine($query, $user);
    }

    /**
     * Tickets creados por el usuario o asignados a su colaborador RRHH.
     *
     * @param  Builder<HelpDesk>  $query
     * @return Builder<HelpDesk>
     */
    public static function constrainToMine(Builder $query, User $user): Builder
    {
        $colaborador = RrhhColaborador::query()
            ->where('user_id', $user->getAuthIdentifier())
            ->first();

        return $query->where(function (Builder $scoped) use ($user, $colaborador): void {
            if ($user->getAuthIdentifier() !== null) {
                $scoped->where('created_by_user_id', $user->getAuthIdentifier());
            }

            $scoped->orWhere(function (Builder $byName) use ($user): void {
                $byName->whereNull('created_by_user_id')
                    ->where('created_by', $user->name);
            });

            if ($colaborador !== null) {
                $scoped->orWhereHas(
                    'rrhhColaboradores',
                    fn (Builder $sub): Builder => $sub->where('rrhh_colaboradors.id', $colaborador->id)
                );
            }
        });
    }

    /**
     * @param  Builder<HelpDesk>  $query
     * @return Builder<HelpDesk>
     */
    public static function constrainUnassigned(Builder $query): Builder
    {
        return $query->whereDoesntHave('rrhhColaboradores');
    }

    /**
     * El backlog del Product Owner (solo Becky) no entra a la cola global de Sistemas
     * hasta que se asigne a Anthony Aular y/o Gustavo Camacho.
     *
     * @param  Builder<HelpDesk>  $query
     * @return Builder<HelpDesk>
     */
    public static function hideProductOwnerInboxUnlessMine(Builder $query, User $user): Builder
    {
        $productOwnerId = HelpdeskBusinessScrumRoles::productOwnerId();
        $developerIds = HelpdeskBusinessScrumRoles::developerIds();

        if ($productOwnerId === null) {
            return $query;
        }

        return $query->where(function (Builder $visible) use ($user, $productOwnerId, $developerIds): void {
            $visible->where(function (Builder $mine) use ($user): void {
                self::constrainToMine($mine, $user);
            })->orWhere(function (Builder $notInbox) use ($productOwnerId, $developerIds): void {
                $notInbox
                    ->whereDoesntHave(
                        'rrhhColaboradores',
                        fn (Builder $sub): Builder => $sub->where('rrhh_colaboradors.id', $productOwnerId)
                    )
                    ->orWhereHas(
                        'rrhhColaboradores',
                        function (Builder $sub) use ($developerIds): void {
                            if ($developerIds === []) {
                                $sub->whereRaw('0 = 1');

                                return;
                            }

                            $sub->whereIn('rrhh_colaboradors.id', $developerIds);
                        }
                    );
            });
        });
    }

    private static function currentUserOrNull(): ?Authenticatable
    {
        try {
            $user = auth()->user();
        } catch (Throwable) {
            return null;
        }

        return $user instanceof Authenticatable ? $user : null;
    }
}
