<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_ip_blocks')) {
            return;
        }

        Schema::create('security_ip_blocks', function (Blueprint $table): void {
            $table->id();
            $table->string('ip', 64);
            $table->string('verdict', 20)->nullable();
            $table->text('reason');
            $table->json('evidence')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('blocked_by_id')->nullable();
            $table->string('blocked_by_name')->nullable();
            $table->string('blocked_from_ip', 64)->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->unsignedBigInteger('lifted_by_id')->nullable();
            $table->string('lifted_by_name')->nullable();
            $table->text('lift_reason')->nullable();
            $table->timestamps();

            $table->index(['ip', 'lifted_at'], 'security_ip_blocks_ip_lifted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_ip_blocks');
    }
};
