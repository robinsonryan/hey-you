<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;

final class ResolverMatch
{
    /**
     * @param  array<string, bool>  $flags
     */
    public function __construct(
        public readonly ContactPoint $contactPoint,
        public readonly Party $owningParty,
        public readonly string $channel,
        public readonly string $normalizedValue,
        public readonly ?string $matchedPurpose,
        public readonly ?string $matchedRole,
        public readonly ?Party $scopeParty,
        public readonly array $flags,
        public readonly int $rank,
    ) {}
}
