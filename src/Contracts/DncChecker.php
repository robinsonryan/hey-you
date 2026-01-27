<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Support\DncResult;

interface DncChecker
{
    public function isBlocked(ContactPoint $contactPoint, ?string $purpose = null): DncResult;
}
