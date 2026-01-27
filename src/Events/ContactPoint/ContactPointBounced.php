<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\ContactPoint;

use RobinsonRyan\HeyYou\Models\ContactPoint;

final readonly class ContactPointBounced
{
    /**
     * @param  array<string, mixed>  $bounceInfo
     */
    public function __construct(
        public ContactPoint $contactPoint,
        public array $bounceInfo,
    ) {}
}
