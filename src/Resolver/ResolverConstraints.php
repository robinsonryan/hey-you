<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

final readonly class ResolverConstraints
{
    /**
     * @param  list<string>  $excludeContactPointIds
     */
    public function __construct(
        public bool $requireVerified = false,
        public bool $requireConsent = false,
        public ?string $consentCategory = null,
        public bool $allowFallback = true,
        public array $excludeContactPointIds = [],
    ) {}
}
