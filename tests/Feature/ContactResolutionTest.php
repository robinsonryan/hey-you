<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\ContactPointPurpose;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\RoleAssignment;
use RobinsonRyan\HeyYou\Resolver\ResolverConstraints;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;

beforeEach(function () {
    $this->resolver = app(ContactResolver::class);
});

it('resolves contact points for a party', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'john@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
        'is_primary' => true,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeFalse()
        ->and($result->best())->not->toBeNull()
        ->and($result->best()->contactPoint->id)->toBe($contactPoint->id)
        ->and($result->best()->normalizedValue)->toBe('john@example.com');
});

it('filters contact points by channel', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'john@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'phone',
        'value_raw' => '+15551234567',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'phone',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(1)
        ->and($result->best()->channel)->toBe('phone');
});

it('excludes contact points with blocked status', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'blocked@example.com',
        'status' => ContactPoint::STATUS_BLOCKED,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeTrue()
        ->and($result->explanation->exclusionSummary['status'] ?? 0)->toBe(1);
});

it('excludes contact points blocked by DNC rules', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'john@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => $contactPoint->id,
        'source' => 'user_request',
        'effective_at' => now(),
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeTrue()
        ->and($result->explanation->exclusionSummary['dnc'] ?? 0)->toBe(1);
});

it('requires verification when constraint is set', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'unverified@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
        'is_verified' => false,
    ]);

    $verifiedContactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'verified@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
        constraints: new ResolverConstraints(requireVerified: true),
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(1)
        ->and($result->best()->contactPoint->id)->toBe($verifiedContactPoint->id);
});

it('requires consent when constraint is set', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $noConsentContactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'noconsent@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $consentContactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'consent@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointConsent::create([
        'contact_point_id' => $consentContactPoint->id,
        'purpose_category' => 'marketing',
        'status' => 'opted_in',
        'captured_at' => now(),
        'source' => 'web_form',
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
        constraints: new ResolverConstraints(
            requireConsent: true,
            consentCategory: 'marketing',
        ),
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(1)
        ->and($result->best()->contactPoint->id)->toBe($consentContactPoint->id);
});

it('excludes specified contact point ids', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    $excludedContactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'excluded@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $includedContactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'included@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
        constraints: new ResolverConstraints(
            excludeContactPointIds: [$excludedContactPoint->id],
        ),
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(1)
        ->and($result->best()->contactPoint->id)->toBe($includedContactPoint->id);
});

it('respects limit parameter', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    for ($i = 1; $i <= 5; $i++) {
        ContactPoint::create([
            'party_id' => $party->id,
            'channel' => 'email',
            'value_raw' => "email{$i}@example.com",
            'status' => ContactPoint::STATUS_ACTIVE,
        ]);
    }

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
        limit: 3,
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(3);
});

it('finds contacts via role assignments', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    $person = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'AP Contact',
    ]);

    RoleAssignment::create([
        'party_id' => $person->id,
        'scope_party_id' => $org->id,
        'role' => 'accounts_payable_contact',
        'priority' => 1,
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $person->id,
        'channel' => 'email',
        'value_raw' => 'ap@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $contactPoint->id,
        'purpose' => 'accounts_payable',
        'priority' => 1,
    ]);

    $request = new ResolverRequest(
        targetParty: $org,
        purpose: 'accounts_payable',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeFalse()
        ->and($result->best()->contactPoint->id)->toBe($contactPoint->id)
        ->and($result->best()->matchedRole)->toBe('accounts_payable_contact');
});

it('returns explanation with resolution details', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'john@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'blocked@example.com',
        'status' => ContactPoint::STATUS_BLOCKED,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->explanation->candidatesConsidered)->toBe(2)
        ->and($result->explanation->exclusionSummary)->toBeArray();
});
