<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\ProspectAgentTask;
use App\Support\ProspectAgents\ProspectAgentTaskAssigneeNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

class NotifyProspectAgentTaskAssigneeJob implements ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $taskId) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [15, 60, 180];
    }

    public function handle(): void
    {
        $task = ProspectAgentTask::query()
            ->with(['prospect_agent', 'rrhh_colaborador.user'])
            ->find($this->taskId);

        if (! $task instanceof ProspectAgentTask) {
            Log::warning('NotifyProspectAgentTaskAssigneeJob: la tarea no existe', [
                'task_id' => $this->taskId,
            ]);

            return;
        }

        ProspectAgentTaskAssigneeNotifier::deliver($task);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('NotifyProspectAgentTaskAssigneeJob: no se pudo avisar al colaborador', [
            'task_id' => $this->taskId,
            'error' => $exception?->getMessage(),
        ]);
    }
}
