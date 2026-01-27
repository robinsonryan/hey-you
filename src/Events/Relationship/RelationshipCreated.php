<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Relationship;

use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyRelationship;

final readonly class RelationshipCreated
{
    public function __construct(
        public PartyRelationship $relationship,
        public Party $fromParty,
        public Party $toParty,
    ) {}
}
