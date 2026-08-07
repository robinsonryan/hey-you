<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;

final readonly class ResolverMatch
{
    /**
     * @param  array<string, bool>  $flags
     */
    public function __construct(
        public ContactPoint $contactPoint,
        public Party $owningParty,
        public string $channel,
        public string $normalizedValue,
        public ?string $matchedPurpose,
        public ?string $matchedRole,
        public ?Party $scopeParty,
        public array $flags,
        public int $rank,
    ) {}
}
