<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Contracts;

use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Support\ConsentResult;

interface ConsentChecker
{
    public function hasConsent(ContactPoint $contactPoint, string $purposeCategory): ConsentResult;
}
