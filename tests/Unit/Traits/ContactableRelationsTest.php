<?php

declare(strict_types=1);

use RobinsonRyan\HeyYou\Models\Address;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\Company;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\LegacyAccount;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\LegacyVendor;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

/**
 * The consumer-level shortcuts documented in spec §3.2, docs/contact-points.md
 * and docs/addresses.md: `$user->contactPoints` and `$user->addresses`, hopping
 * consumer -> heyyou_parties -> the party's rows.
 */

/**
 * Give a contact point to a consumer's party.
 */
function contactPointFor(object $consumer, string $value, string $channel = 'email'): ContactPoint
{
    return ContactPoint::factory()->create([
        'party_id' => $consumer->party->id,
        'channel' => $channel,
        'value_raw' => $value,
    ]);
}

/**
 * Give an address to a consumer's party.
 */
function addressFor(object $consumer, string $line1, string $purpose = 'general'): Address
{
    return Address::factory()->create([
        'party_id' => $consumer->party->id,
        'purpose' => $purpose,
        'line1' => $line1,
    ]);
}

// ---------------------------------------------------------------------------
// R1 / R2 — the relations exist and read the party's rows
// ---------------------------------------------------------------------------

it('reads contact points straight off a consumer', function (): void {
    $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

    contactPointFor($user, 'john@example.com');
    contactPointFor($user, '+15551234567', 'phone');

    expect($user->contactPoints)->toHaveCount(2)
        ->and($user->contactPoints->pluck('value_normalized')->sort()->values()->all())
        ->toBe(['+15551234567', 'john@example.com']);
});

it('filters a consumer\'s contact points by channel', function (): void {
    $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

    contactPointFor($user, 'john@example.com');
    contactPointFor($user, '+15551234567', 'phone');

    $phones = $user->contactPoints()->where('channel', 'phone')->get();

    expect($phones)->toHaveCount(1)
        ->and($phones->first()->value_normalized)->toBe('+15551234567');
});

it('reads addresses straight off a consumer', function (): void {
    $company = Company::create(['legal_name' => 'Acme Corp']);

    addressFor($company, '1 Billing Way', 'billing');
    addressFor($company, '2 Shipping Road', 'shipping');

    expect($company->addresses)->toHaveCount(2);
});

it('filters a consumer\'s addresses by purpose', function (): void {
    $company = Company::create(['legal_name' => 'Acme Corp']);

    addressFor($company, '1 Billing Way', 'billing');
    addressFor($company, '2 Shipping Road', 'shipping');

    $billing = $company->addresses()->where('purpose', 'billing')->get();

    expect($billing)->toHaveCount(1)
        ->and($billing->first()->line1)->toBe('1 Billing Way');
});

it('returns an empty collection for a consumer with no contact points or addresses', function (): void {
    $user = User::create(['name' => 'Jane Doe', 'email' => 'jane@example.com']);

    expect($user->contactPoints)->toHaveCount(0)
        ->and($user->addresses)->toHaveCount(0);
});

it('eager loads consumer contact points and addresses', function (): void {
    $first = Company::create(['legal_name' => 'First Corp']);
    $second = Company::create(['legal_name' => 'Second Corp']);

    contactPointFor($first, 'first@example.com');
    addressFor($first, '1 First Street');
    addressFor($second, '2 Second Street');

    $companies = Company::query()
        ->with(['contactPoints', 'addresses'])
        ->orderBy('legal_name')
        ->get();

    expect($companies)->toHaveCount(2)
        ->and($companies[0]->relationLoaded('contactPoints'))->toBeTrue()
        ->and($companies[0]->contactPoints)->toHaveCount(1)
        ->and($companies[0]->addresses->pluck('line1')->all())->toBe(['1 First Street'])
        ->and($companies[1]->contactPoints)->toHaveCount(0)
        ->and($companies[1]->addresses->pluck('line1')->all())->toBe(['2 Second Street']);
});

it('constrains existence queries on consumer contact points', function (): void {
    $withContact = User::create(['name' => 'Has Contact', 'email' => 'has@example.com']);
    User::create(['name' => 'No Contact', 'email' => 'none@example.com']);

    contactPointFor($withContact, 'has@example.com');

    $matched = User::query()->has('contactPoints')->get();

    expect($matched)->toHaveCount(1)
        ->and($matched->first()->id)->toBe($withContact->id);
});

