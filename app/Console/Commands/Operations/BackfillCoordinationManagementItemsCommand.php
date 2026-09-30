<?php

declare(strict_types=1);

namespace App\Console\Commands\Operations;

use App\Filament\Operations\Resources\TelemedicinePatients\Actions\RegisterTpaRetailServicesAction;
use App\Models\OperationCoordinationService;
use App\Models\User;
use App\Support\Telemedicine\TelemedicineCaseTdgReassignmentCoordination;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Command\Command as CommandAlias;
use Throwable;

/**
 * Siembra el ítem gestionable de las coordinaciones AMD reasignadas a TDG y de
 * las TPA/RETAIL standalone que se crearon antes de que el alta lo hiciera.
 *
 * El cuadro de control ya no lo siembra al pintarse, así que una coordinación
 * vieja sin su ítem se vería «Sin ítems» hasta pasar por aquí. Es idempotente:
 * los `ensure*` no crean nada si el ítem ya existe.
 */
class BackfillCoordinationManagementItemsCommand extends Command
{
    protected $signature = 'operations:backfill-coordination-management-items
                            {--apply : Crea los ítems faltantes; sin esta opción sólo reporta}
                            {--user= : ID del usuario que figura como asignador (obligatorio con --apply)}';

    protected $description = 'Reporta o siembra el ítem gestionable de coordinaciones AMD reasignadas y TPA/RETAIL standalone que no lo tienen.';

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        if ($apply && ! $this->actAsRequestedUser()) {
            return CommandAlias::FAILURE;
        }

        $missing = $this->coordinationsMissingManagementItem();

        if ($missing === []) {
            $this->info('Todas las coordinaciones AMD reasignadas y TPA/RETAIL standalone tienen su ítem gestionable.');

            return CommandAlias::SUCCESS;
        }

        $this->table(
            ['Coordinación', 'Referencia', 'Tipo'],
            array_map(fn (array $row): array => [$row['record']->id, $row['record']->reference_number, $row['kind']], $missing),
        );

        if (! $apply) {
            $this->warn(count($missing).' coordinación(es) sin ítem gestionable. Ejecute con --apply --user=<id> para crearlos.');

            return CommandAlias::SUCCESS;
        }

        $created = 0;
        $failed = 0;

        foreach ($missing as $row) {
            try {
                DB::transaction(function () use ($row): void {
                    TelemedicineCaseTdgReassignmentCoordination::ensureAmdManagementItem($row['record']);
                    RegisterTpaRetailServicesAction::ensureStandaloneManagementItem($row['record']);
                });
                $created++;
            } catch (Throwable $exception) {
                $failed++;
                $this->error("Coordinación {$row['record']->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Ítems sembrados: {$created}. Con error: {$failed}.");

        return $failed === 0 ? CommandAlias::SUCCESS : CommandAlias::FAILURE;
    }

    /**
     * @return list<array{record: OperationCoordinationService, kind: string}>
     */
    private function coordinationsMissingManagementItem(): array
    {
        $missing = [];

        OperationCoordinationService::query()
            ->where(function ($query): void {
                $query->whereRaw('UPPER(TRIM(servicie)) = ?', ['TPA/RETAIL'])
                    ->orWhere('observations', 'like', TelemedicineCaseTdgReassignmentCoordination::OBSERVATION_PREFIX.'%');
            })
            ->orderBy('id')
            ->chunkById(200, function ($records) use (&$missing): void {
                foreach ($records as $record) {
                    if (
                        RegisterTpaRetailServicesAction::isTpaRetailStandaloneCoordination($record)
                        && ! $record->telemedicinePatientSpecialties()->where('specialty', trim((string) $record->specific_service))->exists()
                    ) {
                        $missing[] = ['record' => $record, 'kind' => 'TPA/RETAIL standalone'];

                        continue;
                    }

                    if (
                        TelemedicineCaseTdgReassignmentCoordination::isAmdReassignmentCoordination($record)
                        && ! $record->telemedicinePatientSpecialties()->exists()
                    ) {
                        $missing[] = ['record' => $record, 'kind' => 'AMD reasignada a TDG'];
                    }
                }
            });

        return $missing;
    }

    private function actAsRequestedUser(): bool
    {
        $userId = (int) $this->option('user');
        $user = $userId > 0 ? User::query()->find($userId) : null;

        if (! $user instanceof User) {
            $this->error('Con --apply debe indicar --user=<id> de un usuario existente: queda registrado como asignador del ítem.');

            return false;
        }

        Auth::setUser($user);

        return true;
    }
}
