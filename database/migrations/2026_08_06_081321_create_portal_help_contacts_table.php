<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_help_contacts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('phone');
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('status')->default('ACTIVO');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->index(['status', 'sort_order']);
        });

        $now = now();

        DB::table('portal_help_contacts')->insert([
            'name' => 'MediChat',
            'phone' => '+584242132112',
            'sort_order' => 1,
            'status' => 'ACTIVO',
            'created_by' => 'system',
            'updated_by' => 'system',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_help_contacts');
    }
};
