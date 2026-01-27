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
        Schema::create(TablePrefixer::prefix('contact_point_purposes'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('contact_point_id')->constrained(TablePrefixer::prefix('contact_points'))->cascadeOnDelete();
            $table->string('purpose');
            $table->integer('priority')->default(0);
            $table->boolean('is_preferred')->default(false);
            $table->timestamps();

            $table->unique(['contact_point_id', 'purpose']);
            $table->index(['purpose', 'is_preferred']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('contact_point_purposes'));
    }
};
