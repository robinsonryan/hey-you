<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Resolver;

final readonly class ResolverExplanation
{
    /**
     * @param  array<string, int>  $exclusionSummary
     */
    public function __construct(
        public int $candidatesConsidered,
        public array $exclusionSummary,
        public bool $fallbackUsed,
        public ?string $fallbackPath,
    ) {}
}
