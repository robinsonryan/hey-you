<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Traits;

/**
 * Configures model primary key settings for UUID7 identifiers.
 *
 * The database (PostgreSQL 18+) generates UUID7 values via its native uuidv7()
 * function as the column default. This trait configures Eloquent to treat the
 * primary key as a non-incrementing string.
 */
trait ConfiguresIdentifiers
{
    public function initializeConfiguresIdentifiers(): void
    {
        $this->incrementing = false;
        $this->keyType = 'string';
    }
}
