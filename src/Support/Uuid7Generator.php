<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

use Illuminate\Support\Str;

/**
 * Generates a UUID7 in PHP, for the narrow case of needing an identifier before
 * the row reaches the database.
 *
 * Package tables do NOT use this. Every one of them declares
 * `$table->uuid('id')->primary()->default(DB::raw('uuidv7()'))`, so PostgreSQL
 * assigns the key during INSERT and Eloquent hydrates it back off the
 * `returning "id"` clause. Reach for this only when there is no INSERT to wait
 * for — `Model::factory()->make()` without persisting, or a client-supplied
 * identifier chosen for a specific business reason.
 */
final class Uuid7Generator
{
    public function generate(): string
    {
        return Str::uuid7()->toString();
    }
}
