<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Checkers\DefaultDncChecker;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\DoNotContact;
use RobinsonRyan\HeyYou\Models\Party;

beforeEach(function () {
    $this->checker = new DefaultDncChecker;
});

it('returns allowed when no dnc rules exist', function () {
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

    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeFalse();
    expect($result->scope)->toBeNull();
    expect($result->reason)->toBeNull();
    expect($result->rule)->toBeNull();
});

it('blocks when party-wide dnc exists', function () {
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

    $dnc = DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => null,
        'purpose' => null,
        'reason' => 'Customer requested no contact',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeTrue();
    expect($result->scope)->toBe('party');
    expect($result->reason)->toBe('Customer requested no contact');
    expect($result->rule->id)->toBe($dnc->id);
});

it('blocks when channel-specific dnc exists', function () {
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

    $dnc = DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => 'email',
        'purpose' => null,
        'reason' => 'No email contact',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeTrue();
    expect($result->scope)->toBe('channel');
    expect($result->reason)->toBe('No email contact');
});

it('blocks when purpose-specific dnc exists and purpose matches', function () {
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

    $dnc = DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => null,
        'purpose' => 'marketing',
        'reason' => 'No marketing contact',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    $result = $this->checker->isBlocked($contactPoint, 'marketing');

    expect($result->blocked)->toBeTrue();
    expect($result->scope)->toBe('purpose');
    expect($result->reason)->toBe('No marketing contact');
});

it('does not block when purpose-specific dnc exists but purpose does not match', function () {
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

    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => null,
        'purpose' => 'marketing',
        'reason' => 'No marketing contact',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    // Check with different purpose
    $result = $this->checker->isBlocked($contactPoint, 'transactional');

    expect($result->blocked)->toBeFalse();
});

it('blocks when channel and purpose combination dnc exists', function () {
    $party = Party::create([
        'partyable_type' => 'App\\Models\\User',
        'partyable_id' => 1,
        'display_name_cached' => 'Test User',
    ]);

    $contactPoint = ContactPoint::create([
        'party_id' => $party->id,
        'channel' => 'sms',
        'value_raw' => '+1234567890',
        'value_normalized' => '+1234567890',
    ]);

    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => 'sms',
        'purpose' => 'marketing',
        'reason' => 'No SMS marketing',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    $result = $this->checker->isBlocked($contactPoint, 'marketing');

    expect($result->blocked)->toBeTrue();
    expect($result->scope)->toBe('channel_purpose');
});

it('blocks when specific contact point dnc exists', function () {
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

    $dnc = DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => $contactPoint->id,
        'channel' => null,
        'purpose' => null,
        'reason' => 'Email bounced',
        'source' => 'system',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeTrue();
    expect($result->scope)->toBe('contact_point');
    expect($result->reason)->toBe('Email bounced');
});

it('does not block when dnc not yet effective', function () {
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

    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => null,
        'purpose' => null,
        'reason' => 'Future DNC',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->addDays(5),
    ]);

    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeFalse();
});

it('does not block when dnc has expired', function () {
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

    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => null,
        'purpose' => null,
        'reason' => 'Expired DNC',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDays(30),
        'expires_at' => Carbon::now()->subDays(5),
    ]);

    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeFalse();
});

it('ignores soft deleted dnc rules', function () {
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

    $dnc = DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => null,
        'purpose' => null,
        'reason' => 'Deleted DNC',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    $dnc->delete();

    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeFalse();
});

it('prefers more specific dnc scope over general', function () {
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

    // Party-wide DNC
    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => null,
        'purpose' => null,
        'reason' => 'Party-wide reason',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    // Contact point specific DNC
    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => $contactPoint->id,
        'channel' => null,
        'purpose' => null,
        'reason' => 'Contact point reason',
        'source' => 'system',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    $result = $this->checker->isBlocked($contactPoint);

    // Contact point is most specific, should be returned
    expect($result->blocked)->toBeTrue();
    expect($result->scope)->toBe('contact_point');
    expect($result->reason)->toBe('Contact point reason');
});

it('does not block different channel than dnc channel', function () {
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

    // DNC for SMS channel only
    DoNotContact::create([
        'party_id' => $party->id,
        'contact_point_id' => null,
        'channel' => 'sms',
        'purpose' => null,
        'reason' => 'No SMS',
        'source' => 'user_request',
        'effective_at' => Carbon::now()->subDay(),
    ]);

    // Email contact point should not be blocked
    $result = $this->checker->isBlocked($contactPoint);

    expect($result->blocked)->toBeFalse();
});
