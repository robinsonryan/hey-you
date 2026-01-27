<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

use RobinsonRyan\HeyYou\Models\Party;

final class ResolverRequest
{
    public function __construct(
        public readonly Party $targetParty,
        public readonly string $purpose,
        public readonly string $channel,
        public readonly ?Party $scopeParty = null,
        public readonly ?ResolverConstraints $constraints = null,
        public readonly int $limit = 10,
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
