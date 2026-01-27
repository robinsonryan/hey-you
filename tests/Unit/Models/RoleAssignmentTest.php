<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\RoleAssignment;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->personParty = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $this->orgParty = Party::create([
        'partyable_type' => Company::class,
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);
});

it('uses the prefixed table name', function () {
    $roleAssignment = new RoleAssignment;

    expect($roleAssignment->getTable())->toBe('heyyou_role_assignments');
});

it('has fillable attributes', function () {
    $roleAssignment = RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'accounts_payable_contact',
        'priority' => 1,
        'valid_from' => Carbon::parse('2024-01-01'),
        'metadata' => ['department' => 'Finance'],
    ]);

    expect($roleAssignment->party_id)->toBe($this->personParty->id);
    expect($roleAssignment->scope_party_id)->toBe($this->orgParty->id);
    expect($roleAssignment->role)->toBe('accounts_payable_contact');
    expect($roleAssignment->priority)->toBe(1);
    expect($roleAssignment->metadata)->toBe(['department' => 'Finance']);
});

it('defaults priority to 0', function () {
    $roleAssignment = RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
    ]);

    expect($roleAssignment->priority)->toBe(0);
});

it('belongs to party', function () {
    $roleAssignment = RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
    ]);

    expect($roleAssignment->party->id)->toBe($this->personParty->id);
});

it('belongs to scope party', function () {
    $roleAssignment = RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
    ]);

    expect($roleAssignment->scopeParty->id)->toBe($this->orgParty->id);
});

it('casts valid_from and valid_to to datetime', function () {
    $roleAssignment = RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
        'valid_from' => '2024-01-01 00:00:00',
        'valid_to' => '2024-12-31 23:59:59',
    ]);

    expect($roleAssignment->valid_from)->toBeInstanceOf(Carbon::class);
    expect($roleAssignment->valid_to)->toBeInstanceOf(Carbon::class);
});

it('can be soft deleted', function () {
    $roleAssignment = RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
    ]);

    $roleAssignment->delete();

    expect(RoleAssignment::find($roleAssignment->id))->toBeNull();
    expect(RoleAssignment::withTrashed()->find($roleAssignment->id))->not->toBeNull();
});

it('scopes to current role assignments with no dates', function () {
    RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
        'valid_from' => null,
        'valid_to' => null,
    ]);

    expect(RoleAssignment::current()->count())->toBe(1);
});

it('scopes to current role assignments that have started', function () {
    RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => null,
    ]);

    expect(RoleAssignment::current()->count())->toBe(1);
});

it('excludes role assignments that have not started', function () {
    RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
        'valid_from' => Carbon::now()->addDay(),
        'valid_to' => null,
    ]);

    expect(RoleAssignment::current()->count())->toBe(0);
});

it('excludes role assignments that have ended', function () {
    RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
        'valid_from' => Carbon::now()->subMonth(),
        'valid_to' => Carbon::now()->subDay(),
    ]);

    expect(RoleAssignment::current()->count())->toBe(0);
});

it('includes role assignments that are currently active', function () {
    RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'hr_contact',
        'valid_from' => Carbon::now()->subMonth(),
        'valid_to' => Carbon::now()->addMonth(),
    ]);

    expect(RoleAssignment::current()->count())->toBe(1);
});

it('can find role holders for a scope and role', function () {
    // Create multiple role holders with different priorities
    RoleAssignment::create([
        'party_id' => $this->personParty->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'accounts_payable_contact',
        'priority' => 2,
    ]);

    $person2Party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 2,
        'display_name_cached' => 'Jane Smith',
    ]);

    RoleAssignment::create([
        'party_id' => $person2Party->id,
        'scope_party_id' => $this->orgParty->id,
        'role' => 'accounts_payable_contact',
        'priority' => 1,
    ]);

    $contacts = RoleAssignment::query()
        ->where('scope_party_id', $this->orgParty->id)
        ->where('role', 'accounts_payable_contact')
        ->current()
        ->orderBy('priority')
        ->get();

    expect($contacts)->toHaveCount(2);
    expect($contacts->first()->party_id)->toBe($person2Party->id); // Lower priority first
    expect($contacts->last()->party_id)->toBe($this->personParty->id);
});
