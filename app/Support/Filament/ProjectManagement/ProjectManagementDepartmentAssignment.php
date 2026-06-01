<?php

declare(strict_types=1);

namespace App\Support\Filament\ProjectManagement;

use App\Models\ProjectManagement\Activity;
use App\Models\ProjectManagement\Department;
use Illuminate\Support\Collection;

final class ProjectManagementDepartmentAssignment
{
    public static function isDepartmentActivity(Activity $activity): bool
    {
        if (($activity->assignment_type ?? 'collaborator') === 'department') {
            return true;
        }

        return $activity->executor_type === Department::class && filled($activity->executor_id);
    }

    public static function resolveDepartmentForActivity(Activity $activity, ?Collection $departments = null): ?Department
    {
        if ($activity->relationLoaded('executor') && $activity->executor instanceof Department) {
            return $activity->executor;
        }

        if ($activity->executor_type !== Department::class || ! filled($activity->executor_id)) {
            return null;
        }

        $departmentId = (int) $activity->executor_id;

        if ($departments !== null) {
            $department = $departments->get($departmentId);

            if ($department instanceof Department) {
                return $department;
            }
        }

        return Department::query()->find($departmentId);
    }
}
