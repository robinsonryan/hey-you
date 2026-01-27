<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\ContactPoint;

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Models\ContactPoint;

final readonly class ContactPointVerified
{
    public function __construct(
        public ContactPoint $contactPoint,
        public string $method,
        public Carbon $verifiedAt,
    ) {}
}
