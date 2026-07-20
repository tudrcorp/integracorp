<?php

declare(strict_types=1);

namespace App\Support\ProjectManagement;

use App\Enums\ProjectManagement\SprintStatus;
use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Sprint;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class SprintLifecycle
{
    public function __construct(
        private readonly SprintMetricsRecorder $metricsRecorder = new SprintMetricsRecorder,
    ) {}

    public function activate(Sprint $sprint): Sprint
    {
        if ($sprint->status === SprintStatus::Completed) {
            throw new InvalidArgumentException('No se puede activar un sprint completado.');
        }

        if ($sprint->status === SprintStatus::Active) {
            return $sprint;
        }

        return DB::transaction(function () use ($sprint): Sprint {
            $hasActiveSprint = Sprint::query()
                ->where('project_id', $sprint->project_id)
                ->where('status', SprintStatus::Active)
                ->whereKeyNot($sprint->getKey())
                ->exists();

            if ($hasActiveSprint) {
                throw new RuntimeException('Ya existe un sprint activo en este proyecto. Complétalo antes de activar otro.');
            }

            $sprint->update(['status' => SprintStatus::Active]);
            $this->metricsRecorder->recordForSprint($sprint->fresh());

            return $sprint->fresh();
        });
    }

    public function complete(Sprint $sprint): Sprint
    {
        if ($sprint->status === SprintStatus::Completed) {
            return $sprint;
        }

        return DB::transaction(function () use ($sprint): Sprint {
            Activity::query()
                ->where('sprint_id', $sprint->getKey())
                ->where('status', '!=', 'done')
                ->update([
                    'sprint_id' => null,
                    'updated_at' => now(),
                ]);

            $sprint->update(['status' => SprintStatus::Completed]);
            $this->metricsRecorder->recordForSprint($sprint->fresh());

            return $sprint->fresh();
        });
    }
}
