<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\RoleAssignment;

use RobinsonRyan\HeyYou\Models\RoleAssignment;

final readonly class RoleAssignmentExpired
{
    public function __construct(
        public RoleAssignment $roleAssignment,
    ) {}
}
