<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RobinsonRyan\HeyYou\Events\Address\AddressCreated;
use RobinsonRyan\HeyYou\Events\Address\AddressDeleted;
use RobinsonRyan\HeyYou\Events\Address\AddressRestored;
use RobinsonRyan\HeyYou\Events\Address\AddressUpdated;
use RobinsonRyan\HeyYou\Events\Address\AddressValidated;
use RobinsonRyan\HeyYou\Events\Address\AddressValidationFailed;
use RobinsonRyan\HeyYou\Events\Consent\ConsentGranted;
use RobinsonRyan\HeyYou\Events\Consent\ConsentRevoked;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointBounced;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointCreated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointDeleted;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointMarkedUnreachable;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointPurposeAttached;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointPurposeDetached;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointRestored;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointUpdated;
use RobinsonRyan\HeyYou\Events\ContactPoint\ContactPointVerified;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleCreated;
use RobinsonRyan\HeyYou\Events\Dnc\DncRuleRemoved;
use RobinsonRyan\HeyYou\Events\Party\PartyCreated;
use RobinsonRyan\HeyYou\Events\Party\PartyDeleted;
use RobinsonRyan\HeyYou\Events\Party\PartyRestored;
use RobinsonRyan\HeyYou\Events\Party\PartyUpdated;
use RobinsonRyan\HeyYou\Events\Relationship\RelationshipCreated;
use RobinsonRyan\HeyYou\Events\Relationship\RelationshipDeleted;
use RobinsonRyan\HeyYou\Events\Relationship\RelationshipEnded;
use RobinsonRyan\HeyYou\Events\Relationship\RelationshipUpdated;
use RobinsonRyan\HeyYou\Events\Resolver\ContactResolved;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentCreated;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentDeleted;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentExpired;
use RobinsonRyan\HeyYou\Events\RoleAssignment\RoleAssignmentUpdated;
use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\ContactPointPurpose;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\PartyConsent;
use RobinsonRyan\HeyYou\Models\PartyRelationship;
use RobinsonRyan\HeyYou\Models\RoleAssignment;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

describe('Party events', function (): void {
    it('dispatches PartyCreated when a party is created', function (): void {
        Event::fake([PartyCreated::class]);

        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

        Event::assertDispatched(PartyCreated::class, fn (PartyCreated $event): bool => $event->party->id === $user->party->id
            && $event->partyable->getKey() === $user->getKey());
    });

    it('dispatches PartyUpdated when a party is updated', function (): void {
        Event::fake([PartyUpdated::class]);

        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        Event::assertNotDispatched(PartyUpdated::class);

        $user->party->update(['display_name_cached' => 'Jane Doe']);

        Event::assertDispatched(PartyUpdated::class, fn (PartyUpdated $event): bool => $event->changedAttributes['display_name_cached'] === 'Jane Doe');
    });

    it('dispatches PartyDeleted when a party is deleted', function (): void {
        Event::fake([PartyDeleted::class]);

        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $party = $user->party;
        $party->delete();

        Event::assertDispatched(PartyDeleted::class, fn (PartyDeleted $event): bool => $event->party->id === $party->id);
    });

    it('dispatches PartyRestored when a party is restored', function (): void {
        $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $party = $user->party;
        $party->delete();

        Event::fake([PartyRestored::class]);

        $party->restore();

        Event::assertDispatched(PartyRestored::class, fn (PartyRestored $event): bool => $event->party->id === $party->id);
    });
});

