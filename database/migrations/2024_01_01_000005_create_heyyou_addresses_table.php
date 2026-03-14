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
        Schema::create(TablePrefixer::prefix('addresses'), function (Blueprint $table) {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->foreignUuid('party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->string('purpose');
            $table->boolean('is_primary')->default(false);
            $table->string('label')->nullable();
            $table->string('line1');
            $table->string('line2')->nullable();
            $table->string('city');
            $table->string('region')->nullable();
            $table->string('postal_code')->nullable();
            $table->string('country_code', 2);
            $table->json('geocode')->nullable();
            $table->string('timezone')->nullable();
            $table->string('validation_status')->default('unverified');
            $table->string('formatted_cached')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['party_id', 'purpose', 'is_primary']);
            $table->index(['country_code', 'region', 'city']);
            $table->index('validation_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('addresses'));
    }
};
