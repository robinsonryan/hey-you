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
        Schema::create(TablePrefixer::prefix('parties'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('partyable_type');
            $table->string('partyable_id');
            $table->index(['partyable_type', 'partyable_id']);
            $table->string('display_name_cached');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('display_name_cached');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('parties'));
    }
};
