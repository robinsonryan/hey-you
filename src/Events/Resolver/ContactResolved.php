<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Resolver;

use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Resolver\ResolverResult;

final readonly class ContactResolved
{
    public function __construct(
        public ResolverRequest $request,
        public ResolverResult $result,
    ) {}
}
