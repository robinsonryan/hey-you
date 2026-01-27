<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Contracts\ScopeHierarchyResolver;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointPurpose;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyRelationship;
use RobinsonRyan\HeyYou\Models\RoleAssignment;
use RobinsonRyan\HeyYou\Resolver\ResolverConstraints;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;

beforeEach(function () {
    $this->resolver = app(ContactResolver::class);
    $this->scopeResolver = app(ScopeHierarchyResolver::class);
});

it('resolves scope hierarchy from location to organization', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    $location = Party::create([
        'partyable_type' => 'Location',
        'partyable_id' => 1,
        'display_name_cached' => 'Warehouse A',
    ]);

    PartyRelationship::create([
        'from_party_id' => $location->id,
        'to_party_id' => $org->id,
        'relationship_type' => 'location_of',
    ]);

    $hierarchy = $this->scopeResolver->resolve($location);

    expect($hierarchy)->toHaveCount(2)
        ->and($hierarchy[0]->id)->toBe($location->id)
        ->and($hierarchy[1]->id)->toBe($org->id);
});

it('resolves scope hierarchy through parent organizations', function () {
    $parentOrg = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Parent Corp',
    ]);

    $childOrg = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 2,
        'display_name_cached' => 'Subsidiary Inc',
    ]);

    $location = Party::create([
        'partyable_type' => 'Location',
        'partyable_id' => 1,
        'display_name_cached' => 'Branch Office',
    ]);

    PartyRelationship::create([
        'from_party_id' => $location->id,
        'to_party_id' => $childOrg->id,
        'relationship_type' => 'location_of',
    ]);

    PartyRelationship::create([
        'from_party_id' => $childOrg->id,
        'to_party_id' => $parentOrg->id,
        'relationship_type' => 'member_of',
    ]);

    $hierarchy = $this->scopeResolver->resolve($location);

    expect($hierarchy)->toHaveCount(3)
        ->and($hierarchy[0]->id)->toBe($location->id)
        ->and($hierarchy[1]->id)->toBe($childOrg->id)
        ->and($hierarchy[2]->id)->toBe($parentOrg->id);
});

it('falls back up scope hierarchy when no contacts at lower scope', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    $location = Party::create([
        'partyable_type' => 'Location',
        'partyable_id' => 1,
        'display_name_cached' => 'Warehouse A',
    ]);

    PartyRelationship::create([
        'from_party_id' => $location->id,
        'to_party_id' => $org->id,
        'relationship_type' => 'location_of',
    ]);

    // Create contact point at org level, not location
    $person = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'AP Contact',
    ]);

    RoleAssignment::create([
        'party_id' => $person->id,
        'scope_party_id' => $org->id,
        'role' => 'accounts_payable_contact',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $person->id,
        'channel' => 'email',
        'value_raw' => 'ap@acme.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $contactPoint->id,
        'purpose' => 'accounts_payable',
    ]);

    $request = new ResolverRequest(
        targetParty: $org,
        purpose: 'accounts_payable',
        channel: 'email',
        scopeParty: $location,
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeFalse()
        ->and($result->best()->contactPoint->id)->toBe($contactPoint->id)
        ->and($result->explanation->fallbackUsed)->toBeTrue();
});

it('prefers contacts at lower scope over higher scope', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    $location = Party::create([
        'partyable_type' => 'Location',
        'partyable_id' => 1,
        'display_name_cached' => 'Warehouse A',
    ]);

    PartyRelationship::create([
        'from_party_id' => $location->id,
        'to_party_id' => $org->id,
        'relationship_type' => 'location_of',
    ]);

    // Create contact at org level
    $orgPerson = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Corp AP',
    ]);

    RoleAssignment::create([
        'party_id' => $orgPerson->id,
        'scope_party_id' => $org->id,
        'role' => 'accounts_payable_contact',
    ]);

    $orgContactPoint = ContactPoint::create([
        'party_id' => $orgPerson->id,
        'channel' => 'email',
        'value_raw' => 'corp-ap@acme.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $orgContactPoint->id,
        'purpose' => 'accounts_payable',
    ]);

    // Create contact at location level
    $locationPerson = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 2,
        'display_name_cached' => 'Location AP',
    ]);

    RoleAssignment::create([
        'party_id' => $locationPerson->id,
        'scope_party_id' => $location->id,
        'role' => 'accounts_payable_contact',
    ]);

    $locationContactPoint = ContactPoint::create([
        'party_id' => $locationPerson->id,
        'channel' => 'email',
        'value_raw' => 'location-ap@acme.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $locationContactPoint->id,
        'purpose' => 'accounts_payable',
    ]);

    $request = new ResolverRequest(
        targetParty: $org,
        purpose: 'accounts_payable',
        channel: 'email',
        scopeParty: $location,
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeFalse()
        ->and($result->best()->contactPoint->id)->toBe($locationContactPoint->id)
        ->and($result->best()->scopeParty->id)->toBe($location->id);
});

