<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\RoleAssignment;

use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\RoleAssignment;

final readonly class RoleAssignmentCreated
{
    public function __construct(
        public RoleAssignment $roleAssignment,
        public Party $party,
        public Party $scopeParty,
    ) {}
}
