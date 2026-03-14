<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RobinsonRyan\HeyYou\Contracts\IdentifierGenerator;

final class Uuid7Generator implements IdentifierGenerator
{
    public function generate(): string|int
    {
        return Str::uuid7()->toString();
    }

    public function columnDefinition(Blueprint $table, string $column): void
    {
        $table->uuid($column)->primary()->default(DB::raw('uuidv7()'));
    }
}
