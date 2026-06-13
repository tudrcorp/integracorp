<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_integracorp_portal_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained('suppliers')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('email');
            $table->json('departament');
            $table->timestamps();

            $table->unique(['supplier_id', 'email']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_integracorp_portal_users');
    }
};
