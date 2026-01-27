<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\ContactPoint;

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;

final readonly class ContactPointUpdated
{
    /**
     * @param  array<string, mixed>  $changedAttributes
     */
    public function __construct(
        public ContactPoint $contactPoint,
        public Party $party,
        public array $changedAttributes,
    ) {}
}
