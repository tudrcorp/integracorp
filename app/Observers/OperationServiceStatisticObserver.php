<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\TelemedicineCase;
use App\Support\Operations\OperationServiceStatisticSync;
use Illuminate\Database\Eloquent\Model;

class OperationServiceStatisticObserver
{
    public function saved(Model $model): void
    {
        OperationServiceStatisticSync::syncFromModel($model);
    }

    public function deleted(Model $model): void
    {
        OperationServiceStatisticSync::forgetFromModel($model);
    }

    public function deleting(Model $model): void
    {
        if ($model instanceof TelemedicineCase) {
            OperationServiceStatisticSync::markCaseDenied($model);
        }
    }
}
