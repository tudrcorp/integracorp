<?php

namespace App\Models;

use App\Support\Logging\GuardDutyShiftLogger;
use Illuminate\Database\Eloquent\Model;

class OperationOnCallUser extends Model
{
    protected $table = 'operation_on_call_users';

    protected $fillable = [
        'rrhh_colaborador_id',
        'name',
        'email',
        'phone',
        'hrs_init',
        'hrs_end',
        'date_OnCall',
        'status',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::created(function (OperationOnCallUser $record): void {
            GuardDutyShiftLogger::record('created', $record);
        });

        static::updated(function (OperationOnCallUser $record): void {
            $changes = collect($record->getChanges())
                ->except(['updated_at'])
                ->all();
            if ($changes === []) {
                return;
            }
            GuardDutyShiftLogger::record('updated', $record, [
                'attribute_changes' => $changes,
            ]);
        });

        static::deleted(function (OperationOnCallUser $record): void {
            GuardDutyShiftLogger::record('deleted', $record, [
                'snapshot_name' => $record->name,
            ]);
        });
    }

    public function rrhh_colaborador()
    {
        return $this->belongsTo(RrhhColaborador::class);
    }

    public function created_by()
    {
        return $this->belongsTo(User::class);
    }

    public function updated_by()
    {
        return $this->belongsTo(User::class);
    }
}
