<?php

namespace App\Models;

use App\Observers\OperationServiceStatisticObserver;
use App\Support\Telemedicine\Concerns\HidesDeletedTelemedicineCaseTraces;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([OperationServiceStatisticObserver::class])]
class ObservationCase extends Model
{
    use HidesDeletedTelemedicineCaseTraces;

    protected $table = 'observation_cases';

    protected $fillable = [
        'telemedicine_case_id',
        'description',
        'created_by',
    ];

    public function telemedicineCase()
    {
        return $this->belongsTo(
            TelemedicineCase::class,
            'telemedicine_case_id',
            'id'
        );
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by', 'id');
    }
}