// ---------------------------------------------------------------------------
// R4 — integer-keyed consumers. `heyyou_parties.partyable_id` is a varchar and
// PostgreSQL has no implicit bigint/varchar cast, so eager loading and
// existence queries both fail without deliberate coercion.
// ---------------------------------------------------------------------------

it('reads contact points off an integer-keyed consumer', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    contactPointFor($account, 'billing@legacy.example');

    expect($account->contactPoints->pluck('value_normalized')->all())
        ->toBe(['billing@legacy.example']);
});

it('reads addresses off an integer-keyed consumer', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    addressFor($account, '9 Legacy Lane');

    expect($account->addresses->pluck('line1')->all())->toBe(['9 Legacy Lane']);
});

it('eager loads contact points for integer-keyed consumers', function (): void {
    $first = LegacyAccount::create(['name' => 'First Legacy']);
    $second = LegacyAccount::create(['name' => 'Second Legacy']);

    contactPointFor($first, 'first@legacy.example');
    contactPointFor($second, 'second@legacy.example');

    $accounts = LegacyAccount::query()->with('contactPoints')->orderBy('name')->get();

    expect($accounts)->toHaveCount(2)
        ->and($accounts[0]->contactPoints->pluck('value_normalized')->all())
        ->toBe(['first@legacy.example'])
        ->and($accounts[1]->contactPoints->pluck('value_normalized')->all())
        ->toBe(['second@legacy.example']);
});

it('eager loads addresses for integer-keyed consumers', function (): void {
    $account = LegacyAccount::create(['name' => 'Legacy Widgets']);

    addressFor($account, '9 Legacy Lane');

    $accounts = LegacyAccount::query()->with('addresses')->get();

    expect($accounts->first()->addresses->pluck('line1')->all())->toBe(['9 Legacy Lane']);
});

it('constrains existence queries on integer-keyed consumer contact points', function (): void {
    $withContact = LegacyAccount::create(['name' => 'Has Contact']);
    LegacyAccount::create(['name' => 'No Contact']);

    contactPointFor($withContact, 'has@legacy.example');

    $matched = LegacyAccount::query()->has('contactPoints')->get();

    expect($matched)->toHaveCount(1)
        ->and($matched->first()->id)->toBe($withContact->id);
});

it('constrains existence queries on integer-keyed consumer addresses', function (): void {
    $withAddress = LegacyAccount::create(['name' => 'Has Address']);
    LegacyAccount::create(['name' => 'No Address']);

    addressFor($withAddress, '9 Legacy Lane');

    $matched = LegacyAccount::query()->whereHas('addresses', function ($query): void {
        $query->where('purpose', 'general');
    })->get();

    expect($matched)->toHaveCount(1)
        ->and($matched->first()->id)->toBe($withAddress->id);
});

// ---------------------------------------------------------------------------
// R3 — the load-bearing invariant. Two bigint-keyed consumer tables both count
// from 1, so their `partyable_id` strings collide exactly. Without the
// `partyable_type` constraint every assertion below sees two rows instead of
// one. UUID-keyed fixtures cannot prove this: UUIDs never collide.
// ---------------------------------------------------------------------------

/**
 * Two integer-keyed consumers of different types, forced onto the same key.
 *
 * @return array{0: LegacyAccount, 1: LegacyVendor}
 */
function collidingConsumers(): array
{
    $account = new LegacyAccount(['id' => 4242, 'name' => 'Colliding Account']);
    $account->save();

    $vendor = new LegacyVendor(['id' => 4242, 'name' => 'Colliding Vendor']);
    $vendor->save();

    // The collision is the whole point of the fixture — assert it, so the test
    // cannot quietly stop testing anything if the ids ever drift apart.
    expect($account->id)->toBe($vendor->id)
        ->and($account->party->partyable_id)->toBe($vendor->party->partyable_id);

    contactPointFor($account, 'account@collide.example');
    contactPointFor($vendor, 'vendor@collide.example');

    addressFor($account, '1 Account Alley');
    addressFor($vendor, '1 Vendor Vale');

    return [$account, $vendor];
}

