<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\ContactPoint;

use RobinsonRyan\HeyYou\Models\ContactPoint;

final readonly class ContactPointPurposeAttached
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public ContactPoint $contactPoint,
        public string $purpose,
        public array $attributes,
    ) {}
}