describe('ContactPoint events', function (): void {
    beforeEach(function (): void {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
    });

    it('dispatches ContactPointCreated when a contact point is created', function (): void {
        Event::fake([ContactPointCreated::class]);

        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::assertDispatched(ContactPointCreated::class, fn (ContactPointCreated $event): bool => $event->contactPoint->id === $contactPoint->id
            && $event->party->id === $this->party->id);
    });

    it('dispatches ContactPointUpdated when a contact point is updated', function (): void {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([ContactPointUpdated::class]);

        $contactPoint->update(['label' => 'Work Email']);

        Event::assertDispatched(ContactPointUpdated::class, fn (ContactPointUpdated $event): bool => $event->changedAttributes['label'] === 'Work Email');
    });

    it('dispatches ContactPointVerified when a contact point is verified', function (): void {
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

        Event::assertDispatched(ContactPointVerified::class, fn (ContactPointVerified $event): bool => $event->contactPoint->id === $contactPoint->id
            && $event->method === 'code');
    });

    it('dispatches ContactPointDeleted when a contact point is deleted', function (): void {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([ContactPointDeleted::class]);

        $contactPoint->delete();

        Event::assertDispatched(ContactPointDeleted::class, fn (ContactPointDeleted $event): bool => $event->contactPoint->id === $contactPoint->id);
    });

    it('dispatches ContactPointRestored when a contact point is restored', function (): void {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);
        $contactPoint->delete();

        Event::fake([ContactPointRestored::class]);

        $contactPoint->restore();

        Event::assertDispatched(ContactPointRestored::class, fn (ContactPointRestored $event): bool => $event->contactPoint->id === $contactPoint->id);
    });

    it('dispatches ContactPointBounced when status changes to bounced', function (): void {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
            'status' => ContactPoint::STATUS_ACTIVE,
        ]);

        Event::fake([ContactPointBounced::class, ContactPointUpdated::class]);

        $contactPoint->update(['status' => ContactPoint::STATUS_BOUNCED]);

        Event::assertDispatched(ContactPointBounced::class, fn (ContactPointBounced $event): bool => $event->contactPoint->id === $contactPoint->id);
    });

    it('dispatches ContactPointMarkedUnreachable when status changes to unreachable', function (): void {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
            'status' => ContactPoint::STATUS_ACTIVE,
        ]);

        Event::fake([ContactPointMarkedUnreachable::class, ContactPointUpdated::class]);

        $contactPoint->update(['status' => ContactPoint::STATUS_UNREACHABLE]);

        Event::assertDispatched(ContactPointMarkedUnreachable::class, fn (ContactPointMarkedUnreachable $event): bool => $event->contactPoint->id === $contactPoint->id);
    });

    it('dispatches ContactPointPurposeAttached when a purpose is added', function (): void {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        Event::fake([ContactPointPurposeAttached::class]);

        ContactPointPurpose::create([
            'contact_point_id' => $contactPoint->id,
            'purpose' => 'billing',
            'priority' => 1,
            'is_preferred' => true,
        ]);

        Event::assertDispatched(ContactPointPurposeAttached::class, fn (ContactPointPurposeAttached $event): bool => $event->contactPoint->id === $contactPoint->id
            && $event->purpose === 'billing');
    });

    it('dispatches ContactPointPurposeDetached when a purpose is removed', function (): void {
        $contactPoint = $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);

        $purpose = ContactPointPurpose::create([
            'contact_point_id' => $contactPoint->id,
            'purpose' => 'billing',
            'priority' => 1,
            'is_preferred' => true,
        ]);

        Event::fake([ContactPointPurposeDetached::class]);

        $purpose->delete();

        Event::assertDispatched(ContactPointPurposeDetached::class, fn (ContactPointPurposeDetached $event): bool => $event->contactPoint->id === $contactPoint->id
            && $event->purpose === 'billing');
    });
});

describe('Consent events', function (): void {
    beforeEach(function (): void {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
    });

    it('dispatches ConsentGranted when party consent is created with opted_in', function (): void {
        Event::fake([ConsentGranted::class]);

        PartyConsent::create([
            'party_id' => $this->party->id,
            'purpose_category' => 'marketing',
            'channel' => 'email',
            'status' => 'opted_in',
            'captured_at' => now(),
            'source' => 'web_form',
        ]);

        Event::assertDispatched(ConsentGranted::class, fn (ConsentGranted $event): bool => $event->level === 'party'
            && $event->purposeCategory === 'marketing'
            && $event->channel === 'email');
    });

    it('dispatches ConsentRevoked when party consent is created with opted_out', function (): void {
        Event::fake([ConsentRevoked::class]);

        PartyConsent::create([
            'party_id' => $this->party->id,
            'purpose_category' => 'marketing',
            'channel' => 'sms',
            'status' => 'opted_out',
            'captured_at' => now(),
            'source' => 'user_request',
        ]);

        Event::assertDispatched(ConsentRevoked::class, fn (ConsentRevoked $event): bool => $event->level === 'party'
            && $event->purposeCategory === 'marketing');
    });

    it('dispatches ConsentGranted when contact point consent is granted', function (): void {
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

        Event::assertDispatched(ConsentGranted::class, fn (ConsentGranted $event): bool => $event->level === 'contact_point'
            && $event->purposeCategory === 'transactional');
    });
});

