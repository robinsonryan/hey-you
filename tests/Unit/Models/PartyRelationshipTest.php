<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyRelationship;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->party1 = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $this->party2 = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 2,
        'display_name_cached' => 'Acme Corp',
    ]);
});

it('uses the prefixed table name', function () {
    $relationship = new PartyRelationship;

    expect($relationship->getTable())->toBe('heyyou_party_relationships');
});

it('has fillable attributes', function () {
    $relationship = PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
        'label' => 'Employee',
        'metadata' => ['department' => 'Engineering'],
        'valid_from' => Carbon::parse('2024-01-01'),
        'valid_to' => null,
    ]);

    expect($relationship->from_party_id)->toBe($this->party1->id);
    expect($relationship->to_party_id)->toBe($this->party2->id);
    expect($relationship->relationship_type)->toBe('employment');
    expect($relationship->label)->toBe('Employee');
    expect($relationship->metadata)->toBe(['department' => 'Engineering']);
});

it('casts dates properly', function () {
    $relationship = PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
        'valid_from' => '2024-01-01 00:00:00',
        'valid_to' => '2024-12-31 23:59:59',
    ]);

    expect($relationship->valid_from)->toBeInstanceOf(Carbon::class);
    expect($relationship->valid_to)->toBeInstanceOf(Carbon::class);
});

it('belongs to from party', function () {
    $relationship = PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
    ]);

    expect($relationship->fromParty->id)->toBe($this->party1->id);
});

it('belongs to to party', function () {
    $relationship = PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
    ]);

    expect($relationship->toParty->id)->toBe($this->party2->id);
});

it('scopes to current relationships with no dates', function () {
    PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
        'valid_from' => null,
        'valid_to' => null,
    ]);

    expect(PartyRelationship::current()->count())->toBe(1);
});

it('scopes to current relationships that have started', function () {
    PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
        'valid_from' => Carbon::now()->subDay(),
        'valid_to' => null,
    ]);

    expect(PartyRelationship::current()->count())->toBe(1);
});

it('excludes relationships that have not started', function () {
    PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
        'valid_from' => Carbon::now()->addDay(),
        'valid_to' => null,
    ]);

    expect(PartyRelationship::current()->count())->toBe(0);
});

it('excludes relationships that have ended', function () {
    PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
        'valid_from' => Carbon::now()->subMonth(),
        'valid_to' => Carbon::now()->subDay(),
    ]);

    expect(PartyRelationship::current()->count())->toBe(0);
});

it('includes relationships that are currently active', function () {
    PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
        'valid_from' => Carbon::now()->subMonth(),
        'valid_to' => Carbon::now()->addMonth(),
    ]);

    expect(PartyRelationship::current()->count())->toBe(1);
});

it('can be soft deleted', function () {
    $relationship = PartyRelationship::create([
        'from_party_id' => $this->party1->id,
        'to_party_id' => $this->party2->id,
        'relationship_type' => 'employment',
    ]);

    $relationship->delete();

    expect(PartyRelationship::find($relationship->id))->toBeNull();
    expect(PartyRelationship::withTrashed()->find($relationship->id))->not->toBeNull();
});
