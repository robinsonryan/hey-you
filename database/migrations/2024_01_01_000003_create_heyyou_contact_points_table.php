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
        Schema::create(TablePrefixer::prefix('contact_points'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->string('channel');
            $table->string('value_raw');
            $table->string('value_normalized');
            $table->string('label')->nullable();
            $table->string('status')->default('active');
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->string('verification_method')->nullable();
            $table->timestamp('verification_expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['party_id', 'channel', 'value_normalized']);
            $table->index(['channel', 'value_normalized']);
            $table->index(['party_id', 'channel', 'status']);
            $table->index('is_verified');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('contact_points'));
    }
};