it('never leaks another consumer type\'s contact points on a key collision', function (): void {
    [$account, $vendor] = collidingConsumers();

    expect($account->contactPoints->pluck('value_normalized')->all())
        ->toBe(['account@collide.example'])
        ->and($vendor->contactPoints->pluck('value_normalized')->all())
        ->toBe(['vendor@collide.example']);
});

it('never leaks another consumer type\'s addresses on a key collision', function (): void {
    [$account, $vendor] = collidingConsumers();

    expect($account->addresses->pluck('line1')->all())->toBe(['1 Account Alley'])
        ->and($vendor->addresses->pluck('line1')->all())->toBe(['1 Vendor Vale']);
});

it('never leaks another consumer type\'s rows through an eager load on a key collision', function (): void {
    collidingConsumers();

    $accounts = LegacyAccount::query()->with(['contactPoints', 'addresses'])->get();
    $vendors = LegacyVendor::query()->with(['contactPoints', 'addresses'])->get();

    expect($accounts->first()->contactPoints->pluck('value_normalized')->all())
        ->toBe(['account@collide.example'])
        ->and($accounts->first()->addresses->pluck('line1')->all())->toBe(['1 Account Alley'])
        ->and($vendors->first()->contactPoints->pluck('value_normalized')->all())
        ->toBe(['vendor@collide.example'])
        ->and($vendors->first()->addresses->pluck('line1')->all())->toBe(['1 Vendor Vale']);
});

it('never leaks another consumer type\'s rows through whereHas on a key collision', function (): void {
    collidingConsumers();

    // Only the vendor owns `vendor@collide.example`, so the account must not match.
    $accounts = LegacyAccount::query()->whereHas('contactPoints', function ($query): void {
        $query->where('value_normalized', 'vendor@collide.example');
    })->get();

    $vendors = LegacyVendor::query()->whereHas('contactPoints', function ($query): void {
        $query->where('value_normalized', 'vendor@collide.example');
    })->get();

    expect($accounts)->toHaveCount(0)
        ->and($vendors)->toHaveCount(1);
});

// ---------------------------------------------------------------------------
// Shape of the emitted SQL. The cast belongs on the correlated column of an
// existence query and nowhere else — an ordinary read binds a value, and a
// bound string compares against a varchar column without help.
// ---------------------------------------------------------------------------

it('constrains existence queries on the party relation of a uuid-keyed consumer', function (): void {
    $withParty = User::create(['name' => 'Has Party', 'email' => 'has@example.com']);

    User::query()->insert([
        'id' => fakePartyableId(),
        'name' => 'No Party',
        'email' => 'none@example.com',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $matched = User::query()->has('party')->get();

    expect($matched)->toHaveCount(1)
        ->and($matched->first()->id)->toBe($withParty->id);
});

it('casts the consumer key in an existence query, whatever its key type', function (): void {
    // A native PostgreSQL `uuid` column has no more of an implicit cast to
    // `character varying` than a bigint does, and Eloquent reports 'string' for
    // both a uuid and a varchar key — so the cast cannot be conditioned on the
    // key type. Both consumer shapes get it.
    expect(LegacyAccount::query()->has('contactPoints')->toSql())
        ->toContain('cast("legacy_accounts"."id" as varchar)')
        ->and(User::query()->has('addresses')->toSql())
        ->toContain('cast("users"."id" as varchar)')
        ->and(User::query()->has('party')->toSql())
        ->toContain('cast("users"."id" as varchar)');
});

it('binds rather than casts on an ordinary consumer read', function (): void {
    $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

    expect($user->contactPoints()->toSql())->not->toContain('cast(')
        ->and($user->addresses()->toSql())->not->toContain('cast(');
});

it('constrains the morph type on every consumer relation query', function (): void {
    $user = User::create(['name' => 'John Doe', 'email' => 'john@example.com']);

    expect($user->contactPoints()->toSql())->toContain('"heyyou_parties"."partyable_type"')
        ->and($user->addresses()->toSql())->toContain('"heyyou_parties"."partyable_type"')
        ->and(User::query()->has('contactPoints')->toSql())
        ->toContain('"heyyou_parties"."partyable_type"');
});
