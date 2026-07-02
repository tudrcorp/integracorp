<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->string('vaucher_ils')->nullable()->after('registered_at');
            $table->string('date_init')->nullable()->after('vaucher_ils');
            $table->string('date_end')->nullable()->after('date_init');
            $table->string('document_ils')->nullable()->after('date_end');
        });
    }

    public function down(): void
    {
        Schema::table('company_associates', function (Blueprint $table): void {
            $table->dropColumn([
                'vaucher_ils',
                'date_init',
                'date_end',
                'document_ils',
            ]);
        });
    }
};
