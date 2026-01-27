<?php

declare(strict_types=1);

use Illuminate\Support\Carbon;
use RobinsonRyan\HeyYou\Models\ContactPoint;
use RobinsonRyan\HeyYou\Models\ContactPointConsent;
use RobinsonRyan\HeyYou\Models\Party;
use RobinsonRyan\HeyYou\Models\VerificationEvent;
use RobinsonRyan\HeyYou\Tests\Fixtures\Models\User;

beforeEach(function () {
    $this->party = Party::create([
        'partyable_type' => User::class,
        'partyable_id' => 1,
        'display_name_cached' => 'John Doe',
    ]);
});

it('uses the prefixed table name', function () {
    $contactPoint = new ContactPoint;

    expect($contactPoint->getTable())->toBe('heyyou_contact_points');
});

it('normalizes email on save', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'John.Doe@Example.COM',
    ]);

    expect($contactPoint->value_normalized)->toBe('john.doe@example.com');
});

it('normalizes phone on save', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'phone',
        'value_raw' => '(555) 123-4567',
    ]);

    expect($contactPoint->value_normalized)->toBe('+15551234567');
});

it('normalizes sms like phone', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'sms',
        'value_raw' => '555-123-4567',
    ]);

    expect($contactPoint->value_normalized)->toBe('+15551234567');
});

it('uses default normalizer for unknown channels', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'whatsapp',
        'value_raw' => '  @username  ',
    ]);

    expect($contactPoint->value_normalized)->toBe('@username');
});

it('defaults status to active', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    expect($contactPoint->status)->toBe('active');
});

it('defaults is_primary to false', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    expect($contactPoint->is_primary)->toBeFalse();
});

it('defaults is_verified to false', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    expect($contactPoint->is_verified)->toBeFalse();
});

it('belongs to party', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    expect($contactPoint->party->id)->toBe($this->party->id);
});

it('checks if currently verified when verified and no expiration', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'is_verified' => true,
        'verified_at' => Carbon::now(),
    ]);

    expect($contactPoint->isCurrentlyVerified())->toBeTrue();
});

it('checks if currently verified when not verified', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'is_verified' => false,
    ]);

    expect($contactPoint->isCurrentlyVerified())->toBeFalse();
});

it('checks if currently verified when verification expired', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'is_verified' => true,
        'verified_at' => Carbon::now()->subMonth(),
        'verification_expires_at' => Carbon::now()->subDay(),
    ]);

    expect($contactPoint->isCurrentlyVerified())->toBeFalse();
});

it('checks if currently verified when verification not yet expired', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
        'is_verified' => true,
        'verified_at' => Carbon::now()->subMonth(),
        'verification_expires_at' => Carbon::now()->addMonth(),
    ]);

    expect($contactPoint->isCurrentlyVerified())->toBeTrue();
});

it('can be soft deleted', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    $contactPoint->delete();

    expect(ContactPoint::find($contactPoint->id))->toBeNull();
    expect(ContactPoint::withTrashed()->find($contactPoint->id))->not->toBeNull();
});

it('enforces unique constraint on party channel and normalized value', function () {
    ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'TEST@EXAMPLE.COM', // Same normalized value
    ]);
})->throws(Illuminate\Database\QueryException::class);

it('has consents relationship', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    ContactPointConsent::create([
        'contact_point_id' => $contactPoint->id,
        'purpose_category' => 'marketing',
        'status' => ContactPointConsent::STATUS_OPTED_IN,
        'captured_at' => Carbon::now(),
        'source' => 'web_form',
    ]);

    expect($contactPoint->consents)->toHaveCount(1);
    expect($contactPoint->consents->first()->purpose_category)->toBe('marketing');
});

it('has verificationEvents relationship', function () {
    $contactPoint = ContactPoint::create([
        'party_id' => $this->party->id,
        'channel' => 'email',
        'value_raw' => 'test@example.com',
    ]);

    VerificationEvent::create([
        'contact_point_id' => $contactPoint->id,
        'status' => VerificationEvent::STATUS_PENDING,
        'method' => 'code',
        'initiated_at' => Carbon::now(),
    ]);

    expect($contactPoint->verificationEvents)->toHaveCount(1);
    expect($contactPoint->verificationEvents->first()->method)->toBe('code');
});
