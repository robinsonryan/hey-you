<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Resolver\ResolverConstraints;
use RobinsonRyan\HeyYou\Resolver\ResolverRequest;

it('creates a resolver request with required parameters', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'accounts_payable',
        channel: 'email',
    );

    expect($request->targetParty)->toBe($party)
        ->and($request->purpose)->toBe('accounts_payable')
        ->and($request->channel)->toBe('email')
        ->and($request->scopeParty)->toBeNull()
        ->and($request->constraints)->toBeNull()
        ->and($request->limit)->toBe(10);
});

it('defaults scope party to target party', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'accounts_payable',
        channel: 'email',
    );

    expect($request->getEffectiveScopeParty())->toBe($party);
});

it('uses explicit scope party when provided', function () {
    $targetParty = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Target Party',
    ]);

    $scopeParty = Party::create([
        'partyable_type' => 'Company',
        'partyable_id' => 1,
        'display_name_cached' => 'Scope Party',
    ]);

    $request = new ResolverRequest(
        targetParty: $targetParty,
        purpose: 'accounts_payable',
        channel: 'email',
        scopeParty: $scopeParty,
    );

    expect($request->getEffectiveScopeParty())->toBe($scopeParty);
});

it('provides default constraints when none provided', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'accounts_payable',
        channel: 'email',
    );

    $constraints = $request->getEffectiveConstraints();

    expect($constraints)->toBeInstanceOf(ResolverConstraints::class)
        ->and($constraints->requireVerified)->toBeFalse()
        ->and($constraints->requireConsent)->toBeFalse()
        ->and($constraints->allowFallback)->toBeTrue();
});

it('uses explicit constraints when provided', function () {
    $party = Party::create([
        'partyable_type' => 'User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test Party',
    ]);

    $constraints = new ResolverConstraints(
        requireVerified: true,
        requireConsent: true,
        consentCategory: 'marketing',
        allowFallback: false,
    );

    $request = new ResolverRequest(
        targetParty: $party,
        purpose: 'accounts_payable',
        channel: 'email',
        constraints: $constraints,
    );

    expect($request->getEffectiveConstraints())->toBe($constraints);
});
