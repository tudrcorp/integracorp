<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OperationDocumentList extends Model
{
    //
    protected $table = 'operation_document_lists';

    protected $fillable = [
        'name',
        'created_by',
        'updated_by',
    ];

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
