<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Address;

use RobinsonRyan\HeyYou\Models\Address;

final readonly class AddressValidationFailed
{
    /**
     * @param  array<string, mixed>  $validationResult
     */
    public function __construct(
        public Address $address,
        public array $validationResult,
    ) {}
}
