<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Party;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Models\Party;

final readonly class PartyCreated
{
    public function __construct(
        public Party $party,
        public Model $partyable,
    ) {}
}