describe('DNC events', function (): void {
    beforeEach(function (): void {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
    });

    it('dispatches DncRuleCreated when a DNC rule is created', function (): void {
        Event::fake([DncRuleCreated::class]);

        DoNotContact::create([
            'party_id' => $this->party->id,
            'purpose' => 'marketing',
            'reason' => 'Customer request',
            'source' => 'user_request',
            'effective_at' => now(),
        ]);

        Event::assertDispatched(DncRuleCreated::class, fn (DncRuleCreated $event): bool => $event->scope === 'purpose'
            && $event->party->id === $this->party->id);
    });

    it('dispatches DncRuleRemoved when a DNC rule is deleted', function (): void {
        $dnc = DoNotContact::create([
            'party_id' => $this->party->id,
            'channel' => 'sms',
            'reason' => 'Test',
            'source' => 'test',
            'effective_at' => now(),
        ]);

        Event::fake([DncRuleRemoved::class]);

        $dnc->delete();

        Event::assertDispatched(DncRuleRemoved::class, fn (DncRuleRemoved $event): bool => $event->dncRule->id === $dnc->id
            && $event->scope === 'channel');
    });

    it('determines correct scope for party-wide DNC', function (): void {
        Event::fake([DncRuleCreated::class]);

        DoNotContact::create([
            'party_id' => $this->party->id,
            'reason' => 'Full DNC',
            'source' => 'compliance',
            'effective_at' => now(),
        ]);

        Event::assertDispatched(DncRuleCreated::class, fn (DncRuleCreated $event): bool => $event->scope === 'party');
    });

    it('determines correct scope for contact point DNC', function (): void {
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

        Event::assertDispatched(DncRuleCreated::class, fn (DncRuleCreated $event): bool => $event->scope === 'contact_point');
    });
});

describe('Resolver events', function (): void {
    beforeEach(function (): void {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
        $this->party->contactPoints()->create([
            'channel' => 'email',
            'value_raw' => 'test@example.com',
        ]);
    });

    it('dispatches ContactResolved when resolver is called', function (): void {
        Event::fake([ContactResolved::class]);

        $resolver = app(RobinsonRyan\HeyYou\Contracts\ContactResolver::class);
        $request = new ResolverRequest(
            targetParty: $this->party,
            purpose: 'general',
            channel: 'email',
        );

        $resolver->resolve($request);

        Event::assertDispatched(ContactResolved::class, fn (ContactResolved $event): bool => $event->request->purpose === 'general'
            && $event->request->channel === 'email');
    });
});

describe('Address events', function (): void {
    beforeEach(function (): void {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->party = $this->user->party;
    });

    it('dispatches AddressCreated when an address is created', function (): void {
        Event::fake([AddressCreated::class]);

        $address = Address::factory()->forParty($this->party)->create();

        Event::assertDispatched(AddressCreated::class, fn (AddressCreated $event): bool => $event->address->id === $address->id
            && $event->party->id === $this->party->id);
    });

    it('dispatches AddressUpdated when an address is updated', function (): void {
        $address = Address::factory()->forParty($this->party)->create();

        Event::fake([AddressUpdated::class]);

        $address->update(['line1' => '456 New Street']);

        Event::assertDispatched(AddressUpdated::class, fn (AddressUpdated $event): bool => $event->changedAttributes['line1'] === '456 New Street');
    });

    it('dispatches AddressDeleted when an address is deleted', function (): void {
        $address = Address::factory()->forParty($this->party)->create();

        Event::fake([AddressDeleted::class]);

        $address->delete();

        Event::assertDispatched(AddressDeleted::class, fn (AddressDeleted $event): bool => $event->address->id === $address->id);
    });

    it('dispatches AddressRestored when an address is restored', function (): void {
        $address = Address::factory()->forParty($this->party)->create();
        $address->delete();

        Event::fake([AddressRestored::class]);

        $address->restore();

        Event::assertDispatched(AddressRestored::class, fn (AddressRestored $event): bool => $event->address->id === $address->id);
    });

    it('dispatches AddressValidated when validation_status changes to verified', function (): void {
        $address = Address::factory()->forParty($this->party)->create([
            'validation_status' => 'unverified',
        ]);

        Event::fake([AddressValidated::class, AddressUpdated::class]);

        $address->update(['validation_status' => 'verified']);

        Event::assertDispatched(AddressValidated::class, fn (AddressValidated $event): bool => $event->address->id === $address->id);
    });

    it('dispatches AddressValidationFailed when validation_status changes to invalid', function (): void {
        $address = Address::factory()->forParty($this->party)->create([
            'validation_status' => 'unverified',
        ]);

        Event::fake([AddressValidationFailed::class, AddressUpdated::class]);

        $address->update(['validation_status' => 'invalid']);

        Event::assertDispatched(AddressValidationFailed::class, fn (AddressValidationFailed $event): bool => $event->address->id === $address->id);
    });
});

