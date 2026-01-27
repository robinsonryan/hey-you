<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentCreated;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentDeleted;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentExpired;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentUpdated;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\RoleAssignment;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->company = Company::create(['legal_name' => 'Acme Corp']);
    $this->party = $this->user->party;
    $this->scopeParty = $this->company->party;
});

describe('RoleAssignmentCreated', function () {
    it('contains the role assignment, party, and scope party', function () {
        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);

        $event = new RoleAssignmentCreated($roleAssignment, $this->party, $this->scopeParty);

        expect($event->roleAssignment)->toBeInstanceOf(RoleAssignment::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->scopeParty)->toBeInstanceOf(Party::class)
            ->and($event->roleAssignment->id)->toBe($roleAssignment->id)
            ->and($event->party->id)->toBe($this->party->id)
            ->and($event->scopeParty->id)->toBe($this->scopeParty->id);
    });
});

describe('RoleAssignmentUpdated', function () {
    it('contains the role assignment and changed attributes', function () {
        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);
        $changedAttributes = ['priority' => 5];
        $event = new RoleAssignmentUpdated($roleAssignment, $changedAttributes);

        expect($event->roleAssignment)->toBeInstanceOf(RoleAssignment::class)
            ->and($event->changedAttributes)->toBe($changedAttributes);
    });
});

describe('RoleAssignmentExpired', function () {
    it('contains the role assignment when valid_to passes', function () {
        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);
        $event = new RoleAssignmentExpired($roleAssignment);

        expect($event->roleAssignment)->toBeInstanceOf(RoleAssignment::class)
            ->and($event->roleAssignment->id)->toBe($roleAssignment->id);
    });
});

describe('RoleAssignmentDeleted', function () {
    it('contains the role assignment', function () {
        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);
        $event = new RoleAssignmentDeleted($roleAssignment);

        expect($event->roleAssignment)->toBeInstanceOf(RoleAssignment::class);
    });
});
