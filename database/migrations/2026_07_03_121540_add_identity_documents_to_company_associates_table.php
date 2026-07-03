<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->json('identity_documents')->nullable()->after('identity_document');
        });

        DB::table('company_associates')
            ->whereNotNull('identity_document')
            ->where('identity_document', '!=', '')
            ->orderBy('id')
            ->each(function (object $associate): void {
                DB::table('company_associates')
                    ->where('id', $associate->id)
                    ->update([
                        'identity_documents' => json_encode([(string) $associate->identity_document]),
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->dropColumn('identity_documents');
        });
    }
};
