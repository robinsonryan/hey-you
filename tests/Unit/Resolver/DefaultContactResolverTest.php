<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Contracts\ContactResolver;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Resolver\DefaultContactResolver;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;
use RobinsonRyan\HeyYou\Resolver\ResolverResult;

it('implements ContactResolver contract', function () {
    $resolver = app(ContactResolver::class);

    expect($resolver)->toBeInstanceOf(DefaultContactResolver::class);
});

it('returns ResolverResult from resolve method', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $resolver = app(ContactResolver::class);
    $result = $resolver->resolve($request);

    expect($result)->toBeInstanceOf(ResolverResult::class);
});

it('returns empty result when no contact points exist', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $resolver = app(ContactResolver::class);
    $result = $resolver->resolve($request);

    expect($result->isEmpty())->toBeTrue()
        ->and($result->best())->toBeNull();
});

it('returns empty result when no matching channel exists', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
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
        channel: 'email',
    );

    $resolver = app(ContactResolver::class);
    $result = $resolver->resolve($request);

    expect($result->isEmpty())->toBeTrue();
});

it('populates match with correct owning party', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'status' => ContactPoint::STATUS_ACTIVE,
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $resolver = app(ContactResolver::class);
    $result = $resolver->resolve($request);

    expect($result->best()->owningParty->id)->toBe($party->id);
});

it('assigns sequential ranks to matches', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    for ($i = 0; $i < 3; $i++) {
        ContactPoint::create([
            'party_id' => $party->id,
            'channel' => 'email',
            'value_raw' => "test{$i}@example.com",
            'status' => ContactPoint::STATUS_ACTIVE,
        ]);
    }

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'general',
        channel: 'email',
    );

    $resolver = app(ContactResolver::class);
    $result = $resolver->resolve($request);

    expect($result->matches[0]->rank)->toBe(1)
        ->and($result->matches[1]->rank)->toBe(2)
        ->and($result->matches[2]->rank)->toBe(3);
});
