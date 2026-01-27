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
        Schema::create(TablePrefixer::prefix('party_relationships'), function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->foreignId('to_party_id')->constrained(TablePrefixer::prefix('parties'))->cascadeOnDelete();
            $table->string('relationship_type');
            $table->string('label')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamp('valid_from')->nullable();
            $table->timestamp('valid_to')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['from_party_id', 'relationship_type']);
            $table->index(['to_party_id', 'relationship_type']);
            $table->index('relationship_type');
            $table->index(['valid_from', 'valid_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TablePrefixer::prefix('party_relationships'));
    }
};
