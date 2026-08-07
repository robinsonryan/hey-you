<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use RobinsonRyan\HeyYou\Events\Party\PartyCreated;
use RobinsonRyan\HeyYou\Events\Party\PartyDeleted;
use RobinsonRyan\HeyYou\Events\Party\PartyRestored;
use RobinsonRyan\HeyYou\Events\Party\PartyUpdated;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function (): void {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
});

describe('PartyCreated', function (): void {
    it('contains the party and partyable', function (): void {
        $event = new PartyCreated($this->party, $this->user);

        expect($event->party)->toBeInstanceOf(Party::class)
            ->and($event->partyable)->toBeInstanceOf(Model::class)
            ->and($event->partyable->getKey())->toBe($this->user->getKey());
    });
});

describe('PartyUpdated', function (): void {
    it('contains the party, partyable, and changed attributes', function (): void {
        $changedAttributes = ['display_name_cached' => 'Jane Doe'];
        $event = new PartyUpdated($this->party, $this->user, $changedAttributes);

        expect($event->party)->toBeInstanceOf(Party::class)
            ->and($event->partyable)->toBeInstanceOf(Model::class)
            ->and($event->changedAttributes)->toBe($changedAttributes);
    });
});

describe('PartyDeleted', function (): void {
    it('contains the party and partyable', function (): void {
        $event = new PartyDeleted($this->party, $this->user);

        expect($event->party)->toBeInstanceOf(Party::class)
            ->and($event->partyable)->toBeInstanceOf(Model::class);
    });
});

describe('PartyRestored', function (): void {
    it('contains the party and partyable', function (): void {
        $event = new PartyRestored($this->party, $this->user);

        expect($event->party)->toBeInstanceOf(Party::class)
            ->and($event->partyable)->toBeInstanceOf(Model::class);
    });
});
