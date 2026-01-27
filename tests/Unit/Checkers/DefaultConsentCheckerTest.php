<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Checkers\DefaultConsentChecker;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\PartyConsent;

beforeEach(function () {
    $this->checker = new DefaultConsentChecker;
});

it('returns allowed with level none when no consent records exist', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    expect($result->allowed)->toBeTrue();
    expect($result->level)->toBe('none');
    expect($result->status)->toBeNull();
    expect($result->capturedAt)->toBeNull();
});

it('returns allowed when contact point has opted in', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    $capturedAt = Carbon::now()->subDays(5);

    ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_IN,
        'captured_at' => $capturedAt,
        'source' => 'web_form',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    expect($result->allowed)->toBeTrue();
    expect($result->level)->toBe('contact_point');
    expect($result->status)->toBe('opted_in');
    expect($result->capturedAt->toDateTimeString())->toBe($capturedAt->toDateTimeString());
});

it('returns denied when contact point has opted out', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_OUT,
        'captured_at' => Carbon::now(),
        'source' => 'web_form',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    expect($result->allowed)->toBeFalse();
    expect($result->level)->toBe('contact_point');
    expect($result->status)->toBe('opted_out');
});

it('returns allowed when party has opted in and no contact point consent', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    $capturedAt = Carbon::now()->subDays(5);

    PartyConsent::create([
        'party_id' => $party->id,
        'channel' => null,
        'purpose_category' => 'marketing',
        'status' => PartyConsent::STATUS_OPTED_IN,
        'captured_at' => $capturedAt,
        'source' => 'web_form',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    expect($result->allowed)->toBeTrue();
    expect($result->level)->toBe('party');
    expect($result->status)->toBe('opted_in');
});

it('returns denied when party has opted out and no contact point consent', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    PartyConsent::create([
        'party_id' => $party->id,
        'channel' => null,
        'purpose_category' => 'marketing',
        'status' => PartyConsent::STATUS_OPTED_OUT,
        'captured_at' => Carbon::now(),
        'source' => 'user_request',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    expect($result->allowed)->toBeFalse();
    expect($result->level)->toBe('party');
    expect($result->status)->toBe('opted_out');
});

it('prefers contact point consent over party consent (more specific wins)', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    // Party opted out
    PartyConsent::create([
        'party_id' => $party->id,
        'channel' => null,
        'purpose_category' => 'marketing',
        'status' => PartyConsent::STATUS_OPTED_OUT,
        'captured_at' => Carbon::now(),
        'source' => 'user_request',
    ]);

    // But contact point opted in
    ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_IN,
        'captured_at' => Carbon::now(),
        'source' => 'web_form',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    // Contact point level should win
    expect($result->allowed)->toBeTrue();
    expect($result->level)->toBe('contact_point');
    expect($result->status)->toBe('opted_in');
});

it('uses most recent consent when multiple consents exist at same level', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    // Old opted out
    ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_OUT,
        'captured_at' => Carbon::now()->subDays(10),
        'source' => 'web_form',
    ]);

    // Newer opted in
    ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_IN,
        'captured_at' => Carbon::now()->subDays(5),
        'source' => 'web_form',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    // Most recent should win
    expect($result->allowed)->toBeTrue();
    expect($result->status)->toBe('opted_in');
});

it('prefers channel-specific party consent over generic party consent', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    // Generic party opted in
    PartyConsent::create([
        'party_id' => $party->id,
        'channel' => null,
        'purpose_category' => 'marketing',
        'status' => PartyConsent::STATUS_OPTED_IN,
        'captured_at' => Carbon::now(),
        'source' => 'web_form',
    ]);

    // Channel-specific opted out
    PartyConsent::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'purpose_category' => 'marketing',
        'status' => PartyConsent::STATUS_OPTED_OUT,
        'captured_at' => Carbon::now(),
        'source' => 'user_request',
    ]);

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    // Channel-specific should win
    expect($result->allowed)->toBeFalse();
    expect($result->status)->toBe('opted_out');
});

it('ignores soft deleted consent records', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    $consent = ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_OUT,
        'captured_at' => Carbon::now(),
        'source' => 'web_form',
    ]);

    $consent->delete();

    $result = $this->checker->hasConsent($contactPoint, 'marketing');

    // Should be allowed because the consent is deleted
    expect($result->allowed)->toBeTrue();
    expect($result->level)->toBe('none');
});

it('only considers consent for the specified purpose category', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'value_normalized' => 'test@example.com',
    ]);

    // Consent for marketing
    ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_IN,
        'captured_at' => Carbon::now(),
        'source' => 'web_form',
    ]);

    // Check for transactional (different purpose)
    $result = $this->checker->hasConsent($contactPoint, 'transactional');

    expect($result->level)->toBe('none');
});
