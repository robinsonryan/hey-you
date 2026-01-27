<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Events\Party\PartyCreated;
use RobinsonRyan\HeyYou\Events\Party\PartyDeleted;
use RobinsonRyan\HeyYou\Events\Party\PartyUpdated;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
});

describe('PartyCreated', function () {
    it('contains the party and partyable', function () {
        $event = new PartyCreated($this->party, $this->user);

        expect($event->party)->toBeInstanceOf(Party::class)
            ->and($event->partyable)->toBeInstanceOf(Model::class)
            ->and($event->partyable->getKey())->toBe($this->user->getKey());
    });
});

describe('PartyUpdated', function () {
    it('contains the party, partyable, and changed attributes', function () {
        $changedAttributes = ['display_name_cached' => 'Jane Doe'];
        $event = new PartyUpdated($this->party, $this->user, $changedAttributes);

        expect($event->party)->toBeInstanceOf(Party::class)
            ->and($event->partyable)->toBeInstanceOf(Model::class)
            ->and($event->changedAttributes)->toBe($changedAttributes);
    });
});

describe('PartyDeleted', function () {
    it('contains the party and partyable', function () {
        $event = new PartyDeleted($this->party, $this->user);

        expect($event->party)->toBeInstanceOf(Party::class)
            ->and($event->partyable)->toBeInstanceOf(Model::class);
    });
});
