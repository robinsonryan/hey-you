<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RobinsonRyan\HeyYou\Support\TablePrefixer;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(TablePrefixer::prefix('parties'), function (Blueprint $table) {
            $table->id();
            $table->string('partyable_type');
            $table->unsignedBigInteger('partyable_id');
            $table->string('display_name_cached');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['partyable_type', 'partyable_id']);
            $table->index('display_name_cached');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('parties'));
    }
};
