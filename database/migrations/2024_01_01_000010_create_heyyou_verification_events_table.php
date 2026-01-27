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
        Schema::create(TablePrefixer::prefix('verification_events'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_point_id')->constrained(TablePrefixer::prefix('contact_points'))->cascadeOnDelete();
            $table->string('status');
            $table->string('method');
            $table->json('evidence')->nullable();
            $table->timestamp('initiated_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('verification_events'));
    }
};
