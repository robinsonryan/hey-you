<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

use Illuminate\Database\Schema\Blueprint;

interface IdentifierGenerator
{
    /**
     * Generate a new identifier value.
     */
    public function generate(): string|int;

    /**
     * Define the column on a migration blueprint.
     */
    public function columnDefinition(Blueprint $table, string $column): void;
}
