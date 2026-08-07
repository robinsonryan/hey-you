<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

use RobinsonRyan\HeyYou\Models\Party;

final readonly class ResolverRequest
{
    public function __construct(
        public Party $targetParty,
        public string $purpose,
        public string $channel,
        public ?Party $scopeParty = null,
        public ?ResolverConstraints $constraints = null,
        public int $limit = 10,
    ) {}

    /**
     * Get the effective scope party (defaults to target party).
     */
    public function getEffectiveScopeParty(): Party
    {
        return $this->scopeParty ?? $this->targetParty;
    }

    /**
     * Get the effective constraints (defaults to new instance with defaults).
     */
    public function getEffectiveConstraints(): ResolverConstraints
    {
        return $this->constraints ?? new ResolverConstraints;
    }
}