it('stops fallback when allowFallback is false', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    $location = Party::create([
        'partyable_type' => 'Location',
        'partyable_id' => 1,
        'display_name_cached' => 'Warehouse A',
    ]);

    PartyRelationship::create([
        'from_party_id' => $location->id,
        'to_party_id' => $org->id,
        'relationship_type' => 'location_of',
    ]);

    // Create contact point at org level only
    $person = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'AP Contact',
    ]);

    RoleAssignment::create([
        'party_id' => $person->id,
        'scope_party_id' => $org->id,
        'role' => 'accounts_payable_contact',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $person->id,
        'channel' => 'email',
        'value_raw' => 'ap@acme.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $contactPoint->id,
        'purpose' => 'accounts_payable',
    ]);

    $request = new ResolverRequest(
        targetParty: $org,
        purpose: 'accounts_payable',
        channel: 'email',
        scopeParty: $location,
        constraints: new ResolverConstraints(allowFallback: false),
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeTrue();
});

it('ignores expired relationships in scope hierarchy', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    $location = Party::create([
        'partyable_type' => 'Location',
        'partyable_id' => 1,
        'display_name_cached' => 'Warehouse A',
    ]);

    // Expired relationship
    PartyRelationship::create([
        'from_party_id' => $location->id,
        'to_party_id' => $org->id,
        'relationship_type' => 'location_of',
        'valid_to' => now()->subDay(),
    ]);

    $hierarchy = $this->scopeResolver->resolve($location);

    // Should only contain the location itself
    expect($hierarchy)->toHaveCount(1)
        ->and($hierarchy[0]->id)->toBe($location->id);
});

it('includes fallback path in explanation', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    $location = Party::create([
        'partyable_type' => 'Location',
        'partyable_id' => 1,
        'display_name_cached' => 'Warehouse A',
    ]);

    PartyRelationship::create([
        'from_party_id' => $location->id,
        'to_party_id' => $org->id,
        'relationship_type' => 'location_of',
    ]);

    // Contact only at org level
    $orgContact = ContactPoint::create([
        'party_id' => $org->id,
        'channel' => 'email',
        'value_raw' => 'info@acme.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $request = new ResolverRequest(
        targetParty: $org,
        purpose: 'general',
        channel: 'email',
        scopeParty: $location,
    );

    $result = $this->resolver->resolve($request);

    expect($result->explanation->fallbackUsed)->toBeTrue()
        ->and($result->explanation->fallbackPath)->not->toBeNull();
});

it('finds shared contact points owned by scope party', function () {
    $org = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Acme Corp',
    ]);

    // Contact point owned directly by the org (shared inbox)
    $sharedEmail = ContactPoint::create([
        'party_id' => $org->id,
        'channel' => 'email',
        'value_raw' => 'ap@acme.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    ContactPointPurpose::create([
        'contact_point_id' => $sharedEmail->id,
        'purpose' => 'accounts_payable',
    ]);

    $request = new ResolverRequest(
        targetParty: $org,
        purpose: 'accounts_payable',
        channel: 'email',
    );

    $result = $this->resolver->resolve($request);

    expect($result->isEmpty())->toBeFalse()
        ->and($result->best()->contactPoint->id)->toBe($sharedEmail->id);
});
