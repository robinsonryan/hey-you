<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\ContactPoint;

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;

final readonly class ContactPointDeleted
{
    public function __construct(
        public ContactPoint $contactPoint,
        public Party $party,
    ) {}
}
