<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\ContactPoint;

use RobinsonRyan\HeyYou\Models\ContactPoint;

final readonly class ContactPointVerificationFailed
{
    public function __construct(
        public ContactPoint $contactPoint,
        public string $method,
        public string $reason,
    ) {}
}
