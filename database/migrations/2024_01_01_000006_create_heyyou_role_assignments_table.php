<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TablePrefixer::prefix('role_assignments'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->foreignUuid('scope_party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->string('role');
            $table->integer('priority')->default(0);
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['scope_party_id', 'role']);
            $table->index(['party_id', 'role']);
            $table->index(['role', 'valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('role_assignments'));
    }
};
