<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Events\Dnc\DncRuleCreated;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleRemoved;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
});

describe('DncRuleCreated', function () {
    it('contains the DNC rule, party, and scope', function () {
        $dncRule = DoNotContact::create([
            'party_id' => $this->party->id,
            'purpose' => 'marketing',
            'reason' => 'Customer request',
            'source' => 'user_request',
            'effective_at' => now(),
        ]);

        $event = new DncRuleCreated($dncRule, $this->party, 'purpose');

        expect($event->dncRule)->toBeInstanceOf(DoNotContact::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->scope)->toBe('purpose');
    });

    it('handles party-wide DNC scope', function () {
        $dncRule = DoNotContact::create([
            'party_id' => $this->party->id,
            'reason' => 'Full DNC',
            'source' => 'compliance',
            'effective_at' => now(),
        ]);

        $event = new DncRuleCreated($dncRule, $this->party, 'party');

        expect($event->scope)->toBe('party');
    });
});

describe('DncRuleRemoved', function () {
    it('contains the DNC rule, party, and scope', function () {
        $dncRule = DoNotContact::create([
            'party_id' => $this->party->id,
            'channel' => 'email',
            'reason' => 'Channel DNC',
            'source' => 'user_request',
            'effective_at' => now(),
        ]);

        $event = new DncRuleRemoved($dncRule, $this->party, 'channel');

        expect($event->dncRule)->toBeInstanceOf(DoNotContact::class)
            ->and($event->party)->toBeInstanceOf(Party::class)
            ->and($event->scope)->toBe('channel');
    });
});
