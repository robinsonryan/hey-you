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
        Schema::create(TablePrefixer::prefix('do_not_contacts'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->foreignUuid('contact_point_id')->nullable()->constrained(TablePrefixer::prefix('contact_points'))->cascadeOnDelete();
            $table->string('channel')->nullable();
            $table->string('purpose')->nullable();
            $table->string('reason')->nullable();
            $table->string('source');
            $table->string('created_by_type')->nullable();
            $table->string('created_by_id')->nullable();
            $table->index(['created_by_type', 'created_by_id'], 'dnc_created_by');
            $table->timestamp('effective_at');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['party_id', 'contact_point_id']);
            $table->index(['party_id', 'channel']);
            $table->index(['party_id', 'purpose']);
            $table->index(['effective_at', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('do_not_contacts'));
    }
};
