<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Consent\ConsentRevoked;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\PartyConsent;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
    $this->party = $this->user->party;
});

describe('ConsentGranted', function () {
    it('contains party consent details', function () {
        $consent = PartyConsent::create([
            'party_id' => $this->party->id,
            'purpose_category' => 'marketing',
            'channel' => 'email',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'web_form',
        ]);

        $event = new ConsentGranted($consent, 'party', 'marketing', 'email');

        expect($event->consent)->toBeInstanceOf(PartyConsent::class)
            ->and($event->level)->toBe('party')
            ->and($event->purposeCategory)->toBe('marketing')
            ->and($event->channel)->toBe('email');
    });

    it('contains contact point consent details', function () {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        $consent = ContactPointConsent::create([
            'contact_point_id' => $contactPoint->id,
            'purpose_category' => 'marketing',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'web_form',
        ]);

        $event = new ConsentGranted($consent, 'contact_point', 'marketing', null);

        expect($event->consent)->toBeInstanceOf(ContactPointConsent::class)
            ->and($event->level)->toBe('contact_point')
            ->and($event->channel)->toBeNull();
    });
});

describe('ConsentRevoked', function () {
    it('contains revoked consent details', function () {
        $consent = PartyConsent::create([
            'party_id' => $this->party->id,
            'purpose_category' => 'marketing',
            'channel' => 'sms',
            'status' => 'opted_out',
            'captured_at' => now(),
            'source' => 'user_request',
        ]);

        $event = new ConsentRevoked($consent, 'party', 'marketing', 'sms');

        expect($event->consent)->toBeInstanceOf(PartyConsent::class)
            ->and($event->level)->toBe('party')
            ->and($event->purposeCategory)->toBe('marketing')
            ->and($event->channel)->toBe('sms');
    });
});
