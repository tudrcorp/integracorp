<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('security_user_blocks')) {
            return;
        }

        Schema::create('security_user_blocks', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('user_name')->nullable();
            $table->string('user_email')->nullable();
            $table->text('reason');
            $table->timestamp('expires_at')->nullable();
            $table->unsignedBigInteger('blocked_by_id')->nullable();
            $table->string('blocked_by_name')->nullable();
            $table->string('blocked_from_ip', 64)->nullable();
            $table->timestamp('lifted_at')->nullable();
            $table->unsignedBigInteger('lifted_by_id')->nullable();
            $table->string('lifted_by_name')->nullable();
            $table->text('lift_reason')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'lifted_at'], 'security_user_blocks_user_lifted_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_user_blocks');
    }
};
