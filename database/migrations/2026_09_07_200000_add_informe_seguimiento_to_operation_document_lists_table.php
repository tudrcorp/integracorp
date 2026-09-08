<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const DOCUMENT_NAME = 'INFORME DE SEGUIMIENTO';

    public function up(): void
    {
        if (! Schema::hasTable('operation_document_lists')) {
            return;
        }

        $exists = DB::table('operation_document_lists')
            ->where('name', self::DOCUMENT_NAME)
            ->exists();

        if ($exists) {
            return;
        }

        DB::table('operation_document_lists')->insert([
            'name' => self::DOCUMENT_NAME,
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('operation_document_lists')) {
            return;
        }

        DB::table('operation_document_lists')
            ->where('name', self::DOCUMENT_NAME)
            ->delete();
    }
};
