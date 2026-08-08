<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Fixture consumer tables follow the package's UUID7 convention: the
        // database generates the key via PostgreSQL's native uuidv7().
        Schema::create('users', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table): void {
            $table->uuid('id')->primary()->default(DB::raw('uuidv7()'));
            $table->string('legal_name');
            $table->timestamps();
        });

        // A consumer that did NOT adopt the UUID7 convention: a plain
        // auto-incrementing bigint key. PostgreSQL will not compare a bigint
        // against the varchar `partyable_id` column, so this table exists to
        // keep the Contactable trait honest for legacy consumers.
        Schema::create('legacy_accounts', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_accounts');
        Schema::dropIfExists('companies');
        Schema::dropIfExists('users');
    }
};