describe('Relationship events', function (): void {
    beforeEach(function (): void {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->company = Company::create(['legal_name' => 'Acme Corp']);
        $this->fromParty = $this->user->party;
        $this->toParty = $this->company->party;
    });

    it('dispatches RelationshipCreated when a relationship is created', function (): void {
        Event::fake([RelationshipCreated::class]);

        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);

        Event::assertDispatched(RelationshipCreated::class, fn (RelationshipCreated $event): bool => $event->relationship->id === $relationship->id
            && $event->fromParty->id === $this->fromParty->id
            && $event->toParty->id === $this->toParty->id);
    });

    it('dispatches RelationshipUpdated when a relationship is updated', function (): void {
        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);

        Event::fake([RelationshipUpdated::class]);

        $relationship->update(['label' => 'Senior Developer']);

        Event::assertDispatched(RelationshipUpdated::class, fn (RelationshipUpdated $event): bool => $event->changedAttributes['label'] === 'Senior Developer');
    });

    it('dispatches RelationshipEnded when valid_to is set', function (): void {
        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);

        Event::fake([RelationshipEnded::class, RelationshipUpdated::class]);

        $relationship->update(['valid_to' => now()]);

        Event::assertDispatched(RelationshipEnded::class, fn (RelationshipEnded $event): bool => $event->relationship->id === $relationship->id);
    });

    it('dispatches RelationshipDeleted when a relationship is deleted', function (): void {
        $relationship = PartyRelationship::create([
            'from_party_id' => $this->fromParty->id,
            'to_party_id' => $this->toParty->id,
            'relationship_type' => 'employment',
        ]);

        Event::fake([RelationshipDeleted::class]);

        $relationship->delete();

        Event::assertDispatched(RelationshipDeleted::class, fn (RelationshipDeleted $event): bool => $event->relationship->id === $relationship->id);
    });
});

describe('RoleAssignment events', function (): void {
    beforeEach(function (): void {
        $this->user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);
        $this->company = Company::create(['legal_name' => 'Acme Corp']);
        $this->party = $this->user->party;
        $this->scopeParty = $this->company->party;
    });

    it('dispatches RoleAssignmentCreated when a role assignment is created', function (): void {
        Event::fake([RoleAssignmentCreated::class]);

        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);

        Event::assertDispatched(RoleAssignmentCreated::class, fn (RoleAssignmentCreated $event): bool => $event->roleAssignment->id === $roleAssignment->id
            && $event->party->id === $this->party->id
            && $event->scopeParty->id === $this->scopeParty->id);
    });

    it('dispatches RoleAssignmentUpdated when a role assignment is updated', function (): void {
        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);

        Event::fake([RoleAssignmentUpdated::class]);

        $roleAssignment->update(['priority' => 5]);

        Event::assertDispatched(RoleAssignmentUpdated::class, fn (RoleAssignmentUpdated $event): bool => $event->changedAttributes['priority'] === 5);
    });

    it('dispatches RoleAssignmentExpired when valid_to is set', function (): void {
        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);

        Event::fake([RoleAssignmentExpired::class, RoleAssignmentUpdated::class]);

        $roleAssignment->update(['valid_to' => now()]);

        Event::assertDispatched(RoleAssignmentExpired::class, fn (RoleAssignmentExpired $event): bool => $event->roleAssignment->id === $roleAssignment->id);
    });

    it('dispatches RoleAssignmentDeleted when a role assignment is deleted', function (): void {
        $roleAssignment = RoleAssignment::create([
            'party_id' => $this->party->id,
            'scope_party_id' => $this->scopeParty->id,
            'role' => 'accounts_payable_contact',
        ]);

        Event::fake([RoleAssignmentDeleted::class]);

        $roleAssignment->delete();

        Event::assertDispatched(RoleAssignmentDeleted::class, fn (RoleAssignmentDeleted $event): bool => $event->roleAssignment->id === $roleAssignment->id);
    });
});
