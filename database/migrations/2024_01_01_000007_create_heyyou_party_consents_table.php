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
        Schema::create(TablePrefixer::prefix('party_consents'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->string('channel')->nullable();
            $table->string('purpose_category');
            $table->string('status');
            $table->timestamp('captured_at');
            $table->string('source');
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['party_id', 'channel', 'purpose_category']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('party_consents'));
    }
};
