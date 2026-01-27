<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Party;

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Models\Party;

final readonly class PartyDeleted
{
    public function __construct(
        public Party $party,
        public Model $partyable,
    ) {}
}
