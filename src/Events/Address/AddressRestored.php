<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Address;

use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\Party;

final readonly class AddressRestored
{
    public function __construct(
        public Address $address,
        public Party $party,
    ) {}
}
