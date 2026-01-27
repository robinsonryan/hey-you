<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

final class ResolverConstraints
{
    /**
     * @param  list<int>  $excludeContactPointIds
     */
    public function __construct(
        public readonly bool $requireVerified = false,
        public readonly bool $requireConsent = false,
        public readonly ?string $consentCategory = null,
        public readonly bool $allowFallback = true,
        public readonly array $excludeContactPointIds = [],
    ) {}
}
