<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('affiliation_corporate_contacts')) {
            return;
        }

        Schema::create('affiliation_corporate_contacts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliation_corporate_id')->constrained('affiliation_corporates')->cascadeOnDelete();
            $table->string('name');
            $table->string('relationship', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('affiliation_corporate_contacts');
    }
};
