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
        Schema::create(TablePrefixer::prefix('contact_point_consents'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_point_id')->constrained(TablePrefixer::prefix('contact_points'))->cascadeOnDelete();
            $table->string('purpose_category');
            $table->string('status');
            $table->timestamp('captured_at');
            $table->string('source');
            $table->json('evidence')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['contact_point_id', 'purpose_category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('contact_point_consents'));
    }
};
