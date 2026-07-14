<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * @var list<string>
     */
    private const DOCUMENT_NAMES = [
        'COMPROBANTE DE ENTREGA DE LABORATORIOS',
        'COMPROBANTE DE ENTREGA DE ESTUDIOS',
        'COMPROBANTE DE ENTREGA DE ESPECIALISTAS',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::DOCUMENT_NAMES as $documentName) {
            $exists = DB::table('operation_document_lists')
                ->where('name', $documentName)
                ->exists();

            if ($exists) {
                continue;
            }

            DB::table('operation_document_lists')->insert([
                'name' => $documentName,
                'created_by' => 'system',
                'updated_by' => 'system',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('operation_document_lists')
            ->whereIn('name', self::DOCUMENT_NAMES)
            ->delete();
    }
};
