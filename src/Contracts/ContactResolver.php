<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Resolver\ResolverResult;

interface ContactResolver
{
    public function resolve(ResolverRequest $request): ResolverResult;
}
