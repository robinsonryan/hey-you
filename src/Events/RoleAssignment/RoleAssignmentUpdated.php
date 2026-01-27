<?php

declare(strict_types=1);

namespace RobinsonRyan\HeyYou\Events\RoleAssignment;

use RobinsonRyan\HeyYou\Models\RoleAssignment;

final readonly class RoleAssignmentUpdated
{
    /**
     * @param  array<string, mixed>  $changedAttributes
     */
    public function __construct(
        public RoleAssignment $roleAssignment,
        public array $changedAttributes,
    ) {}
}
