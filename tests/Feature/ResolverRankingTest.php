<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointPurpose;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;

beforeEach(function () {
    $this->resolver = app(ContactResolver::class);
});

it('ranks active status higher than inactive', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    Carbon::setTestNow(now()->subDay());

    $inactive = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'inactive@example.com',
        'status' => ContactPoint::STATUS_INACTIVE,
    ]);

    Carbon::setTestNow(now());

    $active = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'active@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(2)
        ->and($result->matches[0]->contactPoint->id)->toBe($active->id)
        ->and($result->matches[1]->contactPoint->id)->toBe($inactive->id);
});

it('ranks verified higher than unverified', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    Carbon::setTestNow(now()->subDay());

    $unverified = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'unverified@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
        'is_verified' => false,
    ]);

    Carbon::setTestNow(now());

    $verified = ContactPoint::create([
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
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(2)
        ->and($result->matches[0]->contactPoint->id)->toBe($verified->id)
        ->and($result->matches[1]->contactPoint->id)->toBe($unverified->id);
});

it('ranks primary higher than non-primary', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    Carbon::setTestNow(now()->subDay());

    $nonPrimary = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'secondary@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
        'is_primary' => false,
    ]);

    Carbon::setTestNow(now());

    $primary = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'primary@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
        'is_primary' => true,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(2)
        ->and($result->matches[0]->contactPoint->id)->toBe($primary->id)
        ->and($result->matches[1]->contactPoint->id)->toBe($nonPrimary->id);
});

it('ranks exact purpose match higher than no purpose', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    Carbon::setTestNow(now()->subDay());

    $noPurpose = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'general@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    Carbon::setTestNow(now());

    $withPurpose = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'billing@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $withPurpose->id,
        'purpose' => 'billing',
        'priority' => 1,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'billing',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(2)
        ->and($result->matches[0]->contactPoint->id)->toBe($withPurpose->id)
        ->and($result->matches[1]->contactPoint->id)->toBe($noPurpose->id);
});

it('ranks exact purpose match higher than parent purpose match', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    Carbon::setTestNow(now()->subDay());

    $parentPurpose = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'billing@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $parentPurpose->id,
        'purpose' => 'billing',
        'priority' => 1,
    ]);

    Carbon::setTestNow(now());

    $exactPurpose = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'ap@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $exactPurpose->id,
        'purpose' => 'accounts_payable',
        'priority' => 1,
    ]);

    // accounts_payable has billing as parent in config
    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'accounts_payable',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(2)
        ->and($result->matches[0]->contactPoint->id)->toBe($exactPurpose->id)
        ->and($result->matches[0]->matchedPurpose)->toBe('accounts_payable')
        ->and($result->matches[1]->contactPoint->id)->toBe($parentPurpose->id)
        ->and($result->matches[1]->matchedPurpose)->toBe('billing');
});

it('uses purpose priority as tiebreaker', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    Carbon::setTestNow(now()->subDay());

    $lowPriority = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'low@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $lowPriority->id,
        'purpose' => 'billing',
        'priority' => 10,
    ]);

    Carbon::setTestNow(now());

    $highPriority = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'high@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $highPriority->id,
        'purpose' => 'billing',
        'priority' => 1,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'billing',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(2)
        ->and($result->matches[0]->contactPoint->id)->toBe($highPriority->id)
        ->and($result->matches[1]->contactPoint->id)->toBe($lowPriority->id);
});

it('uses created_at as final tiebreaker (older first)', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    Carbon::setTestNow(now()->subDays(2));

    $older = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'older@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    Carbon::setTestNow(now());

    $newer = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'newer@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(2)
        ->and($result->matches[0]->contactPoint->id)->toBe($older->id)
        ->and($result->matches[1]->contactPoint->id)->toBe($newer->id);
});

it('ranks status in correct order: active > inactive > bounced > unreachable', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);

    // Create in reverse order to ensure sorting is based on status
    Carbon::setTestNow(now()->subDays(4));
    $unreachable = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'unreachable@example.com',
        'status' => ContactPoint::STATUS_UNREACHABLE,
    ]);

    Carbon::setTestNow(now()->subDays(3));
    $bounced = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'bounced@example.com',
        'status' => ContactPoint::STATUS_BOUNCED,
    ]);

    Carbon::setTestNow(now()->subDays(2));
    $inactive = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'inactive@example.com',
        'status' => ContactPoint::STATUS_INACTIVE,
    ]);

    Carbon::setTestNow(now()->subDay());
    $active = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'active@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    Carbon::setTestNow(now());

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->matches)->toHaveCount(4)
        ->and($result->matches[0]->contactPoint->status)->toBe(ContactPoint::STATUS_ACTIVE)
        ->and($result->matches[1]->contactPoint->status)->toBe(ContactPoint::STATUS_INACTIVE)
        ->and($result->matches[2]->contactPoint->status)->toBe(ContactPoint::STATUS_BOUNCED)
        ->and($result->matches[3]->contactPoint->status)->toBe(ContactPoint::STATUS_UNREACHABLE);
});

it('includes correct flags in match result', function () {
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
        'is_verified' => true,
        'verified_at' => now(),
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->best()->flags)
        ->toHaveKey('verified')
        ->toHaveKey('is_primary');
});
