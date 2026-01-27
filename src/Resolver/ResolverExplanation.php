<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

final class ResolverExplanation
{
    /**
     * @param  array<string, int>  $exclusionSummary
     */
    public function __construct(
        public readonly int $candidatesConsidered,
        public readonly array $exclusionSummary,
        public readonly bool $fallbackUsed,
        public readonly ?string $fallbackPath,
    ) {}
}
