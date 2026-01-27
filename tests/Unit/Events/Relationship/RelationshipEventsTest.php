<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Events\Relationship\RelationshipCreated;
use RobinsonRyan\HeyYou\Events\Relationship\RelationshipDeleted;
use RobinsonRyan\HeyYou\Events\Relationship\RelationshipEnded;
use RobinsonRyan\HeyYou\Events\Relationship\RelationshipUpdated;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyRelationship;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->company = Company::create(['legal_name' => 'Acme Corp']);
    $this->fromParty = $this->user->party;
    $this->toParty = $this->company->party;
});

describe('RelationshipCreated', function () {
    it('contains the relationship, fromParty, and toParty', function () {
        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);

        $event = new RelationshipCreated($relationship, $this->fromParty, $this->toParty);

        expect($event->relationship)->toBeInstanceOf(PartyRelationship::class)
            ->and($event->fromParty)->toBeInstanceOf(Party::class)
            ->and($event->toParty)->toBeInstanceOf(Party::class)
            ->and($event->relationship->id)->toBe($relationship->id)
            ->and($event->fromParty->id)->toBe($this->fromParty->id)
            ->and($event->toParty->id)->toBe($this->toParty->id);
    });
});

describe('RelationshipUpdated', function () {
    it('contains the relationship and changed attributes', function () {
        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);
        $changedAttributes = ['label' => 'Senior Developer'];
        $event = new RelationshipUpdated($relationship, $changedAttributes);

        expect($event->relationship)->toBeInstanceOf(PartyRelationship::class)
            ->and($event->changedAttributes)->toBe($changedAttributes);
    });
});

describe('RelationshipEnded', function () {
    it('contains the relationship when valid_to is set', function () {
        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);
        $event = new RelationshipEnded($relationship);

        expect($event->relationship)->toBeInstanceOf(PartyRelationship::class)
            ->and($event->relationship->id)->toBe($relationship->id);
    });
});

describe('RelationshipDeleted', function () {
    it('contains the relationship', function () {
        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);
        $event = new RelationshipDeleted($relationship);

        expect($event->relationship)->toBeInstanceOf(PartyRelationship::class);
    });
});
