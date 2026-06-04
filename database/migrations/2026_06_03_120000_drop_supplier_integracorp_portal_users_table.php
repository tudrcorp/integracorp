<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('supplier_integracorp_portal_users')) {
            return;
        }

        $portalUsers = DB::table('supplier_integracorp_portal_users')->get();

        foreach ($portalUsers as $portalUser) {
            if ($portalUser->user_id !== null) {
                DB::table('users')
                    ->where('id', $portalUser->user_id)
                    ->update([
                        'supplier_id' => $portalUser->supplier_id,
                        'name' => $portalUser->name,
                        'email' => $portalUser->email,
                        'departament' => $portalUser->departament,
                        'status' => 'ACTIVO',
                    ]);

                continue;
            }

            if (DB::table('users')->where('email', $portalUser->email)->exists()) {
                continue;
            }

            DB::table('users')->insert([
                'name' => $portalUser->name,
                'email' => $portalUser->email,
                'departament' => $portalUser->departament,
                'supplier_id' => $portalUser->supplier_id,
                'status' => 'ACTIVO',
                'password' => bcrypt(str()->random(32)),
                'created_at' => $portalUser->created_at ?? now(),
                'updated_at' => $portalUser->updated_at ?? now(),
            ]);
        }

        Schema::dropIfExists('supplier_integracorp_portal_users');
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_integracorp_portal_users')) {
            return;
        }

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
};
