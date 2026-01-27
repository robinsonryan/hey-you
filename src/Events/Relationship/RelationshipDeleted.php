<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\Relationship;

use RobinsonRyan\HeyYou\Models\PartyRelationship;

final readonly class RelationshipDeleted
{
    public function __construct(
        public PartyRelationship $relationship,
    ) {}
}
