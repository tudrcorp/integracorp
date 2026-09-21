<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('download_zone_likes')) {
            return;
        }

        Schema::create('download_zone_likes', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('download_zone_id');
            $table->timestamps();

            $table->unique(['user_id', 'download_zone_id'], 'download_zone_likes_user_document_unique');
            $table->index('download_zone_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('download_zone_likes');
    }
};
