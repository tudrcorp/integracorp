<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DOCUMENT_NAME = 'COMPROBANTE DE ENTREGA DE MEDICAMENTOS';

    /**
     * Run the migrations.
     */
    public function up(): void
    {
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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('operation_document_lists')
            ->where('name', self::DOCUMENT_NAME)
            ->delete();
    }
};
