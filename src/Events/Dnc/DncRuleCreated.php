<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Dnc;

use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\Party;

final readonly class DncRuleCreated
{
    /**
     * @param  string  $scope  'party', 'channel', 'purpose', or 'contact_point'
     */
    public function __construct(
        public DoNotContact $dncRule,
        public Party $party,
        public string $scope,
    ) {}
}
