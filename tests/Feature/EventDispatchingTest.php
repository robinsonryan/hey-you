<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Consent\ConsentRevoked;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleCreated;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleRemoved;
use RobinsonRyan\HeyYou\Events\Party\PartyCreated;
use RobinsonRyan\HeyYou\Events\Party\PartyDeleted;
use RobinsonRyan\HeyYou\Events\Party\PartyUpdated;
use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\PartyConsent;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

describe('Party events', function () {
    it('dispatches PartyCreated when a party is created', function () {
        Event::fake([PartyCreated::class]);

        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        Event::assertDispatched(PartyCreated::class, function (PartyCreated $event) use ($user) {
            return $event->party->id === $user->party->id
                && $event->partyable->getKey() === $user->getKey();
        });
    });

    it('dispatches PartyUpdated when a party is updated', function () {
        Event::fake([PartyUpdated::class]);

        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Event::assertNotDispatched(PartyUpdated::class);

        $user->party->update(['display_name_cached' => 'Jane Doe']);

        Event::assertDispatched(PartyUpdated::class, function (PartyUpdated $event) {
            return $event->changedAttributes['display_name_cached'] === 'Jane Doe';
        });
    });

    it('dispatches PartyDeleted when a party is deleted', function () {
        Event::fake([PartyDeleted::class]);

        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $party = $user->party;
        $party->delete();

        Event::assertDispatched(PartyDeleted::class, function (PartyDeleted $event) use ($party) {
            return $event->party->id === $party->id;
        });
    });
});

describe('ContactPoint events', function () {
    beforeEach(function () {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
    });

    it('dispatches ContactPointCreated when a contact point is created', function () {
        Event::fake([ContactPointCreated::class]);

        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::assertDispatched(ContactPointCreated::class, function (ContactPointCreated $event) use ($contactPoint) {
            return $event->contactPoint->id === $contactPoint->id
                && $event->party->id === $this->party->id;
        });
    });

    it('dispatches ContactPointUpdated when a contact point is updated', function () {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([ContactPointUpdated::class]);

        $contactPoint->update(['label' => 'Work Email']);

        Event::assertDispatched(ContactPointUpdated::class, function (ContactPointUpdated $event) {
            return $event->changedAttributes['label'] === 'Work Email';
        });
    });

    it('dispatches ContactPointVerified when a contact point is verified', function () {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([ContactPointVerified::class, ContactPointUpdated::class]);

        $contactPoint->update([
            'is_verified' => true,
            'verification_method' => 'code',
            'verified_at' => now(),
        ]);

        Event::assertDispatched(ContactPointVerified::class, function (ContactPointVerified $event) use ($contactPoint) {
            return $event->contactPoint->id === $contactPoint->id
                && $event->method === 'code';
        });
    });

    it('dispatches ContactPointDeleted when a contact point is deleted', function () {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([ContactPointDeleted::class]);

        $contactPoint->delete();

        Event::assertDispatched(ContactPointDeleted::class, function (ContactPointDeleted $event) use ($contactPoint) {
            return $event->contactPoint->id === $contactPoint->id;
        });
    });
});

describe('Consent events', function () {
    beforeEach(function () {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
    });

    it('dispatches ConsentGranted when party consent is created with opted_in', function () {
        Event::fake([ConsentGranted::class]);

        PartyConsent::create([
            'party_id' => $this->party->id,
            'purpose_category' => 'marketing',
            'channel' => 'email',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'web_form',
        ]);

        Event::assertDispatched(ConsentGranted::class, function (ConsentGranted $event) {
            return $event->level === 'party'
                && $event->purposeCategory === 'marketing'
                && $event->channel === 'email';
        });
    });

    it('dispatches ConsentRevoked when party consent is created with opted_out', function () {
        Event::fake([ConsentRevoked::class]);

        PartyConsent::create([
            'party_id' => $this->party->id,
            'purpose_category' => 'marketing',
            'channel' => 'sms',
            'status' => 'opted_out',
            'captured_at' => now(),
            'source' => 'user_request',
        ]);

        Event::assertDispatched(ConsentRevoked::class, function (ConsentRevoked $event) {
            return $event->level === 'party'
                && $event->purposeCategory === 'marketing';
        });
    });

    it('dispatches ConsentGranted when contact point consent is granted', function () {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([ConsentGranted::class]);

        ContactPointConsent::create([
            'contact_point_id' => $contactPoint->id,
            'purpose_category' => 'transactional',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'web_form',
        ]);

        Event::assertDispatched(ConsentGranted::class, function (ConsentGranted $event) {
            return $event->level === 'contact_point'
                && $event->purposeCategory === 'transactional';
        });
    });
});

describe('DNC events', function () {
    beforeEach(function () {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
    });

    it('dispatches DncRuleCreated when a DNC rule is created', function () {
        Event::fake([DncRuleCreated::class]);

        DoNotContact::create([
            'party_id' => $this->party->id,
            'purpose' => 'marketing',
            'reason' => 'Customer request',
            'source' => 'user_request',
            'effective_at' => now(),
        ]);

        Event::assertDispatched(DncRuleCreated::class, function (DncRuleCreated $event) {
            return $event->scope === 'purpose'
                && $event->party->id === $this->party->id;
        });
    });

    it('dispatches DncRuleRemoved when a DNC rule is deleted', function () {
        $dnc = DoNotContact::create([
            'party_id' => $this->party->id,
            'channel' => 'sms',
            'reason' => 'Test',
            'source' => 'test',
            'effective_at' => now(),
        ]);

        Event::fake([DncRuleRemoved::class]);

        $dnc->delete();

        Event::assertDispatched(DncRuleRemoved::class, function (DncRuleRemoved $event) use ($dnc) {
            return $event->dncRule->id === $dnc->id
                && $event->scope === 'channel';
        });
    });

    it('determines correct scope for party-wide DNC', function () {
        Event::fake([DncRuleCreated::class]);

        DoNotContact::create([
            'party_id' => $this->party->id,
            'reason' => 'Full DNC',
            'source' => 'compliance',
            'effective_at' => now(),
        ]);

        Event::assertDispatched(DncRuleCreated::class, function (DncRuleCreated $event) {
            return $event->scope === 'party';
        });
    });

    it('determines correct scope for contact point DNC', function () {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([DncRuleCreated::class]);

        DoNotContact::create([
            'party_id' => $this->party->id,
            'contact_point_id' => $contactPoint->id,
            'reason' => 'Bounced',
            'source' => 'system',
            'effective_at' => now(),
        ]);

        Event::assertDispatched(DncRuleCreated::class, function (DncRuleCreated $event) {
            return $event->scope === 'contact_point';
        });
    });
});

describe('Resolver events', function () {
    beforeEach(function () {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
        $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);
    });

    it('dispatches ContactResolved when resolver is called', function () {
        Event::fake([ContactResolved::class]);

        $resolver = app(RobinsonRyan\HeyYou\Contracts\ContactResolver::class);
        $request = new ResolverRequest(
            targetParty: $this->party,
            purpose: 'general',
            channel: 'email',
        );

        $resolver->resolve($request);

        Event::assertDispatched(ContactResolved::class, function (ContactResolved $event) {
            return $event->request->purpose === 'general'
                && $event->request->channel === 'email';
        });
    });
});
