<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('doctor_nurses', function (Blueprint $table) {
            $table->string('carta_acceptance')->nullable()->after('speciality');
            $table->json('documents')->nullable()->after('carta_acceptance');
        });
    }

    public function down(): void
    {
        Schema::table('doctor_nurses', function (Blueprint $table) {
            $table->dropColumn(['carta_acceptance', 'documents']);
        });
    }
};
