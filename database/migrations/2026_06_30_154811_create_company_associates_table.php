<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_associates', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('company_id')
                ->constrained('companies')
                ->cascadeOnDelete();
            $table->foreignId('company_responsible_id')
                ->constrained('company_responsibles')
                ->cascadeOnDelete();
            $table->string('full_name');
            $table->string('identity_card');
            $table->date('birth_date');
            $table->unsignedTinyInteger('age');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('sex');
            $table->string('contact_full_name');
            $table->string('contact_phone')->nullable();
            $table->string('contact_email')->nullable();
            $table->string('identity_document');
            $table->timestamp('registered_at');
            $table->timestamps();

            $table->index(['company_id', 'company_responsible_id']);
            $table->index('identity_card');
            $table->index('registered_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('company_associates');
    }
};
